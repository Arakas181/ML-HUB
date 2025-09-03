@echo off
echo Starting ML HUB Esports Chat Server...
echo.
echo Make sure XAMPP is running (Apache and MySQL)
echo Press Ctrl+C to stop the server
echo.

cd /d "c:\xampp\htdocs\Esports"
"c:\xampp\php\php.exe" simple_chat_server.php

pause
