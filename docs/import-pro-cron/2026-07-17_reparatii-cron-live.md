# Reparații "Cron automat" — tab Import PRO (17.07.2026)

## 1. Context / cerință (cuvintele userului)

Pe pagina `admin/import-pro`, tabul **Cron automat**:

1. Când se apasă orice buton (Rulează / Testează / Pauză / Reluare / Stop), logul
   trebuie golit și pornit de la zero — nu rezultate vechi amestecate.
2. "Log activitate cron (live)" trebuie să aibă un indicator verde care **pulsează**
   vizibil cât cronul rulează.
3. Logul trebuie să arate procedura **pas cu pas, pe produs**, nu doar pe fișier:
   pornire cron → scanare fișier furnizor → găsite N produse cu match → pentru
   fiecare produs: match/fără match → dacă are/nu are imagine → în care coadă e mutat.
4. La "Testează cron (10 produse)" nu trebuie să apară dintr-o dată un rezultat vechi
   (ex. "5 produse scanate, 0 rezultate") — trebuie progres real, incremental, cu bară
   de progres, și la final o **notificare** clară.
5. Analiză generală: unde se rupe sistemul, ce coordonate/fișiere sunt implicate.

## 2. Analiză inițială (rezumat)

Motorul complet al tabului Cron e în `app/Import/MatchingPro/index.php` (UI+JS) +
`app/Import/MatchingPro/api/*.php` (endpoints) + `app/Import/MatchingPro/src/*.py`
(Python: `cron_watcher.py`, `progress.py`, `scanner.py`, `matcher.py`) +
`tools/stage_cron_batch.php` (migrare coadă `import_produse`).

Probleme identificate:

- **A.** `resetScanUiState()` nu golea logul/progresul; `cron-start.php` nu scria
  un progres "proaspăt" înainte de a porni Python → fereastră cu date vechi vizibile.
  `import_tail_log()` citea tot logul zilei, fără delimitare pe rularea curentă.
- **B.** `.live-dot` avea doar `background` static, fără `@keyframes`.
- **C.** Pașii din `cron_watcher.py` erau pe fișier/batch agregat, nu pe produs
  individual, deși datele per-produs (`journalItems`) existau deja în
  `stage_cron_batch.php`, doar nu erau trimise ca linii de log live.
- **D.** Bara de progres era calculată pe fișiere (`set_file_phase`), iar faza finală
  pe fișier se numea `done`/100% ÎNAINTE ca migrarea în coadă (staging per-produs) să
  se termine efectiv — dădea impresia falsă de „gata” cât timp mai lucra pe fundal.
- **E.** Nu exista niciun mecanism de notificare (`Notification()`/toast) la final.
- **F.** Existau două definiții `stopCronAndClearCache()` — prima moartă (shadowed
  de a doua, definită mai jos în fișier).

## 3. Puncte de lucru — status final

| # | Punct | Status |
|---|-------|--------|
| A | Reset instant UI + progres proaspăt sincron + log doar din rularea curentă | ✅ FINALIZAT |
| B | Punct verde LIVE animat (pulse) | ✅ FINALIZAT |
| C | Log narativ per-produs (scan → match → imagine → coadă) | ✅ FINALIZAT |
| D | Eliminare "fals gata" pe bara de progres (fază 100% înainte de migrare reală) | ✅ FINALIZAT |
| E | Notificare (browser Notification + toast) la finalizare | ✅ FINALIZAT |
| F | Elimină `stopCronAndClearCache` duplicată/moartă | ✅ FINALIZAT |
| G | Fix bug critic: „10 produse” = produse CU MATCH, nu rânduri citite (raportat de user după primul test real) | ✅ FINALIZAT |

---

## 4. Detalii pe punct

### A. Reset instant UI + progres "proaspăt" + log doar din rularea curentă

