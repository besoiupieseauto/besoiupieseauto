# Audit profund — /admin/planner (Plan eficiență financiară)

**Data:** 2026-07-13
**Scop:** analiză completă a fiecărei secțiuni și funcții + 50 de erori/probleme identificate.
**Verdict general:** pagina **NU e „complet moartă"** — se randează și calculează corect (screenshot-urile arată `PROFIT 11.885.586,60 RON/trimestru`, preț mediu catalog 148,9 RON etc.). Simptomul „nu merge nimic" vine cel mai probabil din **#48 (cache JS vechi după deploy)**, **#49 (o excepție JS la boot îngheață tot)** și dintr-o serie de **desincronizări/UX** care fac interacțiunile să pară stricate (presete, slidere, sync preț).

## Fișiere analizate

| Rol | Cale |
|-----|------|
| Pagină (hero + include) | `admin/Templates/admin/pages/planner/planner.php` |
| Aplicația planner (HTML) | `admin/Templates/admin/pages/_partials/marketing_planner_app.php` |
| Logica JS | `admin/public/assets/js/admin-marketing-planner.js` |
| Stiluri | `admin/public/assets/css/admin-marketing-planner.css` |
| Injectare CSS/JS + rutare | `admin/src/Controllers/Templates.php` (`isPlannerPage`, linii ~115, ~158, ~620) |
| Rezolvare template | `admin/src/Core/AdminPageResolver.php` |
| Endpoint preț mediu | `admin/public/api/dashboard_endpoint.php` + `DashboardService::catalogPriceStats` |
| Duplicare rută modul | `admin/modules/Marketing/pages/planner.php`, `admin/config/optional_modules.php` |

---

## Fluxul real (cum funcționează azi)

1. `/admin/planner` → `Templates.php::isPlannerPage()` = true → injectează `admin-marketing-planner.css` + `.js`.
2. HTML randat server-side (rânduri implicite din `$__bwsPlannerDefaults`).
3. La `DOMContentLoaded`, JS: `readPlannerState()` (localStorage `bws_marketing_planner_v3`) → `renderAllSections()` (re-randează TOT) → `updateResults()`.
4. `loadCatalogPricing()` face `POST /admin/api/dashboard_endpoint.php` → citește `data.products.pricing.avg`.
5. Fiecare tastă/slider → `persistAndRecalc()` → salvează în localStorage + recalcul complet.

Persistența e **exclusiv localStorage**. Nu există salvare pe server.

---

## 50 de probleme identificate

### A. Logica de calcul & scenariu (JS)

1. **Slider „Utilizatori" plafonat la 100.000, inputul text nelimitat.** `syncSlidersFromState` (JS ~179) face `Math.min(100000, …)`. Valori mari (938.000 în screenshot) rămân în input dar sliderul se blochează la capăt → pare stricat.
2. **Slider „Conversie" plafonat la 20%, inputul acceptă până la 100%.** `getScenarioCalc` (JS ~512) permite până la 100, sliderul (`max="20"`) se blochează → desincronizare vizuală.
3. **Conversie minimă forțată 0,1%.** `Math.max(0.1, …)` (JS ~512) — imposibil de modelat un scenariu cu 0% conversie; suprascriere silențioasă, fără feedback.
4. **Utilizatori minim forțat 1.** `Math.max(1, …)` (JS ~511) — nu poți modela 0 utilizatori.
5. **Presetele se „canibalizează".** Butonul „Salvează" scrie **starea curentă** peste eticheta built-in. În screenshot toate 3 (Pesimist/Realist/Optimist) arată identic `938.000 util · 3% · salvat` → presetele devin inutile și derutante. (`savePreset` ~113, `updatePresetMeta` ~157)
6. **Nu există ștergere/resetare preset.** Odată salvat, singura revenire la built-in e ștergerea manuală a cheii `bws_marketing_planner_presets_v1` din localStorage.
7. **„Reset" nu curăță presetele.** `resetPlannerDefaults` (JS ~206) resetează doar planul; dacă presetele sunt corupte/greșite, reset-ul nu ajută.
8. **„Încarcă Pesimist/Realist/Optimist" nu dă un scenariu complet.** `applyBuiltinScenario` (JS ~127) schimbă doar `users`, `conversion` și eventual `price`; cheltuielile/veniturile/investițiile rămân cele curente → rezultatul nu corespunde etichetei.
9. **Presete fără versionare/migrare.** `deepCloneState` presupune array-uri v3; un preset salvat cu structură veche poate produce erori sau pierderi tăcute de date la o viitoare schimbare de schemă.
10. **`parsePlannerNumber` confundă zecimalele cu grupele de mii.** „1.234" → 1234, deci un preț zecimal scris cu punct și 3 zecimale devine mii (JS ~470-474). Ambiguitate periculoasă pentru valori monetare.
11. **Recalcul complet la fiecare tastă.** `input` pe tot panoul → `persistAndRecalc` → `updateResults` (inclusiv tabel strategie + sincronizare slidere). Cost inutil / posibilă parpaială pe liste lungi. (JS ~910)
12. **Editarea etichetei declanșează recalcul financiar.** Label-ul nu afectează calculul, dar `handlePlannerFieldChange` (JS ~901) recalculează tot.
13. **Sliderul „sare".** `syncSlidersFromState` e chemat în `updateResults` la fiecare recalc; dacă tragi sliderul în timp ce alt câmp schimbă starea, poziția poate sări.
14. **Pierdere silențioasă de date la atingerea sliderului.** `applySliderValues` (JS ~196) scrie `usersSlider.value` (max 100.000) în inputul target_users → dacă aveai 938.000 și atingi sliderul, valoarea cade brusc la 100.000.
15. **Dublă legare de evenimente.** `panel.addEventListener('input', …)` **și** `('change', …)` (JS ~910-911) apelează aceeași funcție → pe slidere `applySliderValues` rulează de două ori (recalcul dublu).
16. **Rândurile scenariu adăugate manual pierd constrângerile.** `buildRowElement` (JS ~708) nu setează `step/min/max` pe input, deși `collectStateFromDom` le citește → constrângerile dispar la re-render.
17. **„+ Adaugă parametru" în scenariu creează un rând inert.** Rândul nou nu are `calcKey`, deci nu intră în nicio formulă (`addRow` ~853); e pur decorativ, fără avertisment.
18. **Unitate neclară pentru parametru scenariu nou.** `getRowUnit` fallback → „valoare" (JS ~72).
19. **Butonul „×" pare stricat pe secțiuni cu un singur rând.** `deleteRow` blochează silențios ștergerea ultimului rând (`if rows.length<=1 return`, JS ~875) fără mesaj.
20. **Ștergere dependentă de structura DOM.** `deleteRow` deduce secțiunea din `closest('[data-planner-stage]')` (JS ~869); orice schimbare de markup transformă butonul în no-op silențios.

