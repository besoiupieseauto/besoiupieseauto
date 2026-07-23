# Șablon modul admin (gol)

Copiază manual sau generează automat cu:

```bash
php F:\laragon\www\besoiupieseimport\module_generator\bin\generate.php --id=exemplu --name="Modul Exemplu"
```

## AI / Ollama (inclus în șablon)

Modulele noi folosesc **același Ollama** ca admin, import și scraper:

| Fișier | Rol |
|--------|-----|
| `src/Service/__STUDLY__AiService.php` | `ollamaStatus()` + `complete()` |
| `Besoiu\Services\ModuleOllamaSupport` | Helper global în Backend |
| API `ollama_status` | Badge verde/galben în pagină |
| API `ai_complete` | Prompt → răspuns Ollama |

Config: `app/Config/.env` → `OLLAMA_MODEL=qwen2.5:7b`, `OLLAMA_VISION_MODEL=llava:7b`

Verificare stack:
```bash
php app/Import/tools/verify_ollama_stack.php
```

## Placeholder-e în fișiere

| Token | Exemplu | Unde |
|-------|---------|------|
| `__MODULE_ID__` | `inventar` | id modul, tabele, handler |
| `__STUDLY__` | `Inventar` | namespace PHP, clase |
| `__MODULE_NAME__` | `Inventar Stoc` | UI, manifest |
| `__URL_SLUG__` | `inventar` | URL `/admin/inventar` |
| `__WORKSPACE__` | `orders` | workspace admin |
| `__NAV_GROUP__` | `Comenzi` | grup meniu |
| `__NAV_ICON__` | `puzzle-piece` | icon Lucide |

## Structură

```
_template/
├── module.json
├── CONNECT.md          ← checklist 7 puncte
├── src/                ← logică PHP
├── pages/              ← UI real
├── api/                ← endpoint modul (opțional, alternativ la proxy admin)
├── migrations/         ← SQL
├── assets/             ← CSS/JS
└── shim/               ← fișiere de copiat în admin/ (generator le pune automat)
```

**Nu copia folderul `_template` direct** — folosește generatorul care înlocuiește token-urile și conectează cele 7 puncte.
