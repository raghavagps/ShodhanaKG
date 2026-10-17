$file = "src\lib\api.ts"
$content = Get-Content $file -Raw
$content = $content -replace 'limit: number = 50', 'limit: number = 2000'
Set-Content -Path $file -Value $content -Encoding UTF8
