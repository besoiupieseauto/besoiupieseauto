# Besoiu Piese Auto — Storefront compact (Laragon)

## Root

```
besoiupieseauto.ro/
├── index.php          ← storefront (singur entry public)
├── admin/             ← ERP admin CORE (fără module opționale integrate)
├── api/               ← JSON public
├── assets/            ← CSS/JS/imagini statice
└── app/
    ├── Core/          ← CORE infrastructură (Router, DB, Template)
    ├── Modules/       ← module pagini magazin (Catalog, Cart…)
    ├── Views/         ← pagini de bază (catalog, cart, product…)
    ├── Legacy/        ← kernel procedural (header, TecDoc, coș)
    ├── Backend/       ← servicii Besoiu (vendor, src, storage) — partajat cu admin
    └── Config/        ← .env + rute storefront
```

## Flux storefront

`index.php` → `app/bootstrap.php` → `StorefrontApplication` → `app/Modules/*/Controller` → `app/Views/*.php`

## Flux admin

`/admin/` → `admin/public/index.php` → `HttpApplication` (CORE: produse, comenzi, furnizori…)

**Fără** folder `admin/modules/` — Import, AI, Scraper etc. nu sunt integrate aici.

## Instalare / update admin Laragon

Din repo sursă:

```bash
php tools/deploy_admin_laragon.php "F:\laragon\www\besoiupieseauto.ro"
```

## URL

| Zona | URL local |
|------|-----------|
| Magazin | http://besoiupieseauto.ro.test/ |
| Catalog | http://besoiupieseauto.ro.test/catalog |
| Admin | http://besoiupieseauto.ro.test/admin/ |

## Secrete (.env)

Fișierele `.env`/`.env.*`/`*.local.php` (`app/Config/.env`, `app/Backend/.env`, `admin/.env`, `app/Backend/config/*.local.php`) nu se versionează (`.gitignore` la root) și sunt blocate la acces HTTP direct (`<FilesMatch>` în `.htaccess` root + `admin/.htaccess`). Nu adăuga alte fișiere cu secrete fără să le adaugi și în `.gitignore`.
