# Coadă nouă „Produse fără imagine” — admin/importreview (17.07.2026)

## 1. Cerință (cuvintele userului)

Pe `admin/importreview`, la secțiunea „Tip coadă", userul a cerut o coadă nouă,
separată: **„Produse fără imagine"**. Când cronul găsește un produs cu match TecDoc
dar FĂRĂ imagine, acesta trebuie trimis în această coadă nouă (nu aruncat).
Produsele CU imagine continuă să se împartă ca înainte:
- dacă fac parte dintr-o categorie de vitrină (ex. ulei) → coada **„Produse vitrină"**,
- altfel → coada **„Produse normale"**.

## 2. Ce am găsit (starea înainte de fix)

Produsele găsite cu match TecDoc dar FĂRĂ imagine erau **complet aruncate** —
`import_stage_products_for_review()` (`app/Backend/src/Controllers/Produse/importproduse.php`)
făcea `continue` (fără `INSERT`) când `require_image=true` și nu găsea imagine. Rămâneau
doar ca statistică (`skipped_no_image`) în jurnalul MatchingPro — invizibile în
`admin/importreview`, fără nicio coadă dedicată. Pagina avea deja 2 „lane"-uri
(„standard" / „showcase"), stocate în `raw_json.import_lane` (nu coloană DB dedicată).

## 3. Fix aplicat

### A. Backend — nu mai aruncăm, ci rutăm către lane nou `no_image`

**Fișier:** `app/Backend/src/Controllers/Produse/importproduse.php`,
funcția `import_stage_products_for_review()`.

- Înainte: `if ($requireImage && empty($images[0])) { ++skipped_no_image; continue; }`
- Acum: nu mai facem `continue` — produsul e inserat normal, dar cu
  `import_lane` forțat la `'no_image'` (indiferent ce lane a cerut apelantul —
  `standard` sau `showcase`), suprascriind decizia vitrină/standard anterioară.
- Statistica `skipped_no_image` e păstrată (contorizează în continuare produsele
  fără imagine), dar semnificația s-a schimbat: nu mai înseamnă „aruncat", ci
  „rutat în coada fără imagine".

### B. Filtrare — `ImportReviewQueueService`

**Fișier:** `app/Backend/src/Services/ImportReviewQueueService.php`,
`listQueueRows()` — adăugat branch nou:
```php
} elseif ($lane === 'no_image') {
    $where[] = "JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.import_lane')) = 'no_image'";
}
```

### C. UI — tab nou „Produse fără imagine"

**Fișier:** `modules/coada_import/pages/views/importreview.php`:
```php
$laneTabs = [
    'standard' => ['label' => 'Produse normale', 'icon' => 'package'],
    'showcase' => ['label' => 'Produse vitrină', 'icon' => 'sparkles'],
    'no_image' => ['label' => 'Produse fără imagine', 'icon' => 'image-off'],
];
```
Restul UI-ului (link-uri, filtre, reset furnizor) folosește deja `$lane` generic,
fără listă fixă de valori — tab-ul nou funcționează fără alte modificări.

### D. Narațiune corectă în log-ul live + jurnalul MatchingPro

**Fișier:** `app/Import/MatchingPro/tools/stage_cron_batch.php`:
- Jurnalul per-produs (`journalItems`) marchează acum `'lane' => 'no_image'` pentru
  produsele fără imagine (în loc de a rămâne etichetate „showcase"/„standard" cât
  timp erau de fapt aruncate).
- Mesajele agregate „Standard: N candidat(e)..." / „Vitrină: N candidat(e)..." nu
  mai spun „X respinse (fără imagine)" — spun „X mutate în coada «Produse fără
  imagine»" (adevărat acum, pentru că efectiv sunt migrate, nu aruncate).
- Liniile per-produs din log (adăugate în runda anterioară de fix-uri, punctul C)
  spuneau deja „FĂRĂ imagine → coadă produse fără imagine" — mesaj care ERA
  înșelător înainte de acest fix (produsul era de fapt aruncat). Acum e adevărat.

### E. Dashboard MatchingPro (`app/Import/MatchingPro/index.php`)

- Textul de sub „Rezultat migrare coadă import" nu mai spune „respinse fără match
  sau fără imagine" — spune corect că toate 3 tipurile (vitrină/standard/fără
  imagine) sunt migrate în `import_produse`, doar cele fără match sunt respinse.
- Hint-ul de sub statistici nu mai spune „⚠️ blocate" pentru produsele fără
  imagine — spune „ℹ️ N produs(e) fără imagine → coada «Produse fără imagine»
  (admin/importreview)".
- Mesajul final „Cron terminat: ..." nu mai include produsele fără imagine în
  categoria „blocate" — le arată ca parte din „trimise în coadă (din care N fără
  imagine)".
- Eticheta statisticii „Fără imagine” → „Fără imagine (coadă separată)”, pentru claritate.

## 4. Cum s-a testat

**Test real, pe baza de date de producție**, cu un singur produs de test creat și
șters imediat (fără a afecta datele reale):

1. S-a construit un card de produs cu match TecDoc simulat, explicit **fără imagine**
   (`hasImage: false`), folosind același flux (`ImportCardStaging::cardsToProducts`)
   ca cel real din `stage_cron_batch.php`.
2. S-a apelat `import_stage_products_for_review()` cu `require_image=true` (exact ca
   la cron), `import_lane='standard'` (cerere inițială).
3. S-a verificat:
   - produsul a fost **inserat** (`queued=1`), nu aruncat;
   - `import_lane` din `raw_json` e efectiv `'no_image'` (suprascris corect, ignorând
     `'standard'` cerut inițial);
   - `ImportReviewQueueService::listQueueRows(lane='no_image')` **include** produsul;
   - `listQueueRows(lane='standard')` și `listQueueRows(lane='showcase')` **NU** îl includ.
4. Produsul de test a fost **șters** imediat din `import_produse`, iar la final s-a
   confirmat că baza de date nu are niciun rând rezidual (`leftover_test_rows=0`).

**Rezultat: 10/10 verificări OK, fără reziduuri în baza de date reală.**

Sintaxă PHP validă (`php -l`) pe toate cele 5 fișiere modificate; fără erori de linting.

## 5. Ce rămâne de confirmat vizual

- Deschide `admin/importreview` și confirmă că tab-ul „Produse fără imagine" apare
  lângă „Produse normale" / „Produse vitrină", cu iconița corectă.
- La următoarea rulare de cron (sau „Testează cron") care găsește un produs cu match
  dar fără imagine, acesta ar trebui să apară direct în acest tab, gata de completat
  manual cu o imagine și publicat.

## 6. Concluzie (fără jargon tehnic)

Înainte, un produs găsit de cron care nu avea imagine era pur și simplu pierdut —
nu ajungea nicăieri în coada de review. Acum, orice astfel de produs e salvat
într-o coadă nouă și vizibilă, „Produse fără imagine", exact ca cele „normale" și
cele de „vitrină" — poți să îi adaugi manual o imagine și să-l publici, în loc să-l
pierzi definitiv.
