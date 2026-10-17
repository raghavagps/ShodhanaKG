$file = "src\lib\chatApi.ts"
$content = Get-Content $file -Raw
$content = $content -replace 'onContext: \(context: any\) => void', 'onContext: (context: Record<string, unknown>) => void'
$content = $content -replace 'catch \(e\) \{\s*\}', 'catch (e) { /* ignore */ }'
Set-Content -Path $file -Value $content -Encoding UTF8
