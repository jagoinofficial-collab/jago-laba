$ErrorActionPreference = 'Stop'
$workspacePath = Split-Path -Parent (Join-Path (Split-Path -Parent (Split-Path -Parent $PSScriptRoot)) 'scripts')
$phpCliPath = if ($env:JL_PHP_CLI) { $env:JL_PHP_CLI } else { 'D:\xampp\php\php.exe' }
$quotePath = { param($value) "'" + $value.Replace("'", "''") + "'" }
$phpQuoted = & $quotePath $phpCliPath
$scriptQuoted = & $quotePath (Join-Path (Join-Path (Split-Path -Parent (Split-Path -Parent $PSScriptRoot)) 'scripts') 'marketplace-auto-sync.php')
$sessionQuoted = & $quotePath ('session.save_path=' + (Join-Path $workspacePath 'storage'))
$taskArguments = '-NoProfile -NonInteractive -WindowStyle Hidden -Command "& ' + $phpQuoted + " '-d' " + $sessionQuoted + ' ' + $scriptQuoted + '; exit $LASTEXITCODE"'
$taskAction = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $taskArguments
$taskTrigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$taskSettings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 2) -StartWhenAvailable
Register-ScheduledTask -TaskName 'JagoLaba-Marketplace-AutoSync' -Action $taskAction -Trigger $taskTrigger -Settings $taskSettings -Description 'Sinkronisasi pesanan marketplace sesuai pengaturan per toko Jago Laba.' -Force | Out-Null
Start-ScheduledTask -TaskName 'JagoLaba-Marketplace-AutoSync'
