<?php
declare(strict_types=1);

namespace Besoiu\Modules\Dashboard;

use Besoiu\Core\Module\AbstractModule;
use Besoiu\Core\Module\ModuleContext;

/**
 * Modul Dashboard — container pentru layout-ul și logica tabloului de bord per zonă de lucru.
 *
 * Conținut:
 *  - pages/dashboard.php            → pagina randată la /admin/dashboard (prin shim)
 *  - pages/_partials/*.php          → panouri (workspace deck, company tabs, marketing pulse)
 *  - src/DashboardCatalog.php       → ce panouri/quick-actions sunt vizibile per zonă
 *
 * Nu adaugă rute noi: /admin/dashboard e deja mapat pe admin/Templates/.../homepages.php
 * (shim subțire care include pages/dashboard.php din acest modul).
 */
final class DashboardModule extends AbstractModule
{
    public function boot(ModuleContext $context): void
    {
        // Nu are hook-uri către CORE; layout + logică proprii, servite prin shim.
    }
}
