Set objShell = CreateObject("WScript.Shell")
objShell.CurrentDirectory = "C:\BACKUP MEU NOTEBOOK\Projeto ERP\pdv-fiscal"
objShell.Run """C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"" artisan schedule:run", 0, True