$path = "src\lib\types.ts"
$content = Get-Content $path -Raw
$content = $content -replace '(?s)export interface ChatHistoryTurn \{[^}]+\}\s*$', ''
Set-Content $path $content -Encoding UTF8
