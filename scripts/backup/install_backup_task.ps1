# Jalankan PowerShell sebagai Administrator.
$ErrorActionPreference = "Stop"
$Project = Split-Path -Parent (Split-Path -Parent (Join-Path (Split-Path -Parent (Split-Path -Parent $PSScriptRoot)) 'scripts/install_backup_task.ps1'))
$Script = "$Project\scripts\backup_mysql.ps1"
$TaskName = "JAGO LABA Daily Backup"
$Action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$Script`""
$Trigger = New-ScheduledTaskTrigger -Daily -At 2:00AM
$Settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries
Register-ScheduledTask -TaskName $TaskName -Action $Action -Trigger $Trigger -Settings $Settings -Description "Backup otomatis MySQL/JSON JAGO LABA setiap hari" -Force | Out-Null
Write-Host "Task '$TaskName' terpasang. Backup berjalan setiap hari pukul 02:00." -ForegroundColor Green