**Fișiere modificate:**
- `app/Import/MatchingPro/api/bootstrap.php` — funcție nouă `import_start_fresh_cron_progress()`;
  `import_tail_log()` primește parametru opțional `$sinceIso` (filtrează liniile scrise
  înainte de startul rulării curente).
- `app/Import/MatchingPro/api/cron-start.php` — apelează `import_start_fresh_cron_progress()`
  SINCRON, înainte de a porni procesul Python în background.
- `app/Import/MatchingPro/api/cron-progress.php` — trimite `progress.started_at` ca filtru
  `$sinceIso` către `import_tail_log()`.
- `app/Import/MatchingPro/index.php` (JS) — funcție nouă `resetCronLiveUiState()`, apelată
  sincron din `runCron()` la click pe „Rulează cron acum” / „Testează cron” — golește
  instant bara de progres, consola de log, pașii și panoul de staging, ÎNAINTE de primul
  poll către server.

**De ce rezolvă problema:** înainte, exista o fereastră (de la click până la primul write
al procesului Python) în care UI-ul mai arăta progresul/logul rulării ANTERIOARE. Acum:
1. UI-ul se golește instant, sincron, la click (front-end).
2. Backend-ul scrie un `cron_progress.json` nou (status=running, percent=0, steps=[])
   ÎNAINTE de a porni Python, deci nu mai există fereastră cu date vechi.
3. Logul afișat e filtrat să conțină doar liniile de după `started_at` al rulării curente.

**Cum s-a testat:**
- Backend: script PHP izolat (`_tmp_test_bootstrap.php`, șters după test) care a verificat:
  - `import_start_fresh_cron_progress()` scrie `status=running`, `percent=0`, `run_id` corect,
    `steps` cu 1 element.
  - `import_motor_append_log()` scrie linia în formatul `[ISO] [LEVEL] mesaj`.
  - `import_tail_log($n, $sinceIso)` EXCLUDE o linie scrisă cu 1h în urmă când `$sinceIso`
    = acum, dar o INCLUDE când `$sinceIso = null`.
  - **Rezultat: 13/13 verificări OK.**
- Frontend: verificare statică a codului (linting fără erori) + confirmare că
  `resetCronLiveUiState()` e apelată înainte de `fetchJson(CRON_START_API, …)`.
- State-ul real (`cron_progress.json`, `cron_staging_last.json`, `cron_control.json`) a fost
  salvat înainte de test și restaurat identic după — **nu s-au afectat date reale**.

---

### B. Punct verde LIVE animat (pulse)

**Fișier modificat:** `app/Import/MatchingPro/index.php` (CSS `.cron-log-header .live-dot`)
— adăugat `@keyframes cron-live-pulse` (box-shadow expandabil, ca un „radar”), aplicat
cât timp punctul nu are clasa `.off`.

**Cum s-a testat:** verificare vizuală a regulii CSS (sintaxă validă, `@keyframes` corect
referențiat), verificare că `renderCronProgress()` continuă să comute clasa `off` exact ca
înainte (`status === 'running'` → fără `.off` → pulsează; altfel → `.off` → static, gri).

> Verificare vizuală finală (animația chiar se vede pulsând în browser) recomandată de user
> direct în admin, la următoarea rulare — logica CSS e validă și cablajul JS e intact.

---

### C. Log narativ per-produs (scan → match → imagine → coadă)

**Fișiere modificate:**
- `app/Import/MatchingPro/api/bootstrap.php` — funcție nouă `import_motor_append_log()`
  (scrie în același fișier de log zilnic folosit de Python, cu format identic, ca liniile
  PHP și Python să apară intercalate cronologic).
- `app/Import/MatchingPro/tools/stage_cron_batch.php` — pentru fiecare produs procesat:
  - **fără match** → linie `✗ [furnizor] Nume (cod X) — FĂRĂ MATCH în TecDoc, nu e trimis în coadă`
  - **match + card incomplet** → linie `⚠ ... card incomplet, sărit`
  - **match + candidat vitrină/standard** → linie `✓ [furnizor] Nume (cod X) — MATCH exact/probable/conflict → standard/vitrină · CU/FĂRĂ imagine → coadă produse cu/fără imagine`
  - + linie de start „Migrare coadă import — N produs(e) de procesat din raport …”

