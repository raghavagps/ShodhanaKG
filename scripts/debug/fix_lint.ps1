$file = "src\context\LanguageContext.tsx"
$content = Get-Content $file -Raw
$content = $content -replace 'any', 'unknown'
Set-Content -Path $file -Value $content -Encoding UTF8

$file = "src\data\compoundGraph.ts"
$content = Get-Content $file -Raw
$content = $content -replace 'any', 'unknown'
Set-Content -Path $file -Value $content -Encoding UTF8

$file = "src\data\diseaseGraph.ts"
$content = Get-Content $file -Raw
$content = $content -replace 'any', 'unknown'
Set-Content -Path $file -Value $content -Encoding UTF8

$file = "src\data\integratedGraph.ts"
$content = Get-Content $file -Raw
$content = $content -replace 'any', 'unknown'
Set-Content -Path $file -Value $content -Encoding UTF8

$file = "tests\provenance.spec.ts"
$content = Get-Content $file -Raw
$content = $content -replace 'any', 'unknown'
Set-Content -Path $file -Value $content -Encoding UTF8

$file = "src\components\ui\command.tsx"
$content = Get-Content $file -Raw
$content = $content -replace 'interface CommandEmptyProps extends DialogPrimitive.DialogContentProps \{\}', 'type CommandEmptyProps = DialogPrimitive.DialogContentProps;'
Set-Content -Path $file -Value $content -Encoding UTF8

$file = "src\components\ui\textarea.tsx"
$content = Get-Content $file -Raw
$content = $content -replace 'export interface TextareaProps\s*extends React.TextareaHTMLAttributes<HTMLTextAreaElement> \{\}', 'export type TextareaProps = React.TextareaHTMLAttributes<HTMLTextAreaElement>;'
Set-Content -Path $file -Value $content -Encoding UTF8

$file = "tailwind.config.ts"
$content = Get-Content $file -Raw
$content = $content -replace 'require\("tailwindcss-animate"\)', 'import("tailwindcss-animate")'
$content = $content -replace 'require\("@tailwindcss/typography"\)', 'import("@tailwindcss/typography")'
Set-Content -Path $file -Value $content -Encoding UTF8
