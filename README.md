# besoiupieseauto.ro

Platformă e-commerce piese auto (România) — site public PHP, admin EvaSystem, robot AI, ERP Laravel legacy.

**Repository:** [besoiupieseauto/besoiupieseauto](https://github.com/besoiupieseauto/besoiupieseauto)

## Ultimele corecții (admin)

Vezi [`docs/CHANGELOG_CORECTARI_2026-06-25.md`](docs/CHANGELOG_CORECTARI_2026-06-25.md) — produs nou, caiet comenzi, log formare preț, shell UI (overlay-uri).

## Cerințe

- PHP 8.1+ (extensii: pdo_mysql, curl, json, mbstring, zip, gd/imagick)
- MySQL 8 / MariaDB
- Composer
- Node.js + npm (doar pentru ERP Laravel `bes/well-known`)

## Instalare rapidă

### 1. Site + Admin (EvaSystem)

```bash
cd admin
cp .env.example .env
# Editează .env: DB_*, RAPIDAPI_*, chei API
composer install --no-dev
```

### 2. Robot (chat / WhatsApp)

```bash
cd robot
cp .env.example .env
# Editează .env: OPENAI_KEY, ULTRAMSG_*, WEBHOOK_KEY
```

### 3. ERP Laravel (caiet comenzi)

```bash
cd bes/well-known
cp .env.example .env   # creează din .env.example Laravel dacă lipsește
composer install --no-dev
npm ci && npm run build
php artisan key:generate
php artisan storage:link
```

### 4. Directoare runtime (goale la deploy)

Asigură-te că există și sunt writeable:

- `cache_tecdoc/`
- `uploads/products/tecdoc/`
- `admin/storage/imports/`, `admin/storage/logs/`, `admin/storage/cache/`
- `robot/data/`, `robot/cache_tecdoc/`

## Ce NU se versionează

- Fișiere `.env` (credențiale)
- `admin/vendor/`, `bes/well-known/vendor/`, `node_modules/`
- Cache TecDoc, importuri CSV, loguri, upload-uri produse
- Dump-uri SQL (`*.sql`)

## Structură

| Folder | Rol |
|--------|-----|
| `/` (root) | Site public: catalog, coș, TecDoc |
| `admin/` | EvaSystem ERP — produse, comenzi, import |
| `robot/` | Chat AI, WhatsApp webhook |
| `bes/well-known/` | ERP Laravel legacy (comenzi TM/Utvin) |
| `system/` | Bibliotecă PHP partajată site |

## Licență

Proprietate BESOIU PIESE AUTO SRL / Galac-Web. Cod confidențial — nu redistribui fără acord.
