@echo off
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe --host=127.0.0.1 --user=root --ssl-mode=DISABLED besoiu_tecdoc_base < "C:\laragon\www\besoiupieseimport\backups\besoiu_tecdoc_base_compat_20260710_174325.sql" > "C:\laragon\www\besoiupieseauto.ro\app\Import\MatchingPro\logs\compat_restore2.log" 2> "C:\laragon\www\besoiupieseauto.ro\app\Import\MatchingPro\logs\compat_restore2.err"
