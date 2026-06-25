# PROMPT PENTRU LLM — Integrare TecDoc + Validare Stoc Local

> Copiază tot ce e mai jos și trimite-l către LLM (Claude / GPT). Atașează separat fișierele existente (`tecdoc_proxy.php`, `index.php` sau `selector.html`, schema MySQL) când le ceri.

---

## 1. CONTEXT PROIECT

Sunt proprietarul magazinului online **besoiupieseauto.ro**, găzduit local la `C:\laragon\www\besoiupieseauto.ro` (Laragon / Apache + PHP + MySQL).

**Stack tehnic:**
- Backend: PHP 8+ (fără framework, cod procedural)
- Bază de date: MySQL (produsele magazinului sunt deja în BD)
- Frontend: HTML + Bootstrap 5 + JavaScript vanilla (fetch API)
- Sursă date externe: **RapidAPI — auto-parts-catalog (TecDoc)**
- Cache local: fișiere JSON în `/cache_tecdoc/`

**Ce funcționează deja (NU strica):**
1. Selector în 3 pași: Marcă → Model → Motorizare (carId)
2. La submit, se aduc categoriile de piese pentru `carId`
3. Click pe categorie → modal cu produsele din TecDoc pentru `carId + nodeId`
4. Sistem de cache pe fișier (md5 din URL → JSON), TTL 86400s
5. Proxy-ul `tecdoc_proxy.php` cu 4 acțiuni: `get_models`, `get_vehicles`, `get_parts`, `get_articles`

---

## 2. CE AM NEVOIE SĂ CONSTRUIEȘTI (TASK NOU)

### 2.1. Modificare structură pagină principală
- Inputurile selectorului (Marcă/Model/Motorizare) trebuie mutate **sus, în header-ul paginii principale**, în format **orizontal** (4 coloane pe desktop, stivuite pe mobil).
- Adaugă o **a 4-a coloană**: input text cu placeholder `"Caută după cod OEM sau cod articol"` + buton lupă.
- Butonul `CAUTĂ PIESELE ACUM` rămâne sub inputuri.
- Sub header rămâne grila de produse existentă din magazin (nu o atinge).

### 2.2. Logica de căutare cu validare dublă
Când utilizatorul face o căutare (fie selector complet, fie OEM/cod):

```
1. Trimit cererea la TecDoc prin proxy-ul existent
2. TecDoc îmi returnează N piese
3. Pentru FIECARE piesă returnată, verific în MySQL local:
   - articleNumber EXISTĂ în coloana products.cod_articol  ?
   - SAU cod OEM există în tabela products_oem  ?
   - SAU brand+cod combinație există ?
4. Afișez DOAR piesele validate (există în stocul meu)
5. Piesele care nu există în BD locală → ignorate complet
```

### 2.3. Import imagini din TecDoc la momentul importului Excel
Când import produse noi din Excel:
- Excel-ul are doar: `cod_articol`, `brand`, `denumire`, `pret`, `stoc`
- Pentru fiecare rând, interogez TecDoc cu codul, obțin URL-ul `s3image`
- **Descarc fizic imaginea** și o salvez la `/uploads/products/{cod_articol}.jpg`
- Salvez calea în `products.image_path`
- Dacă TecDoc nu are imagine → marchez `needs_image = 1` (admin o adaugă manual)

### 2.4. Endpoint nou: `search_oem`
Adaugă în `tecdoc_proxy.php`:
```
case 'search_oem':
    // primește ?code=XXXX
    // întreabă TecDoc după cod OEM
    // returnează lista de piese candidate
```

---

## 3. REGULI STRICTE — NON-REGRESIE (CRITIC)

Aceste reguli sunt **obligatorii**. Dacă vreuna e încălcată, refac totul.

