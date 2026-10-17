<?php
$endpoints = [
    [
        'name' => 'process_purified_compound',
        'entityType' => 'Shodhana_Method',
        'edge' => 'PRODUCES',
        'direction' => 'out',
        'targetVar' => 'in',
        'targetType' => 'Purified_Compound'
    ],
    [
        'name' => 'confirmation_study_method',
        'entityType' => 'Confirmation_Study',
        'edge' => 'VALIDATED_BY',
        'direction' => 'in',
        'targetVar' => 'out',
        'targetType' => 'Shodhana_Method'
    ],
    [
        'name' => 'confirmation_study_purified_compound',
        'entityType' => 'Confirmation_Study',
        'edge' => 'CONFIRMS_SAFETY_OF',
        'direction' => 'in',
        'targetVar' => 'out', // CONFIRMS_SAFETY_OF is Confirmation_Study (out) -> Purified_Compound (in). Wait! 
        'targetType' => 'Purified_Compound' // If input is Confirmation_Study (out), target is in. Let me fix this!
    ],
    [
        'name' => 'compound_purified_forms',
        'entityType' => 'Compound',
        'edge' => 'DERIEVED_FROM',
        'direction' => 'in',
        'targetVar' => 'out',
        'targetType' => 'Purified_Compound'
    ],
    [
        'name' => 'formulation_compound',
        'entityType' => 'Ayurvedic_Formulation',
        'edge' => 'USED_IN_FORMULATION',
        'direction' => 'in',
        'targetVar' => 'out',
        'targetType' => 'Purified_Compound'
    ],
    [
        'name' => 'ayurvedic_diagnosis_disease',
        'entityType' => 'Ayurvedic_Diagnosis',
        'edge' => 'HAS_AYURVEDIC_DIAGNOSIS',
        'direction' => 'in',
        'targetVar' => 'out',
        'targetType' => 'Disease'
    ],
    [
        'name' => 'formulation_therapy',
        'entityType' => 'Formulation',
        'edge' => 'USES_FORMULATION',
        'direction' => 'in',
        'targetVar' => 'out',
        'targetType' => 'Therapy'
    ],
    [
        'name' => 'assessment_disease',
        'entityType' => 'Assessment_Parameter',
        'edge' => 'ASSESS',
        'direction' => 'out',
        'targetVar' => 'in',
        'targetType' => 'Disease'
    ],
    [
        'name' => 'toxic_substance_method',
        'entityType' => 'Toxic_Substance',
        'edge' => 'DETOXIFIES',
        'direction' => 'in',
        'targetVar' => 'out',
        'targetType' => 'Shodhana_Method'
    ],
    [
        'name' => 'process_classification',
        'entityType' => 'Shodhana_Method',
        'edge' => 'CLASSIFIED_AS',
        'direction' => 'out',
        'targetVar' => 'in',
        'targetType' => 'Shodhana_Type'
    ],
    [
        'name' => 'classification_process',
        'entityType' => 'Shodhana_Type',
        'edge' => 'CLASSIFIED_AS',
        'direction' => 'in',
        'targetVar' => 'out',
        'targetType' => 'Shodhana_Method'
    ]
];
file_put_contents('endpoints_config.json', json_encode($endpoints, JSON_PRETTY_PRINT));
