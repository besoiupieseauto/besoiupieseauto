# Cron Jobs — Windows Task Scheduler (Besoiu Piese Auto)

Procesele de fundal rulează **independent de browser** prin Windows Task Scheduler + scripturi `.bat` din `admin/scripts/`.

## Pregătire

1. Copiază `admin/.env.example` → `admin/.env` (dacă lipsește).
2. Setează în `.env`:
   - `DASHBOARD_CRON_KEY` — pentru refresh HTTP snapshot (opțional).
   - `SUPPLIER_SYNC_TOKEN` — pentru agent furnizori.
3. Pentru sync furnizori: `admin/config/supplier_sync_agent.local.php` (vezi `supplier_sync_agent.example.php`).

## Scripturi .bat (Laragon local)

| Job | Fișier .bat | Frecvență recomandată |
|-----|-------------|----------------------|
| Dashboard snapshot | `run_refresh_dashboard_snapshot.bat` | La 3 minute |
| Queue BaseLinker | `run_queue_worker.bat` | La 1–2 minute |
| Sync furnizori (rclone) | `run_supplier_rclone_sync.bat` | La 6 ore |
| Backup zilnic | `run_daily_backup.bat` | Zilnic 03:00 |

Test manual (cmd.exe):

```bat
cd /d E:\laragon\www\besoiupieseauto.ro\admin\scripts
run_refresh_dashboard_snapshot.bat
run_queue_worker.bat
```

Loguri: `admin/storage/logs/cron_*.log`

## Task Scheduler — exemplu „Dashboard snapshot”

1. `taskschd.msc` → Create Task.
2. **General:** Run whether user is logged on or not; Run with highest privileges (dacă e nevoie de rclone).
3. **Triggers:** Daily → Repeat task every **3 minutes** for a duration of **Indefinitely**.
4. **Actions:** Start a program  
   - Program: `E:\laragon\www\besoiupieseauto.ro\admin\scripts\run_refresh_dashboard_snapshot.bat`  
   - Start in: `E:\laragon\www\besoiupieseauto.ro\admin\scripts`
5. **Conditions:** debifați „Start only on AC power” pe laptop.
6. **Settings:** Allow task to run on demand.

Repetă pentru `run_queue_worker.bat` (1–2 min) și `run_supplier_rclone_sync.bat` (6 h).

## Verificare

```bat
php admin\tools\verify_cron_setup.php
php admin\tools\run_all_tests.php
```

Panou admin: **Cron Sync** (`/admin/cron`) — listă joburi + program recomandat.

## Program per furnizor (admin)

Pe fiecare profil furnizor → tab **Program & import** (`/admin/profilefurnizori?randomn_id=…`):

| Mod | Ce face |
|-----|---------|
| La fiecare X minute | Repetă sync la interval (ex. 60, 360) |
| O dată pe zi | După ora setată (ex. 06:00) |
| Interval orar | Doar între orele start–sfârșit, la X minute |
| Doar manual | Cron-ul sare furnizorul; rulezi din Cron Sync |

Salvare: **Salvează Program & import**. Migrare BD: `php admin/migrations/run_048_furnizori_scan_schedule.php`

## Module admin (scanări / sincronizări)

| Modul | URL | Selector HTML | Script .bat |
|-------|-----|---------------|-------------|
| Furnizori | `/admin/furnizori` | `#furnizori-grid` | `run_supplier_rclone_sync.bat` |
| Cron Sync | `/admin/cron` | `#cron-sync-page` | `run_queue_worker.bat` |
| Backup DB | `/admin/backup` | `#backup-run` | `run_daily_backup.bat` |
| Dashboard snapshot | CLI / HTTP cron | — | `run_refresh_dashboard_snapshot.bat` |

Registru PHP: `admin/config/cron_tasks.php` → `admin_cron_modules_registry()`.

## Producție (HTTP cron)

Alternativ la CLI, snapshot dashboard:

```bat
curl -s -X POST -H "X-Dashboard-Cron-Key: CHEIA_TA" https://besoiupieseauto.ro/admin/api/dashboard_snapshot_cron.php
```

Nu înlocuiți joburile CLI de queue/backup cu apeluri din browser.
