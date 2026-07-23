# Taxonomie evenimente — AI Intelligence

Documentație pentru modulul `ai-intelligence`: ce evenimente colectăm, cum arată `metadata`, și cum ajung la AI (doar agregate, nu evenimente brute).

## Mapare tabele (MySQL)

| Concept (spec)   | Tabel fizic MySQL      | Rol |
|------------------|------------------------|-----|
| `sessions`       | `ai_intel_sessions`    | Sesiune vizitator (anonim sau autentificat) |
| `events`         | `ai_intel_events`      | Evenimente individuale (buffer → worker) |
| `event_aggregates` | `ai_intel_aggregates` | Semnale zilnice per entitate (CTR, views) |

Migrare: `modules/ai-intelligence/migrations/001_events_tracking.sql`

## Reguli arhitectură

1. **Orchestratorul / re-rankerul / RAG** citesc doar `ai_intel_aggregates` + date produse — **nu** interoghează `ai_intel_events` direct în prompt.
2. **Ollama** = inferență (clasificare, embeddings). Nu stochează comportament utilizator.
3. Evenimentele trec prin **coadă** (Redis Streams) → worker batch → `ai_intel_events` → job orar → `ai_intel_aggregates`.

## Tipuri de evenimente (`event_type`)

### `page_view`

Vizualizare pagină (home, categorie, CMS, checkout).

| Câmp | Valoare |
|------|---------|
| `entity_type` | `page` |
| `entity_id` | slug sau path normalizat (ex. `/catalog/ulei-motor`) |

```json
{
  "path": "/catalog/ulei-motor",
  "title": "Ulei motor — Besoiu Piese Auto",
  "referrer": "https://google.com/",
  "device": "desktop"
}
```

### `search_query`

Utilizatorul a trimis o căutare (site search, VIN, OEM).

| Câmp | Valoare |
|------|---------|
| `entity_type` | `search` |
| `entity_id` | hash scurt al query-ului normalizat |

```json
{
  "query": "filtru ulei zollex",
  "query_normalized": "filtru ulei zollex",
  "results_count": 12,
  "source": "header_search",
  "filters": { "brand": "ZOLLEX" }
}
```

### `search_result_click`

Click pe un rezultat din listă după căutare.

| Câmp | Valoare |
|------|---------|
| `entity_type` | `product` |
| `entity_id` | ID produs (string) |

```json
{
  "query": "filtru ulei zollex",
  "position": 3,
  "result_id": "18452",
  "sku": "T-522Z"
}
```

### `product_view`

Pagină produs deschisă (ficha detaliu).

| Câmp | Valoare |
|------|---------|
| `entity_type` | `product` |
| `entity_id` | ID produs |

```json
{
  "sku": "T-522Z",
  "oem": "1234567890",
  "category_id": "42",
  "price_ron": 45.90,
  "in_stock": true,
  "source": "search" 
}
```

### `add_to_cart`

Produs adăugat în coș.

| Câmp | Valoare |
|------|---------|
| `entity_type` | `product` |
| `entity_id` | ID produs |

```json
{
  "sku": "T-522Z",
  "qty": 2,
  "unit_price_ron": 45.90,
  "cart_total_items": 3
}
```

### `purchase`

Comandă finalizată (conversion).

| Câmp | Valoare |
|------|---------|
| `entity_type` | `order` |
| `entity_id` | ID comandă |

```json
{
  "order_id": "9912",
  "total_ron": 189.50,
  "item_count": 4,
  "product_ids": ["18452", "18453"],
  "payment": "ramburs"
}
```

Pentru agregare pe produs, job-ul orar explodează `product_ids` în linii `entity_type=product`.

### `recommendation_click`

Click pe recomandare (upsell, „produse similare”, widget AI).

| Câmp | Valoare |
|------|---------|
| `entity_type` | `product` |
| `entity_id` | ID produs recomandat |

```json
{
  "recommendation_slot": "product_similar",
  "anchor_product_id": "18452",
  "position": 1,
  "algorithm": "hybrid_rag_v1"
}
```

### `image_check_result`

Rezultat verificare imagine (admin sau pipeline automat).

