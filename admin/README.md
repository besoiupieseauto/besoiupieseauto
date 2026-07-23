# Admin Besoiu — shell CORE + module

| Folder | Rol |
|--------|-----|
| `public/index.php` | Front controller |
| `src/` → `app/Backend/src` | Logică MVC CORE |
| `vendor/` → `app/Backend/vendor` | Composer |
| `Templates/` | UI pagini core (fallback) |
| `../modules/` | Module admin OOP (ex. `users/`) |

Storefront: `index.php` → `app/Core/` + `app/Views/` + `app/Modules/`.

## Constante de cale — module

`BESOIU_MODULES` a fost eliminată (era ambiguă: însemna `app/Modules` în storefront și `modules/` root în admin). Folosește:

- `BESOIU_STOREFRONT_MODULES` (definită în `app/bootstrap.php`) → `app/Modules/`.
- `BESOIU_ADMIN_MODULES` (definită în `admin/bootstrap.php`) → `modules/` (root, lângă `admin/`).

## Sursă unică pentru fișiere partajate

Câteva fișiere existau duplicate (copiate identic) în două locuri — au fost unificate cu un `require` către sursa canonică, ca să nu mai diverjeze:

| Fișier | Sursă canonică | Mirror (doar `require`) |
|--------|-----------------|---------------------------|
| `Config\Database` | `app/Config/Database.php` | `app/Backend/config/Database.php` |
| `optional_modules.php` | `app/Backend/config/optional_modules.php` (folosit de `ModuleGate::optionalMap()`) | `admin/config/optional_modules.php` |

Regulă: când editezi una din aceste zone, editează **doar sursa canonică** — mirror-ul se actualizează automat.
