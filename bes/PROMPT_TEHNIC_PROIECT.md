# PROMPT TEHNIC — Analiză completă proiect Laravel "Besoiu Piese Auto"

> **Cum se folosește:** Copiază tot conținutul de mai jos și trimite-l ca prompt unui asistent AI (Claude, ChatGPT etc.) împreună cu codul sursă al proiectului (sau cu fișierele relevante atașate). Promptul este construit ca o **briefing tehnic detaliat** + **set de instrucțiuni clare** ce să producă AI-ul ca rezultat (documentație, analiză, refactor, etc.).

---

## 🎯 PROMPT — COPIAZĂ DE AICI ÎN JOS

Ești un arhitect software senior cu experiență profundă în Laravel 10, PHP 8.1+, MySQL, integrări B2B (curierat, facturare, furnizori piese auto) și sisteme ERP de retail. Vei analiza în profunzime un proiect Laravel existent numit **„Besoiu Piese Auto & Fun"** — un sistem ERP intern pentru un magazin/atelier de piese auto din România cu două locații (Timișoara & Utvin), care gestionează comenzi interne și externe, facturare, încasări, livrări prin curier, și **căutare/comparare în timp real a prețurilor de la 5 furnizori B2B de piese auto**.

Misiunea ta este să produci o **documentație tehnică extrem de detaliată** care să descrie:

1. Structura proiectului și organizarea codului
2. Modelul de date complet (tabele, chei, relații)
3. Modulele funcționale și legăturile dintre ele
4. Integrările externe și cum funcționează
5. Sistemul de permisiuni și autentificare
6. Fluxurile de business end-to-end
7. Punctele slabe / datoria tehnică / riscuri

---

### 📦 CONTEXT TEHNIC AL PROIECTULUI

**Framework & stack:**
- Laravel 10.10 pe PHP 8.1+
- MySQL (utf8mb4_unicode_ci)
- Frontend: Blade + jQuery 3.7 + Bootstrap-datepicker + Alpine.js + Tailwind CSS 3.1
- Build: Vite 6.2
- Autentificare: Laravel Breeze + Sanctum (pentru API)
- DataTables (Yajra) pentru tabele server-side
- DOMPDF + FPDF (cu RotatedPdf helper custom) pentru PDF-uri
- Guzzle HTTP pentru integrări REST/SOAP

**Pachete externe critice:**
- `sameday-courier/php-sdk` — SDK oficial Sameday
- `seniorprogramming/fancourier` + `shusaura85/fancourier-api` — două SDK-uri FanCourier folosite în paralel
- `barryvdh/laravel-dompdf` + `codedge/laravel-fpdf` — PDF generation

**Limba:** mixtă RO/EN. Modele, tabele și controlere sunt în română (Comenzi, Facturi, Clienți, Incasari, Produse, Utilizatori, Pieseauto), iar codul intern e în engleză. UI-ul este 100% în română.

---

### 🗂️ STRUCTURA DE DIRECTOARE

