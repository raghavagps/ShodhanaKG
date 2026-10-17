<?php

require_once __DIR__ . '/PhpCompatibility.php';
require_once __DIR__ . '/QuestionRouter.php';
require_once __DIR__ . '/ConversationContext.php';
require_once __DIR__ . '/QueryUnderstanding.php';
class ChatbotOrchestrator
{
    private static $allowlist = [
        'compound_methods' => 'execute_compound_methods',
        'compound_shodhana_process' => 'execute_compound_shodhana_process',
        'compound_media' => 'execute_compound_media',
        'compound_validation' => 'execute_compound_validation',
        'compound_toxicity' => 'execute_compound_toxicity',
        'compound_detoxification' => 'execute_compound_detoxification',
        'compound_purification' => 'execute_compound_purification',
        'compound_therapeutic_use' => 'execute_compound_therapeutic_use',
        'disease_symptoms' => 'execute_disease_symptoms',
        'disease_therapy' => 'execute_disease_therapy',
        'disease_formulation' => 'execute_disease_formulation',
        'integrated_compound_disease' => 'execute_integrated_compound_disease',
        'process_media' => 'execute_process_media',
        'shodhana_method_validation' => 'execute_shodhana_method_validation',
        'indication_compound' => 'execute_indication_compound',
        // New Schema Endpoints
        'toxic_substance_compound' => 'execute_toxic_substance_compound',
        'process_purified_compound' => 'execute_process_purified_compound',
        'confirmation_study_method' => 'execute_confirmation_study_method',
        'confirmation_study_purified_compound' => 'execute_confirmation_study_purified_compound',
        'compound_purified_forms' => 'execute_compound_purified_forms',
        'formulation_compound' => 'execute_formulation_compound',
        'ayurvedic_diagnosis_disease' => 'execute_ayurvedic_diagnosis_disease',
        'formulation_therapy' => 'execute_formulation_therapy',
        'assessment_disease' => 'execute_assessment_disease',
        'toxic_substance_method' => 'execute_toxic_substance_method',
        'process_classification' => 'execute_process_classification',
        'classification_process' => 'execute_classification_process',
        'purified_compound_disease' => 'execute_purified_compound_disease',
        'purified_compound_formulation' => 'execute_purified_compound_formulation',
        'purified_compound_source' => 'execute_purified_compound_source',
        'purified_compound_validation' => 'execute_purified_compound_validation',
        'integrated_disease_compound' => 'execute_integrated_disease_compound',
        'disease_dosha' => 'execute_disease_dosha',
        'disease_diagnosis' => 'execute_disease_diagnosis',
        'disease_assessment' => 'execute_disease_assessment',
        'symptoms_disease' => 'execute_symptoms_disease',
        'therapy_disease' => 'execute_therapy_disease',
        'formulation_disease' => 'execute_formulation_disease',
        'dosha_disease' => 'execute_dosha_disease',
        'process_compound' => 'execute_process_compound',
        'media_compound' => 'execute_media_compound',
        'therapy_formulation' => 'execute_therapy_formulation',
        'media_process' => 'execute_media_process',
        'shodhana_method_process' => 'execute_shodhana_method_process',
        'process_shodhana_method' => 'execute_process_shodhana_method',
        'purified_compound_process' => 'execute_purified_compound_process',
        'method_toxic_substance' => 'execute_method_toxic_substance',
        'process_next_process' => 'execute_process_next_process',
        'next_process_process' => 'execute_next_process_process',
        'provenance_compound_method_media' => 'execute_provenance_compound_method_media',
        'provenance_media_method_compound' => 'execute_provenance_media_method_compound',
        'provenance_disease_therapy_formulation' => 'execute_provenance_disease_therapy_formulation',
        'provenance_formulation_therapy_disease' => 'execute_provenance_formulation_therapy_disease',
    ];
    public static function process($question, $history = [], $conversationContext = null, $offset = 0, $selection = null)
    {
        $offset = PhpCompatibility::value($offset, 'int', false);
        $original = $question;
        $matcher = null;
        if ($selection !== null) {
            if (!is_array($selection)) {
                return ['status' => 'needs_clarification', 'reason' => 'invalid_selection', 'message' => 'Please choose a current KG entity option.'];
            }
            $pending = (is_array($conversationContext) ? (isset($conversationContext['pending_question']) ? $conversationContext['pending_question'] : '') : '');
            $validated = ConversationContext::validate($conversationContext);
            $options = ($pending ? call_user_func(function () use (&$pending, &$validated) {
                $__kgCoalesce1 = QuestionRouter::route($pending, [], null, $validated);
                return (isset($__kgCoalesce1['clarification']['candidates']) ? $__kgCoalesce1['clarification']['candidates'] : []);
            }) : []);
            if (!$options && $pending) {
                $options = call_user_func(function () use (&$pending, &$validated) {
                    $__kgCoalesce2 = self::processResolved($pending, [], 0, $validated);
                    return (isset($__kgCoalesce2['clarification']['candidates']) ? $__kgCoalesce2['clarification']['candidates'] : []);
                });
            }
            $chosen = null;
            foreach ($options as $option) {
                if ((isset($option['id']) ? $option['id'] : '') === (isset($selection['id']) ? $selection['id'] : '') && (isset($option['result_type']) ? $option['result_type'] : null) === (isset($selection['result_type']) ? $selection['result_type'] : null)) {
                    $chosen = $option;
                }
            }
            $node = ($chosen ? EntityResolution::live()->node($chosen['id']) : null);
            if (!$node) {
                return ['status' => 'needs_clarification', 'reason' => 'invalid_selection', 'message' => 'Please choose an entity from the current clarification options.'];
            }
            $question = (in_array((isset($chosen['match_method']) ? $chosen['match_method'] : ''), ['broader', 'supported_alternative', 'intent_proposal'], true) && !empty($chosen['result_type']) ? 'Which ' . str_replace('_', ' ', $chosen['result_type']) . ' are associated with ' . $node['name'] . '?' : $pending);
            $matcher = new ConversationEntities(null, $node['rid']);
            $previous = ConversationContext::validate($conversationContext);
            $history = [];
        }
        if ($selection === null && $conversationContext !== null) {
            list($question, $history, $previous) = ConversationContext::prepare($question, $history, $conversationContext);
        } elseif ($selection === null) {
            $previous = ['version' => 2];
        }
        $response = self::processResolved($question, $history, $offset, $previous, $matcher);
        $response['question'] = $original;
        $response['conversation_context'] = ConversationContext::finish($response, $original, $previous);
        return $response;
    }
    private static function processResolved($question, $history = [], $offset = 0, array $previous = [], $matcher = null)
    {
        $offset = PhpCompatibility::value($offset, 'int', false);
        $matcher = PhpCompatibility::value($matcher, 'ConversationEntities', true);
        $routing = QuestionRouter::route($question, $history, $matcher, (!empty($previous['_provided']) ? $previous : null));
        $understanding = ['mode' => 'deterministic'];
        $languageFallback = (isset($routing['reason']) ? $routing['reason'] : '') === 'intent_ambiguous' && !SemanticQuery::target($question) && !empty($routing['debug_entity']) || in_array((isset($routing['status']) ? $routing['status'] : ''), ['entity_not_found', 'needs_clarification'], true) && (isset($routing['reason']) ? $routing['reason'] : '') === 'entity_ambiguous' && preg_match('/\b(purif\w*|detox\w*|methods?|techniques?|symptoms?|therap\w*|formulat\w*|media|safe)\b/i', $question);
        if ($languageFallback && getenv('KG_NLU_MODEL_ENABLED') !== 'false') {
            $proposal = QueryUnderstanding::propose($question);
            $understanding = ['mode' => 'model_fallback', 'result' => $proposal['status'], 'reason' => (isset($proposal['reason']) ? $proposal['reason'] : null)];
            if ($proposal['status'] === 'proposed') {
                $understanding['result'] = 'rejected';
                $understanding['reason'] = 'unresolved_or_incompatible_proposal';
                $p = $proposal['proposal'];
                $mentions = [];
                $valid = true;
                foreach ($p['mentions'] as $mention) {
                    $r = EntityResolution::live()->resolve($mention);
                    if ($r['outcome'] !== 'resolved') {
                        $valid = false;
                        break;
                    }
                    $mentions[] = $r['candidates'][0]['label'];
                }
                // Do not let the model drop or introduce an already detected constraint.
                $originalGroups = (new ConversationEntities())->find($question)['groups'];
                $originalLabels = array_map(static function ($g) {
                    return reset($g)['name'];
                }, $originalGroups);
                if ($valid && !array_diff($originalLabels, $mentions) && (!$originalLabels || !array_diff($mentions, $originalLabels))) {
                    $candidate = QuestionRouter::route('Which ' . str_replace('_', ' ', $p['result_type']) . ' are associated with ' . implode(' in ', $mentions) . '?', $history);
                    if ((isset($candidate['status']) ? $candidate['status'] : '') === 'success' && SemanticQuery::target($question)) {
                        $routing = $candidate;
                        $understanding['result'] = 'accepted';
                        $understanding['reason'] = null;
                    } elseif ((isset($candidate['status']) ? $candidate['status'] : '') === 'success' && count($mentions) === 1) {
                        $resolved = EntityResolution::live()->resolve($p['mentions'][0]);
                        $choice = $resolved['candidates'][0];
                        $choice['result_type'] = $p['result_type'];
                        $choice['match_method'] = 'intent_proposal';
                        $routing = ['status' => 'needs_clarification', 'reason' => 'intent_confirmation', 'message' => 'I matched ' . $choice['label'] . '. Did you mean its associated ' . strtolower(str_replace('_', ' ', $p['result_type'])) . '? You can select this interpretation or rephrase your question.', 'clarification' => ['candidates' => [$choice]], 'resolution' => [$resolved]];
                        $understanding['result'] = 'confirmation_required';
                        $understanding['reason'] = 'implicit_result_type';
                    }
                }
            }
        }
        $response = self::executeRouting($question, $history, $offset, $previous, $routing);
        $response['resolution'] = (isset($routing['resolution']) ? $routing['resolution'] : []);
        $response['understanding'] = $understanding;
        if (isset($routing['clarification'])) {
            $response['clarification'] = $routing['clarification'];
        }
        return $response;
    }
    private static function executeRouting($question, $history, $offset, array $previous, array $routing)
    {
        $offset = PhpCompatibility::value($offset, 'int', false);
        if ((isset($routing['status']) ? $routing['status'] : '') === 'success' && (isset($routing['routing']['endpoint']) ? $routing['routing']['endpoint'] : '') !== 'node_property' && !empty($previous['constraints']) && preg_match('/^(and\b|what about\b)|\b(it|its|this|that)\b/i', $question)) {
            $r = $routing['routing'];
            $target = call_user_func(function () use (&$question, &$previous) {
                $__kgCoalesce3 = SemanticQuery::target($question);
                return (isset($__kgCoalesce3) ? $__kgCoalesce3 : (isset($previous['result_type']) ? $previous['result_type'] : null));
            });
            if ($r['entity'] === (isset($previous['subject']['name']) ? $previous['subject']['name'] : null) && $target) {
                $constraints = [];
                foreach (array_merge($previous['constraints'], (isset($r['constraints']) ? $r['constraints'] : [])) as $constraint) {
                    $constraints[$constraint['type']] = $constraint;
                }
                $constraints = array_values($constraints);
                $compatible = false;
                foreach (SemanticQuery::definitions($r['entity_type'], $target) as $definition) {
                    $types = array_merge(...array_column($definition['steps'], 'types'));
                    if (!array_diff(array_column($constraints, 'type'), $types)) {
                        $routing['routing']['endpoint'] = 'semantic_query';
                        $routing['routing']['result_type'] = $target;
                        $routing['routing']['constraints'] = $constraints;
                        $compatible = true;
                        break;
                    }
                }
                if (!$compatible) {
                    return ['status' => 'needs_clarification', 'reason' => 'incompatible_constraint', 'message' => 'The previous constraints cannot be applied to this requested relationship. Please change the request or say “without previous constraints”.'];
                }
            }
        }
        $status = $routing['status'];
        if ($status === 'needs_clarification') {
            return ['status' => 'needs_clarification', 'reason' => (isset($routing['reason']) ? $routing['reason'] : 'intent_ambiguous'), 'routing' => (isset($routing['routing']) ? $routing['routing'] : (object) []), 'message' => (isset($routing['message']) ? $routing['message'] : self::buildClarificationMessage($routing))];
        }
        if ($status === 'unsupported') {
            return ['status' => 'unsupported', 'reason' => (isset($routing['reason']) ? $routing['reason'] : 'no_retrieval_pattern'), 'routing' => (isset($routing['routing']) ? $routing['routing'] : (object) []), 'message' => 'That question is outside the currently supported ShodhanaKG knowledge retrieval patterns.'];
        }
        // Entity ambiguous due to not found
        if ($status === 'entity_not_found') {
            return ['status' => 'entity_not_found', 'message' => 'The requested compound or disease could not be found in ShodhanaKG.'];
        }
        // If router succeeded, we must execute the deterministic retrieval
        if ($status === 'success') {
            $endpoint = $routing['routing']['endpoint'];
            $entityName = $routing['routing']['entity'];
            if ($endpoint === 'node_property') {
                $r = $routing['routing'];
                $raw = EntityResolution::live()->rawNode($r['entity_id']);
                if (!$raw || $raw['@type'] !== $r['entity_type']) {
                    return ['status' => 'entity_not_found'];
                }
                $answer = NodePropertyAnswer::build($raw, $r['property']);
                return ['status' => $answer['status'], 'routing' => $r, 'node_answer' => $answer];
            }
            if ($endpoint === 'semantic_query') {
                $r = $routing['routing'];
                $data = SemanticQuery::run($r['entity_type'], (isset($r['entity_id']) ? $r['entity_id'] : $entityName), $r['result_type'], (isset($r['constraints']) ? $r['constraints'] : []), $offset);
                return ['status' => $data['status'], 'question' => $question, 'routing' => $r, 'retrieval' => $data];
            }
            if (!isset(self::$allowlist[$endpoint])) {
                return ['status' => 'unsupported', 'reason' => 'invalid_retrieval_endpoint', 'routing' => $routing['routing'], 'message' => 'The requested endpoint is not allowlisted.'];
            }
            $functionName = self::$allowlist[$endpoint];
            $endpointFile = __DIR__ . '/../' . $endpoint . '.php';
            if (!file_exists($endpointFile)) {
                return ['status' => 'error', 'message' => 'Endpoint file not found.'];
            }
            // Require the file which now contains the function definition
            require_once $endpointFile;
            if (!function_exists($functionName)) {
                return ['status' => 'error', 'message' => 'Retrieval function not found in endpoint.'];
            }
            try {
                // Execute the function
                if (in_array($endpoint, ['compound_methods', 'integrated_compound_disease', 'integrated_disease_compound'], true)) {
                    $source = ($endpoint === 'integrated_disease_compound' ? 'Disease' : 'Compound');
                    $target = ($endpoint === 'compound_methods' ? 'Shodhana_Method' : ($source === 'Disease' ? 'Compound' : 'Disease'));
                    $retrievalData = SemanticQuery::run($source, (isset($routing['routing']['entity_id']) ? $routing['routing']['entity_id'] : $entityName), $target, [], $offset);
                } else {
                    $retrievalData = $functionName($entityName, false);
                }
                if (!empty($routing['routing']['requested_result_types'])) {
                    $retrievalData['query_plan']['requested_result_types'] = $routing['routing']['requested_result_types'];
                }
                // Determine no_data or error
                $finalStatus = 'success';
                if (isset($retrievalData['status']) && $retrievalData['status'] === 'error') {
                    $finalStatus = 'error';
                } else if (isset($retrievalData['status']) && $retrievalData['status'] === 'no_data') {
                    $finalStatus = 'no_data';
                } else if (isset($retrievalData['status']) && $retrievalData['status'] === 'entity_not_found') {
                    $finalStatus = 'entity_not_found';
                } else if (isset($retrievalData['results']) && empty($retrievalData['results'])) {
                    // Fallback check if the normalizer output an empty array but kept status 'success'
                    $finalStatus = 'no_data';
                    $retrievalData['status'] = 'no_data';
                }
                return ['status' => $finalStatus, 'question' => $question, 'routing' => $routing['routing'], 'retrieval' => $retrievalData, 'reason' => (isset($retrievalData['reason']) ? $retrievalData['reason'] : null)];
            } catch (Exception $e) {
                return ['status' => 'error', 'message' => 'Retrieval execution failed: ' . $e->getMessage()];
            }
        }
        // Fallback for unknown router state
        return ['status' => 'error', 'message' => 'Unknown routing status.'];
    }
    private static function buildClarificationMessage($routing)
    {
        if (isset($routing['debug_entity'])) {
            return "What would you like to know about " . $routing['debug_entity'] . "?";
        }
        return "Could you please clarify your question?";
    }
}
