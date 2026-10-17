<?php

require_once __DIR__ . "/core/EvidenceCorrelator.php";
require_once __DIR__ . "/core/EdgePresentation.php";
require_once __DIR__ . '/db.php';
function get_schema_vertex_types()
{
    return array('Disease', 'Dosha', 'Ayurvedic_Diagnosis', 'Symptom', 'Therapy', 'Formulation', 'Assessment_Parameter', 'Compound', 'Shodhana_Method', 'Toxic_Substance', 'Confirmation_Study', 'Media', 'Purified_Compound', 'Shodhana_Type', 'Process', 'Indication', 'Ayurvedic_Formulation');
}
function vertex_types_for_domain($domain)
{
    $types = array('disease' => array('Disease', 'Dosha', 'Ayurvedic_Diagnosis', 'Symptom', 'Therapy', 'Formulation', 'Assessment_Parameter'), 'compound' => array('Compound', 'Shodhana_Method', 'Toxic_Substance', 'Confirmation_Study', 'Media', 'Purified_Compound', 'Shodhana_Type', 'Process', 'Indication', 'Ayurvedic_Formulation'), 'integrated' => get_schema_vertex_types());
    return (isset($types[$domain]) ? $types[$domain] : $types['disease']);
}
function edge_types_for_domain($domain)
{
    $types = array('disease' => array('HAS_DOSHA_IMBALANCE', 'HAS_AYURVEDIC_DIAGNOSIS', 'HAS_SYMPTOM', 'PRE_TREATED_BY', 'POST_TREATED_BY', 'TREATED_BY', 'USES_FORMULATION', 'RESOLVED_BY', 'ASSESS'), 'compound' => array('UNDERGOES', 'CONTAINS', 'VALIDATED_BY', 'USES_MEDIA', 'CONFIRMS_SAFETY_OF', 'DERIEVED_FROM', 'DETOXIFIES', 'CLASSIFIED_AS', 'USED_FOR_TREATMENT', 'USED_IN_FORMULATION', 'FOLLOWED_BY', 'TREATS', 'PRODUCES'), 'integrated' => array('ASSESS', 'CLASSIFIED_AS', 'CONFIRMS_SAFETY_OF', 'CONTAINS', 'DERIEVED_FROM', 'DETOXIFIES', 'FOLLOWED_BY', 'HAS_AYURVEDIC_DIAGNOSIS', 'HAS_DOSHA_IMBALANCE', 'HAS_SYMPTOM', 'POST_TREATED_BY', 'PRE_TREATED_BY', 'PRODUCES', 'RESOLVED_BY', 'TREATED_BY', 'UNDERGOES', 'USED_FOR_TREATMENT', 'USED_IN_FORMULATION', 'USES_FORMULATION', 'USES_MEDIA', 'TREATS', 'VALIDATED_BY'));
    return (isset($types[$domain]) ? $types[$domain] : $types['disease']);
}
function normalize_type($dbType)
{
    switch ($dbType) {
        case 'Ayurvedic_Diagnosis':
            return 'AyurvedicDiagnosis';
        case 'Assessment_Parameter':
            return 'AssessmentParameter';
        case 'Shodhana_Method':
            return 'ShodhanaMethod';
        case 'Toxic_Substance':
            return 'ToxicSubstance';
        case 'Confirmation_Study':
            return 'ConfirmationStudy';
        case 'Purified_Compound':
            return 'PurifiedCompound';
        case 'Shodhana_Type':
            return 'ShodhanaType';
        case 'Ayurvedic_Formulation':
            return 'AyurvedicFormulation';
        default:
            return $dbType;
    }
}
function db_type_for_frontend_type($frontendType)
{
    switch ($frontendType) {
        case 'AyurvedicDiagnosis':
            return 'Ayurvedic_Diagnosis';
        case 'AssessmentParameter':
            return 'Assessment_Parameter';
        case 'ShodhanaMethod':
            return 'Shodhana_Method';
        case 'ToxicSubstance':
            return 'Toxic_Substance';
        case 'ConfirmationStudy':
            return 'Confirmation_Study';
        case 'PurifiedCompound':
            return 'Purified_Compound';
        case 'ShodhanaType':
            return 'Shodhana_Type';
        case 'AyurvedicFormulation':
            return 'Ayurvedic_Formulation';
        default:
            return $frontendType;
    }
}
function normalize_edge_type($dbEdgeType)
{
    switch ($dbEdgeType) {
        case 'HAS_SYMPTOM':
            return 'HAS_SYMPTOM';
        case 'HAS_DOSHA_IMBALANCE':
            return 'HAS_DOSHA_IMBALANCE';
        case 'PRE_TREATED_BY':
            return 'PRETREATED_BY';
        case 'POST_TREATED_BY':
            return 'POSTTREATED_BY';
        case 'USES':
            return 'USES_FORMULATION';
        case 'DERIEVED_FROM':
            return 'DERIVED_FROM';
        case 'ASSESS':
            return 'ASSESS';
        case 'TREATS':
            return 'TREATS';
        default:
            return $dbEdgeType;
    }
}
function label_fields_for_type($dbType)
{
    switch ($dbType) {
        case 'Disease':
            return array('name', 'Disease');
        case 'Symptom':
            return array('name', 'Symptoms');
        case 'Ayurvedic_Diagnosis':
            return array('name', 'label');
        case 'Dosha':
            return array('name', 'Doshas');
        case 'Therapy':
            return array('name', 'Therapy');
        case 'Formulation':
            return array('name', 'Formulation');
        case 'Assessment_Parameter':
            return array('name', 'Type');
        case 'Compound':
            return ['name', 'Ayurvedic_Name', 'Common_Name', 'label'];
        case 'Shodhana_Method':
        case 'Toxic_Substance':
        case 'Confirmation_Study':
        case 'Media':
        case 'Purified_Compound':
        case 'Shodhana_Type':
        case 'Process':
            return array('name', 'label');
        default:
            return array('name', 'label', 'Disease', 'Symptoms');
    }
}
function description_fields_for_type($dbType)
{
    switch ($dbType) {
        case 'Disease':
            return array('description', 'Description');
        case 'Therapy':
            return array('description', 'Description', 'Therapy_Description');
        case 'Assessment_Parameter':
            return array('description', 'Description', 'Type');
        case 'Compound':
        case 'Shodhana_Type':
        case 'Media':
        case 'Process':
            return array('description', 'Description');
        case 'Shodhana_Method':
            return array('description', 'Method_Description', 'Description');
        default:
            return array('description', 'Description');
    }
}
function search_fields_for_type($dbType)
{
    switch ($dbType) {
        case 'Disease':
            return array('name', 'Disease', 'Description');
        case 'Symptom':
            return array('name', 'Symptoms');
        case 'Ayurvedic_Diagnosis':
            return array('name', 'label', 'Description.1');
        case 'Dosha':
            return array('name', 'Doshas');
        case 'Therapy':
            return array('name', 'Therapy', 'Therapy_Description');
        case 'Formulation':
            return array('name', 'Formulation', 'Description.4');
        case 'Assessment_Parameter':
            return array('name', 'Description', 'Type');
        case 'Compound':
            return ['name', 'Ayurvedic_Name', 'Common_Name', 'label'];
        case 'Shodhana_Method':
        case 'Toxic_Substance':
        case 'Confirmation_Study':
        case 'Media':
        case 'Purified_Compound':
        case 'Shodhana_Type':
        case 'Process':
            return array('name', 'description');
        default:
            return array('name', 'description', 'Disease', 'Symptoms', 'label');
    }
}
function vertex_label($rec)
{
    $t = (isset($rec['@type']) ? $rec['@type'] : '');
    foreach (label_fields_for_type($t) as $f) {
        $v = (isset($rec[$f]) ? trim((string) $rec[$f]) : '');
        if ($v !== '') {
            return $v;
        }
    }
    return (isset($rec['name']) ? trim((string) $rec['name']) : '');
}
function vertex_description($rec)
{
    $t = (isset($rec['@type']) ? $rec['@type'] : '');
    foreach (description_fields_for_type($t) as $f) {
        $v = (isset($rec[$f]) ? trim((string) $rec[$f]) : '');
        if ($v !== '') {
            return $v;
        }
    }
    return '';
}
function vertex_id($rec)
{
    if (isset($rec['@rid'])) {
        return $rec['@rid'];
    }
    if (isset($rec['id'])) {
        return $rec['id'];
    }
    return '';
}
function build_node($rec)
{
    $node = array('id' => vertex_id($rec), 'label' => vertex_label($rec), 'type' => normalize_type((isset($rec['@type']) ? $rec['@type'] : '')), 'description' => vertex_description($rec));
    foreach ($rec as $k => $v) {
        if (in_array($k, array('@rid', '@type', '@class', '@version', '@cat', 'id', 'name', 'label', 'description', 'Description', 'Common_Name'), true)) {
            continue;
        }
        if (!isset($node[$k])) {
            $node[$k] = $v;
        }
    }
    return $node;
}
function map_edge_props($rec)
{
    $raw = array_filter($rec, static function ($key) {
        return strpos($key, '@') !== 0;
    }, ARRAY_FILTER_USE_KEY);
    $props = ['properties' => $raw, 'display_properties' => EdgePresentation::properties($raw), 'raw_type' => (isset($rec['@type']) ? $rec['@type'] : ''), 'treatment_phase' => EdgePresentation::phase((isset($rec['@type']) ? $rec['@type'] : ''))];
    if (!empty($rec['ref'])) {
        $props['reference'] = (string) $rec['ref'];
    }
    foreach ($rec as $k => $v) {
        $mapped = null;
        switch ($k) {
            case 'Pubmed ID':
                $mapped = null;
                break;
            case 'ref':
                $mapped = null;
                break;
            case 'Outcome Measure':
            case 'Outcome_Measure':
            case 'Output Measure':
            case 'Output_Measure':
                $mapped = 'outputMeasure';
                break;
            case 'Adjuvant':
            case 'adjuvant':
                $mapped = 'adjuvant';
                break;
            case 'Dose':
            case 'dose':
                $mapped = 'dose';
                break;
            case 'Duration':
            case 'duration':
                $mapped = 'duration';
                break;
            case 'Procedure':
                $mapped = 'procedure';
                break;
            case 'Result':
                $mapped = 'result';
                break;
            case 'Media_Used':
            case 'Media Used':
                $mapped = 'mediaUsed';
                break;
        }
        if ($mapped !== null && $v !== null) {
            $props[$mapped] = $v;
        }
    }
    $ids = EvidenceCorrelator::extractIdentifiers($rec);
    $props['references'] = $ids['references'];
    foreach (['pubmed_id' => 'pubmedId', 'doi' => 'doi', 'ayush_id' => 'ayushId'] as $from => $to) {
        if ($ids[$from] !== '') {
            $props[$to] = $ids[$from];
        }
    }
    return $props;
}
function fetch_vertex_map($ridCsv)
{
    if (!$ridCsv) {
        return array();
    }
    $sql = sprintf('SELECT FROM %s LIMIT 2000', $ridCsv);
    $res = arcade_command($sql, 'sql');
    $map = array();
    $results = (isset($res['result']) ? $res['result'] : array());
    foreach ($results as $rec) {
        $rid = (isset($rec['@rid']) ? $rec['@rid'] : '');
        if ($rid) {
            $map[$rid] = build_node($rec);
        }
    }
    return $map;
}
function build_edge($rec, $vertexMap)
{
    $outRid = (isset($rec['@out']) ? $rec['@out'] : '');
    $inRid = (isset($rec['@in']) ? $rec['@in'] : '');
    if (!isset($vertexMap[$outRid], $vertexMap[$inRid])) {
        return null;
    }
    $src = (isset($vertexMap[$outRid]['id']) ? $vertexMap[$outRid]['id'] : $outRid);
    $tgt = (isset($vertexMap[$inRid]['id']) ? $vertexMap[$inRid]['id'] : $inRid);
    if (!$src || !$tgt) {
        return null;
    }
    $props = map_edge_props($rec);
    $base = array('id' => (isset($rec['@rid']) ? $rec['@rid'] : ''), 'source' => $src, 'target' => $tgt, 'type' => normalize_edge_type((isset($rec['@type']) ? (string) $rec['@type'] : '')));
    return array_merge($base, $props);
}
function resolve_seeds($seedIds, $domain = 'disease')
{
    $dbTypes = vertex_types_for_domain($domain);
    $seeds = array();
    foreach ($seedIds as $s) {
        $s = trim($s);
        if (!$s) {
            continue;
        }
        if (substr($s, 0, 1) === '#') {
            if (!preg_match('/^#\d+:\d+$/D', $s)) {
                continue;
            }
            $sql = sprintf("SELECT FROM %s LIMIT 1", $s);
            try {
                $res = arcade_command($sql, 'sql');
                if (!empty($res['result'][0])) {
                    $seeds[] = $res['result'][0];
                }
            } catch (Exception $e) {
                /* skip */
            }
        } else {
            foreach ($dbTypes as $type) {
                $sql = sprintf("SELECT FROM `%s` WHERE id = '%s' LIMIT 1", $type, arcade_escape($s));
                try {
                    $res = arcade_command($sql, 'sql');
                    if (!empty($res['result'][0])) {
                        $seeds[] = $res['result'][0];
                        break;
                    }
                } catch (Exception $e) {
                    /* skip */
                }
            }
        }
    }
    return $seeds;
}
function arcade_escape($s)
{
    return str_replace(array("'", '\\'), array("''", '\\\\'), $s);
}
/** Direct node details use the same canonical identities and edge DTO as graphs. */
function fetch_node_context($id, $domain, $neighborLimit = 50)
{
    $seeds = resolve_seeds([$id], $domain);
    if (count($seeds) !== 1) {
        return null;
    }
    $seed = $seeds[0];
    $rid = $seed['@rid'];
    $schema = json_decode(file_get_contents(__DIR__ . '/core/relationship_metadata.json'), true)['edges'];
    $rawEdges = [];
    $neighbors = [];
    $truncated = false;
    foreach (array_intersect(edge_types_for_domain($domain), array_keys($schema)) as $type) {
        $rows = call_user_func(function () use (&$type, &$rid) {
            $__kgCoalesce1 = arcade_command('SELECT FROM `' . $type . '` WHERE @out = ' . $rid . ' OR @in = ' . $rid);
            return (isset($__kgCoalesce1['result']) ? $__kgCoalesce1['result'] : []);
        });
        foreach ($rows as $edge) {
            $other = ($edge['@out'] === $rid ? $edge['@in'] : $edge['@out']);
            if ($other !== $rid && !isset($neighbors[$other]) && count($neighbors) >= $neighborLimit) {
                $truncated = true;
                continue;
            }
            if ($other !== $rid) {
                $neighbors[$other] = true;
            }
            $rawEdges[$edge['@rid']] = $edge;
        }
    }
    $map = ($neighbors ? fetch_vertex_map(implode(',', array_keys($neighbors))) : []);
    $node = build_node($seed);
    $map[$rid] = $node;
    $edges = [];
    $evidence = [];
    foreach ($rawEdges as $edge) {
        $dto = build_edge($edge, $map);
        if (!$dto) {
            continue;
        }
        $edges[] = $dto;
        $evidence[] = ['edgeId' => $dto['id']] + $dto;
    }
    unset($map[$rid]);
    return ['node' => $node, 'neighbors' => array_values($map), 'edges' => $edges, 'evidence' => $evidence, 'meta' => ['truncated' => $truncated, 'neighborLimit' => $neighborLimit]];
}
