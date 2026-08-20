Set shell = CreateObject("WScript.Shell")
batPath = "C:\BACKUP MEU NOTEBOOK\Projeto ERP\pdv-fiscal\iniciar_pdv.bat"

' Executa o .bat de forma invisível
shell.Run Chr(34) & batPath & Chr(34), 0, False

Set shell = Nothing