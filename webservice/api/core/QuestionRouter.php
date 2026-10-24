<?php

require_once __DIR__ . '/PhpCompatibility.php';
require_once __DIR__ . '/EntityResolver.php';
require_once __DIR__ . '/GraphRetriever.php';
require_once __DIR__ . '/TraversalCatalog.php';
require_once __DIR__ . '/ConversationEntities.php';
require_once __DIR__ . '/SemanticQuery.php';
require_once __DIR__ . '/NodePropertyAnswer.php';
class QuestionRouter
{
    /**
     * Detects whether a question is written in a non-English language.
     * Checks for Devanagari Unicode script and common Romanised Hindi words.
     *
     * @param string $question Raw question string
     * @return bool True if non-English is detected
     */
    private static function isNonEnglish($question)
    {
        $question = PhpCompatibility::value($question, 'string', false);
        // Detect Devanagari script (Unicode block U+0900-U+097F)
        if (preg_match('/[\x{0900}-\x{097F}]/u', $question)) {
            return PhpCompatibility::value(true, 'bool', false);
        }
        // Detect common Romanised Hindi word patterns
        $hindiPatterns = ['/\bke liye\b/i', '/\bkaunsa\b/i', '/\bkaunse\b/i', '/\bkaun\b/i', '/\bkaise\b/i', '/\bkab\b/i', '/\bkahan\b/i', '/\bkyun\b/i', '/\bkya\b/i', '/\blakshan\b/i', '/\bhota hai\b/i', '/\bhoti hai\b/i', '/\bhain\b/i', '/\bkarta hai\b/i', '/\bkarti hai\b/i', '/\bkis\b/i', '/\bkisko\b/i', '/\bkiski\b/i', '/\blekin\b/i', '/\bnahin\b/i', '/\bnahi\b/i', '/\brog\b/i', '/\bilaj\b/i', '/\bupchar\b/i', '/\baushadh\b/i', '/\bdavai\b/i'];
        foreach ($hindiPatterns as $pattern) {
            if (preg_match($pattern, $question)) {
                return PhpCompatibility::value(true, 'bool', false);
            }
        }
        return PhpCompatibility::value(false, 'bool', false);
    }
    public static function route($question, $history = [], $matcher = null, $structuredContext = null)
    {
        $matcher = PhpCompatibility::value($matcher, 'ConversationEntities', true);
        $structuredContext = PhpCompatibility::value($structuredContext, 'array', true);
        $q = self::normalizeIntent($question);
        // English-only guard — reject non-English queries immediately
        if (self::isNonEnglish($question)) {
            return ['status' => 'unsupported', 'reason' => 'language_not_supported', 'message' => 'ShodhanaKG currently supports English language queries only. Please rephrase your question in English.', 'routing' => (object) []];
        }
        if (preg_match('/\b(buy|guarantee|who discovered|history of)\b/i', $q) || preg_match('/\b(?:dosage|dose)\b.*\b(?:should|recommend|take)\b/i', $q) || preg_match('/\b(write|compose|generate)\b.*\b(poem|story|song)\b/i', $q) || preg_match('/^(please\s+|could you\s+|can you\s+)?(delete|drop|insert|update)\b/i', $q)) {
            return ['status' => 'unsupported', 'reason' => 'no_retrieval_pattern'];
        }
        if (preg_match('/\b(how (do|can|to) (i|we)|navigate|explorer|filter|help|about (this|the) (app|site|explorer)|what does (the|this))\b/i', $q)) {
            return ['status' => 'success', 'routing' => ['domain' => 'general', 'intent' => 'platform_help', 'entity' => null, 'entity_type' => null, 'endpoint' => null, 'confidence' => 0.9, 'needs_clarification' => false]];
        }
        $originalQ = trim($question);
        $matcher = (isset($matcher) ? $matcher : new ConversationEntities());
        $found = $matcher->find(NodePropertyAnswer::subjectQuestion($originalQ));
        foreach ((isset($found['resolutions']) ? $found['resolutions'] : []) as $resolution) {
            if ((isset($resolution['candidates'][0]['match_method']) ? $resolution['candidates'][0]['match_method'] : '') === 'terminology_suggestion') {
                return self::resolutionClarification($found, 'terminology_confirmation', $originalQ);
            }
        }
        foreach ((isset($found['resolutions']) ? $found['resolutions'] : []) as $resolution) {
            if ((isset($resolution['candidates'][0]['match_method']) ? $resolution['candidates'][0]['match_method'] : '') === 'suggestion') {
                return self::resolutionClarification($found, 'uncertain_spelling', $originalQ);
            }
        }
        foreach ($found['groups'] as $group) {
            if (!ConversationEntities::select($group, $originalQ)) {
                return self::resolutionClarification($found, 'entity_type_ambiguous', $originalQ);
            }
        }
        if ($found['groups'] && !empty($found['suggestions'])) {
            return ['status' => 'needs_clarification', 'reason' => 'multiple_entities', 'message' => 'Please specify a KG entity for the broader term before combining it with another entity.'];
        }
        if (!empty($structuredContext['subject']['id']) && empty($structuredContext['_invalid']) && $found['groups'] && preg_match('/\b(it|its|their|that|this)\b/i', $originalQ) && preg_match('/\b(in|during|with|using)\b/i', $originalQ) && SemanticQuery::target($originalQ)) {
            $subject = EntityResolution::live()->node($structuredContext['subject']['id']);
            $types = array_merge(...array_map(static function ($group) {
                return array_column($group, 'inferred_type');
            }, $found['groups']));
            if ($subject && !in_array($subject['inferred_type'], $types, true)) {
                $found['groups'][] = [$subject['rid'] => $subject];
            }
        }
        if (count($found['groups']) > 1) {
            return call_user_func(function () use (&$found, &$originalQ) {
                $__kgCoalesce1 = SemanticQuery::routeMultiple($found['groups'], $originalQ);
                return (isset($__kgCoalesce1) ? $__kgCoalesce1 : ['status' => 'needs_clarification', 'reason' => 'multiple_entities']);
            });
        }
        $contextEntity = null;
        $contextQuestion = null;
        foreach (($structuredContext !== null ? [] : $history) as $turn) {
            if ((isset($turn['role']) ? $turn['role'] : '') !== 'user') {
                continue;
            }
            $previous = self::normalizeIntent($turn['text']);
            $match = $matcher->find($turn['text']);
            if (count($match['groups']) > 1 || $match['unresolved']) {
                $contextEntity = null;
                $contextQuestion = null;
                continue;
            }
            if ($match['groups']) {
                $contextEntity = ConversationEntities::select($match['groups'][0], $turn['text']);
            }
            if ($contextEntity) {
                $intent = self::classifyIntentContextually($previous, $contextEntity['inferred_type']);
                if (self::getEndpointForIntent($contextEntity['inferred_type'], $intent)) {
                    $contextQuestion = $previous;
                }
            }
        }
        $resolvedEntity = ($found['groups'] ? ConversationEntities::select($found['groups'][0], $originalQ) : null);
        $followup = preg_match('/^(and\b|what about\b)|\b(it|its|this|that|they|them|these|those)\b/i', $originalQ);
        if ($structuredContext !== null && $followup) {
            if (!$found['groups'] && !empty($structuredContext['_invalid'])) {
                return ['status' => 'needs_clarification', 'reason' => 'invalid_context', 'message' => 'Please select the entity again; the previous context could not be validated against the KG.'];
            }
            $contextEntity = (isset($structuredContext['subject']['id']) ? EntityResolution::live()->node($structuredContext['subject']['id']) : null);
            if (preg_match('/\b(?:the first|the second|the last|one of those|that result|those results)\b/i', $originalQ)) {
                return ['status' => 'needs_clarification', 'reason' => 'ambiguous_result_reference', 'message' => 'Please name or select the result you mean.'];
            }
        }
        if (!$resolvedEntity && !$found['unresolved'] && $followup) {
            $resolvedEntity = $contextEntity;
        }
        if (!$resolvedEntity) {
            if ($followup && !$found['unresolved'] && !$contextEntity && !$found['groups']) {
                return ['status' => 'needs_clarification', 'reason' => 'missing_context', 'message' => 'Please name the KG entity you mean.'];
            }
            if (!empty($found['suggestions'])) {
                return self::resolutionClarification($found, 'broader_term', $originalQ);
            }
            return ['status' => ($found['unresolved'] && (preg_match('/\b(symptom|media|therap|formulat)/i', $q) || self::extractEntityCandidates($originalQ)) ? 'entity_not_found' : 'needs_clarification'), 'reason' => 'entity_ambiguous'];
        }
        $property = NodePropertyAnswer::requested($originalQ, $resolvedEntity);
        if ($property === null && $structuredContext !== null && $followup && count($found['groups']) === 1 && !SemanticQuery::target($q) && preg_match('/^(?:and\s+)?what about\b/i', $originalQ) && isset($structuredContext['property'])) {
            $property = $structuredContext['property'];
        }
        if ($property !== null) {
            return ['status' => 'success', 'resolution' => (isset($found['resolutions']) ? $found['resolutions'] : []), 'routing' => ['domain' => 'node', 'intent' => 'node_property', 'endpoint' => 'node_property', 'entity' => $resolvedEntity['name'], 'entity_id' => $resolvedEntity['rid'], 'entity_type' => $resolvedEntity['inferred_type'], 'property' => $property]];
        }
        if ($followup && $contextQuestion && !self::getEndpointForIntent($resolvedEntity['inferred_type'], self::classifyIntentContextually($q, $resolvedEntity['inferred_type']))) {
            $q = $contextQuestion;
        }
        $entityType = $resolvedEntity['inferred_type'];
        if ($structuredContext !== null && $followup && !SemanticQuery::target($q) && !self::getEndpointForIntent($entityType, self::classifyIntentContextually($q, $entityType)) && (isset($structuredContext['requested_result_types']) ? $structuredContext['requested_result_types'] : []) === ['Shodhana_Method', 'Media']) {
            $q = 'which methods and media';
        }
        $coordinated = QueryUnderstanding::coordinatedTargets($q);
        if (count($coordinated) > 1 && !($entityType === 'Compound' && $coordinated === ['Shodhana_Method', 'Media'])) {
            return ['status' => 'needs_clarification', 'reason' => 'unsupported_result_combination', 'message' => 'Please ask for one of these result types at a time; this combination has no supported combined retrieval.'];
        }
        $entityName = (isset($resolvedEntity['name']) ? $resolvedEntity['name'] : '');
        if ($structuredContext !== null && $followup && !SemanticQuery::target($q) && !self::getEndpointForIntent($entityType, self::classifyIntentContextually($q, $entityType)) && !empty($structuredContext['result_type'])) {
            $q = 'which ' . str_replace('_', ' ', $structuredContext['result_type']);
        }
        $target = SemanticQuery::target($q);
        if ($entityType === 'Indication' && $target === 'Purified_Compound') {
            return ['status' => 'success', 'resolution' => (isset($found['resolutions']) ? $found['resolutions'] : []), 'routing' => ['domain' => 'indication', 'intent' => 'typed_path', 'entity' => $entityName, 'entity_id' => $resolvedEntity['rid'], 'entity_type' => $entityType, 'endpoint' => 'semantic_query', 'result_type' => $target, 'constraints' => [], 'confidence' => 0.9, 'needs_clarification' => false]];
        }
        // An Indication is never relabelled as Disease or Therapy. Offer actual alternatives.
        if ($entityType === 'Indication' && in_array($target, ['Symptom', 'Therapy', 'Formulation'], true)) {
            $suggestions = EntityResolution::live()->broader($entityName);
            $choices = [];
            foreach ($suggestions as $candidate) {
                $choices[] = $candidate + ['result_type' => $target];
            }
            $choices[] = ['id' => $resolvedEntity['rid'], 'label' => $entityName, 'type' => $entityType, 'result_type' => 'Purified_Compound', 'match_method' => 'supported_alternative', 'source' => 'KG schema'];
            return ['status' => 'needs_clarification', 'reason' => 'unsupported_entity_relationship', 'message' => $entityName . ' is recorded as an Indication. The KG does not provide the requested ' . strtolower(str_replace('_', ' ', $target)) . ' relationship for this entity. Choose a specific condition, or explore the purified compounds recorded for this indication.', 'clarification' => ['requested_result_type' => $target, 'candidates' => $choices], 'resolution' => (isset($found['resolutions']) ? $found['resolutions'] : [])];
        }
        if (in_array($entityType, ['Shodhana_Method', 'Process'], true) && TraversalCatalog::intent($q, $entityType) !== null) {
            $otherType = ($entityType === 'Process' ? 'Shodhana_Method' : 'Process');
            $other = EntityResolver::resolve($otherType, $entityName);
            if ($other && TraversalCatalog::intent($q, $otherType) !== null) {
                $explicitMethod = preg_match('/\bmethod\s+' . preg_quote($entityName, '/') . '\b/i', $question);
                $explicitProcess = preg_match('/\bprocess\s+' . preg_quote($entityName, '/') . '\b/i', $question);
                if (!$explicitMethod && !$explicitProcess) {
                    return ['status' => 'needs_clarification', 'reason' => 'entity_type_ambiguous'];
                }
                $entityType = ($explicitMethod ? 'Shodhana_Method' : 'Process');
            }
        }
        $intent = self::classifyIntentContextually($q, $entityType);
        if (!$intent) {
            return ['status' => 'needs_clarification', 'reason' => 'intent_ambiguous', 'debug_entity' => $entityName];
        }
        $endpoint = self::getEndpointForIntent($entityType, $intent);
        $targetType = SemanticQuery::target($q);
        if ($entityType === 'Shodhana_Method' && $endpoint === 'therapy_disease') {
            $targetType = (isset($targetType) ? $targetType : 'Disease');
        }
        if ($targetType && (!$endpoint || $entityType === 'Shodhana_Method' && $endpoint === 'therapy_disease') && SemanticQuery::definitions($entityType, $targetType)) {
            return ['status' => 'success', 'routing' => ['domain' => strtolower($entityType), 'intent' => 'typed_path', 'entity' => $entityName, 'entity_type' => $entityType, 'endpoint' => 'semantic_query', 'result_type' => $targetType, 'constraints' => [], 'confidence' => 0.9, 'needs_clarification' => false]];
        }
        if (!$endpoint) {
            if (PhpCompatibility::endsWith($intent, '_general') || in_array($intent, ['disease', 'compound', 'process', 'media', 'general_info'])) {
                return ['status' => 'needs_clarification', 'reason' => 'intent_ambiguous', 'debug_entity' => $entityName];
            }
            return ['status' => 'unsupported', 'reason' => 'no_retrieval_pattern', 'debug_entityType' => $entityType, 'debug_entityName' => $entityName, 'debug_intent' => $intent];
        }
        return ['status' => 'success', 'resolution' => (isset($found['resolutions']) ? $found['resolutions'] : []), 'routing' => ['domain' => strtolower($entityType), 'intent' => $intent, 'entity' => $entityName, 'entity_id' => $resolvedEntity['rid'], 'entity_type' => $entityType, 'endpoint' => $endpoint, 'requested_result_types' => QueryUnderstanding::coordinatedTargets($q), 'confidence' => 0.9, 'needs_clarification' => false]];
    }
    private static function resolutionClarification(array $found, $reason, $question)
    {
        $reason = PhpCompatibility::value($reason, 'string', false);
        $question = PhpCompatibility::value($question, 'string', false);
        $candidates = (isset($found['suggestions']) ? $found['suggestions'] : []);
        foreach ((isset($found['resolutions']) ? $found['resolutions'] : []) as $r) {
            $candidates = array_merge($candidates, $r['candidates']);
        }
        $unique = [];
        foreach ($candidates as $c) {
            $unique[$c['id']] = $c + ['result_type' => SemanticQuery::target($question)];
        }
        return PhpCompatibility::value(['status' => 'needs_clarification', 'reason' => $reason, 'message' => ($reason === 'broader_term' ? 'That is a broader term. Which specific KG entity did you mean?' : ($reason === 'terminology_confirmation' ? 'The terminology source lists this name for the following KG entities. Please confirm the intended entity and type.' : 'Several KG entities match. Please choose the intended entity and type.')), 'clarification' => ['requested_result_type' => SemanticQuery::target($question), 'candidates' => array_values($unique)], 'resolution' => (isset($found['resolutions']) ? $found['resolutions'] : [])], 'array', false);
    }
    private static function normalizeIntent($question)
    {
        $question = PhpCompatibility::value($question, 'string', false);
        $q = strtolower(trim($question));
        return PhpCompatibility::value(preg_replace_callback('/\b[a-z]{6,}\b/', static function ($match) {
            foreach (['symptoms', 'therapies', 'formulations'] as $word) {
                if (levenshtein($match[0], $word) === 1) {
                    return $word;
                }
            }
            return $match[0];
        }, $q), 'string', false);
    }
    private static function extractEntityCandidates($question)
    {
        $candidates = [];
        if (preg_match_all('/\b(?:of|for|about|in|on|with|to|by)\s+([a-zA-Z0-9\s\.\-]+?)(?=\?|,|\.|(?:\s+is\b)|\s*$)/i', $question, $matches)) {
            foreach ($matches[1] as $m) {
                $candidates[] = trim($m);
            }
        }
        if (preg_match('/^([a-zA-Z\s]+?),\s*(what|how|is|are|tell)/i', $question, $match)) {
            $candidates[] = trim($match[1]);
        }
        return $candidates;
    }
    private static function classifyIntentContextually($q, $entityType)
    {
        $addition = TraversalCatalog::intent($q, $entityType);
        if ($entityType === 'Compound') {
            if (QueryUnderstanding::coordinatedTargets($q) || QueryUnderstanding::procedureRequest($q)) {
                return 'compound_shodhana_process';
            }
            // Output nouns outrank descriptions of intermediate path nodes.
            $outputs = ['Disease' => 'integrated_compound_disease', 'Indication' => 'compound_therapeutic_use', 'Purified_Compound' => 'compound_purified_forms', 'Shodhana_Method' => 'compound_methods', 'Media' => 'compound_media'];
            $requested = SemanticQuery::target($q);
            // Evidence qualifies the requested output; an intermediate node must
            // not override an explicit request for a different result type.
            if ($addition !== null) {
                $steps = call_user_func(function () use (&$addition) {
                    $__kgCoalesce2 = TraversalCatalog::all();
                    return (isset($__kgCoalesce2[$addition]['steps']) ? $__kgCoalesce2[$addition]['steps'] : []);
                });
                $terminal = ($steps ? $steps[count($steps) - 1] : []);
                if (in_array($requested, (isset($terminal['types']) ? $terminal['types'] : []), true)) {
                    return $addition;
                }
            }
            if (isset($outputs[$requested])) {
                return $outputs[$requested];
            }
        }
        if ($addition !== null) {
            return $addition;
        }
        if ($entityType === 'Process') {
            return 'process_general';
        }
        if ($entityType === 'Compound') {
            if (SemanticQuery::target($q) === 'Shodhana_Method' || preg_match('/\bwhat is used to purify\b/i', $q)) {
                return 'compound_methods';
            }
            if (preg_match('/\b(purified form|purified forms|purified compound|purified product|derived|derivatives|produce)/i', $q)) {
                return 'compound_purified_forms';
            }
            // Priority 1: Explicit integrated/multi-hop intents
            if (preg_match('/\b(disease|diseases|condition|linked|treat|treats|treating|treat this disease|rog|bimari|ilaj|chikitsa|upayog)\b/i', $q)) {
                return 'integrated_compound_disease';
            }
            if (preg_match('/\b(complete\s+(shodhana|purification)\s+(process|procedure)|how\s+is\s+.*?\s+purified|how\s+is\s+.*?\s+subjected\s+to\s+shodhana|method\s+and\s+media|purification\s+(process|procedure)|purified\s+using\s+shodhana|shodhana\s+procedure)/i', $q)) {
                return 'compound_shodhana_process';
            }
            // Priority 2: Specific single-hop intents
            if (preg_match('/\b(complete shodhana process|how is .* purified|purification process|purified using shodhana|shodhana procedure)/i', $q)) {
                return 'compound_shodhana_process';
            }
            if (preg_match('/\b(substances?|materials?)\b.*\b(purification|purify)\b/i', $q)) {
                return 'compound_media';
            }
            if (preg_match('/\b(media|medium|liquid|purified with|shodhana media|dravya)/i', $q)) {
                return 'compound_media';
            }
            if (preg_match('/\b(validat|confirm|stud|proof)/i', $q)) {
                return 'compound_validation';
            }
            if (preg_match('/\b(toxic|poison|harmful)/i', $q)) {
                return 'compound_toxicity';
            }
            if (preg_match('/\b(detoxif)/i', $q)) {
                return 'compound_detoxification';
            }
            // A purification verb alone does not specify methods, media or products.
            // Explicit requested output types above determine the traversal;
            // otherwise the general intent asks for clarification.
            if (preg_match('/\b(therap|indicat|used for)/i', $q)) {
                return 'compound_therapeutic_use';
            }
            // Priority 3: Generic intent
            return 'compound_general';
        }
        if ($entityType === 'Toxic_Substance') {
            if (preg_match('/\b(shodhana|method|process|detoxif|remove)/i', $q)) {
                return 'toxic_substance_method';
            }
            if (preg_match('/\b(compound|contain|which)/i', $q)) {
                return 'toxic_substance_compound';
            }
            return 'toxic_substance_general';
        }
        if ($entityType === 'Purified_Compound') {
            if (preg_match('/\b(validat|confirm|stud|proof)/i', $q)) {
                return 'purified_compound_validation';
            }
            if (preg_match('/\b(indicat|disease|condition|treat|treats|used for|rog|bimari|ilaj|chikitsa|upayog)/i', $q)) {
                return 'purified_compound_disease';
            }
            if (preg_match('/\b(formulat|medicin|drug|churna|bhasma|vati|rasa|leha|ghrita|taila)/i', $q)) {
                return 'purified_compound_formulation';
            }
            if (preg_match('/\b(compound|raw|source|derived)/i', $q)) {
                return 'purified_compound_source';
            }
            return 'purified_compound_general';
        }
        if ($entityType === 'Confirmation_Study') {
            if (preg_match('/\b(method|process|shodhana)/i', $q)) {
                return 'confirmation_study_method';
            }
            if (preg_match('/\b(compound|purified|safe)/i', $q)) {
                return 'confirmation_study_purified_compound';
            }
            return 'confirmation_study_general';
        }
        if ($entityType === 'Ayurvedic_Diagnosis') {
            if (preg_match('/\b(disease|modern|match)/i', $q)) {
                return 'ayurvedic_diagnosis_disease';
            }
            return 'ayurvedic_diagnosis_general';
        }
        if ($entityType === 'Assessment_Parameter') {
            if (preg_match('/\b(disease|assess)/i', $q)) {
                return 'assessment_disease';
            }
            return 'assessment_general';
        }
        if ($entityType === 'Shodhana_Type') {
            if (preg_match('/\b(method|process|classif)/i', $q)) {
                return 'classification_process';
            }
            return 'classification_general';
        }
        if ($entityType === 'Disease') {
            if (SemanticQuery::target($q) === 'Symptom') {
                return 'disease_symptoms';
            }
            if (preg_match('/\b(compound|purif|shodhit)/i', $q)) {
                return 'integrated_disease_compound';
            }
            // integrated
            if (preg_match('/\b(symptom|sign|manifest|lakshan)/i', $q)) {
                return 'disease_symptoms';
            }
            if (preg_match('/\b(therap|treat|treats|cure|heal)\b.*\b(formulat|medicin|drug|preparation|churna|bhasma|vati)\b/i', $q) || preg_match('/\b(formulat|medicin|drug|preparation|churna|bhasma|vati)\b.*\b(therap|treat|treats|cure|heal)\b/i', $q)) {
                return 'disease_formulation';
            }
            if (preg_match('/\b(formulat|medicin|drug|preparation|churna|bhasma|vati)/i', $q)) {
                return 'disease_formulation';
            }
            if (preg_match('/\b(therap|treat|treats|cure|heal|is treated by)/i', $q)) {
                return 'disease_therapy';
            }
            if (preg_match('/\b(dosha|imbalance)/i', $q)) {
                return 'disease_dosha';
            }
            if (preg_match('/\b(assess|parameter|measure)/i', $q)) {
                return 'disease_assessment';
            }
            if (preg_match('/\b(ayurved|diagnosis|diagnos)/i', $q)) {
                return 'disease_diagnosis';
            }
            return 'disease_general';
        }
        if ($entityType === 'Indication') {
            if (preg_match('/\b(treat|treats|therapy|therapeutic|compound|purified|use|shodhit)/i', $q)) {
                return 'indication_compound';
            }
            return 'indication_general';
        }
        if ($entityType === 'Symptom') {
            if (preg_match('/\b(disease|condition|indicat)/i', $q)) {
                return 'symptoms_disease';
            }
            return 'symptoms_general';
        }
        if ($entityType === 'Therapy') {
            if (preg_match('/\b(disease|diseases|condition|indicat|used for|treat|treats|treating|cure|heal)\b/i', $q)) {
                return 'therapy_disease';
            }
            return 'therapy_general';
        }
        if ($entityType === 'Formulation' || $entityType === 'Ayurvedic_Formulation') {
            if (preg_match('/\b(disease|diseases|condition|indicat|used for|treat|treats|treating|cure|heal|rog|bimari|ilaj|chikitsa|upayog)\b/i', $q)) {
                return 'formulation_disease';
            }
            if (preg_match('/\b(compound|compounds|contain|contains|ingredient|ingredients|dravya)\b/i', $q)) {
                return 'formulation_compound';
            }
            if (preg_match('/\b(therapy|therapies|use|uses)\b/i', $q)) {
                return 'formulation_therapy';
            }
            return 'formulation_general';
        }
        if ($entityType === 'Dosha') {
            if (preg_match('/\b(disease|condition|indicat)/i', $q)) {
                return 'dosha_disease';
            }
            return 'dosha_general';
        }
        if ($entityType === 'Shodhana_Method') {
            if (preg_match('/\b(classif|type|category)/i', $q)) {
                return 'process_classification';
            }
            if (preg_match('/\b(purified compound|produce|result)/i', $q)) {
                return 'process_purified_compound';
            }
            if (preg_match('/\b(validat|confirm|stud|proof)/i', $q)) {
                return 'shodhana_method_validation';
            }
            if (preg_match('/\b(disease|diseases|condition|indicat|treat|treats|treating|cure|heal)/i', $q)) {
                return 'therapy_disease';
            }
            if (preg_match('/\b(media|medium|liquid|material|substance)/i', $q)) {
                return 'process_media';
            }
            if (preg_match('/\b(compound|purif|undergo|used for|produc)/i', $q)) {
                return 'process_compound';
            }
            return 'process_general';
        }
        if ($entityType === 'Media') {
            if (preg_match('/\b(compound|purif|undergo|shodhana|used|use|using|method)/i', $q)) {
                return 'media_compound';
            }
            return 'media_general';
        }
        return null;
    }
    private static function getEndpointForIntent($entityType, $intent)
    {
        $spec = call_user_func(function () use (&$intent) {
            $__kgCoalesce3 = TraversalCatalog::all();
            return (isset($__kgCoalesce3[$intent]) ? $__kgCoalesce3[$intent] : null);
        });
        if ($spec && in_array($entityType, $spec['input'], true)) {
            return $intent;
        }
        $map = ['Compound' => ['compound_methods' => 'compound_methods', 'compound_media' => 'compound_media', 'compound_validation' => 'compound_validation', 'compound_toxicity' => 'compound_toxicity', 'compound_shodhana_process' => 'compound_shodhana_process', 'compound_detoxification' => 'compound_detoxification', 'compound_purification' => 'compound_purification', 'compound_therapeutic_use' => 'compound_therapeutic_use', 'compound_purified_forms' => 'compound_purified_forms', 'integrated_compound_disease' => 'integrated_compound_disease'], 'Toxic_Substance' => ['toxic_substance_compound' => 'toxic_substance_compound', 'toxic_substance_method' => 'toxic_substance_method'], 'Purified_Compound' => ['purified_compound_disease' => 'purified_compound_disease', 'purified_compound_formulation' => 'purified_compound_formulation', 'purified_compound_source' => 'purified_compound_source', 'purified_compound_validation' => 'purified_compound_validation'], 'Confirmation_Study' => ['confirmation_study_method' => 'confirmation_study_method', 'confirmation_study_purified_compound' => 'confirmation_study_purified_compound'], 'Ayurvedic_Diagnosis' => ['ayurvedic_diagnosis_disease' => 'ayurvedic_diagnosis_disease'], 'Assessment_Parameter' => ['assessment_disease' => 'assessment_disease'], 'Shodhana_Type' => ['classification_process' => 'classification_process'], 'Disease' => ['disease_symptoms' => 'disease_symptoms', 'disease_therapy' => 'disease_therapy', 'disease_formulation' => 'disease_formulation', 'integrated_disease_compound' => 'integrated_disease_compound', 'disease_dosha' => 'disease_dosha', 'disease_diagnosis' => 'disease_diagnosis', 'disease_assessment' => 'disease_assessment'], 'Indication' => ['indication_compound' => 'indication_compound'], 'Symptom' => ['symptoms_disease' => 'symptoms_disease'], 'Therapy' => ['therapy_disease' => 'therapy_disease'], 'Formulation' => ['formulation_disease' => 'formulation_disease', 'formulation_compound' => 'formulation_compound', 'formulation_therapy' => 'formulation_therapy'], 'Ayurvedic_Formulation' => ['formulation_disease' => 'formulation_disease', 'formulation_compound' => 'formulation_compound', 'formulation_therapy' => 'formulation_therapy'], 'Dosha' => ['dosha_disease' => 'dosha_disease'], 'Shodhana_Method' => ['process_media' => 'process_media', 'shodhana_method_validation' => 'shodhana_method_validation', 'therapy_disease' => 'therapy_disease', 'process_compound' => 'process_compound', 'process_purified_compound' => 'process_purified_compound', 'process_classification' => 'process_classification'], 'Media' => ['media_compound' => 'media_compound']];
        return (isset($map[$entityType][$intent]) ? $map[$entityType][$intent] : null);
    }
}
