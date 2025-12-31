@echo off
:loop
cls
echo [Worker] Checking for new jobs...
php cron/process_jobs.php
timeout /t 60
goto loop
