$TaskName = "JAGO LABA Daily Backup"
Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue
Write-Host "Task backup dihapus." -ForegroundColor Yellow
