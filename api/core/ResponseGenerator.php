<?php

require_once __DIR__ . '/PhpCompatibility.php';
require_once __DIR__ . '/ConversationalAnswer.php';
require_once __DIR__ . '/ResultsPresentation.php';
class ResponseGenerator
{
    public static function generateStream($question, array $orchestratorResponse, array $history, callable $onToken)
    {
        $question = PhpCompatibility::value($question, 'string', false);
        $result = self::generate($question, $orchestratorResponse, $history);
        // Build and validate everything before emitting any answer text.
        foreach (explode("\n", $result['answer']) as $i => $line) {
            $onToken(($i ? "\n" : '') . $line);
        }
        return PhpCompatibility::value($result, 'array', false);
    }
    public static function generate($question, array $orchestratorResponse, array $history = [])
    {
        $question = PhpCompatibility::value($question, 'string', false);
        if (is_array((isset($orchestratorResponse['routing']) ? $orchestratorResponse['routing'] : null)) && (isset($orchestratorResponse['routing']['endpoint']) ? $orchestratorResponse['routing']['endpoint'] : '') === 'node_property' && isset($orchestratorResponse['node_answer'])) {
            return PhpCompatibility::value($orchestratorResponse['node_answer'], 'array', false);
        }
        $status = (isset($orchestratorResponse['status']) ? $orchestratorResponse['status'] : 'error');
        // Deterministic gates (LLM bypassed)
        if ($status === 'needs_clarification') {
            return PhpCompatibility::value(['status' => 'needs_clarification', 'answer' => (isset($orchestratorResponse['message']) ? $orchestratorResponse['message'] : 'Could you please clarify your question?')], 'array', false);
        }
        if ($status === 'unsupported') {
            return PhpCompatibility::value(['status' => 'unsupported', 'answer' => (isset($orchestratorResponse['message']) ? $orchestratorResponse['message'] : 'That question is outside the currently supported ShodhanaKG knowledge retrieval patterns.')], 'array', false);
        }
        if ($status === 'entity_not_found') {
            return PhpCompatibility::value(['status' => 'entity_not_found', 'answer' => (isset($orchestratorResponse['message']) ? $orchestratorResponse['message'] : 'The requested compound or disease could not be found in ShodhanaKG.')], 'array', false);
        }
        if ($status === 'no_data') {
            return PhpCompatibility::value(['status' => 'no_data', 'answer' => 'I couldn’t find information about that in the available records.'], 'array', false);
        }
        if ($status === 'success') {
            return PhpCompatibility::value(ResultsPresentation::build((isset($orchestratorResponse['retrieval']) ? $orchestratorResponse['retrieval'] : [])), 'array', false);
        }
        if ((isset($orchestratorResponse['retrieval']['reason']) ? $orchestratorResponse['retrieval']['reason'] : '') === 'query_limit') {
            return PhpCompatibility::value(['status' => 'error', 'answer' => 'There are too many matching graph paths to retrieve completely. Please narrow the query with an entity or treatment constraint.'], 'array', false);
        }
        return PhpCompatibility::value(['status' => 'error', 'answer' => 'An unknown error occurred during orchestration.'], 'array', false);
    }
}