```
/
├── app/
│   ├── Console/
│   ├── Exceptions/
│   ├── Helpers/
│   │   ├── GlobalHelper.php
│   │   ├── PaginationHelper.php
│   │   └── RotatedPdf.php          ← auto-loaded via composer "files"
│   ├── Http/
│   │   ├── Controllers/            ← 22 controllere
│   │   │   ├── Auth/               ← Breeze controllers
│   │   │   ├── OrderController.php           (3448 LOC, comenzi interne)
│   │   │   ├── ComenziController.php         (3793 LOC, comenzi externe)
│   │   │   ├── FacturiController.php         (1825 LOC, facturi)
│   │   │   ├── SearchingController.php       (5734 LOC, monolit căutare furnizori)
│   │   │   ├── SupplierSearchNewController.php (175 LOC, varianta nouă cu pool)
│   │   │   ├── ClientController.php
│   │   │   ├── ProduseController.php
│   │   │   ├── IncasariController.php
│   │   │   ├── UtilizatoriController.php
│   │   │   ├── PieseautoController.php
│   │   │   ├── ApiCredentialController.php
│   │   │   ├── ProfileController.php
│   │   │   ├── SamedayController.php / SamedayApiController.php /
│   │   │   │   SamedayProxyController.php / SamedayTestController.php
│   │   │   ├── FanCourierController.php / FanCourierTestController.php
│   │   │   ├── AwbController.php / AwbControllerfan.php
│   │   │   ├── InvoiceController.php
│   │   │   └── Controller.php
│   │   ├── Middleware/
│   │   │   ├── CheckPermission.php           ← alias 'permission'
│   │   │   ├── Authenticate.php
│   │   │   ├── SamedayMockProxyMiddleware.php
│   │   │   └── (Laravel defaults)
│   │   └── Requests/
│   ├── Models/                     ← 26 modele Eloquent
│   ├── Providers/
│   ├── Services/                   ← 14 servicii + 5 directoare integrări furnizori
│   │   ├── AnafService.php                   (ANAF — verificare CUI firme)
│   │   ├── SmartBillService.php              (SmartBill — facturare e-factura)
│   │   ├── SmsService.php                    (WhosMS gateway)
│   │   ├── SamedayService.php / SamedayMockService.php
│   │   ├── FanCourierService.php
│   │   ├── LKQImportService.php              (import pricelist LKQ din ZIP)
│   │   ├── AutoPartner/AutoPartnerService.php
│   │   ├── Autonet/AutonetService.php
│   │   ├── Autototal/AutototalService.php
│   │   ├── Elit/ElitService.php
│   │   ├── Materom/MateromService.php
│   │   ├── FanCourier/FanCourierService.php  (separat de cel root!)
│   │   ├── Sameday/                          (SDK extras local: Sameday.php,
│   │   │                                      SamedayClient.php, Objects, Requests,
│   │   │                                      Responses, Http, Exceptions)
│   │   ├── SupplierSearch/                   (helpers vechi)
│   │   │   ├── DeliveryFormatter.php
│   │   │   ├── PricingApplier.php
│   │   │   ├── ProductNameResolver.php
│   │   │   └── SearchPayloadBuilder.php
│   │   └── SupplierSearchNew/                (refactor cu pool & contracts)
│   │       ├── RunSupplierSearchNewAction.php
│   │       ├── PoolRunner.php
│   │       ├── ProductAggregator.php
│   │       ├── ResultBuilder.php
│   │       ├── SearchEnricher.php
│   │       ├── AutonetProductEnricher.php
│   │       ├── ElitFromDbMerger.php
│   │       ├── PartsCatalogLookup.php
│   │       ├── GuzzleResponseAdapter.php
│   │       ├── ArrayResponseAdapter.php
│   │       ├── Contracts/
│   │       └── Parsers/
│   ├── View/
│   └── SearchingController.php     ⚠️ ANOMALIE: există un controller și la
│                                     root-ul app/ (nu doar în Http/Controllers/)
├── bootstrap/, config/, database/, public/, routes/, storage/, tests/, vendor/
├── resources/
│   ├── views/                      ← ~95 fișiere Blade
│   │   ├── layouts/                (mainapp, mainappv1, guest, header_common_*)
│   │   ├── partials/               (navbar, header, footer, top-menu, user-menu)
│   │   ├── components/             (Breeze UI components)
│   │   ├── auth/                   (login, register, password reset, verify)
│   │   ├── orders/                 (comenzi interne + modals)
│   │   ├── comenzi/                (comenzi externe + modals)
│   │   ├── facturi/
│   │   ├── clients/
│   │   ├── produse/
│   │   ├── incasari/
│   │   ├── utilizatori/
│   │   ├── pieseauto/
│   │   ├── searching/              (index, index_new, cart, saved_carts,
│   │   │                            excluded_autototal_cart, orders,
│   │   │                            cart_offer_pdf)
│   │   ├── api_credentials/
│   │   ├── sameday/
│   │   └── profile/
│   ├── css/, js/
└── routes/
    ├── web.php        (323 linii — toate rutele aplicației, sub auth middleware)
    ├── auth.php       (Breeze)
    ├── api.php        (FanCourier + Sameday — auth:sanctum)
    ├── channels.php
    └── console.php
```

---

### 🗄️ MODELUL DE DATE

**Atenție critică:** Migrațiile Laravel nu reflectă schema reală! În `database/migrations/` există doar 4 fișiere default Laravel (`users`, `password_reset_tokens`, `failed_jobs`, `personal_access_tokens`). **Schema reală a fost creată direct în MySQL** și e ingenierizată invers prin modele Eloquent și apeluri `DB::table()` directe. Tu trebuie să tratezi tabelele de mai jos ca fiind sursa de adevăr.

#### Tabele core (din modele Eloquent)

