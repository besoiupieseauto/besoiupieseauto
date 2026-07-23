# CORE — infrastructură storefront

Tot ce e reutilizabil (fără logică business) stă aici. **Modulele** nu depind unele de altele; CORE nu știe de Produse/Comenzi.

| Componentă | Rol |
|------------|-----|
| `Bootstrap/StorefrontApplication.php` | Pornire request |
| `Router/StorefrontRouter.php` | Match URL → Controller |
| `Module/ModuleRegistry.php` | Încarcă `app/Modules/*/module.json` |
| `Database/Connection.php` | PDO singleton |
| `Template/ThemeRenderer.php` | Randare view |

Paginile HTML de bază sunt în **`app/Views/`** (catalog, cart, product…).

Admin ERP: **`/admin/`** (shell CORE, fără `admin/modules/`).
