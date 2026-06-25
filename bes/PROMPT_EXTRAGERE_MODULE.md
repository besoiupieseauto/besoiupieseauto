# PROMPT — Extragere module Laravel → PHP pur / framework custom

> **Cum se folosește:** Copiază tot conținutul de mai jos într-o conversație nouă cu un AI (Claude / ChatGPT). Atașează apoi fișierele specifice fiecărui modul (lista exactă este în prompt). AI-ul îți va livra modul după modul, cu cod + explicații.
>
> **Recomandare:** Nu cere toate modulele odată — vor fi prea multe pentru o singură conversație. Fă **o conversație per modul**. Promptul de mai jos îi spune AI-ului acest lucru și începe cu Modulul 1 (Comenzi). La final, schimbi „M1" cu „M2", „M3", „M4" pentru următoarele.

---

## 🎯 PROMPT — COPIAZĂ DE AICI ÎN JOS

Ești un dezvoltator PHP senior. Sarcina ta este să **extragi module funcționale dintr-un proiect Laravel 10** și să le **traduci în PHP pur** (eventual cu framework custom minimal), păstrând logica de business 100%, dar **eliminând toate dependențele Laravel** (Eloquent, Facades, Blade, helpers globali, middleware).

Codul sursă este un ERP pentru un magazin de piese auto din România. Stack-ul curent: Laravel 10 + PHP 8.1 + MySQL + Blade + jQuery. Voi atașa fișierele relevante pentru fiecare modul în parte.

**Limba de comunicare cu mine: română. Limba codului și a comentariilor: română pentru comentarii/UI, engleză pentru identificatori (variabile, clase, metode, funcții).**

---

### 🗺️ CONTEXTUL PROIECTULUI ORIGINAL

Proiectul are 4 module mari pe care vreau să le port:

| # | Modul | Sursă în Laravel |
|---|---|---|
| **M1** | **Comenzi (interne + externe)** | `OrderController` (comenzi interne, pickup) + `ComenziController` (comenzi externe, livrare prin curier) |
| **M2** | **Căutare furnizori** (5 API-uri B2B paralele) | `SearchingController` + `SupplierSearchNewController` + servicii: AutoPartner, Materom, Autonet, AutoTotal, Elit |
| **M3** | **Facturare + SmartBill** | `FacturiController` + `SmartBillService` |
| **M4** | **Curierat (Sameday + FanCourier)** | `SamedayController` + `FanCourierController` + `AwbController` + SDK-uri |

---

### 📦 PENTRU FIECARE MODUL — INVENTAR DE FIȘIERE CARE TREBUIE ATAȘATE ÎMPREUNĂ

Asta e harta de dependențe pe care eu am extras-o deja. Când îți atașez fișierele pentru un modul, vei avea exact aceste fișiere:

#### M1 — Comenzi
**Controllere (2):**
- `app/Http/Controllers/OrderController.php` (3448 LOC — comenzi interne)
- `app/Http/Controllers/ComenziController.php` (3793 LOC — comenzi externe cu AWB)

**Modele (8):**
- `Comenzi` (tabel `comenzi`, PK `idcmd`) — header comandă internă
- `ComenziExt` (tabel `comenzi_ext`, PK `idcmd`) — header comandă externă
- `Detaliu` (tabel `detaliu`, PK `iddetaliu`) — linii comandă internă
- `DetaliuExt` (tabel `detaliu_ext`, PK `iddetaliu`) — linii comandă externă
- `Client` (tabel `clienti`, PK `idclienti`)
- `Localitate` (tabel `localitati`, PK `idlocatie`)
- `Tmp` (tabel `tmp`, PK `id_tmp`) — coș temporar bazat pe `session_id`
- `Produse` (tabel `produse`, PK `idprodus`)
- `MessageTemplate` (tabel `message_templates`) — template-uri WhatsApp/SMS
- `OrderStatusHistory` (tabel `order_status_history`) — istoric statusuri
- `Factura` + `FacturiDetail` (pentru generarea facturii din comandă)

**Servicii:**
- `App\Services\SmartBillService` (apel REST către SmartBill pentru e-facturare)
- `App\Services\SmsService` (WhosMS gateway)
- `App\Services\FanCourier\FanCourierService` (pentru AWB la comenzi externe)

**Views (Blade):**
- `resources/views/orders/{index,create,edit,editare_order,editare_facturare,edit_factura,print,utvinindex}.blade.php`
- `resources/views/orders/modals/{client_nou,location,sms,color,total,supplier,add_product,status,search_product}.blade.php`
- `resources/views/comenzi/{index,create,edit,edit_extreme}.blade.php`
- `resources/views/comenzi/partials/{modals,results}.blade.php`