1. **NU șterge** și **NU rescrie** funcția `get_cached_response()` — doar extinde dacă e nevoie.
2. **NU schimba** semnăturile actuale ale acțiunilor `get_models`, `get_vehicles`, `get_parts`, `get_articles`. Pot fi extinse, dar nu modificate retroactiv.
3. **NU modifica** structura folder-ului `/cache_tecdoc/` și nu invalida cache-ul existent.
4. **NU folosi** librării noi care necesită `composer install` fără să-mi spui explicit (am voie doar PDO, cURL, GD/Imagick — deja prezente în PHP).
5. **NU schimba** cheia RapidAPI — o las în cod cum e (știu că ideal ar fi în `.env`, dar nu acum).
6. **Toate query-urile MySQL** se fac cu **PDO + prepared statements** (anti SQL injection). Niciun `mysqli_query` cu concatenare.
7. **JS-ul existent** (`onchange` pe selecturi) trebuie să continue să funcționeze identic. Adăugări — DA. Modificări de comportament — NU.
8. **Modal-ul Bootstrap** existent (`#modalProduse`) rămâne. Refolosește-l.
9. **Niciun `console.log()`** lăsat în producție în codul livrat.
10. **Compatibilitate**: codul trebuie să meargă pe PHP 8.0+ și MySQL 5.7+.

---

## 4. SCHEMA MySQL — REFERINȚĂ

```sql
-- Tabela principală de produse (EXISTĂ deja, nu o modifica fără permisiune)
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cod_articol VARCHAR(100) NOT NULL,
    brand VARCHAR(100) NOT NULL,
    denumire VARCHAR(255),
    pret DECIMAL(10,2),
    stoc INT DEFAULT 0,
    image_path VARCHAR(255) NULL,
    needs_image TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cod (cod_articol),
    INDEX idx_brand_cod (brand, cod_articol)
);

-- Tabela de coduri OEM (mulți la unu)
CREATE TABLE products_oem (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    oem_code VARCHAR(100) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_oem (oem_code)
);
```

> Dacă propui modificări de schemă, dă-mi **ALTER TABLE-uri separate**, niciodată `DROP`.

---

## 5. FORMAT DE OUTPUT AȘTEPTAT

Vreau răspunsul împărțit în **secțiuni clare**, în această ordine:

### A. Plan de implementare (5–10 rânduri)
Ce fișiere creezi, ce fișiere modifici, ce ordine.

### B. Fișiere NOI — cod complet
Fiecare fișier nou într-un bloc separat:
```php
// === FIȘIER NOU: /includes/db.php ===
<?php
// cod complet
```

### C. Fișiere MODIFICATE — diff sau bloc complet
Pentru fișierele existente, dă-mi **fișierul întreg actualizat** (nu doar diff-uri parțiale), ca să-l pot înlocui direct. Marchează clar ce ai schimbat cu comentarii `// MODIFICAT:` sau `// ADĂUGAT:`.

### D. Migrare SQL
Toate `CREATE TABLE` și `ALTER TABLE` necesare, idempotente (`IF NOT EXISTS`).

### E. Pași de testare manuală
Listă numerotată: ce să verific în browser ca să confirm că totul merge.

### F. Riscuri / limitări cunoscute
Ce ai presupus, ce ar putea pica, ce ar trebui îmbunătățit într-o iterație viitoare.

---

## 6. STIL DE COD CERUT

- PHP: indent 4 spații, `declare(strict_types=1);` în fișierele noi.
- Funcții cu **typed params** și **return types** (`function foo(int $id): array`).
- Comentarii în **română**, scurte și utile (nu evidente).
- JS: `const`/`let`, nu `var`. `async/await`, nu `.then()` înlănțuit.
- HTML: clase Bootstrap 5, fără CSS inline decât pentru lucruri minore.
- Niciun framework JS nou (no React, no Vue) — vanilla.

---

## 7. INFORMAȚII PE CARE LE PRIMEȘTI ATAȘAT
La acest mesaj atașez:
- `tecdoc_proxy.php` (codul actual integral)
- `selector.html` sau `index.php` (frontend actual)
- Eventual screenshot cu cum vreau să arate header-ul

Dacă ai nevoie de informație suplimentară (ex: structura altui tabel, cum se autentifică admin-ul), **întreabă înainte să scrii cod**. Nu inventa.

---

## 8. PRIMUL TĂU PAS

Înainte să scrii o linie de cod, răspunde-mi cu:
1. Confirmarea că ai înțeles cele 4 task-uri (2.1 – 2.4).
2. Ce întrebări ai despre infrastructură / date / business logic.
3. O estimare aproximativă: câte fișiere noi, câte modificate.

Apoi îți dau OK și începi implementarea.
