# PROMPT — Integrare din proiectul vechi Besoiu

> **Cum se folosește în chat:** atașează acest fișier cu `@PROMPT_INTEGRARE_PROIECT_VECHI.md` (sau copiază secțiunea **PROMPT RAPID**) și completează `[CERINTA_TA]`.
>
> **Regula Cursor** `.cursor/rules/integrare-proiect-vechi.mdc` este activă permanent — agentul știe de proiectul vechi fără să repeți calea de fiecare dată.

---

## 1. Cele două proiecte

| | Proiect NOU (activ) | Proiect VECHI (sursă / backup) |
|---|---|---|
| **Cale absolută** | `F:\laragon\www\besoiupieseauto.ro` | `F:\bakapbesoiu\besoiupieseauto.ro\besoiupieseauto.ro` |
| **Rol** | Codul pe care îl modifici acum | Referință read-only — extragi logică, UI, SQL, config |
| **Arhitectură** | Storefront modular (`app/Core`, `app/Modules`) + Admin CORE (`admin/`, `app/Backend/`) | Monolit: site PHP procedural + admin MVC + ERP Laravel + robot |
| **Reguli vechi** | — | `.cursor/rules/besoiupieseauto.mdc` |
| **Prompturi vechi** | — | `.cursor/PROMPT_TEHNIC_PROIECT.md`, `.cursor/PROMPT_EXTRAGERE_MODULE.md` |

**Regulă de aur:** nu copia orb din vechi. **Citește → înțelege → adaptează** la arhitectura nouă.

---

## 2. Hartă locații: VECHI → NOU

### Site public (vitrină)

| Vechi | Nou |
|-------|-----|
| `*.php` rădăcină (`catalog.php`, `cart.php`, …) | `app/Modules/*/Controller.php` + `app/Views/*.php` |
| `system/` (TecDoc, header, coș procedural) | `app/Legacy/` + `app/Core/` |
| `assets/css`, `assets/js` | `assets/` |
| `index.php` + routing manual | `index.php` → `StorefrontApplication` → `StorefrontRouter` |

### Admin Besoiu

| Vechi | Nou |
|-------|-----|
| `admin/src/Controllers/` | `app/Backend/src/Controllers/` |
| `admin/src/Services/` | `app/Backend/src/Services/` |
| `admin/src/Models/` | `app/Backend/src/Models/` |
| `admin/Templates/admin/` | `admin/Templates/admin/` |
| `admin/public/` | `admin/public/` |
| `admin/config/` | `admin/config/` + `app/Backend/config/` |
| `admin/.env` | `admin/.env` (nu se copiază în git) |

### ERP Laravel (comenzi interne, facturi, furnizori B2B, curierat)

| Vechi | Nou (destinație la portare) |
|-------|----------------------------|
| `bes/well-known/app/Http/Controllers/` | `app/Backend/src/Controllers/` (ex: `Comenzi/`, `CaietComenzi/`) |
| `bes/well-known/app/Services/` | `app/Backend/src/Services/` |
| `bes/well-known/app/Models/` | `app/Backend/src/Models/` sau Repository PDO |
| `bes/well-known/resources/views/` | `admin/Templates/admin/pages/` |
| `bes/well-known/routes/web.php` | `admin/config/routes_bootstrap.php`, `admin_nav_routes.php` |

### Alte zone vechi

| Vechi | Notă |
|-------|------|
| `robot/` | `app/robot/` în proiectul nou |
| `Modules/` (vechi) | Module opționale — verifică dacă există echivalent în `app/Backend/src/Core/Module/` |
| `SQL/` | Schema de referință — validează cu codul actual |
| `cache_tecdoc/` | Cache TecDoc — nu copia date, doar logica |

---

## 3. Ce extragi din vechi (și cum)

