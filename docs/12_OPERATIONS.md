# Operations Guide — MiCatalogo

Operational procedures for Windows VPS hosting.

## 1. Windows Task Scheduler (Laravel Scheduler)

To ensure scheduled commands (cleaning stale upload files in `storage/app/private/temp` and hard-purging soft-deleted products older than 30 days) execute automatically every minute:

Create a scheduled task in Windows PowerShell as Administrator:

```powershell
$Action = New-ScheduledTaskAction -Execute "C:\xampp\php\php.exe" -Argument "C:\xampp\php\www\MiCatalogo\artisan schedule:run"
$Trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration ([TimeSpan]::MaxValue)
Register-ScheduledTask -TaskName "MiCatalogo_Scheduler" -Action $Action -Trigger $Trigger -User "SYSTEM"
```

To verify execution:
```powershell
Get-ScheduledTask -TaskName "MiCatalogo_Scheduler"
```

## 2. Persistent Background Queue Worker

Background media processing (`ProcessProductImageJob`) processes files asynchronously via `QUEUE_CONNECTION=database`.

In production on Windows VPS, configure NSSM (Non-Sucking Service Manager) or an elevated background task:

```powershell
# Using NSSM to create a Windows Service:
nssm install MiCatalogoQueue "C:\xampp\php\php.exe" "C:\xampp\php\www\MiCatalogo\artisan queue:work --tries=3 --timeout=120 --sleep=3"
nssm set MiCatalogoQueue AppDirectory "C:\xampp\php\www\MiCatalogo"
nssm set MiCatalogoQueue Start SERVICE_AUTO_START
nssm start MiCatalogoQueue
```

## 3. Queue & Job Failure Monitoring

```powershell
# Check failed jobs table:
php artisan queue:failed

# Retry all failed jobs after issue resolution:
php artisan queue:retry all

# Purge failed jobs older than 168 hours (automated weekly):
php artisan queue:prune-failed --hours=168
```

## 4. Log Inspection & Rotation

- Laravel logs: `storage/logs/laravel.log`.
- Apache logs: `C:\xampp\apache\logs\micatalogo-error.log`.
- Failed image processing: monitored in `ProductImage` table (`processing_status = failed`) and admin dashboard `/admin`.

