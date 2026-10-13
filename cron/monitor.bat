@echo off
REM ============================================================
REM MirrorHub — اجرای روزانهٔ پایشگر میرورها روی ویندوز
REM ------------------------------------------------------------
REM برای زمان‌بندی در ویندوز (Task Scheduler)، این دستور را در
REM «برنامهٔ زمان‌بندی وظایف» با تکرار روزانه ثبت کنید:
REM
REM   schtasks /Create /TN "MirrorHub-Monitor" /SC DAILY /ST 04:15 ^
REM            /TR "\"C:\wamp64\bin\php\php8.2.26\php.exe\" \"C:\wamp64\www\mirrorhub_ir\cron.php\""
REM
REM یا از GUI: Task Scheduler → Create Basic Task → Daily →
REM Start a Program → مسیر php.exe با آرگومان cron.php
REM ============================================================
cd /d "%~dp0.."
"C:\wamp64\bin\php\php8.2.26\php.exe" "%~dp0cron.php" %*
echo. >> "%~dp0monitor.log"
echo [%date% %time%] cron.php executed with code %ERRORLEVEL% >> "%~dp0monitor.log"