| Tip | Unde cauți în vechi | Ce aduci în nou | Ce NU copiezi direct |
|-----|---------------------|-----------------|---------------------|
| **Logică business** | Controllers, Services | Service PHP pur în `app/Backend/src/Services/` | Eloquent, Facades, `Auth::`, `DB::` |
| **Interfață admin** | `admin/Templates/`, Blade ERP | PHP templates în `admin/Templates/admin/pages/` | Blade `@extends`, `@csrf` Laravel |
| **Rute / meniu** | `routes/web.php`, nav admin | `admin/config/*routes*` | Route Laravel |
| **SQL / tabele** | Modele, `DB::table()`, `SQL/` | Repository PDO, migrări | Presupuneri fără verificare |
| **JS / CSS** | `assets/`, `admin/public/assets/` | Același path relativ dacă există | Path-uri hardcodate vechi |
| **Integrări API** | `app/Services/*/` (Laravel) | Service standalone + `.env` | Credențiale hardcodate |
| **Config** | `.env`, `config/` | `admin/.env`, `app/Config/` | Parole / chei reale |

### Pași obligatorii pentru agent

1. **Identifică** în proiectul vechi: fișiere, funcții, tabele, rute implicate de cerință.
2. **Rezumat scurt** (max 15 puncte): ce face feature-ul, input/output, side-effects.
3. **Verifică** dacă există deja ceva similar în proiectul nou (`Grep` / căutare după nume clasă, rută, tabel).
4. **Propune maparea** vechi → nou (tabel + fișiere concrete).
5. **Implementează** doar în proiectul nou, respectând convențiile existente.
6. **Listează** ce ai ignorat din vechi (dead code, duplicări, Laravel-only).

---

## 4. Convenții proiect NOU (la portare)

- `declare(strict_types=1);` în fișiere PHP noi
- PDO prepared statements — fără concatenare SQL cu input user
- `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` la output în view-uri
- Namespace-uri: `Storefront\` (vitrină), servicii admin în `app/Backend/src/`
- UI română, identificatori cod engleză
- Prețuri RON, 2 zecimale
- ID produs site: `randomn_id`; ERP: `idprodus` — **nu amesteca**
- Secrete doar în `.env` — dacă găsești hardcodate în vechi, mută în `.env.example` cu placeholder

### Înlocuiri Laravel → PHP pur (când sursa e `bes/well-known/`)

| Laravel | În proiect nou |
|---------|----------------|
| Eloquent / `DB::table()` | PDO + Repository / Service |
| `Auth::user()` | sesiune admin existentă |
| `Request $request` | `$_GET`/`$_POST` sau wrapper existent |
| `view('x', $data)` | `include` template admin sau `PageView` storefront |
| Blade | PHP pur în `admin/Templates/` |
| `Route::get/post` | `routes_bootstrap.php` / router storefront |
| `config()` / `env()` | `admin/.env` + helpers config existente |

---

## 5. PROMPT RAPID — copiază și completează

```
@PROMPT_INTEGRARE_PROIECT_VECHI.md

Task: [CERINTA_TA — ex: „portează modalul de status comenzi din admin vechi”]

Instrucțiuni:
1. Citește implementarea din proiectul VECHI: F:\bakapbesoiu\besoiupieseauto.ro\besoiupieseauto.ro
2. Verifică ce există deja în proiectul NOU: F:\laragon\www\besoiupieseauto.ro
3. Livrează: (a) fișiere vechi relevante, (b) mapare vechi→nou, (c) plan modificări, (d) cod doar în proiectul nou
4. Nu modifica proiectul vechi. Nu copia Laravel/Eloquent — traduce în PHP pur.
5. Dacă lipsesc fișiere, spune exact ce path-uri să deschid.
```

---

## 6. PROMPT DETALIAT — feature complet / modul

```
@PROMPT_INTEGRARE_PROIECT_VECHI.md

Ești dezvoltator PHP senior. Integrezi din proiectul VECHI în cel NOU.