### B. Endpoint & date catalog

21. **Parametrul `type_product: 'overview'` e mort.** `dashboard_endpoint.php` îl ignoră complet și întoarce tot snapshot-ul; codul JS sugerează o filtrare inexistentă.
22. **Prețul mediu se sincronizează în input DOAR fără stare salvată.** `applyCatalogPricing` (JS ~958) actualizează inputul avg_price numai dacă `!hasSaved`. Cu localStorage existent, hero arată „Catalog sincronizat · medie 148,9" dar prețul folosit efectiv în calcul poate fi altul → discrepanță.
23. **Butonul inline „Preț mediu din catalog" nu face nimic dacă avg=0.** (JS ~737) fără feedback la eșec endpoint.
24. **Eșecul endpoint-ului e complet tăcut.** `loadCatalogPricing` prinde eroarea și lasă 141,23 default; mesajul hero „Se sincronizează prețul mediu din catalog…" poate rămâne pe ecran → fals „loading" infinit.
25. **AVG SQL fragil la separatori de mii.** `catalogPriceStats` face doar `REPLACE(pPrice, ',', '.')`; prețuri „1.234,56" produc AVG greșit (dep. formatul din BD). (`DashboardService` ~116)
26. **„Preț mediu live" nu e live.** Vine din snapshot cache (TTL 300s); poate fi vechi dacă cronul nu rulează.
27. **Hero rămâne pe „Se sincronizează…" dacă avg=0.** `applyCatalogPricing` actualizează mesajul hero doar când `avg>0`.

### C. HTML / structură

