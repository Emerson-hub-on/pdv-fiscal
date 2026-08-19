@echo off
title PDV Fiscal
cd /d "%~dp0"

:: Inicia o PHP Artisan Serve em segundo plano
start /min cmd /k "php artisan serve"

:: Aguarda 3 segundos para o PHP carregar
timeout /t 3 /nobreak > nul

:: Inicia o Tauri (seu app nativo)
npm run tauri dev