**Fluxul narativ complet, așa cum apare acum în log (exemplu):**
```
[..] [INFO] Cron pornit — 3 fișier(e) de procesat
[..] [INFO] [1/3] Start autopartner/Lista_pret.csv
[..] [INFO] [1/3] Parsare CSV…
[..] [INFO] [1/3] Matching 20 produse…
[..] [INFO] [1/3] Match Lista_pret.csv: exact=18 probable=0 conflict=0 fără_match=2
[..] [INFO] Migrare coadă import — 20 produs(e) de procesat din raport …
[..] [OK]   ✓ [autopartner] Filtru ulei X (cod 123) — MATCH exact → standard · CU imagine → coadă produse cu imagine
[..] [WARN] ✗ [autopartner] Produs Y (cod 456) — FĂRĂ MATCH în TecDoc, nu e trimis în coadă
[..] [OK]   ✓ [autopartner] Bec Z (cod 789) — MATCH exact → vitrină (bec) · FĂRĂ imagine → coadă produse fără imagine
[..] [INFO] [1/3] Coadă import: +18 produse · match=18 · vitrină=4 · standard=14 · fără match=2 · fără imagine=3
```

**Cum s-a testat:** aceleași teste PHP izolate de la punctul A confirmă că
`import_motor_append_log()` scrie corect formatul `[ISO] [LEVEL] mesaj` (2/2 verificări OK
pe conținut și format). Logica narativă per-produs a fost verificată prin citire de cod
(fiecare ramură din `stage_cron_batch.php` — no-match / incomplet / candidat standard /
candidat vitrină — are acum o linie `import_motor_append_log()` corespunzătoare).

---

### D. Eliminare "fals gata" pe bara de progres

**Fișiere modificate:**
- `app/Import/MatchingPro/src/scanner.py` — ultima fază din `scan_file()` nu mai e
  `done`/100%, ci `scanned`/88% cu mesaj „…migrez în coada de import…” (fișierul e doar
  SCANAT, nu și migrat).
- `app/Import/MatchingPro/src/cron_watcher.py` — după ce `stage_report_batch()` (migrarea
  reală în `import_produse`) se termină, se apelează explicit `set_file_phase(..., "staged",
  100, "Fișier complet — migrat în coada de import")`.
- `app/Import/MatchingPro/index.php` — `CRON_PHASE_LABELS` are etichete noi pentru
  `scanned` și `staged`.

**De ce rezolvă problema:** înainte, faza `done`/100% apărea IMEDIAT după scanare+matching,
înainte ca produsele să fie efectiv migrate în coada de import — utilizatorul vedea
"Gata fișier X" cât timp, de fapt, se mai lucra pe fundal la migrare. Acum eticheta de
"gata" (fază `staged`) apare abia DUPĂ ce migrarea per-produs s-a terminat.

**Cum s-a testat:** script Python izolat (`_tmp_test_progress.py`, șters după test), cu
`STATE_DIR` temporar (nu a afectat `cron_progress.json` real):
- confirmat că faza după scanare e `scanned` (nu `done`), cu mesaj distinct de faza `staged`.
- confirmat că procentul e capat la 99% (comportament preexistent al formulei
  `min(99, …)` — 100% real vine mereu doar din `finish_cron()` la finalul întregului cron).
- confirmat că faza finală pe fișier (`staged`) apare abia după simularea migrării.
- **Rezultat: 10/10 verificări OK.**

---

### E. Notificare la finalizare cron

