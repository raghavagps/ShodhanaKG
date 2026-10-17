<?php
$ch = curl_init('http://localhost:8081/api/chatbot_response.php');
$payload = json_encode([
    'question' => 'what assessment parameters used for it?',
    'history' => [
        ['role' => 'user', 'text' => 'what are the symptoms of venous ulcer?'],
        ['role' => 'bot', 'text' => 'Based on the provided knowledge context, the symptoms of Venous Ulcer are...']
    ]
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$res = curl_exec($ch);
echo $res;
