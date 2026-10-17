$file = "api\tests\fixtures\chatbot_evaluation_cases.json"
$content = Get-Content $file -Raw
$content = $content -replace '"expected_endpoint": "compound_purification"', '"expected_endpoint": "compound_shodhana_process"'
Set-Content -Path $file -Value $content -Encoding UTF8
