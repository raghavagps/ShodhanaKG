<?php
$ch = curl_init('http://localhost:8081/api/chatbot_response.php');
$payload = json_encode([
    'question' => 'what assessment parameters are used for it?',
    'history' => [
        ['role' => 'user', 'text' => 'what are the symptoms of venous ulcer?'],
        ['role' => 'bot', 'text' => 'Based on the provided knowledge context, the symptoms of Venous Ulcer are...'],
        ['role' => 'user', 'text' => 'what assessment parameters are used for it?'],
        ['role' => 'bot', 'text' => 'Based on the provided knowledge context, the assessment parameters used for Venous Ulcer are not explicitly mentioned. The context only provides information about the Bates-Jensen Wound Assessment Tool (Bwat) and two related entities: Chronic Non-Healing Ulcer and Diabetic Foot Ulcer. The assessment parameters used for Venous Ulcer are not explicitly stated in the provided knowledge context.']
    ]
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$res = curl_exec($ch);
echo $res;
