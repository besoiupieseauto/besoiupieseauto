' Porneste robot_pieseauto.py fara fereastra (port 5003)
Option Explicit

Dim fso, robotDir, pythonBin, logFile, WshShell
Set fso = CreateObject("Scripting.FileSystemObject")
robotDir = fso.GetParentFolderName(WScript.ScriptFullName)
pythonBin = "C:\laragon\bin\python\python-3.13\python.exe"
If Not fso.FileExists(pythonBin) Then pythonBin = "python"
logFile = robotDir & "\robot_pieseauto_service.log"

Set WshShell = CreateObject("WScript.Shell")
WshShell.CurrentDirectory = robotDir

Dim http
On Error Resume Next
Set http = CreateObject("MSXML2.ServerXMLHTTP.6.0")
http.open "GET", "http://127.0.0.1:5007/verificare_sesiune", False
http.setTimeouts 1500, 1500, 1500, 1500
http.send
If Err.Number = 0 And http.Status = 200 And InStr(LCase(http.responseText), "online") > 0 Then
    WScript.Quit 0
End If
On Error GoTo 0

WshShell.Run "cmd /c """ & robotDir & "\run_pieseauto_service.bat""", 0, False