| Tabel | Model | PK | Timestamps | Câmpuri-cheie |
|---|---|---|---|---|
| `users` | `User` | `Id` (PascalCase!) | parțial | username, email, nume_complet, telefon, magazin_id, avatar, **rol** (manager/...), password, is_admin, active, email_verified, verification_code, requires_2fa, failed_attempts, blocked_until, last_login |
| `user_permissions` | `UserPermission` | id | – | user_id, **menu_key**, permission (0/1) |
| `clienti` | `Client` & `Clienti` | `idclienti` | doar created_at | nume, adresa, telefon, idmasina, marca, sasiu, nr_inmat, idlocalitate, companie, cif, regcom, email, cont_banca, nume_banca, localitate_livrare, adresa_livrare, localitate_facturare, adresa_facturare |
| `masina` | `Masina` | `idmasina` | – | marca, sasiu |
| `localitati` | `Localitate` | `idlocatie` | – | judet, localitate, codrutare |
| `produse` | `Produse` | `idprodus` | doar created_at | denumire, cod_produs, pret, TVA, um |
| `comenzi` | `Comenzi` & `Order` (DUBLU MAPPING!) | `idcmd` | – | idcomanda, idclient, userid, data, idmasina, total, **stare**, cont_awb, locatie_mgz, marca, observations |
| `detaliu` | `Detaliu` | `iddetaliu` | doar created_at | idprodus, idcomanda, cantitate, pret, culoare, furnizor |
| `comenzi_ext` | `ComenziExt` | `idcmd` | – | idcomanda, idclient, userid, idprodus, cantitate, total, idmasina, stare, retur, data, awb, cont_awb, id_factura |
| `detaliu_ext` | `DetaliuExt` | `iddetaliu` (NU autoincrement) | doar created_at | idprodus, idcomanda, cantitate, pret, culoare, furnizor (default culoare=FFFFFF, furnizor='__') |
| `facturi` | `Factura` | `OrderID` | doar created_at | CustomerID, EmployeeID, OrderDate, RequiredDate, seria, valid, tip_incas, id_comanda, **tip_comanda**, id_chitanta, id_oferta, id_proforma, id_aviz, id_fact, negative_issued |
| `facturidetails` | `FacturiDetail` | `OrderID` (ciudat — nu unic!) | – | ProductID, UnitPrice, Quantity, Discount, tva, total, culoare, furnizor |
| `tip_plata` | `TipPlata` | `id_plata` | – | denumire |
| `incasari` | `Incasari` | id | – | idcmd, userid, idclient, cstmtext, suma, data, data_time, idstare, locatie_mgz |
| `employees` | `Employee` | `EmployeeId` | – | LastName, FirstName, Title, BirthDate, HireDate, Address, City, Photo (BLOB), CI, CiNr, CNP |
| `tmp` | `Tmp` | `id_tmp` | – | id_produs, cantitate_tmp, pret_tmp, **session_id**, culoare, furnizor, tva, tva_tmp |
| `sms` | `Sms` | `idsms` | – | idcomanda, idprimit, status, data_exp, cost, idcomanda_ext, telefon, mesaj, data |
| `order_status_history` | `OrderStatusHistory` | id | da | order_id, old_status, new_status, user_id |
| `api_credentials` | `ApiCredential` | id | – | service_name, data_key, **data_value (ENCRYPTED via Laravel encrypt())** |
| `message_templates` | `MessageTemplate` | id | – | channel (whatsapp/sms), code, name, template |
| `promotions` | `Promotion` | id | da | supplier, brand |
| `autototal_data` | `AutototalData` | id | – | itemkey, art_article_nr, sup_brand, pret, code_echiv, sup_brand_echiv, devumire |
| `autototal_excluded_cart_items` | `AutototalExcludedCartItem` | id | – | user_id, order_from, cart_item_key, supplier, product_code, variant_code, itemkey, product_name, manufacturer, qty, price, currency, stock, livrare, depozit |
| `supplier_carts` | `SupplierCart` | id | – | user_id, cart (JSON cast) |
| `supplier_saved_carts` | `SupplierSavedCart` | id | – | user_id, name, phone, vin, cart (JSON), alreadygenerated |
| `supplier_orders` | `SupplierOrder` | id | – | supplier, order_number, raw_response (JSON) |

#### Tabele accesate doar prin `DB::table()` (fără model)

`agentiifan`, `autonet_qwp_data`, `autototal_branduri_proprii`, `coduri_postale`, `incasari_entries`, `lkq_prices`, `parts_catalog`, `settings`

---

### 🔐 SISTEMUL DE PERMISIUNI

Există **un rol special `manager`** care primește acces total. Pentru alți utilizatori, accesul este pe **chei de meniu** stocate în `user_permissions.menu_key`.

Permission keys folosite explicit în `routes/web.php`:
- `produse` — gestionare produse
- `clienti` — gestionare clienți
- `comenzi_externe` — modulul ComenziController (comenzi cu livrare prin curier)
- `facturi` — modulul facturi
- `incasari` — modulul încasări
- `ultilizatori` ⚠️ (typo în cod: trebuia `utilizatori`)
- `pieseauto` — modulul import LKQ / fetch comenzi piese
- `searching` — modulul de căutare furnizori
- `apicredentials` — administrare credentiale API

