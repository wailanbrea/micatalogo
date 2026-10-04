@echo off
cd /d C:\xampp\htdocs\micatalogo
C:\xampp\php\php.exe artisan queue:work database --tries=3 --timeout=120 --sleep=3
