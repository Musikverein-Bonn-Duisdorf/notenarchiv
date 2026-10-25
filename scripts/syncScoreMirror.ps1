# Copy Nextcloud/pCloud Notenarchiv into the local gitignored mirror.
# NEVER writes to the source mount — only reads from it.
#
# Usage (from repo root):
#   powershell -File scripts/syncScoreMirror.ps1
#   powershell -File scripts/syncScoreMirror.ps1 -Source "D:\other\Notenarchiv"

param(
    [string]$Source = "$env:USERPROFILE\Documents\MVD Cloud\Notenarchiv",
    [string]$Destination = ""
)

$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
if($Destination -eq '') {
    $Destination = Join-Path $repoRoot 'data\Notenarchiv'
}

if(-not (Test-Path -LiteralPath $Source)) {
    Write-Error "Source missing (read-only mount expected): $Source"
}

New-Item -ItemType Directory -Force -Path $Destination | Out-Null
Write-Host "Source (read-only): $Source"
Write-Host "Destination:        $Destination"

# /E copy subdirs; /COPY:DAT no ACL; never /MIR onto the source
& robocopy $Source $Destination /E /COPY:DAT /R:2 /W:2 /MT:8 /NFL /NDL /NP
$code = $LASTEXITCODE
# robocopy 0–7 = success with various copy stats
if($code -ge 8) {
    Write-Error "robocopy failed with exit $code"
}

$srcPdf = @(Get-ChildItem -LiteralPath $Source -Recurse -File -Filter *.pdf -ErrorAction SilentlyContinue).Count
$dstPdf = @(Get-ChildItem -LiteralPath $Destination -Recurse -File -Filter *.pdf -ErrorAction SilentlyContinue).Count
Write-Host "PDFs source=$srcPdf destination=$dstPdf robocopy_exit=$code"
if($dstPdf -lt $srcPdf) {
    Write-Warning "Destination has fewer PDFs than source — re-run after cloud sync settles."
}