Middleware-ul `CheckPermission` (`app/Http/Middleware/CheckPermission.php`, alias `permission`):
1. Dacă nu e autentificat → redirect la login
2. Dacă `Auth::user()->rol === 'manager'` → trece direct
3. Altfel `User::hasPermission($key)` verifică `user_permissions` cu `permission = 1`

Comenzile interne (`/orders/*`) și AWB-printing NU sunt protejate de `permission`, ci doar de `auth`.

---

### 🧩 MODULELE FUNCȚIONALE

#### 1. Autentificare (Auth — Laravel Breeze)
- `routes/auth.php` — register / login / forgot password / reset / verify email / confirm password / logout
- API tokens prin Sanctum (`routes/api.php` — `auth:sanctum`)
- Câmpul de login este `username` (vezi `User::getAuthIdentifierName()`), NU email
- User-ul are mecanisme de: 2FA flag (`requires_2fa`), email verification cu cod, lockout (`failed_attempts`, `blocked_until`, `last_failed_login`), parolă în `password` (hashed)
- Există și un `LoginController` custom în `App\Http\Controllers\Auth\LoginController` referit în `web.php` cu route `/login` POST → `login`

#### 2. Comenzi INTERNE (`OrderController`)
- Tabel: `comenzi` + `detaliu` + `tmp` (coș temporar bazat pe session_id)
- Statusuri (atribut accesor `getStatusTextAttribute`):
  - 1 = Comandat (orange)
  - 2 = Sosit (blue)
  - 3 = Cash (green)
  - 4 = Avans (red)
  - 5 = Retur (purple)
  - 6 = Card (dark green)
  - 7 = FD (dark orange)
- Funcționalități:
  - CRUD complet pe comenzi
  - Adăugare produse temporare în coș (`tmp` table) per `session_id`
  - Update inline pentru: status, culoare, furnizor, locație, total
  - Generare factură (PDF) cu DOMPDF, cu integrare opțională SmartBill
  - Trimitere SMS (WhosMS) și WhatsApp (link generat din template)
  - Check status SMS

#### 3. Comenzi EXTERNE (`ComenziController`)
- Tabele: `comenzi_ext` + `detaliu_ext`
- Diferența esențială: au **AWB** (Air Waybill), expediate prin curier
- Curierii suportați:
  - **Sameday** — `createSamedayAwb` folosește SDK-ul `sameday-courier/php-sdk`
  - **FanCourier** — `createFanCourierAwb` folosește `FanCourierService` (wrapper local)
- Funcționalitate `fetchCourierStatus` pentru a interoga statusul real-time
- Cron: `/cron/fancourier-tracking` (rută publică, fără auth) — `FanCourierController@cronFancourierTracking`
- Generare factură proformă, factură finală, printare invoice
- SMS + WhatsApp cu template-uri specifice pentru fiecare curier (vezi `MessageTemplate::DEFAULTS`)

#### 4. Facturi (`FacturiController`)
- Tabele: `facturi` + `facturidetails`
- Tipuri factură: factură, chitanță, ofertă, proformă, aviz (`id_chitanta`, `id_oferta`, `id_proforma`, `id_aviz`, `id_fact`)
- Integrare **SmartBill** (`SmartBillService`) pentru e-facturare
- Câmp `negative_issued` pentru storno
- Add product temporar prin `tmp` table (același mecanism ca la orders)

#### 5. Clienți (`ClientController`)
- Tabel: `clienti` (model `Client` cu PK `idclienti`)
- **Integrare ANAF** (`AnafService` → `webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva`):
  - La introducerea unui CIF, se face request automat pentru a aduce: denumire firmă, adresă, CUI, RegCom, status TVA, status TVA la încasare
- Adrese separate: facturare vs livrare
- Asociere cu mașini (marcă, șasiu, nr înmatriculare)
- Liste de localități din `localitati` + `coduri_postale`

