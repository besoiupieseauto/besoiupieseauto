# Raport transfer besoiupieseauto.ro

Generat: 2026-07-23 14:25:29

## 1) Ce e pe GitHub (descarci pe PC nou)

- Repo: https://github.com/besoiupieseauto/besoiupieseauto
- Branch: `upload/snapshot-20260723`
- Commit: `192148f`
- ~5363 fisiere (cod aplicatie: admin, app, api, assets, modules, tools fara venv)
- Schema DB: `SQL/besoiupieseauto_schema.sql`

### Cum descarci pe PC nou
```powershell
git clone -b upload/snapshot-20260723 https://github.com/besoiupieseauto/besoiupieseauto.git
cd besoiupieseauto
```

## 2) Ce NU e pe GitHub

| Continut | Dimensiune aprox. | In arhiva MISSING? | Motiv |
|---|---:|---|---|
| Dump full MySQL besoiupieseauto.ro | ~1148 MB .gz | DA (database/) | limita GitHub 100MB |
| admin/storage/tecdoc | ~2905 MB | DA | CSV >100MB |
| admin/storage/ttc_image_library | ~234 MB | DA | sqlite mare |
| admin/storage/supplier_feeds | ~227 MB | DA | feed-uri pret |
| admin/vendor | ~9 MB | DA | composer vendor |
| admin/storage/ai_rag + ai_work | ~1 MB | DA | cache AI |
| admin/storage/imports | ~4267 MB | NU | cache mare; optional |
| app/Backend/storage/tecdoc|imports|feeds | ~duplicat | NU | aceleasi date ca admin |
| app/Import | ~427 MB | NU | optional |
| storage/scraper | ~745 MB | NU | profil browser regenerabil |
| tools/.../venv | ~120 MB | NU | recreezi cu venv |
| .env / rclone.conf | mic | NU in arhiva | secretes - vezi secrets_MANUAL/README.txt |

## 3) Arhiva

- Fisier: `C:\laragon\backup\besoiupieseauto_ro\besoiupieseauto_MISSING_20260723_142518.7z`
- Contine: database/ + project_extras/ + secrets_MANUAL/README.txt + TRANSFER_REPORT.md

## 4) Pasi pe PC-ul nou

1. `git clone -b upload/snapshot-20260723 ...`
2. Extrage arhiva; copiaza `project_extras\admin\...` peste `admin\...` din proiect
3. Copiaza manual `.env` si `rclone.conf` (vezi secrets_MANUAL/README.txt)
4. Restore DB din `database\besoiupieseauto_ro_full_20260723_141701.sql.gz`
5. Optional: daca ai nevoie de cache imports (~4GB), copiaza de pe PC vechi `admin\storage\imports`

## 5) Checklist

- [ ] git clone branch
- [ ] extrage arhiva MISSING peste proiect
- [ ] pune .env + rclone.conf
- [ ] restore MySQL dump
- [ ] testeaza site/admin