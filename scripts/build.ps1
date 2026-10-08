$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$pluginDir = Join-Path $projectRoot 'ferienwohnung-buchung'
if (!(Test-Path -LiteralPath (Join-Path $pluginDir 'lib/dompdf/autoload.inc.php'))) { throw 'Dompdf fehlt.' }
Copy-Item -LiteralPath (Join-Path $projectRoot 'README.md') -Destination (Join-Path $pluginDir 'ANLEITUNG.md') -Force
$distDir = Join-Path $projectRoot 'dist'
New-Item -ItemType Directory -Force -Path $distDir | Out-Null
$pluginHeader = Get-Content -LiteralPath (Join-Path $pluginDir 'ferienwohnung-buchung.php') -Raw
$version = [regex]::Match($pluginHeader, 'Version:\s*([\d.]+)').Groups[1].Value
if (!$version) { throw 'Plugin-Version fehlt.' }
$zipPath = Join-Path $distDir ('ferienwohnung-buchung-' + $version + '.zip')
Compress-Archive -LiteralPath $pluginDir -DestinationPath $zipPath -Force
Get-FileHash -LiteralPath $zipPath -Algorithm SHA256
Get-Item -LiteralPath $zipPath | Select-Object FullName,Length
