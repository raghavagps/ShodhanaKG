<?php
require 'api/core/ChatbotOrchestrator.php';
require 'api/core/EntityResolver.php';

$history = [
    ['role' => 'user', 'text' => 'what are the symptoms of venous ulcer?'],
    ['role' => 'bot', 'text' => 'Based on the provided knowledge context, the symptoms of Venous Ulcer are...'],
    ['role' => 'user', 'text' => 'what assessment parameters are used for it?'],
    ['role' => 'bot', 'text' => 'Based on the provided knowledge context, the assessment parameters used for Venous Ulcer are not explicitly mentioned. The context only provides information about the Bates-Jensen Wound Assessment Tool (Bwat) and two related entities: Chronic Non-Healing Ulcer and Diabetic Foot Ulcer. The assessment parameters used for Venous Ulcer are not explicitly stated in the provided knowledge context.']
];

$originalQ = 'what assessment parameters are used for it?';

for ($i = count($history) - 1; $i >= 0; $i--) {
    if (isset($history[$i]['role']) && $history[$i]['role'] === 'user') {
        $lastQ = $history[$i]['text'];
        echo "Found last user Q: $lastQ\n";
        break;
    }
}