| Câmp | Valoare |
|------|---------|
| `entity_type` | `product` |
| `entity_id` | ID produs |

```json
{
  "check_type": "ollama_vision",
  "passed": false,
  "issues": ["watermark", "low_resolution"],
  "confidence": 0.87
}
```

### `filter_applied`

Filtru aplicat în catalog (marcă, categorie, preț).

| Câmp | Valoare |
|------|---------|
| `entity_type` | `category` sau `page` |
| `entity_id` | ID categorie sau path catalog |

```json
{
  "filters": {
    "brand": "MANN",
    "price_min": 20,
    "price_max": 100
  },
  "results_count": 48
}
```

## Mapare tracker vechi → tipuri noi

Tracker-ul existent (`assets/js/besoiu-client-tracker.js`) folosește `action` în loc de `event_type`:

| Tracker vechi (`action`) | `event_type` nou |
|--------------------------|------------------|
| `page_view` | `page_view` |
| `search` | `search_query` |
| `product_view` | `product_view` |
| `product_click` | `search_result_click` sau `recommendation_click` (după context) |
| `add_to_cart` | `add_to_cart` |
| `cart_view` | `page_view` (`entity_type=page`, checkout/cart) |
| `click` | ignorat sau mapat contextual |
| `scroll`, `time_on_page`, `idle_return` | **nu** persistate în AI (doar analytics opțional) |

## Agregate (`ai_intel_aggregates`)

Recalculate orar din `ai_intel_events`:

| Coloană | Sursă evenimente |
|---------|------------------|
| `views` | `page_view`, `product_view` pe entitate |
| `clicks` | `search_result_click`, `recommendation_click` |
| `purchases` | `purchase` (per produs din metadata) |
| `ctr` | `clicks / views` când `views > 0` |

**Re-ranker search:** combină scor hybrid (keyword + embedding) cu `ctr` și `purchases` normalizate — fără acces la rânduri individuale din `ai_intel_events`.

## Payload API (`POST /api/events` — Pas 3)

```json
{
  "session_id": "550e8400-e29b-41d4-a716-446655440000",
  "user_id": null,
  "source": "web",
  "events": [
    {
      "event_type": "search_query",
      "entity_type": "search",
      "entity_id": "q_filtru_ulei",
      "metadata": { "query": "filtru ulei", "results_count": 8 },
      "ts": "2026-07-19T08:15:00.000Z"
    }
  ]
}
```

Validare: `event_type` ∈ lista din `EventTrackingStore::ALLOWED_EVENT_TYPES`.

## Sesiuni

- `session_id`: UUID v4 (recomandat) sau string compatibil tracker vechi (max 36 caractere în CHAR(36)).
- `user_id`: opțional — `idclienti` ca string când e autentificat; NULL pentru vizitatori anonimi.
- `source`: `web` | `mobile` | `admin` (default `web`).

## Coexistență MySQL (fără conflict cu ce ai instalat)

Toate tabelele noi sunt în baza **`DB_NAME`** (ex. `besoiupieseauto.ro`), cu prefix **`ai_intel_*`**.

| Tabel | Conflict cu existent? | Notă |
|-------|----------------------|------|
| `ai_intel_sessions` | Nu | Nu există tabel generic `sessions` |
| `ai_intel_events` | Nu | Separat de `search_logs`, `ai_interaction_logs` |
| `ai_intel_aggregates` | Nu | Doar semnale zilnice pentru re-ranker |

**Paralel (nu se suprascriu):**

| Sistem vechi | Unde | Status după consolidare |
|--------------|------|-------------------------|
| `client_behavior_endpoint.php` | JSONL pe disc | **Redirect** → aceeași coadă ca `/api/events` |
| `search_logs` | MySQL | Rămâne — căutări VIN/OEM TecDoc |
| `ai_product_embeddings` | MySQL | Rămâne — RAG produse (Pas 5+) |
| Coadă evenimente | Redis / fișier | **Nu** e tabel MySQL |

Endpoint-uri active tracking: **`POST /api/events`** (preferat) și **`POST /api/client_behavior_endpoint.php`** (compat, același ingest).