**Rute (din `routes/web.php`):**
- toate rutele `/orders/*` și `/comenzi/*`

**Statusuri comenzi (codate numeric):**
- 1 = Comandat | 2 = Sosit | 3 = Cash | 4 = Avans | 5 = Retur | 6 = Card | 7 = FD

#### M2 — Căutare furnizori
**Controllere (2):**
- `app/Http/Controllers/SearchingController.php` (5734 LOC — versiunea veche, monolit)
- `app/Http/Controllers/SupplierSearchNewController.php` (175 LOC — versiunea nouă cu pool paralel)

**Servicii furnizori (5):**
- `App\Services\AutoPartner\AutoPartnerService` (API v2.13, token cu expirare)
- `App\Services\Materom\MateromService` (api.materom.ro, **token per locație: UTVIN/TIMISOARA**)
- `App\Services\Autonet\AutonetService` (wes.autonet-group.com, prod/staging)
- `App\Services\Autototal\AutototalService` (atx.autototal.ro:15063 & :15085, **credentiale per locație**)
- `App\Services\Elit\ElitService` (econnector.elit.cz — SOAP BusinessService + BuyerService)

**Servicii orchestrare (în `App\Services\SupplierSearchNew\`):**
- `RunSupplierSearchNewAction` — orchestrator principal
- `PoolRunner` — pool Guzzle pentru cereri paralele
- `ProductAggregator` — agregare rezultate
- `ResultBuilder` — formatare output
- `SearchEnricher` + `AutonetProductEnricher` + `ElitFromDbMerger` + `PartsCatalogLookup` — îmbogățire cu date locale
- `Parsers/` (folder cu parser-e per furnizor)

**Servicii helpers vechi (în `App\Services\SupplierSearch\`):**
- `DeliveryFormatter`, `PricingApplier`, `ProductNameResolver`, `SearchPayloadBuilder`

**Modele:**
- `AutototalData` (cache local)
- `AutototalExcludedCartItem` (produse excluse din comandă automată)
- `SupplierCart` (coș activ per user, JSON)
- `SupplierSavedCart` (wishlist salvat per user cu name/phone/vin, JSON)
- `SupplierOrder` (raw_response salvat după plasare)
- `Promotion` (markup per supplier+brand)

**Tabele accesate direct prin `DB::table()`:**
- `autonet_qwp_data`, `autototal_branduri_proprii`, `parts_catalog`, `lkq_prices`, `settings`

**Views:**
- `resources/views/searching/{index,index_new,cart,saved_carts,excluded_autototal_cart,orders,cart_offer_pdf}.blade.php`

#### M3 — Facturare + SmartBill
**Controller:**
- `app/Http/Controllers/FacturiController.php` (1825 LOC)

**Modele:**
- `Factura` (tabel `facturi`, PK `OrderID`) — header factură
- `FacturiDetail` (tabel `facturidetails`, PK `OrderID` ⚠ non-unic!)
- `Client`, `Produse`, `Localitate`, `TipPlata`, `Tmp`, `Employee`

**Servicii:**
- `App\Services\SmartBillService` (Basic Auth: `userEmail:apiKey`, endpoint `/invoice`)
- `App\Models\ApiCredential` (stocare encrypted a credentialelor SmartBill)
- `App\Services\AnafService` (verificare CIF firme — folosit la add client)

**Views:**
- `resources/views/facturi/{index,create,edit,edit_sub,print,show}.blade.php`

**Tipuri documente fiscale generate:**
- factură, chitanță, ofertă, proformă, aviz (câmpuri `id_chitanta`, `id_oferta`, `id_proforma`, `id_aviz`, `id_fact` în tabela `facturi`)
- `negative_issued` pentru storno

#### M4 — Curierat (Sameday + FanCourier)
**Controllere:**
- `app/Http/Controllers/SamedayController.php` (408 LOC)
- `app/Http/Controllers/SamedayApiController.php`
- `app/Http/Controllers/SamedayProxyController.php`
- `app/Http/Controllers/FanCourierController.php` (796 LOC)
- `app/Http/Controllers/AwbController.php` (149 LOC — printare AWB PDF)
- `app/Http/Controllers/AwbControllerfan.php` (99 LOC — FanCourier-only print)

**SDK-uri externe (composer):**
- `sameday-courier/php-sdk` (namespace `Sameday\*`)
- `seniorprogramming/fancourier` + `shusaura85/fancourier-api` (namespace `Fancourier\*`)
- SDK Sameday e și extras local în `app/Services/Sameday/` (Sameday.php, SamedayClient.php, Objects/, Requests/, Responses/, Http/, HttpClients/, Exceptions/)

**Servicii:**
- `App\Services\SamedayService`
- `App\Services\SamedayMockService` (pentru testing)
- `App\Services\FanCourier\FanCourierService`
- `App\Services\FanCourierService` (la root — ⚠ duplicat!)

**Middleware:**
- `App\Http\Middleware\SamedayMockProxyMiddleware`

**Endpoint cron public:**
- `GET /cron/fancourier-tracking` (NU este în spatele auth — actualizează statusuri din tracking FanCourier)

---

### 🔧 STACK-UL ȚINTĂ (PHP pur / framework custom)

Codul meu de destinație este **PHP pur**, fără Laravel. Asta înseamnă că vei face **următoarele înlocuiri obligatorii** când traduci codul:

| Laravel | Înlocuire în PHP pur |
|---|---|
| Eloquent Model | Clasă PHP simplă + **PDO** (prepared statements) sau un Active Record minimal pe care îl scriem împreună |
| `DB::table('x')->where(...)->get()` | PDO cu `$pdo->prepare(...)->execute([...])` și `fetchAll(PDO::FETCH_ASSOC)` |
| `Auth::user()` | `$_SESSION['user']` sau o clasă `AuthManager` pe care o scriem |
| `auth()->check()` | `isset($_SESSION['user_id'])` |
| `Request $request` + `$request->input('x')` | `$_POST['x'] ?? null`, `$_GET['x'] ?? null` sau o clasă `Request` simplă cu `$request->input('x')` |
| `Validator::make(...)` | Funcție custom `validate($data, $rules)` sau filter_var |
| `response()->json($x)` | `header('Content-Type: application/json'); echo json_encode($x); exit;` |
| `redirect()->route('x')` | `header('Location: /x'); exit;` |
| `view('x', $data)` | `include __DIR__.'/views/x.php'` (cu `extract($data)` înainte) — sau folosim un mic engine de template pe care îl putem scrie |
| Blade (`@foreach`, `{{ }}`, `@if`) | `<?php foreach (...): ?>`, `<?= htmlspecialchars(...) ?>`, `<?php if (...): ?>` |
| `Log::info(...)` | `error_log(...)` sau scriere într-un fișier cu `file_put_contents(..., FILE_APPEND)` |
| `Cache::remember(key, ttl, fn)` | APCu sau o cache simplă pe fișiere — îmi scrii și un wrapper |
| `Carbon\Carbon::now()` | `new DateTime()` / `date('Y-m-d H:i:s')` |
| `now()` | `date('Y-m-d H:i:s')` |
| `session()->get/put` | `$_SESSION[...]` direct |
| `config('services.x.y')` | constante PHP, fișier `.env` parsat manual, sau o funcție `config('x.y')` simplă |
| `env('KEY')` | `getenv('KEY')` sau `$_ENV['KEY']` (sau parsare manuală .env) |
| `Http::withHeaders([...])->post(...)` | cURL direct (cu wrapper) sau **Guzzle** (Guzzle merge în PHP pur, e doar un pachet composer — îl păstrezi) |
| `encrypt()` / `decrypt()` | `openssl_encrypt` / `openssl_decrypt` cu o cheie din `.env` |
| `bcrypt($password)` / `Hash::check` | `password_hash($pw, PASSWORD_BCRYPT)` / `password_verify` |
| `CSRF token` | implementăm un token simplu pe sesiune |
| Middleware `auth` | un `require __DIR__.'/auth_check.php';` în fiecare entry point sau front-controller |
| Middleware `permission:x` | funcție `requirePermission('x')` care abort-ează cu 403 |
| Route `Route::get/post` | un router simplu (ex: `bramus/router` din composer) **sau** routing manual cu `switch` pe `$_SERVER['REQUEST_URI']` |
| Eloquent relationship (`hasMany`, `belongsTo`) | JOIN-uri SQL explicite scrise de mână |
| Mass assignment (`fillable`) | array allow-list manual înainte de insert |

**Pachete composer care RĂMÂN folosibile** (nu depind de Laravel):
- `guzzlehttp/guzzle` (HTTP client)
- `sameday-courier/php-sdk` (SDK Sameday standalone)
- `seniorprogramming/fancourier`, `shusaura85/fancourier-api` (SDK FanCourier)
- `dompdf/dompdf` (instalabil direct, fără `barryvdh/laravel-dompdf`)
- `setasign/fpdf` sau alternativa standalone

---

### 📋 CE TREBUIE SĂ LIVREZI PENTRU FIECARE MODUL

Pentru **fiecare modul** pe care îl atac (M1 → M2 → M3 → M4), livrează strict următoarele, **în această ordine**:

#### Pasul 1 — Diagnoză (înainte de cod)
- Listă scurtă (10-20 puncte) cu **ce face modulul** — funcționalități end-to-end
- Listă cu **input-urile** (rute / endpoint-uri / parametri așteptați)
- Listă cu **output-urile** (response JSON / redirect / HTML / PDF)
- Listă cu **side-effects** (insert-uri DB, apeluri API externe, SMS-uri trimise, fișiere generate)
- Listă cu **dependențele Laravel pe care le voi tăia** (cu numărul aproximativ de înlocuiri necesare)

#### Pasul 2 — Schema SQL
- `CREATE TABLE` complet pentru fiecare tabel atins de modul, derivat din modele Eloquent + apeluri `DB::table()` din controller. Include:
  - tipuri inferate (INT / VARCHAR / TEXT / DATETIME / DECIMAL / TINYINT etc.)
  - PK, indexuri probabile
  - NULL / NOT NULL
  - default values
  - foreign keys logice (chiar dacă nu sunt declarate fizic)
- Marchează **explicit** dacă o coloană este **presupunere** vs. **certă din cod**

#### Pasul 3 — Cod PHP pur, fișier cu fișier

Pentru fiecare fișier produs, scrie:
1. **Calea** pe care recomanzi să-l pun (ex: `src/Models/Comanda.php`, `src/Services/SmartBillService.php`, `public/orders.php`, `views/orders/index.php`)
2. **Header de explicații** (3-7 rânduri): la ce folosește fișierul, ce înlocuiește din originalul Laravel
3. **Cod PHP complet, funcțional**, fără placeholdere de tip `// TODO` ascunse — dacă lipsește ceva, marchează clar `// ⚠ NEEDS_CONFIG: ...`
4. **Sub fiecare metodă sau bloc important**, un mic paragraf de explicație în română: ce face, ce primește, ce returnează, ce diferențe sunt față de codul Laravel original
5. **La sfârșitul fișierului**, un comentariu cu „cum se apelează" (snippet de exemplu)

Ordine recomandată per modul:
1. Modele / clase de DAO (acces date) — întâi
2. Servicii (logică business + integrări externe)
3. Helpers comune (Auth, Request, Response, Logger, Config)
4. Routing / entry-points / controllere PHP
5. View-uri convertite din Blade → PHP pur
6. Fișier `.env.example` cu toate variabilele necesare

#### Pasul 4 — Bootstrap minim
Un fișier `bootstrap.php` care:
- pornește sesiunea
- conectează la DB (PDO)
- încarcă autoload composer
- încarcă `.env`
- inițializează loggerul
- include helper-ele comune
- e cerut o singură dată în front-controller-ul (`index.php` sau pe fiecare endpoint)

#### Pasul 5 — Test smoke
Pentru fiecare endpoint principal al modulului, dă-mi:
- comanda `curl` cu care îl testez
- response-ul așteptat
- ce verific în DB după (ce rânduri trebuie să apară/se modifice)

#### Pasul 6 — Migrare incrementală
Recomandare scurtă: **în ce ordine** să integrez modulul în codul meu existent ca să nu spargem nimic.

---

### 🛡️ REGULI DE CALITATE A CODULUI

1. **Prepared statements peste tot** — niciodată concatenare directă cu input utilizator în SQL.
2. **`declare(strict_types=1);`** la începutul fiecărui fișier PHP.
3. **Type hints** la toți parametrii și return types peste tot unde se poate (PHP 8.1+).
4. **Nu folosi globale** (`global $db`) — pasează dependențele prin constructori (DI manual simplu).
5. **Nicio credențială hardcodată** — totul în `.env`. Dacă găsești credențiale hardcodate în codul Laravel original, mută-le în `.env.example` cu valoare placeholder și avertizează-mă.
6. **Tratare erori** — fiecare apel către API extern și fiecare query critic în try/catch cu logare; răspuns user-friendly în loc de stack trace.
7. **Transacții** unde modulul face >1 insert legate logic (ex: salvare comandă + linii detaliu) — folosește `$pdo->beginTransaction() / commit() / rollBack()`.
8. **Output escaping**: în view-uri întotdeauna `htmlspecialchars($x, ENT_QUOTES, 'UTF-8')` pentru orice variabilă din DB / user input.
9. **CSRF**: implementăm token pe sesiune și verificăm pe POST.
10. **Nu inventa** funcționalități care nu sunt în codul original. Dacă o metodă din controller pare incompletă (TODO, comentată, dead code), notează asta și **nu o include** în output decât marcată explicit.

---

### 🚦 MOD DE LUCRU PROPUS — ÎNTREBĂRI ÎNAINTE DE START

Înainte să livrezi orice cod, **răspunde-mi la următoarele 5 întrebări**, ca să nu lucrăm degeaba:

1. **Routing**: vrei să folosim un router composer (`bramus/router`, `nikic/fast-route`) sau routing manual cu front-controller + `switch`?
2. **Template engine**: PHP pur (`<?= ?>` în fișiere `.php`) sau un mini-engine simplu (ex: `league/plates`)?
3. **DB layer**: PDO direct cu o clasă DAO per tabel, sau un micro Active Record (~150 linii) pe care îl scriem împreună?
4. **Composer**: ai composer instalat ca să poți păstra Guzzle + SDK-urile Sameday/FanCourier? (Răspuns negativ înseamnă că trebuie să rescriu și aceste integrări cu cURL pur — mult mai multă muncă.)
5. **Front-end**: păstrăm jQuery + Bootstrap-datepicker așa cum sunt, sau modernizăm? (Recomand: păstrăm — schimbarea front-end-ului scoate scope-ul mult în afara „extragere module".)

După ce-mi răspund la astea, **începem cu Modulul 1 (Comenzi)**, conform pașilor 1-6 de mai sus.

---

### 🎬 ÎNCEPE ACUM

Voi atașa în mesajul următor fișierele pentru **Modulul 1 (M1 — Comenzi)**.

Întâi pune-mi cele 5 întrebări de Pas 0. Apoi, când îți răspund și îți atașez fișierele, livrează Modulul 1 conform pașilor 1-6, complet, fără rezumate, fără să sari peste secțiuni. Dacă răspunsul iese prea lung pentru un singur mesaj, împarte-l în mai multe mesaje secvențiale și spune-mi la final „aștept «continuă» pentru următorul fișier".

---

## 📌 PROMPTURI SCURTE PENTRU MODULELE URMĂTOARE

După ce termini Modulul 1, folosește unul din mesajele scurte de mai jos pentru a continua cu următorul modul, **în aceeași conversație** (păstrează contextul pe deciziile de stack):

**Pentru M2 — Căutare furnizori:**
> Atașez fișierele Modulului 2 — Căutare furnizori. Livrează-l conform aceleiași structuri (pași 1-6). Specific pentru M2: focus pe paralelismul Guzzle Pool și pe parser-ele per furnizor. Recomandă-mi cum să fac pool-ul de cereri paralele în PHP pur (Guzzle promises sau curl_multi_*).

**Pentru M3 — Facturare:**
> Atașez fișierele Modulului 3 — Facturare + SmartBill. Livrează-l conform aceleiași structuri (pași 1-6). Specific pentru M3: focus pe integrarea SmartBill (auth, payload, response parsing) și pe generarea PDF (DOMPDF standalone — nu `barryvdh/laravel-dompdf`).

**Pentru M4 — Curierat:**
> Atașez fișierele Modulului 4 — Curierat (Sameday + FanCourier). Livrează-l conform aceleiași structuri (pași 1-6). Specific pentru M4: păstrăm SDK-urile standalone din composer; focus pe wrappere clare care abstractizează diferențele între Sameday și FanCourier și pe cron-ul de tracking.

---

## 📝 NOTE PERSONALE PENTRU MINE (NU FAC PARTE DIN PROMPT — DE ȘTEARS ÎNAINTE DE PASTE)

- Fă **o conversație separată pe modul** dacă AI-ul se sufocă pe context.
- Verifică credentialele hardcodate în codul original și pune-le în `.env` ÎNAINTE de a împărtăși fișierele (sunt parole reale în `MateromService`, `AwbController`, `AutototalService`).
- Atunci când AI-ul produce SQL, rulează-l pe o DB de test goală și verifică să nu pice — sunt anumite presupuneri (FK-uri, tipuri exacte).
- Atunci când AI-ul produce cod, prima dată citește **doar pașii 1+2** (diagnoză + schema) și abia după ce confirmi că arată bine, lasă-l să facă pasul 3 (codul). Altfel e risc să livreze 4000 linii de cod cu o greșeală repetată peste tot.
