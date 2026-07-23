@echo off
cd /d C:\laragon\www\besoiupieseauto.ro\app\Import\MatchingPro
set PYTHONUNBUFFERED=1
python scripts\import_qwp_tecdoc.py --file "C:\laragon\www\besoiupieseauto.ro\app\Backend\storage\supplier_feeds\autonet\Autonet QWP-update filtrare.xlsx" --batch 100 --offset 0
