# Modul product_formation

Editor reguli **formare carte produs**: titlu dinamic per canal (site, PieseAuto, OEM) și tab-uri descriere pe pagina produs.

## URL

- Pagină: `/admin/product-formation` (alias `/admin/produse-formare`)
- API: `/admin/api/product_card_formation_endpoint.php`

## Dependențe

- Modul `produse` (activ)
- Servicii CORE: `ProductCardFormationService`, `ProductDescriptionTabsService`, `ProductProseDescriptionService`, `ProductTabChartService`
- Config JSON: `app/Backend/storage/config/product_card_formation.json`

## Puncte de conectare

1. `module.json` — manifest
2. `admin/storage/modules/state.json` — `"product_formation": true`
3. `app/Backend/config/optional_modules.php` — slugs + API
4. `admin/Templates/admin/pages/product-formation/product-formation.php` — shim UI
5. `admin/public/api/product_card_formation_endpoint.php` — shim API
6. `app/Backend/config/api_workspace_features.php` — permisiune `module.product_formation`
7. `app/Backend/src/Core/AdminPageResolver.php` — override slug

## Storefront

Tab-urile produsului sunt randate via `app/Legacy/product-tabs.php` (consumă serviciile CORE).
