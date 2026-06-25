# SCRAPER — context proiect (besoiupieseauto_ro)

> Fișier central — **toate task-urile Scraper** citesc de aici. Nu repeta URL/curl în fiecare task.

## URL sursă (Besoiu local — task-uri vechi)
https://besoiupieseauto.ro/catalog

## ePiesa (Scraper admin)
- **Intrare:** `/admin/scraper` — carduri per site (ePiesa, eMAG, Autodoc, PieseAuto, Autovit, TecDoc)
- **Configurează:** pași 1 fetch → 2 parse listă → 3 follow links + testare per sursă
- Config salvat: `storage/scraper/sources/{id}.json`
- Registru carduri: `config/scraper-sources-registry.php`
- Fetch: `SCRAPE_DO_TOKEN` în `admin/.env`
- API: `scraper_endpoint.php` — `view=sources|source`, `source_save`, `source_test`
- JSON: `storage/scraper/json/epiesa_latest.json`
- HTML raw: `storage/scraper/raw/epiesa_*.html`
- CLI: `php tools/scraper/epiesa_category.php`

## Comandă curl (fetch manual)
```bash
curl -sL test
```

## Foldere
- `lib/Scraper/` — cod parser + HTTP
- `storage/scraper/raw/` — HTML descărcat
- `storage/scraper/samples/` — HTML fix pentru teste
- `storage/scraper/logs/` — erori runtime

## Flux (3 faze)
1. **Pregătire** — fetch + raw HTML
2. **Construiește** — parsere câmp cu câmp
3. **Verifică** — staging DB + insert test

## Interzis (global)
- NU modifica checkout, homepage public, layout admin existent
- NU importa masiv în produse live până staging e OK
- NU refactoriza tot scraper-ul într-un singur task

## Sample de lucru
După task 3: `storage/scraper/raw/page_001.html`  
După task 4: `storage/scraper/samples/product_001.html`

## Notițe operator
(adaugă aici logica ta, selectori, particularități site)
