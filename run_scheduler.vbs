Option Explicit

Dim objFSO, objShell, objWMI, colProcessos, objProcesso
Dim strProjeto, strPhp, strPasta, objPastaLaragon, objSubPasta, strCandidato
Dim objExec, strSaida, jaRodando

Set objFSO = CreateObject("Scripting.FileSystemObject")
Set objShell = CreateObject("WScript.Shell")

' ===================== 1. LOCALIZA A PASTA DO PROJETO =====================
' Este arquivo precisa estar salvo dentro da pasta raiz do projeto
' (ex: C:\pdv-fiscal\run_scheduler.vbs). Assim ele acha o projeto sozinho,
' não importa em qual máquina ou em qual pasta ele esteja.
strProjeto = objFSO.GetParentFolderName(WScript.ScriptFullName)
objShell.CurrentDirectory = strProjeto

If Not objFSO.FileExists(strProjeto & "\artisan") Then
    MsgBox "Não encontrei o arquivo 'artisan' em:" & vbCrLf & strProjeto & vbCrLf & _
           "Confirme se este .vbs está salvo na raiz do projeto Laravel.", vbCritical, "run_scheduler"
    WScript.Quit 1
End If

' ===================== 2. EVITA RODAR EM DUPLICIDADE =====================
' Se o Windows já tiver um schedule:work rodando (ex: reinício rápido,
' login duplicado), não sobe outro processo por cima.
jaRodando = False
On Error Resume Next
Set objWMI = GetObject("winmgmts:\\.\root\cimv2")
Set colProcessos = objWMI.ExecQuery("Select CommandLine from Win32_Process Where Name = 'php.exe'")
For Each objProcesso In colProcessos
    If InStr(objProcesso.CommandLine, "schedule:work") > 0 Then
        jaRodando = True
    End If
Next
On Error Goto 0

If jaRodando Then
    WScript.Quit 0
End If

' ===================== 3. DESCOBRE O PHP AUTOMATICAMENTE =====================
strPhp = ""

' Tenta primeiro o PATH do sistema (funciona se o Laragon foi adicionado ao PATH)
On Error Resume Next
Set objExec = objShell.Exec("cmd /c where php")
If Err.Number = 0 Then
    strSaida = ""
    Do While Not objExec.StdOut.AtEndOfStream
        strSaida = strSaida & objExec.StdOut.ReadLine()
    Loop
    If Trim(strSaida) <> "" And objFSO.FileExists(Trim(strSaida)) Then
        strPhp = "php"
    End If
End If
Err.Clear
On Error Goto 0

' Se não achou no PATH, procura qualquer pasta "php-*" dentro do Laragon
' (cobre qualquer versão do PHP instalada, não só a 8.3.30 fixa de antes)
If strPhp = "" Then
    strPasta = "C:\laragon\bin\php\"
    If objFSO.FolderExists(strPasta) Then
        Set objPastaLaragon = objFSO.GetFolder(strPasta)
        For Each objSubPasta In objPastaLaragon.SubFolders
            If InStr(objSubPasta.Name, "php-") = 1 Then
                strCandidato = objSubPasta.Path & "\php.exe"
                If objFSO.FileExists(strCandidato) Then
                    strPhp = strCandidato
                End If
            End If
        Next
    End If
End If

If strPhp = "" Then
    MsgBox "PHP não encontrado (nem no PATH, nem em C:\laragon\bin\php\)." & vbCrLf & _
           "Ajuste manualmente a variável strPasta neste .vbs.", vbCritical, "run_scheduler"
    WScript.Quit 1
End If

' ===================== 4. INICIA O SCHEDULE:WORK EM LOOP CONTÍNUO =====================
' Janela oculta (0), não espera terminar (False) - ele fica rodando em segundo
' plano, sem travar o login nem precisar do Agendador de Tarefas do Windows.
objShell.Run Chr(34) & strPhp & Chr(34) & " artisan schedule:work", 0, False
