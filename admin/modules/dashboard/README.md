# Modul Dashboard

Tabloul de bord per zonă de lucru (workspace). Toată logica și layout-ul stau aici.

## Structură

```
modules/dashboard/
├── module.json                 # manifest (type: core, enabled)
├── src/
│   ├── DashboardModule.php      # entry class (boot minimal, fără hook-uri)
│   └── DashboardCatalog.php     # LOGICĂ: ce panouri + quick-actions apar per zonă
└── pages/
    ├── dashboard.php            # LAYOUT: pagina randată la /admin/dashboard
    └── _partials/
        ├── dashboard_workspace_deck.php   # metrici, grafice, tabele per zonă (+ bloc Furnizori)
        ├── dashboard_company_tabs.php     # tablou Company Settings (gol implicit)
        └── dashboard_marketing_pulse.php  # pulse marketing (statistici + linkuri)
```

## Rută

`/admin/dashboard` e mapat pe `admin/Templates/admin/pages/homepages.php`, care e un **shim** de 1 linie:

```php
require dirname(__DIR__, 4) . '/modules/dashboard/pages/dashboard.php';
```

Vechile partiale din `admin/Templates/admin/pages/_partials/dashboard_*.php` sunt tot shim-uri către acest modul (sursă unică).

## Logică panouri (DashboardCatalog)

`DashboardCatalog::panelsFor($workspace)` decide ce se afișează:

| Zonă | Panouri |
|------|---------|
| orders | hero, workspace-deck, furnizori-section, quick-actions |
| suppliers / ai / social / shop | hero, workspace-deck, quick-actions |
| marketing | hero, quick-actions |
| company | (gol — reconstruit manual) |

## Assets (rămân în admin/public, încărcate de `Templates.php` pentru pagina homepages)

- JS date live: `admin/public/assets/js/admin-workspace-dashboard.js`, `admin-company-dashboard.js`
- CSS: `admin/public/assets/css/admin-workspace-dashboard.css`, `admin-company-dashboard.css`
- Snapshot cache (randare instant): `admin/storage/cache/dashboard_snapshot.json`

## Activare / dezactivare

`admin/storage/modules/state.json` → `"dashboard": true`. Fiind `type: core`, e mereu pornit.
