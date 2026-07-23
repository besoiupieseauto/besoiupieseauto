# Navigație admin — cum se construiește

Documentație pentru sidebar-ul admin Besoiu: surse de date, flux de randare, workspace-uri și editor.

## Sursă unică de adevăr

| Fișier | Rol |
|--------|-----|
| `app/Backend/storage/navigation/registry.json` | Meniul efectiv (secțiuni + linkuri) |
| `admin/storage/navigation/registry.json` | Junction către același fișier |

Schema: `besoiu_nav_registry_v1`.

**Stare curentă:** registry gol (`"sections": []`) — sidebar-ul rămâne gol până adaugi secțiuni manual sau prin sync.

---

## Flux: de la date la HTML

```
registry.json
    ↓
AdminNavJsonRegistry (app/Backend/src/Services/AdminNavJsonRegistry.php)
    ↓ ensureBootstrapped() → buildTree()
nav.php → nav_renderer.php (admin/Templates/admin/static_elements/)
    ↓
Sidebar HTML (linkuri + iconițe Lucide)
```

1. Fiecare pagină admin încarcă `nav.php`.
2. `AdminNavRegistryService` normalizează registry-ul (`ensureBootstrapped`).
3. `listTreeForRender()` filtrează pe workspace, module active, preferințe user.
4. `nav_renderer.php` generează `<li>` / linkuri.

Dacă registry-ul nu e gata, se folosește fallback `nav_legacy.php`.

---

## Cele 3 moduri de populare

| # | Sursă | Fișier / UI | Când |
|---|-------|-------------|------|
| 1 | **Manual** | `/admin/nav` | Construiești structura tu (recomandat acum) |
| 2 | **Seed CORE** | `admin/config/admin_nav_seed.php` | La primul bootstrap dacă registry e gol |
| 3 | **Dinamic** | `modules/*/module.json` → `nav[]` | La „Resync” sau activare modul |

### 1. Manual — editor `/admin/nav`

- **Modul:** `modules/nav/`
- **API:** `admin/public/api/nav_registry_endpoint.php`
- **Permisiuni:** `super_ambassador`, `admin`, sau drept gestionare utilizatori

Acțiuni API (`POST`, câmp `action`):

| Action | Descriere |
|--------|-----------|
| `list` | Snapshot complet registry + zone |
| `add_section` | Secțiune nouă |
| `add_item` | Link nou într-o secțiune |
| `save_section` | Actualizează secțiune (label, workspace, sort…) |
| `save_item` | Actualizează link (label, url, icon…) |
| `delete_item` | Șterge link |
| `sync` | Resincronizează din toate `module.json` |
| `save_raw` | Salvează JSON brut |
| `reset_module` | Resetează secțiunea unui modul la manifest |

### 2. Seed CORE

`admin/config/admin_nav_seed.php` returnează o listă de secțiuni CORE. La bootstrap (registry lipsă sau `sections: []`), conținutul seed se scrie în `registry.json`.

**Acum:** seed gol (`return []`).

Exemplu structură seed (când vrei meniu de bază):

```php
return [
    [
        'section_key' => 'core_comenzi',
        'label' => 'Comenzi',
        'group_label' => 'COMENZI',
        'workspace' => 'orders',
        'sort_order' => 10,
        'items' => [
            [
                'item_key' => 'orders',
                'label' => 'Comenzi',
                'url' => '/admin/orders',
                'icon' => 'shopping-cart',
                'sort_order' => 10,
            ],
        ],
    ],
];
```

### 3. Dinamic din `module.json`

În manifestul modulului (`modules/furnizori/module.json`):

```json
"workspace": "orders",
"nav": [
    {
        "id": "furnizori",
        "label": "Furnizori",
        "href": "/admin/furnizori",
        "icon": "truck",
        "group": "COMENZI"
    }
]
```

La sync (`AdminNavJsonRegistry::syncModule`):

- Creează/actualizează secțiunea `mod_{module_id}` (ex. `mod_furnizori`)
- Adaugă linkurile din `nav[]`
- Dacă modulul e dezactivat în `state.json` → secțiunea devine inactivă
- Dacă `nav: []` → nu creează nimic (doar dezactivează secțiunea existentă)

**Acum:** toate modulele active au `"nav": []`.

---

## Structura `registry.json`

### Secțiune

```json
{
    "section_key": "mod_furnizori",
    "module_id": "furnizori",
    "label": "Furnizori B2B",
    "group_label": "COMENZI",
    "workspace": "orders",
    "sort_order": 50,
    "is_active": true,
    "source": "manual",
    "customized": true,
    "items": []
}
```

| Câmp | Descriere |
|------|-----------|
| `section_key` | ID unic (`core_*` pentru CORE, `mod_*` pentru module) |
| `module_id` | Modul asociat sau `null` pentru secțiuni manuale/CORE |
| `group_label` | Titlul gri din sidebar (grup vizual) |
| `workspace` | Zona de lucru — filtrează vizibilitatea |
| `sort_order` | Ordine în sidebar |
| `is_active` | `false` = ascuns la randare |
| `source` | `core` / `module` / `manual` |
| `customized` | `true` = sync-ul **nu** suprascrie label/url/icon |