**Fișier modificat:** `app/Import/MatchingPro/index.php`:
- CSS: `.cron-toast-container` + `.cron-toast` (patru variante de culoare: ok/warn/error/info).
- JS: `requestCronNotifyPermission()` (cerută la click pe buton, gest de user, nu la load),
  `showCronToast()`, `notifyCronFinished()` (browser `Notification` API, cu fallback automat
  la toast intern dacă browserul/userul nu a permis notificări).
- Apelată din `pollCronProgress()` exact în momentul în care cronul trece din `running` în
  `done`/`error`, cu mesajul final (identic cu ce apare în bannerul de status).

**Cum s-a testat:** verificare statică a codului — funcțiile nu depind de stare externă,
folosesc doar `window.Notification` (cu try/catch pentru browsere fără suport) și DOM API
standard; linting fără erori. Punctele de apel (`notifyCronFinished(...)`) au fost verificate
să fie exact pe ambele ramuri de finalizare (cu produse procesate / fără fișiere de procesat).

> Popup-ul de permisiune al browserului și notificarea vizuală efectivă se văd doar în
> sesiune reală de browser — recomand userului să confirme „Permite notificări” la primul
> click pe „Testează cron”.

---

### F. Eliminare `stopCronAndClearCache` duplicată/moartă

**Fișier modificat:** `app/Import/MatchingPro/index.php` — ștearsă prima definiție
(shadowed, niciodată executată din cauza celei de-a doua definite mai jos în același scope).

**Cum s-a testat:** `grep` confirmă o singură definiție rămasă (linia ~4920), corect legată
de `$('cronStopBtn').addEventListener('click', stopCronAndClearCache)`. Sintaxă PHP/JS validă
(`php -l` OK, fără erori de linting).

---

## 5. Verificare finală

```
php -l api/bootstrap.php            → No syntax errors detected
php -l api/cron-start.php           → No syntax errors detected
php -l api/cron-progress.php        → No syntax errors detected
php -l tools/stage_cron_batch.php   → No syntax errors detected
php -l index.php                    → No syntax errors detected
py -m py_compile cron_watcher.py scanner.py progress.py → OK
ReadLints (toate fișierele modificate) → fără erori
```

Toate testele de backend (PHP + Python) au fost rulate izolat, cu backup/restaurare a
fișierelor de stare (`cron_progress.json`, `cron_staging_last.json`, `cron_control.json`)
și cu curățarea liniilor de test din logul zilnic — **datele reale din producție
(furnizori, fișiere, coada `import_produse`) nu au fost afectate.**

## 6. Ce rămâne de confirmat vizual (recomandare pentru user)

Codul e verificat static + prin teste izolate de backend, dar comportamentul 100% vizual
(animația pulse, popup-ul de notificare al browserului, aspectul toast-urilor) se
confirmă cel mai bine direct în `admin/import-pro`, apăsând „Testează cron (10 produse)”:
1. Ar trebui să vezi logul golit instant, cu „Se pornește rularea nouă…”.
2. Punctul verde ar trebui să pulseze vizibil.
3. În log ar trebui să apară linii per-produs (✓/✗/⚠) pe măsură ce rulează.
4. La final, un toast în colțul din dreapta sus + (dacă ai permis) o notificare de browser.

## 7. Bug critic găsit ULTERIOR de user, la primul test real (17.07.2026, 08:01)

După implementarea punctelor A-F, userul a testat live cu „Testează cron (10 produse)”
și a raportat un rezultat greșit: cronul s-a oprit după procesarea a UN SINGUR fișier
(1/5), citind exact 10 rânduri din CSV-ul Autonet — și, din întâmplare, niciunul din
cele 10 rânduri nu avea match TecDoc (toate „no_match”) — deci testul s-a terminat cu
**0 produse utile**, fără să caute în celelalte 4 fișiere.

