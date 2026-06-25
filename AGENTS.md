# Agenți proiect — besoiupieseauto.ro

## Auditor Imagini Produse (`image-audit`)

**Scop:** depistează și analizează imaginile produselor; verifică dacă corespund titlului și descrierii.

| Resursă | Locație |
|---------|---------|
| Skill Cursor | `.cursor/skills/product-image-audit/SKILL.md` |
| Regulă agent | `.cursor/rules/agent-image-audit.mdc` |
| Serviciu PHP | `admin/src/Services/ProductImageAuditService.php` |
| CLI | `admin/tools/audit_product_images.php` |
| Admin UI | `/admin/product` — buton **Audit imagini** + modal rezultate |
| Edit produs | `/admin/editproduse?id=` — **Verifică imaginea (AI)** |
| Loturi / rapoarte | `admin/storage/image_audit/` |

### Rulare în Composer 2.5 (analiză vizibilă)

Composer poate **vedea imaginile** și explica pas cu pas ce analizează.

```bash
# 1. Pregătește 10 produse
php admin/tools/audit_product_images.php --prepare --limit=10

# 2. În Cursor Composer 2.5:
#    @product-image-audit Analizează ultimul batch și scrie raportul
```

Filtre opționale:

```bash
php admin/tools/audit_product_images.php --prepare --vitrina --limit=8
php admin/tools/audit_product_images.php --prepare --category="Frâne" --limit=20
php admin/tools/audit_product_images.php --prepare --id=RANDOMN_ID_HEX
```

### Rulare în admin (fără OpenAI)

1. `/admin/product` → selectează produse → **Audit imagini (Cursor)**
2. Modal: **Copiază prompt Cursor**
3. Cursor Composer 2.5 + `@product-image-audit` → analizează vizual
4. Revino în admin → **Încarcă rezultate Cursor**

### Rulare automată OpenAI (opțional, doar CLI)

```bash
IMAGE_AUDIT_ENGINE=openai php admin/tools/audit_product_images.php --analyze --limit=10
```

### Verdicturi

- **match** — imagine corectă pentru titlu
- **partial** — familie corectă, dar ambiguu
- **mismatch** — imagine greșită (alt produs, logo, generic)
- **review** — de verificat manual

Rapoartele apar în `admin/storage/image_audit/reports/report_*.md`.
