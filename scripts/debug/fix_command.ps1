$file = "src\components\ui\command.tsx"
$content = Get-Content $file -Raw
$content = $content -replace 'interface CommandDialogProps extends DialogProps \{\}', 'type CommandDialogProps = DialogProps;'
Set-Content -Path $file -Value $content -Encoding UTF8