28. **Dublă randare.** Rândurile sunt generate server-side (`bws_planner_default_rows_html`), apoi JS le aruncă imediat prin `renderAllSections` la boot → efort dublu și FOUC posibil.
29. **Sliderele au valori hardcodate în HTML** (`value="1000"`, „1.000", „5%") care nu reflectă starea salvată până rulează JS → la refresh vezi o clipă 1.000/5% chiar dacă ai salvat altceva.
30. **Meta presete hardcodat în HTML** („500 util · 2%" etc.); dacă JS pică, rămân valori false pe ecran.
31. **„Descarcă Excel" apare de 3 ori** (hero, head, docs) — redundant.
32. **ID-uri aproape identice ușor de confundat:** `bws-planner-catalog-meta` (partial) vs `bpa-planner-catalog-meta` (hero) — risc de mentenanță/setare greșită.
33. **Navigare inconsistentă pe mobil.** Secțiunile 5/6 (Rezultat/Recuperare) sunt `<div>` în coloana dreaptă, dar nav-ul le tratează ca pași liniari; pe o singură coloană ordinea vizuală nu corespunde ordinii linkurilor.
34. **Tabelul strategie fără accesibilitate.** Fără `<caption>`/aria; starea goală doar cu `colspan="6"`.

### D. Accesibilitate / UX

35. **Butonul „×" blocat nu are `disabled`/`aria-disabled`** pe ultimul rând → cititoarele de ecran anunță buton activ care nu face nimic.
36. **Sliderele fără `aria-label`/`aria-valuetext`** cu unitate → screen reader citește doar numărul brut.
37. **`window.confirm` (Reset) și `window.alert` (popup blocat)** — dialoguri native urâte, în afara stilului aplicației.
38. **Fără feedback la „Salvează/Încarcă" preset** — utilizatorul nu știe dacă salvarea a reușit.
39. **Semnalizare doar prin culoare** la pill-uri și delta (verde/roșu) — atenuat de text („DA/NU"), dar tot un risc pentru daltoniști.
40. **Valori lungi pe mobil** (11.939.136,60 RON) în bara formulă/compare — fără wrap explicit, risc de depășire.

### E. Securitate / robustețe

41. **`escapeHtml` incomplet.** Escapează doar `& < > "`, nu și apostroful; folosit apoi cu `document.write` în export PDF (JS ~301, ~376) — practică fragilă (risc XSS scăzut, doar date proprii).
42. **`document.write` + `window.open` deprecate.** Poate fi blocat de browser/CSP; fără fallback dacă `win.print()` eșuează (JS ~370-381).
43. **Persistență DOAR în localStorage.** Schimbare browser/device sau curățare cache = pierderea tuturor scenariilor și presetelor. Lacună majoră pentru un instrument financiar de business.
44. **Fără validare de plauzibilitate.** Se poate genera „profit" de 11,8M RON/trimestru cu 938.000 utilizatori, fără niciun avertisment.
45. **Input invalid → 0 tăcut.** `parsePlannerNumber` întoarce 0 pentru text invalid, fără marcaj vizual pe câmpul greșit → utilizatorul crede că valoarea e validă.

### F. Integrare / arhitectură (cauze plauzibile pentru „nu merge nimic")

46. **Pagină servită pe DOUĂ căi.** Legacy `planner/planner.php` și modulul `modules/Marketing/pages/planner.php` (care doar `require` legacy). Comportament diferit după cum modulul Marketing e activ/inactiv → greu de depanat.
47. **`isPlannerPage()` bazat pe string-matching fragil** (`planner/planner`, `modules/marketing/pages/planner`, `/admin/planner`). Orice redenumire rupe injectarea CSS/JS → pagină fără stiluri/script = „nu merge nimic" clasic. (`Templates.php` ~620)
48. **Versiuni de cache hardcodate manual** (`?v=20260713-planner-fix2`). Dacă se uită la un deploy, utilizatorii primesc JS vechi din cache — **cauza clasică a „a mers și acum nu mai merge"**.
49. **Nicio stare de eroare vizibilă.** Dacă JS aruncă o excepție la boot (ex. preset corupt din localStorage), tot planificatorul rămâne înghețat cu „—" peste tot, fără mesaj — exact simptomul „nu merge nimic". Nu există `try/catch` global la `boot()`.
50. **Ținte strategice (7) și Buget Ads (8) sunt HARDCODATE.** `STRATEGIC_MILESTONES` (JS ~21) și cardurile Faza 1/2/3 (HTML) nu vin din Marketing Hub → secțiunile 7 și 8 nu „fac" nimic dinamic în afară de comparația de venit; par interactive, dar sunt statice.

---

## Top 5 recomandări prioritare

1. **Elimină cache-busting manual** — folosește `filemtime()` (există deja `publicAssetVersion()` în `Templates.php`, dar planner-ul are versiune fixă). Rezolvă #48.
2. **Adaugă `try/catch` global la `boot()` + banner de eroare vizibil** și `try/catch` la citirea localStorage/presetelor. Rezolvă #49, #9.
3. **Reproiectează presetele:** built-in read-only + presete salvate separat, cu buton de ștergere și confirmare/toast. Rezolvă #5, #6, #7, #8, #38.
4. **Aliniază sliderele cu inputurile** (aceleași min/max, sau lasă sliderul să urmeze inputul, fără clamp distructiv). Rezolvă #1, #2, #14.
5. **Persistență pe server** (endpoint dedicat pt. planuri/scenarii) în locul localStorage exclusiv. Rezolvă #43.

---

## Concluzie onestă

Codul planner-ului este, în esență, **funcțional și corect matematic**. Percepția „nu merge nimic" e generată de:
- posibil **JS vechi din cache** după un deploy (#48),
- o eventuală **excepție la boot care îngheață tot fără mesaj** (#49),
- **desincronizări slider↔input** și **presete care se suprascriu** (#1, #2, #5, #14), care fac interacțiunile să pară stricate,
- **feedback zero** la erori/salvări (#24, #27, #38, #49).

Niciuna dintre cele 50 nu e o eroare de sintaxă care blochează întreaga pagină; sunt bug-uri logice, desincronizări, lacune de robustețe și UX. Prioritizează Top 5 pentru impact maxim.
