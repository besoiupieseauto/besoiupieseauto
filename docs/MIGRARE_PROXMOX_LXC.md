# Migrare Besoiu pe Proxmox (LXC) — checklist

Scop: vezi magazinul + admin pe un container LXC din Proxmox (`https://192.168.1.2:8006`).

| Canal | Ce urcă |
|-------|---------|
| **Git** | Cod pe branch `upload/snapshot-20260723` |
| **USB** | Dump MySQL, `.env`, `uploads.zip`, scripturi deploy |
| **Nu acum** | imports / tecdoc / feeds / vendor (~8 GB) |

Scripturi: [`scripts/proxmox/`](../scripts/proxmox/)

---

## 1. Pe PC (Laragon) — pachet USB

1. Pornește MySQL în Laragon.
2. Alege litera USB (ex. `E:`).
3. PowerShell:

```powershell
cd C:\laragon\www\besoiupieseauto.ro\scripts\proxmox
.\Prepare-UsbMigrate.ps1 -Destination F:\besoiu-migrate
```

4. Verifică pe USB/HDD: `F:\besoiu-migrate\` → `MANIFEST.txt`, `*.sql` (~8+ GB), `*.env`, `deploy-lxc.sh`.
5. Asigură-te că branch-ul e pe GitHub:

```powershell
& "C:\laragon\bin\git\bin\git.exe" -C C:\laragon\www\besoiupieseauto.ro push -u origin HEAD
```

---

## 2. Pe Proxmox — Create CT

UI: `https://192.168.1.2:8006` → **Create CT**

| Setare | Valoare recomandată |
|--------|---------------------|
| Template | Debian 12 |
| Disk | ≥ **40 GB** (dump `besoiupieseauto.ro.sql` ~8 GB + spațiu import/Apache) |
| RAM | 2–4 GB |
| CPU | 2 |
| Network | `vmbr0`, IP static ex. `192.168.1.50/24`, gateway `192.168.1.1` |
| Nesting / keyctl | opțional (nu obligatoriu) |

Pornește CT → **Console**.

### USB în container

Pe host Proxmox (Shell):

```bash
# exemplu: USB apare ca /dev/sdb1 — verifică cu lsblk
mkdir -p /mnt/usb
mount /dev/sdb1 /mnt/usb
# copiază în CT (înlocuiește 150 cu VMID-ul CT)
pct push 150 /mnt/usb/besoiu-migrate /root/migrate
# sau: pct mount + cp; alternativ scp din PC pe IP-ul CT
```

Din PC (dacă CT are IP și SSH):

```powershell
scp -r E:\besoiu-migrate\* root@192.168.1.50:/root/migrate/
```

---

## 3. În LXC — deploy

```bash
chmod +x /root/migrate/deploy-lxc.sh
APP_URL=http://192.168.1.50 MIGRATE_DIR=/root/migrate bash /root/migrate/deploy-lxc.sh
```

Variabile utile:

| Variabilă | Implicit | Rol |
|-----------|----------|-----|
| `APP_URL` | `http://192.168.1.50` | URL din browser |
| `DB_PASS` | generat | parolă user MySQL `besoiu` |
| `GIT_BRANCH` | `upload/snapshot-20260723` | branch clone |
| `SKIP_GIT=1` | — | dacă ai clonat deja în `/var/www/besoiupieseauto.ro` |
| `SKIP_DB=1` | — | fără import SQL |

Credențiale DB după deploy: `/root/besoiu-db-credentials.txt`

---

## 4. Verificare în browser (LAN)

- Magazin: `http://192.168.1.50/`
- Admin: `http://192.168.1.50/admin/`

Dacă 500 / pagină albă:

```bash
tail -n 80 /var/log/apache2/besoiu-error.log
tail -n 80 /var/www/besoiupieseauto.ro/admin/storage/logs/*.log 2>/dev/null
```

---

## 5. Ce NU e pe server (intenționat)

- `app/Backend/storage/imports`, `tecdoc`, `supplier_feeds`
- unelte scraper / Ollama / Playwright (`tools/`, `storage/scraper`)
- AI automat — dezactivat în `.env` de script (`OLLAMA_ENABLED=0`, etc.)

Importurile grele rămân pe PC; pe server rulează doar magazinul + admin + DB.
