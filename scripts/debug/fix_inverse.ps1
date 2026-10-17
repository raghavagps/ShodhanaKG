$file = "api\tests\test_chatbot_inverse.php"
$content = Get-Content $file -Raw
$content = $content -replace "start /B php -S localhost:\{\`$apiPort\} -t \" \. realpath", "start /B php -S localhost:{`$apiPort} -t `\"\" . realpath"
$content = $content -replace "\/mock_ollama.php\"", "/mock_ollama.php`\"\""
Set-Content -Path $file -Value $content -Encoding UTF8