### Item (link)

```json
{
    "item_key": "furnizori",
    "module_id": "furnizori",
    "item_type": "link",
    "label": "Furnizori",
    "url": "/admin/furnizori",
    "icon": "truck",
    "badge_key": null,
    "alert_key": null,
    "is_global": false,
    "open_new_tab": false,
    "sort_order": 10,
    "is_active": true,
    "source": "manual",
    "customized": true
}
```

| Câmp | Descriere |
|------|-----------|
| `item_type` | `link` (implicit) sau `submenu` (cazuri speciale, ex. website) |
| `icon` | Nume icon Lucide (`truck`, `users`, `settings`…) |
| `is_global` | `true` = vizibil în **toate** workspace-urile |
| `badge_key` | Cheie pentru badge dinamic (`new_orders`, `unread_messages`…) |

ID compus la randare: `{section_key}::{item_key}`.

---

## Workspace-uri (zone de lucru)

Definite în `app/Backend/src/Core/Auth/AdminWorkspaceCatalog.php`.

| ID | Label |
|----|-------|
| `orders` | Clienți și Comenzi |
| `suppliers` | Produse Furnizori |
| `shop` | Produse Besoiupieseauto |
| `marketing` | Marketing și promovare |
| `social` | Comunicare & Socializare |
| `ai` | AI, agenți și robotică |
| `navigatie` | Navigație admin |
| `company` | (fără filtrare — vede tot) |

### Filtrare la randare

`buildTree($data, $renderOnly = true)`:

- Dacă workspace curent ≠ `company` → arată doar secțiunile cu `workspace` egal + item-uri `is_global: true`
- Ascunde secțiuni/item-uri cu `is_active: false`
- Ascunde module dezactivate (`ModuleGate::enabled`)
- Aplică preferințe user (ordine secțiuni, item-uri ascunse)

Filtrarea e **pe server** (`serverFiltered: true` în `nav.php`).

---

## Comportament după reset (stare curentă)

| Ce | Comportament |
|----|--------------|
| Registry gol | Sidebar gol |
| Seed gol | Bootstrap nu adaugă secțiuni |
| `nav: []` în module | Sync nu creează secțiuni |
| `ensureCoreNavHub()` | Rulează **doar** dacă există deja secțiuni (nu injectează automat la zero) |
| `NavModule::boot()` | `ensureBootstrapped(false)` — fără sync automat la încărcare |

După ce adaugi **prima secțiune**, motorul poate injecta `core_navigatie` (grup „NAVIGAȚIE ADMIN”) cu linkuri către editor — util ca acces la `/admin/nav`.

---

## Fișiere cheie

| Fișier | Rol |
|--------|-----|
| `app/Backend/src/Services/AdminNavJsonRegistry.php` | Motor: bootstrap, sync, buildTree, CRUD |
| `app/Backend/src/Services/AdminNavRegistryService.php` | Fațadă publică |
| `admin/config/admin_nav_seed.php` | Seed inițial CORE |
| `admin/Templates/admin/static_elements/nav.php` | Entry sidebar |
| `admin/Templates/admin/static_elements/nav_renderer.php` | Randare HTML |
| `modules/nav/` | Editor UI + `NavHubService` |
| `admin/public/api/nav_registry_endpoint.php` | API editor |
| `app/Backend/storage/modules/state.json` | Module activate/dezactivate |
| `modules/_template/module.json` | Șablon `nav[]` pentru module noi |

---

## Workflow recomandat

1. **Planifică pe zone** — ce workspace folosește fiecare flux (`orders`, `suppliers`…).
2. **Definește grupuri** — `group_label` pentru titluri în sidebar.
3. **Adaugă secțiuni** — din `/admin/nav` sau direct în JSON.
4. **Adaugă linkuri** — URL `/admin/...`, icon Lucide.
5. **Marchează `customized: true`** dacă vrei control manual permanent.
6. **Opțional:** pune `nav[]` în `module.json` și folosește **Resync** ca punct de plecare.

### Exemplu: primul meniu minimal (workspace `orders`)

1. Deschide `/admin/nav`.
2. Adaugă secțiune: label „Furnizori”, `workspace: orders`, `group_label: COMENZI`.
3. Adaugă link: label „Furnizori”, url `/admin/furnizori`, icon `truck`.
4. Repetă pentru clienți, comenzi etc.

Sau editează direct `registry.json` și reîncarcă admin.

---

## Legături

- Algoritm module plug-in: `modules/ALGORITM_MODULE_PLUG_IN.md`
- Shell admin: `admin/README.md`
- Integrare proiect vechi: `.cursor/PROMPT_INTEGRARE_PROIECT_VECHI.md`