PROIECT VECHI (read-only): F:\bakapbesoiu\besoiupieseauto.ro\besoiupieseauto.ro
PROIECT NOU (write): F:\laragon\www\besoiupieseauto.ro

CERINȚĂ: [descriere detaliată]

Livrează în ordine:

### Pas 1 — Inventar sursă (vechi)
- Fișiere citite (cale completă)
- Tabele DB atinse
- Rute / endpoint-uri
- Dependențe externe (API, SDK)

### Pas 2 — Stare curentă (nou)
- Ce există deja (fișiere, servicii, rute)
- Ce lipsește față de vechi

### Pas 3 — Plan integrare
- Tabel: fișier vechi → fișier nou → acțiune (create / modify / skip)
- Riscuri (duplicări, permisiuni, breaking changes)

### Pas 4 — Implementare
- Cod complet în proiectul NOU
- Fără placeholder-e ascunse; marchează `// NEEDS_CONFIG:` dacă lipsește config

### Pas 5 — Verificare
- Pași manuali de test (URL admin, acțiune, rezultat așteptat)
- Fișiere .env noi necesare
```

---

## 7. Prompturi scurte pe tip de task

**Doar analiză (fără cod):**
> Analizează în proiectul VECHI `[FEATURE]`. Nu scrie cod. Livrează: fișiere, flux, tabele, ce e de portat vs ce e de aruncat.

**Doar extragere logică:**
> Extrage din VECHI logica din `[CALEA_VECHE]` și propune echivalent în `app/Backend/src/Services/` — fără Laravel.

**UI / template admin:**
> Compară template-ul vechi `admin/Templates/...` sau Blade ERP cu echivalentul din NOU. Portează markup/JS adaptat la structura admin actuală.

**Integrare API furnizor / curier / facturare:**
> Citește service-ul Laravel din `bes/well-known/app/Services/[Nume]/`. Rescrie ca service PHP pur în NOU; credențiale în `.env`.

**SQL / migrare date:**
> Deduce schema din modelele vechi + `SQL/`. Propune query/migrare compatibilă cu tabelele din NOU. Marchează presupunerile.

---

## 8. Module ERP mari (referință rapidă)

Pentru migrări mari din Laravel (`bes/well-known/`), vezi și promptul vechi:
`F:\bakapbesoiu\besoiupieseauto.ro\besoiupieseauto.ro\.cursor\PROMPT_EXTRAGERE_MODULE.md`

| Modul | Sursă Laravel (vechi) | Destinație tipică (nou) |
|-------|----------------------|-------------------------|
| M1 Comenzi | `OrderController`, `ComenziController` | `app/Backend/src/Controllers/Comenzi/`, `CaietComenzi/` |
| M2 Căutare furnizori | `SearchingController`, `SupplierSearchNew/*` | `app/Backend/src/Services/Furnizori/` |
| M3 Facturare | `FacturiController`, `SmartBillService` | de creat în `Services/` |
| M4 Curierat | `SamedayController`, `FanCourierController` | de creat în `Services/` |

---

## 9. Checklist înainte de „gata”

- [ ] Modificări doar în `F:\laragon\www\besoiupieseauto.ro`
- [ ] Proiectul vechi netouched
- [ ] Fără credențiale în cod
- [ ] Fără dependențe Laravel în cod nou
- [ ] Stil și structură aliniate la fișierele vecine din același folder
- [ ] Test manual descris (URL + pași)

---

## 10. Exemplu completat

```
@PROMPT_INTEGRARE_PROIECT_VECHI.md

Task: Adaugă în admin butonul „Export comenzi CSV” ca în proiectul vechi.

Instrucțiuni: [prompt rapid de mai sus]
```

**Ce ar trebui să facă agentul:** caută în vechi `Comenzi` / export CSV → găsește controller + template → verifică `app/Backend/src/Controllers/Comenzi/Comenzi.php` în nou → portează acțiunea ca endpoint admin + buton în template existent.
