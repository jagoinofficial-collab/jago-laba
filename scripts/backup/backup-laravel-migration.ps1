$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$backupRoot = 'D:\JAGO_LABA_BACKUP\laravel-migration\20261101-step1-4'
$stagingRoot = 'D:\JAGO_LABA_STAGING\laravel-step1-4-20261101'
if (Test-Path -LiteralPath $backupRoot) { throw 'Immutable backup target already exists; inspect manifest instead of overwriting.' }
New-Item -ItemType Directory -Path $backupRoot | Out-Null
$taskSid = [System.Security.Principal.WindowsIdentity]::GetCurrent().User.Value
& icacls $backupRoot /inheritance:r /grant:r "*$($taskSid):(OI)(CI)F" '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Backup ACL could not be restricted.' }
$entries = @()
$sources = @(
    @{ Source = (Join-Path $projectRoot 'storage\private'); Target = 'files\storage-private'; Kind = 'private-files-and-config' },
    @{ Source = (Join-Path $projectRoot 'public\storage\documents'); Target = 'files\legacy-documents'; Kind = 'legacy-documents' },
    @{ Source = (Join-Path $stagingRoot 'backup'); Target = 'database'; Kind = 'supplied-sql-20261030-not-current-live-snapshot' }
)
foreach ($sourceGroup in $sources) {
    $exists = Test-Path -LiteralPath $sourceGroup.Source
    $sourceFiles = @()
    if ($exists) { $sourceFiles = @(Get-ChildItem -LiteralPath $sourceGroup.Source -Recurse -File | Where-Object Name -ne '.gitkeep') }
    foreach ($sourceFile in $sourceFiles) {
        $relative = $sourceFile.FullName.Substring($sourceGroup.Source.Length).TrimStart('\')
        $destination = Join-Path (Join-Path $backupRoot $sourceGroup.Target) $relative
        New-Item -ItemType Directory -Path (Split-Path $destination) -Force | Out-Null
        Copy-Item -LiteralPath $sourceFile.FullName -Destination $destination
        $sourceHash = (Get-FileHash -LiteralPath $sourceFile.FullName -Algorithm SHA256).Hash
        $copyHash = (Get-FileHash -LiteralPath $destination -Algorithm SHA256).Hash
        if ($sourceHash -ne $copyHash) { throw 'Backup checksum mismatch.' }
        $entries += @{ RelativePath = $sourceGroup.Target + '\' + $relative; Bytes = $sourceFile.Length; SHA256 = $copyHash; Kind = $sourceGroup.Kind }
    }
    $entries += @{ SourceGroup = $sourceGroup.Kind; SourceExists = $exists; FileCount = $sourceFiles.Count }
}
$keySource = 'D:\xampp\apache\conf\extra\jago-laba-marketplace.key'
$keyCopied = $false
if (Test-Path -LiteralPath $keySource) {
    New-Item -ItemType Directory -Path (Join-Path $backupRoot 'secrets') -Force | Out-Null
    $keyDestination = Join-Path $backupRoot 'secrets\marketplace.key'
    Copy-Item -LiteralPath $keySource -Destination $keyDestination
    if ((Get-FileHash -LiteralPath $keySource).Hash -ne (Get-FileHash -LiteralPath $keyDestination).Hash) { throw 'Key copy mismatch.' }
    $keyCopied = $true
}
@{ CreatedAt = (Get-Date -Format o); Status = 'snapshot-of-locally-available-files; completeness-requires-DB-reference-audit'; KeyCopied = $keyCopied; Entries = $entries } |
    ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $backupRoot 'manifest.json') -Encoding utf8
Write-Output "Private backup created: $backupRoot"
Write-Output 'Secret values and key hashes omitted. Source connection configuration is backup-only; do not restore it as staging configuration.'
