$ErrorActionPreference = "Stop"
$Project = Split-Path -Parent (Split-Path -Parent (Join-Path (Split-Path -Parent (Split-Path -Parent $PSScriptRoot)) 'scripts/backup_mysql.ps1'))
$Php = "D:\xampp\php\php.exe"
if (!(Test-Path $Php)) { throw "PHP XAMPP tidak ditemukan: $Php" }
& $Php "$Project\scripts\backup.php" AUTO
if ($LASTEXITCODE -ne 0) { throw "Backup JAGO LABA gagal." }
Write-Host "Backup JAGO LABA selesai." -ForegroundColor Green