**Cauza reală:** `total_limit` (setat de butonul „Testează cron”) era interpretat ca
„**10 RÂNDURI citite din CSV**”, nu „10 PRODUSE CU MATCH găsite” — exact opusul cerinței
userului. Codul din `cron_watcher.py::run_watcher()` decrementa limita cu
`found_here = summary['total']` (rânduri citite), nu cu produsele efectiv matched
(`exact + probable + conflict`), și se oprea la PRIMUL fișier, indiferent de rezultat.

**Fix aplicat** (`app/Import/MatchingPro/src/cron_watcher.py`):
- Redenumit conceptul intern: `match_target` = produse CU MATCH căutate (nu rânduri).
- Bucla de procesare a unui fișier a fost transformată într-un `while` cu **mai multe
  treceri** pe același fișier (folosind mecanismul existent de reluare pe offset CSV),
  continuând să citească rânduri suplimentare din același fișier până când:
  - fie s-au găsit `match_target` produse CU MATCH (agregat pe toate fișierele),
  - fie fișierul curent a fost citit complet (`partial == False`) → trece la fișierul următor,
  - fie s-a atins un prag de siguranță (`MAX_PASSES_PER_FILE = 40` treceri/fișier — evită
    citirea la infinit a unui fișier fără niciun match).
- Comportamentul cronului REAL (fără `total_limit`, deci fără testare) **nu s-a schimbat**
  — rămâne exact o trecere per fișier per rulare, ca înainte.
- Textele din UI (`index.php`, `cron-start.php`) au fost actualizate să spună explicit
  „caut N produse CU MATCH”, nu „N produse TOTAL”.

**Cum s-a testat:** script Python izolat cu `scan_file`/`collect_pending_files`/
`stage_report_batch` simulate (mock), fără bază de date reală și fără fișiere reale:
1. **Scenariu bug reprodus + reparat:** fișier cu 3 treceri fără match + a 4-a cu 10 match →
   confirmat că motorul face toate cele 4 treceri pe ACELAȘI fișier (40 rânduri citite)
   înainte să se declare „găsit", în loc să se oprească după prima trecere fără rezultat.
2. **Scenariu regresie cron normal:** fără `total_limit`, motorul face EXACT o trecere
   per fișier (2 fișiere → 2 apeluri), comportament identic cu cel dinainte de fix.
3. **Scenariu plasă de siguranță:** fișier care nu are NICIUN match, motorul se oprește
   exact la 40 de treceri (nu buclează infinit) și trece la fișierul următor.
   **Rezultat: 9/9 verificări OK**, fără a afecta fișierele/state-ul real (mock + `STATE_DIR`
   temporar, șters după test).

---

## 8. Concluzie (fără jargon tehnic)

Toate cele 6 probleme identificate în analiza inițială au fost reparate:
logul se golește la fiecare pornire, indicatorul verde pulsează cât cronul lucrează,
apar acum mesaje pe fiecare produs (găsit/nu găsit match, cu/fără imagine, în ce coadă a
fost pus), bara de progres nu mai anunță „gata” înainte ca migrarea reală să se termine,
și la final primești o notificare vizuală clară. Un cod mort (funcție duplicată) a fost
și el eliminat.

La primul test real, ai mai găsit o problemă importantă (necunoscută la momentul
analizei inițiale): butonul „Testează cron (10 produse)” se oprea după 10 RÂNDURI citite
din primul fișier, nu după 10 PRODUSE CU MATCH — dacă rândurile alese nu aveau match
(caz real întâlnit: Autonet, toate 10 fără match), testul se termina fără rezultat,
fără să mai caute în celelalte fișiere. Acum motorul continuă să caute — pe același
fișier (mai multe „pagini" de citire) și apoi pe următoarele fișiere — până găsește
efectiv 10 produse cu match sau epuizează toate fișierele disponibile, cu o plasă de
siguranță care evită blocarea pe un fișier fără niciun match.

Toate schimbările au fost testate izolat (13 + 10 + 9 verificări automate = 32 total),
fără a afecta datele reale din coada de import sau fișierele furnizorilor.
