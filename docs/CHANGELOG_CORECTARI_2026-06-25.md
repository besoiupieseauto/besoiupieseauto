# Corecții integrare admin — 25 iunie 2026

Patch-uri aplicate local (Laragon) pentru fluxuri admin care existau dar nu funcționau end-to-end: redirect, permisiuni workspace, API CRUD JSON, dropdown furnizori, shell UI.

## 1. Produs nou — `/admin/addproduse` / popup

**Simptom:** „Produs nou” și „Adaugă produs” arătau lista de produse, nu formularul.

**Cauză:** `redirectStubPage()` redirecționa `addproduse` → `product` în `Admin.php`.

**Fișier:** `admin/src/Controllers/Admin.php` — eliminat stub `['addproduse', 'product']`.

**Verificare:** GET `/admin/addproduse` → formular; popup iframe încarcă formularul; POST `/admin/crudproduse` → JSON success.

---

## 2. Caiet comenzi — `/admin/caiet-produse`

**Simptom:** Buton/tab „Caiet comenzi” redirecționa la lista produse.

**Cauză:** Rută blocată de workspace „Produse Furnizori”; lipsea din permisiuni și navigație.

**Fișiere:**
- `admin/config/admin_nav_routes.php` — rută `caiet-produse`
- `admin/src/Core/AdminPageResolver.php` — template + ROUTE_DIRS
- `admin/src/Core/Auth/AdminPermissionCatalog.php` — feature `produse.caiet`
- `admin/src/Core/Auth/AdminWorkspaceCatalog.php` — URL în workspace suppliers
- `admin/Templates/admin/pages/produse/_produse-section-nav.php` — tab
- `admin/Templates/admin/pages/caietcomenzi/caiet-produse.php` — layout ERP
- `admin/Templates/admin/pages/produse/produse.php` — buton duplicat scos
- `admin/src/Controllers/Templates.php` — titlu pagină

**Verificare:** `/admin/caiet-produse` — pagină dedicată, tab activ, fără `?workspace_denied=1`.

---

## 3. Log formare preț — `/admin/adaoscomercial?tab=price-log`

**Simptom A:** `?import_id=N` — zonă goală.  
**Simptom B:** „Încarcă log import” → „Răspuns invalid de la server.”  
**Simptom C:** Dropdown „Furnizor (coadă import)” gol.

**Cauze:**
- Preload/JS deep-link incomplet
- POST `/admin/crudadaoscomercial` blocat de workspace → HTML în loc de JSON
- Lista furnizori doar din query simplu, fără catalog + cache

**Fișiere:**
- `admin/src/Core/Auth/AdminWorkspace.php` — exempt rute `/admin/crud*`
- `admin/src/Core/Auth/AdminPermissionCatalog.php` — permisiune CRUD adaos
- `admin/src/Controllers/AdaosComercial/PriceFormationTraceService.php` — `listImportQueueSuppliers()` + cache
- `admin/src/Controllers/AdaosComercial/AdaosComercial.php` — API `list_import_suppliers`
- `admin/Templates/admin/pages/adaoscomercial/adaoscomercial.php` — preload furnizori tab log
- `admin/Templates/admin/pages/adaoscomercial/_price-formation-log-tab.php` — UI + fetch JSON + dropdown

**Verificare:** Furnizor în dropdown (Autototal, Materom…); batch + click rând; ID singular pentru trace.

---

## 4. Shell admin — click-uri blocate / încărcare lentă

**Simptom:** Butoane greu de apăsat, linkuri lente sau inactive pe mai multe pagini.

**Cauză:** Overlay-uri rămase (page-loader, modale produse, lock scan import).

**Fișiere:**
- `admin/public/assets/js/admin-shell-guard.js` — **NOU** — curăță overlay-uri la load
- `admin/src/Controllers/Templates.php` — încarcă shell-guard global
- `admin/public/assets/css/admin-layout.css` — pointer-events sigure
- `admin/Templates/admin/dist/js/components/base/page-loader.js` — fără dependență jQuery
- `admin/public/assets/js/admin-sidebar-persist.js` — mai puține poll-uri

**Verificare:** Hard refresh `Ctrl+Shift+R`; navigare meniu + butoane pe product, import, adaos.

---

## Deploy producție

1. `git pull` pe server (sau rsync/ FTP fișierele din commit).
2. `cd admin && composer install --no-dev` (dacă s-au schimbat dependențe).
3. Verificare manuală autentificat — checklist secțiunile 1–4.
4. Opțional: golire cache browser + `admin/storage/cache/` pe server.

## Pattern pentru viitoare corecții

Verifică întotdeauna (în ordine):
1. `Admin.php` → `redirectStubPage()` / stub pairs
2. `AdminWorkspace.php` → `isExemptPath()` pe POST CRUD
3. `AdminPermissionCatalog.php` + `AdminWorkspaceCatalog.php`
4. `admin_nav_routes.php` + `AdminPageResolver.php`
5. JS fetch → headers JSON; răspuns HTML = sesiune/permisiune/workspace
