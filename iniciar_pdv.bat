@echo off
title PDV Fiscal
cd /d "%~dp0"

:: 1. Verifica se a porta 8000 já está em uso
netstat -ano | findstr :8000 >nul
if %errorlevel% equ 0 (
    goto :iniciar_tauri
)

:: 2. Se não estiver rodando, inicia o PHP de forma limpa
start /b php artisan serve >nul 2>&1
timeout /t 2 /nobreak > nul

:iniciar_tauri
:: 3. Abre o executavel de producao do Tauri
start "" "c:\BACKUP MEU NOTEBOOK\Projeto ERP\pdv-fiscal\src-tauri\target\release\app.exe"
exit