#### 6. Produse (`ProduseController`)
- CRUD pe `produse`
- Bulk update TVA (cu valoare „curentă" extrasă)
- Bulk update preț

#### 7. Încasări (`IncasariController`)
- Tabel: `incasari` + `incasari_entries`
- Preț zilnic (`getDailyPrice` / `updateDailyPrice`) — folosit pentru calcule de raport
- Asociat cu user, client și comandă

#### 8. Utilizatori (`UtilizatoriController`)
- Administrare conturi + atribuire permisiuni (`user_permissions`)
- Doar manager / utilizatori cu permission `ultilizatori`

#### 9. **Searching / Supplier Search** — modulul cel mai complex
Două generații de implementare convietuiesc:

**Vechi:** `SearchingController` (5734 LOC monolit) — folosește direct serviciile furnizorilor sincron, secvențial.

**Nou:** `SupplierSearchNewController` (175 LOC) + `App\Services\SupplierSearchNew\*` — refactor cu:
- `RunSupplierSearchNewAction` — orchestrator
- `PoolRunner` — pool de cereri HTTP **paralele** Guzzle pentru toți furnizorii simultan
- `ProductAggregator` — agregare rezultate
- `ResultBuilder` — formatare output
- `SearchEnricher`, `AutonetProductEnricher`, `ElitFromDbMerger`, `PartsCatalogLookup` — îmbogățire cu date locale (autonet_qwp_data, parts_catalog, autototal_data)
- `Parsers/` — parser-uri specifice per furnizor

**Furnizori suportați** (în ordinea SUPPORTED_SUPPLIERS):
1. `autopartner` — `AutoPartnerService` (API v2.13, token cu expirare)
2. `materom` — `MateromService` (api.materom.ro, **token diferit per locație**: UTVIN vs TIMISOARA)
3. `autonet` — `AutonetService` (wes.autonet-group.com, prod/staging)
4. `autototal` — `AutototalService` (atx.autototal.ro:15063 availability + :15085 order, **credentiale per locație**)
5. `elit` — `ElitService` (econnector.elit.cz SOAP-like — BusinessService & BuyerService)

**Workflow:**
1. User introduce un cod produs (sau VIN) și selectează furnizori
2. Search rulează în paralel → rezultate normalizate cu preț, stoc, livrare, depozit, marcă, variante
3. User adaugă în coș (`supplier_carts` — JSON per user)
4. Special pentru AutoTotal: există `autototal_excluded_cart_items` — produse care nu pot fi comandate direct prin API și sunt salvate pentru comandă manuală/zilnică
5. Plasare comandă → request către API furnizor → salvare în `supplier_orders` cu raw_response
6. **Wishlist**: `supplier_saved_carts` (per user, cu nume client, telefon, VIN, JSON cart) → din care se poate genera o ofertă PDF (`cart_offer_pdf.blade.php`) sau un link WhatsApp cu template `whatsapp.wishlist_offer`
7. **Promoții**: tabela `promotions` (supplier + brand) — markup-uri preferențiale per brand
8. Integrare cu produsele de pe site (`siteProduseListForCart`, `cartAddSiteProduse`) — pentru transferul comenzilor dintre site-ul public și ERP

#### 10. Piese Auto / LKQ Import (`PieseautoController` + `LKQImportService`)
- Endpoint cron protejat prin secret env (`LKQ_CRON_SECRET`): `GET /lkq-import?key=...`
- Citește un ZIP cu pricelist (`upload/LKQRO_pricelist_1888856.zip`) din `storage/app/lkq/`
- Îl dezarhivează și încarcă în tabela `lkq_prices`
- Folosit pentru a avea un fallback de prețuri când API-ul nu răspunde

#### 11. API Credentials (`ApiCredentialController`)
- Stocare key-value criptată: `api_credentials` cu `data_value` ENCRYPT/DECRYPT automat prin Eloquent Attribute
- Folosit cel puțin pentru SmartBill (api_url, api_key, user_email)

#### 12. Profile + Theme
- `ProfileController` — editare profil, schimbare temă (`/change-theme` POST)

---

### 🌐 INTEGRĂRI EXTERNE — SUMAR

| Serviciu | Tip | Locație în cod | Scopul |
|---|---|---|---|
| **ANAF** | REST | `AnafService` | Verificare firme după CUI (date fiscale) |
| **SmartBill** | REST | `SmartBillService` | E-facturare oficială |
| **WhosMS** | HTTP GET | `SmsService` | Trimitere SMS |
| **Sameday** | SDK PHP | `Services/Sameday/` (SDK extras local) + `SamedayService` | Curier — creare AWB, status, tarife, pickup points, AWB PDF |
| **FanCourier** | 2 SDK-uri | `Services/FanCourier/FanCourierService` + namespace global `Fancourier\Fancourier` | Curier — alternativă |
| **AutoTotal** | REST | `Autototal/AutototalService` | Furnizor piese — Availability & Order, credentiale per locație |
| **Materom** | REST | `Materom/MateromService` | Furnizor piese — token per locație |
| **Autonet** | REST | `Autonet/AutonetService` | Furnizor piese — prod/staging |
| **AutoPartner** | REST | `AutoPartner/AutoPartnerService` | Furnizor piese — Customer API v2.13 |
| **Elit** | SOAP-like | `Elit/ElitService` | Furnizor piese — BusinessService + BuyerService |
| **LKQ** | ZIP/CSV | `LKQImportService` | Import zilnic pricelist via cron |
| **WhatsApp** | Click2Chat | template-uri din `MessageTemplate` + helper de generare link | Notificări client |

---

### 🛣️ HARTĂ DE RUTE (highlights)

- **Public:**
  - `GET /` → redirect login
  - `POST /login`
  - `GET /awb-print/{id_awb}` (auth middleware doar)
  - `GET /cron/fancourier-tracking` (cron, fără auth)
  - `POST /change-theme`

- **Auth middleware (toate sub `/`):**
  - `/profile/*`
  - `/orders/*` (interne) — fără permission gate
  - `/comenzi/*` (externe) — `permission:comenzi_externe` (parțial — multe rute AJAX sunt în afara grupului)
  - `/facturi/*` — `permission:facturi`
  - `/clients/*` — `permission:clienti`
  - `/produse/*` — `permission:produse`
  - `/incasari/*` — `permission:incasari`
  - `/utilizatori/*` — `permission:ultilizatori`
  - `/pieseauto/*` — `permission:pieseauto`
  - `/searching/*` — `permission:searching`
  - `/api-credentials/*` — `permission:apicredentials`
  - `/lkq-import?key=...` — gated prin env secret

- **API (Sanctum):**
  - `/api/fancourier/{services,create-awb,calculate-price}`
  - `/api/sameday/{services,pickup-points,calculate-price,create-awb,awb-status/{id}}`

---

### ⚠️ ANOMALII, DUPLICĂRI ȘI PUNCTE SLABE OBSERVATE

1. **Două modele pentru același tabel `clienti`**: `Client` și `Clienti` — confuz, decizie inconsistentă unde se folosește care.
2. **Două modele pentru `comenzi`**: `Comenzi` (PK `idcmd`, timestamps off, fillable cu observations) și `Order` (același tabel, fillable diferit, are getStatusTextAttribute/getStatusColorAttribute, declarația `protected $primaryKey = 'idcmd'`). În același fișier `Order.php` sunt și clase „dummy" `Product`, `Address`, `Supplier` fără `$table` definit — referite în relații dar fără tabele reale.
3. **Două controllere FanCourier-AWB**: `AwbController` (Sameday + Fancourier print) și `AwbControllerfan` (99 LOC, doar Fancourier).
4. **Trei controllere Sameday**: `SamedayController` (408 LOC), `SamedayApiController`, `SamedayProxyController` (71 LOC), `SamedayTestController` — fără claritate care e canonic.
5. **Două generații Searching** care coexistă cu rute alias-ate (`searching.searchSuppliers` vs `searching.searchSuppliersNew` vs `searching.new.searchSuppliers`) — toate trei pointează spre același controller nou. Backward compatibility cu view-ul `index_new.blade.php`.
6. **Credentiale hardcodate** în surse (parole, token-uri AutoTotal, Materom, Sameday — vezi `MateromService`, `AwbController`, `AutototalService`). Risc major de securitate.
7. **PK-uri non-standard**: `OrderID` în Factura, `EmployeeId` (cu Id majuscul) în Employee, `Id` (capitalized) în users. Acest lucru îngreunează relațiile Eloquent.
8. **`FacturiDetail` are PK `OrderID` care NU este unic** (un OrderID poate avea multe rânduri). Aceasta înseamnă că `find()` și relațiile Eloquent vor returna doar primul rând. Probabil schema reală are alt PK auto-increment ignorat de model.
9. **`DetaliuExt`: `incrementing = false`** dar fără cheie compusă declarată — risc de inserții corupte.
10. **Lipsă migrații reale** — schema e doar în producție. Nu există seeder-e funcționale (doar `IncasariSeeder` + `DatabaseSeeder` default).
11. **Typo permission key**: `ultilizatori` în loc de `utilizatori` în routes.
12. **Controller orfan**: `app/SearchingController.php` (la root-ul app/, nu în Http/Controllers/) — probabil rest dintr-un refactor incomplet.
13. **Rute neacoperite de middleware permission**: multe rute AJAX `comenzi/get-data`, `comenzi/update-status` etc. sunt în afara `Route::middleware(['permission:comenzi_externe'])->group()`.
14. **WhosMS user/pass în env are valori dummy** (`userultau`, `parolata`) — verifică ce e în prod.
15. **`view::status.blade.php`** este într-o locație neașteptată: `app/Models/status.blade.php` — fișier orfan, foarte probabil mutat greșit.

---

### 🧭 INSTRUCȚIUNI PENTRU AI — CE TREBUIE SĂ LIVREZI

Pe baza tuturor informațiilor de mai sus și a codului sursă pe care îl voi atașa / referi în conversație, **livrează o documentație tehnică structurată în următoarele secțiuni**, în limba română, cu marcaj Markdown și diagrame Mermaid unde adaugă valoare:

#### Secțiunea 1 — REZUMAT EXECUTIV (1 pagină)
- Ce este sistemul, pentru cine, ce probleme rezolvă
- Stack-ul în linii mari
- Lista modulelor și cum se leagă (diagramă bloc)

#### Secțiunea 2 — ARHITECTURĂ
- Diagramă Mermaid `flowchart` cu: Browser → Routes → Middleware → Controllers → Services → Models → DB
- Diagramă a integrărilor externe (Mermaid `graph`)
- Cum sunt împărțite responsabilitățile între Controllers / Services / Models

#### Secțiunea 3 — MODELUL DE DATE (cel mai important)
Pentru fiecare entitate, livrează:
- Numele tabelului și al modelului
- Cheia primară și particularitățile
- Lista completă de coloane cu tipuri inferate
- Toate relațiile (declarate sau implicite prin foreign keys logice)
- Diagrama ER în Mermaid (`erDiagram`) — grupată pe domenii: Auth, CRM Clienți, Vânzări Interne, Vânzări Externe, Facturare, Furnizori, Logistică
- Convențiile de denumire și inconsistențele

#### Secțiunea 4 — MODULELE FUNCȚIONALE
Pentru fiecare modul (Comenzi Interne, Comenzi Externe, Facturi, Clienți, Produse, Încasări, Utilizatori, Searching, Piese Auto, API Credentials, Profile):
- Scop business
- Rute (cu nume, metode HTTP, controller@action, middleware aplicat)
- Controller — listă completă de metode publice cu descriere scurtă (1 rând fiecare)
- Modele și tabele atinse (CRUD-ul fiecăruia)
- View-urile asociate
- Fluxuri end-to-end principale, fiecare cu un diagram Mermaid `sequenceDiagram` (User → Browser → Controller → Service → API extern → DB → Response)
- Dependențe de alte module

#### Secțiunea 5 — INTEGRĂRI EXTERNE
Pentru fiecare integrare (ANAF, SmartBill, WhosMS, Sameday, FanCourier, AutoTotal, Materom, Autonet, AutoPartner, Elit, LKQ):
- Endpoint-uri / URL-uri de bază
- Tip autentificare (token static / OAuth / basic / SOAP)
- Unde sunt stocate credentialele (.env / api_credentials / hardcodat — flag de risc!)
- Metodele/operațiile suportate de service-ul din proiect
- Workflow-ul de retry / cache / error handling
- Limitări cunoscute

#### Secțiunea 6 — AUTENTIFICARE ȘI AUTORIZARE
- Cum se face login (username vs email, hashing, 2FA flag, lockout)
- Cum funcționează `CheckPermission` middleware
- Lista completă a `permission keys` și ce module gating-uiesc
- Lista de rute care **NU** sunt protejate de permission (doar de `auth`) — flag de revizuit
- Sanctum și API tokens

#### Secțiunea 7 — FLUXURI DE BUSINESS DETALIATE
Documentează minim aceste fluxuri cu diagrame `sequenceDiagram` în Mermaid:
- **F1**: Crearea unei comenzi interne — de la search produs până la imprimare bon/factură
- **F2**: Crearea unei comenzi externe cu AWB Sameday — de la create order → addProduct → createSamedayAwb → SMS/WhatsApp client → cronFancourierTracking
- **F3**: Căutare furnizori (varianta New) — Browser → SupplierSearchNewController.searchSuppliers → RunSupplierSearchNewAction → PoolRunner (5 furnizori paraleli) → Parsers → Aggregator → ResultBuilder → JSON response
- **F4**: Plasare comandă la furnizor + tratare excluded AutoTotal
- **F5**: Wishlist → ofertă PDF → WhatsApp client
- **F6**: Generare factură prin SmartBill (cu fallback DOMPDF)
- **F7**: Verificare client prin ANAF la introducerea CIF-ului
- **F8**: Import LKQ pricelist via cron

#### Secțiunea 8 — INVENTAR DE COD
- Tabel cu toate cele 22 controllere + LOC + responsabilitate
- Tabel cu toate cele 26 modele + tabel + PK
- Tabel cu toate cele ~14 servicii + rol + integrare externă
- Tabel cu toate view-urile grupate pe modul

#### Secțiunea 9 — PROBLEME, RISCURI, DATORIE TEHNICĂ
Pentru fiecare problemă, livrează:
- Severitate (Critic / Mare / Mediu / Mic)
- Locație în cod
- Descriere
- Impact
- Recomandare de remediere

Probleme de acoperit obligatoriu (vezi secțiunea „ANOMALII" de mai sus): credentiale hardcodate, dublu mapping de model, controllere duplicate, lipsă migrații, typo permission key, controller orfan, view orfan în `app/Models/`, PK-uri non-unice în FacturiDetail, lipsă transaction handling în multe metode care fac multiple insert-uri DB.

#### Secțiunea 10 — RECOMANDĂRI DE REFACTOR
- Plan de migrare: cum am putea consolida modelele duplicate fără a sparge codul
- Cum am putea introduce migrații reale fără a pierde schema curentă (squash & reverse-engineer)
- Cum am putea muta credentialele din cod în `api_credentials` deja existent + .env
- Cum am putea elimina monolitul `SearchingController` (5734 LOC) treptat
- Cum am putea standardiza PK-urile

#### Secțiunea 11 — GLOSAR
- Termeni RO ↔ EN (Comenzi = Orders, Facturi = Invoices, Încasări = Payments, Utilizatori = Users, Furnizori = Suppliers, Pieseauto = Auto Parts)
- Statusuri comenzi cu codurile lor numerice
- Tipuri documente fiscale: factură, proformă, aviz, chitanță, ofertă, storno

---

### 📐 REGULI DE STIL PENTRU OUTPUT

1. Limba: **română**, terminologie tehnică în engleză unde e standard (controller, route, middleware, foreign key, etc.).
2. Markdown curat, headers ierarhizate corect (H1 doar pentru titlul mare).
3. **Toate diagramele de fluxuri și entități în Mermaid** (folosește `sequenceDiagram` pentru fluxuri, `erDiagram` pentru schema bazei, `flowchart`/`graph` pentru arhitectură).
4. Cod / nume de tabele / nume de clase întotdeauna în `backticks`.
5. Tabele Markdown pentru: liste de rute, liste de metode controller, schema fiecărui tabel.
6. **Fii exhaustiv, nu rezumat**. Vreau să pot folosi documentul ca singura sursă de adevăr pentru on-boarding și pentru a face refactor în siguranță.
7. Unde codul e ambiguu, **scrie explicit „presupunere"** și ce ar trebui verificat manual în DB.
8. La final fiecare secțiune mare, include un mic **„checklist verificare"** — 3-5 lucruri care ar trebui validate empiric (rulare query SQL, deschidere de view, etc.).

---

### 🎬 ÎNCEPE ACUM

Procesează codul atașat / referit conform planului. Dacă unele fișiere lipsesc, listează ce ai și ce ți-ar mai trebui ca să închizi golurile (de ex.: `SHOW CREATE TABLE` pentru tabelele fără model, conținutul real al `.env`, view-urile pe care nu le-am atașat). Apoi livrează documentul complet structurat în ordinea de la **Secțiunea 1** la **Secțiunea 11**.

Stilul tău: precis, exhaustiv, fără filler. Documentația trebuie să fie utilizabilă atât pentru un dev nou care vine în echipă, cât și pentru un decision-maker care vrea să decidă unde să investească în refactor.

---

## 📌 INSTRUCȚIUNI ADIȚIONALE OPȚIONALE (folosește-le dacă vrei un alt focus)

Înlocuiește instrucțiunea din **Secțiunea 10 (Refactor)** cu una dintre variantele de mai jos dacă vrei alt rezultat:

**Varianta A — Audit de securitate:**
> Înlocuiește Secțiunea 10 cu un audit de securitate detaliat: SQL injection (raw queries în controllers), CSRF, XSS în view-uri, parole hardcodate, secrete în git, lipsă rate limiting pe rute publice (`/cron/fancourier-tracking`, `/awb-print/{id_awb}`), encryption la repos, validare input, mass assignment.

**Varianta B — Plan de migrare la Laravel 11:**
> Înlocuiește Secțiunea 10 cu un plan pas-cu-pas de upgrade Laravel 10 → 11 → 12, identificând breaking changes ce afectează acest proiect (Kernel.php deprecated, structure changes, etc.).

**Varianta C — Plan de teste:**
> Înlocuiește Secțiunea 10 cu un plan de testare: ce ar trebui acoperit cu Feature Tests (PHPUnit/Pest), ce cu Unit Tests, mock-uri pentru integrările externe, fixture-uri pentru DB.

**Varianta D — Documentație pentru utilizatorul final:**
> Schimbă toată instrucțiunea: livrează un manual de utilizare în română, scris pentru personalul magazinului, ne-tehnic, organizat pe role (vânzător, magaziner, manager).
