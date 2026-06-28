<?php

namespace Besoiu\Controllers;

use Besoiu\Core\AdminPageResolver;
use Besoiu\Core\AdminUrl;
use Besoiu\Core\Auth\AdminCsrf;

class Templates
{

    protected $basePath = '/admin/Templates/admin/static_elements/';
    protected $basPathcontent = '/admin/Templates/admin/pages/';
    protected string $contentFile = '';

    protected $partials = [
        'head'      => 'heade.php',
        'nav_top'   => 'nav.php',
        'left_nav'  => 'nav.php',
        'topbar'    => 'admin-topbar.php',
        'footer'    => 'footer.php',
        'testpag'    => 'testpag.php',
    ];
    public function __construct($contentFile)
    {
        $this->contentFile = $contentFile;
    }

    public function getCurrentUrl(): string
    {
        return AdminUrl::siteBaseUrl();
    }

    public function rederLogin()
    {
        $file = trim($this->contentFile); // elimină newline și spații la început/sfârșit
        $files = $this->resolveLocalPath($file);
        if ($files !== null && file_exists($files)) {
            require $files;
        } else {
            echo "<!-- ⚠️ Partial  -->";
        }

    }

    public function getDates(): string
    {
        return AdminUrl::siteBaseUrl();
    }

    public function render()
    {
        $accountName = htmlspecialchars(
            (string) ($_SESSION['user_name'] ?? $_SESSION['user_login'] ?? 'Administrator'),
            ENT_QUOTES,
            'UTF-8'
        );
        $accountRole = htmlspecialchars((string) ($_SESSION['role'] ?? 'guest'), ENT_QUOTES, 'UTF-8');
        $accountRoleLabel = htmlspecialchars(
            ucwords(str_replace('_', ' ', (string) ($_SESSION['role'] ?? 'guest'))),
            ENT_QUOTES,
            'UTF-8'
        );
        $breadcrumbMeta = $this->getBreadcrumbMeta();
        $pageTitle = htmlspecialchars($breadcrumbMeta['title'] . ' — Besoiu Admin', ENT_QUOTES, 'UTF-8');

        $link = $this->getDates();
        echo '<!DOCTYPE html><!--
Template Name: Midone - Admin Dashboard Template
Author: Left4code
Website: http://www.left4code.com/
Contact: leftforcode@gmail.com
Purchase: https://themeforest.net/user/left4code/portfolio
Renew Support: https://themeforest.net/user/left4code/portfolio
License: You must have a valid license purchased only from themeforest(the above link) in order to legally use the theme for your project.
-->
<html lang="ro"><!-- BEGIN: Head -->
<head>
    <meta charset="utf-8">
    ' . AdminCsrf::metaTag() . '
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Panou administrare Besoiu Piese Auto">
    <meta name="author" content="BesoiuPieseAuto">
    <script>
    (function () {
      try {
        if (window.matchMedia && window.matchMedia("(min-width: 1280px)").matches) {
          localStorage.setItem("compactMenu", "false");
        }
      } catch (e) { /* ignore */ }
      document.documentElement.classList.add("besoiu-layout-stable");
    })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <title>' . $pageTitle . '</title>
    <!-- BEGIN: CSS Assets-->
    <link rel="stylesheet" href="/admin/Templates/admin/dist/css/themes/rubick/side-menu.css">
    <link rel="stylesheet" href="/admin/Templates/admin/dist/css/vendors/simplebar.css">
    <link rel="stylesheet" href="/admin/Templates/admin/dist/css/app.css">
    <link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-layout.css') . '?v=20260625-notif-popup">
    <link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-besoiu-theme.css') . '?v=20260623-search-analytics-layout">
    <link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-pages.css') . '?v=20260625-adaos-pfl-v2">
    <link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-mobile.css') . '?v=20260625-adaos-pfl-v2">
    <link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-workspace.css') . '?v=20260624f">
    <link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-load-progress.css') . '?v=20260627-progress">
    <script src="' . AdminUrl::publicAsset('js/admin-action-progress.js') . '?v=20260627-progress"></script>
    ' . (str_contains(strtolower($this->contentFile), 'homepages')
        ? '<link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-workspace-dashboard.css') . '?v=20260625-ws-grid2">' . "\n    "
            . '<link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-company-dashboard.css') . '?v=20260625-company-organic2">' . "\n    "
        : '') . '<link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-comunicare.css') . '?v=20260625-adaos-pfl-v2">
    <link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-ai-agent.css') . '?v=20260627-rag2">
    ' . (str_contains(strtolower($this->contentFile), 'ai-agent')
        ? '<link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-ai-agent-mission.css') . '?v=20260627-ui2">' . "\n    "
            . '<link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-ai-agent-supervisor.css') . '?v=20260627-ui3">' . "\n    "
        : '') . '
    <link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-alert-fix.css') . '?v=20260626-links2">
    ' . ($this->sectionAssistantEnabled()
        ? '<link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-section-assistant.css') . '?v=' . $this->publicAssetVersion('css/admin-section-assistant.css') . '">' . "\n    "
        : '') . (str_contains(strtolower($this->contentFile), 'alerts/alerts')
        ? '<link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-alerts.css') . '?v=20260625-alerts2">' . "\n    "
        : '') . (str_contains(strtolower($this->contentFile), 'system-errors')
        ? '<link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-system-errors.css') . '?v=20260625-syserr">' . "\n    "
        : '') . (str_contains(strtolower($this->contentFile), 'comenzi/comenzi') || str_contains(strtolower($this->contentFile), 'comenzi.php')
        ? '<link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-comenzi.css') . '?v=20260625-comenzi-v2">' . "\n    "
        : '') . (str_contains(strtolower($this->contentFile), 'export/export') || str_contains(strtolower($this->contentFile), 'export.php')
        ? '<link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-export.css') . '?v=20260625-export-hub">' . "\n    "
        : '') . (str_contains(strtolower($this->contentFile), 'import/import')
        ? '<link rel="stylesheet" href="' . AdminUrl::publicAsset('css/admin-import-consumables.css') . '?v=20260625-consumables-v1">' . "\n    "
        : '') . '<!-- END: CSS Assets-->
    <script defer src="' . AdminUrl::publicAsset('js/admin-async.js') . '?v=20260625-load-v1"></script>
    <script>
    (function(){var m=document.querySelector(\'meta[name="csrf-token"]\');if(!m||!m.content)return;var t=m.content;var f=window.fetch;window.fetch=function(u,o){o=o||{};o.headers=o.headers||{};if(o.headers instanceof Headers){if(!o.headers.has("X-Admin-CSRF"))o.headers.set("X-Admin-CSRF",t);}else{if(!o.headers["X-Admin-CSRF"])o.headers["X-Admin-CSRF"]=t;}return f.call(this,u,o);};})();
    </script>
    <script defer src="' . AdminUrl::publicAsset('js/admin-pagination.js') . '"></script>
    <script defer src="' . AdminUrl::publicAsset('js/admin-workspace-switcher.js') . '?v=20260624e"></script>
    ' . (str_contains(strtolower($this->contentFile), 'homepages')
        ? '<script defer src="' . AdminUrl::publicAsset('js/admin-workspace-dashboard.js') . '?v=20260625-ws-user"></script>' . "\n    "
            . '<script defer src="' . AdminUrl::publicAsset('js/admin-company-dashboard.js') . '?v=20260625-company-datafix"></script>' . "\n    "
        : '') . '
';


echo '
        </head>
<body data-page="dash" class="besoiu-admin-2026">

    <div class="page-loader bg-background fixed inset-0 z-[100] flex items-center justify-center transition-opacity" aria-hidden="true">
        <div class="loader-spinner !w-14"></div>
    </div>
    <script>
    (function () {
      var loader = document.querySelector(".page-loader");
      if (loader) {
        loader.classList.add("hidden", "opacity-0");
        loader.setAttribute("aria-hidden", "true");
      }
    })();
    </script>
    <script src="' . AdminUrl::publicAsset('js/admin-shell-guard.js') . '?v=20260625-shell-guard"></script>

    <div class="rubick min-h-screen">
        <nav class="side-menu text-background dark:text-foreground xl:ml-0 transition-[margin] duration-200 fixed top-0 left-0 z-50 group before:content-[\'\'] before:fixed before:inset-0 before:bg-black/80 dark:before:bg-foreground/5 before:backdrop-blur before:xl:hidden after:content-[\'\'] after:absolute after:inset-0 after:bg-primary after:xl:hidden dark:after:bg-background after:bg-noise [&.side-menu--mobile-menu-open]:ml-0 [&.side-menu--mobile-menu-open]:before:block -ml-[275px] before:hidden" aria-label="Meniu administrare">
            <div class="close-mobile-menu fixed ml-[275px] xl:hidden z-50 cursor-pointer [&.close-mobile-menu--mobile-menu-open]:block hidden">
                <div class="ml-5 mt-5 flex size-10 items-center justify-center">
                    <i data-lucide="x" class="[--color:currentColor] stroke-(--color) fill-(--color)/25 size-7 stroke-1"></i>
                </div>
            </div>
            <div class="side-menu__content z-20 pt-5 pb-[7.5rem] relative w-[275px] duration-200 transition-[width] group-[.side-menu--collapsed]:xl:w-[110px] group-[.side-menu--collapsed.side-menu--on-hover]:xl:w-[275px] h-screen flex flex-col">
                <div class="relative z-10 hidden h-[65px] w-[275px] flex-none items-center overflow-hidden px-6 duration-200 xl:flex group-[.side-menu--collapsed.side-menu--on-hover]:xl:w-[275px] group-[.side-menu--collapsed]:xl:w-[110px]">
                    <a class="brand-logo flex items-center transition-[margin] duration-200 xl:ml-2 group-[.side-menu--collapsed.side-menu--on-hover]:xl:ml-2 group-[.side-menu--collapsed]:xl:ml-6" href="/admin/dashboard">
                        BesoiuPieseAuto
                        <span id="besoiu-logo-alert-dot" class="besoiu-logo-alert-dot hidden" title="Alerte critice"></span>
                    </a>
                    <a class="toggle-compact-menu border-background/20 bg-background/10 dark:bg-foreground/[.02] dark:border-foreground/[.09] ml-auto hidden items-center justify-center rounded-md border py-0.5 pl-0.5 pr-1 opacity-70 transition-[opacity,transform] hover:opacity-100 group-[.side-menu--collapsed]:xl:rotate-180 group-[.side-menu--collapsed.side-menu--on-hover]:xl:opacity-100 group-[.side-menu--collapsed]:xl:opacity-0 2xl:flex" href="/admin/dashboard">
                        <i data-lucide="chevron-left" class="size-4 stroke-[1.5] [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                    </a>
                </div>
                ';
        $this->includePartial('left_nav');
echo '
                <div class="side-menu__account besoiu-account-wrap absolute inset-x-0 bottom-0 mx-4 mb-8 z-40">
                    <div id="besoiu-account-backdrop" class="besoiu-account-backdrop" hidden aria-hidden="true"></div>
                    <div id="besoiu-account-popup" class="besoiu-account-popup" role="dialog" aria-modal="true" aria-labelledby="besoiu-account-popup-title" hidden>
                        <div class="besoiu-account-popup__head">
                            <div class="besoiu-account-popup__avatar">
                                <img src="/admin/Templates/admin/dist/images/fakers/profile-11.jpg" alt="">
                            </div>
                            <div class="besoiu-account-popup__meta">
                                <div id="besoiu-account-popup-title" class="besoiu-account-popup__name">' . $accountName . '</div>
                                <div class="besoiu-account-popup__role">' . $accountRoleLabel . '</div>
                            </div>
                            <button type="button" id="besoiu-account-close" class="besoiu-account-popup__close" aria-label="Închide meniul cont">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                        <nav class="besoiu-account-popup__nav" aria-label="Meniu cont utilizator">
                            <a class="besoiu-account-popup__link" href="/admin/profileusers">
                                <i data-lucide="user-circle"></i>
                                <span>Profil &amp; utilizatori</span>
                            </a>
                            <a class="besoiu-account-popup__link" href="/admin/addusers">
                                <i data-lucide="user-plus"></i>
                                <span>Adaugă cont admin</span>
                            </a>
                            <a class="besoiu-account-popup__link" href="/admin/settings">
                                <i data-lucide="settings"></i>
                                <span>Setări sistem</span>
                            </a>
                            <a class="besoiu-account-popup__link" href="/admin/reset-password">
                                <i data-lucide="key-round"></i>
                                <span>Reset parolă</span>
                            </a>
                            <a class="besoiu-account-popup__link" href="/admin/backup">
                                <i data-lucide="database-backup"></i>
                                <span>Backup</span>
                            </a>
                            <a class="besoiu-account-popup__link" href="/admin/help">
                                <i data-lucide="circle-help"></i>
                                <span>Ajutor</span>
                            </a>
                        </nav>
                        <div class="besoiu-account-popup__foot">
                            <a id="admin-logout-link" class="besoiu-account-popup__logout" href="/admin/logout" data-admin-action="logout">
                                <i data-lucide="log-out"></i>
                                <span>Deconectare</span>
                            </a>
                        </div>
                    </div>
                    <button type="button" id="besoiu-account-trigger" class="besoiu-account-trigger" aria-expanded="false" aria-controls="besoiu-account-popup" aria-haspopup="dialog">
                        <span class="besoiu-account-trigger__avatar">
                            <img src="/admin/Templates/admin/dist/images/fakers/profile-11.jpg" alt="">
                        </span>
                        <span class="besoiu-account-trigger__text">
                            <span class="besoiu-account-trigger__name">' . $accountName . '</span>
                            <span class="besoiu-account-trigger__role">' . $accountRoleLabel . '</span>
                        </span>
                        <i data-lucide="chevron-up" class="besoiu-account-trigger__chevron"></i>
                    </button>
                </div>
            </div>
        </nav>
        <div class="content h-screen transition-[margin,width] duration-200 z-10 relative xl:ml-[275px] [&.content--compact]:xl:ml-[110px]">
            <div class="h-full overflow-x-hidden">
                <div class="content__scroll-area relative z-20 h-full overflow-y-auto transition-[margin] duration-200">';
        $breadcrumbSection = $breadcrumbMeta['section'];
        $breadcrumbTitle = $breadcrumbMeta['title'];
        $this->includePartial('topbar');
        echo '<main id="admin-main" class="admin-page-main" role="main">
                    <div class="admin-content">
                    ';

        $this->includeContent();
        echo '</div>';
        $this->includePartial('footer');
        echo '
                    </main>
                </div>
            </div>
        </div>
    </div>
 ';




echo '


';


        echo '
    <script type="application/json" id="besoiu-admin-nav-cfg">' . json_encode([
        'base' => AdminUrl::BASE,
        'legacy' => [
            '/admin/cron-sync' => AdminUrl::navPath('cron'),
            '/admin/homepages' => AdminUrl::navPath('dashboard'),
            '/admin/furnizori' => AdminUrl::navPath('suppliers'),
        ],
        'paths' => [
            'import' => AdminUrl::navPath('import'),
            'importJobs' => AdminUrl::navPath('import') . '#job-progress-wrap',
            'cron' => AdminUrl::navPath('cron'),
            'suppliers' => AdminUrl::navPath('suppliers'),
            'alerts' => AdminUrl::navPath('alerts'),
        ],
    ], JSON_UNESCAPED_UNICODE) . '</script>
    <script defer src="' . AdminUrl::publicAsset('js/admin-alert-fix.js') . '?v=20260626-links2"></script>
    <script defer src="' . AdminUrl::publicAsset('js/admin-ops-alerts.js') . '?v=20260626-fix"></script>
    ' . ($this->sectionAssistantEnabled()
        ? '<script type="application/json" id="besoiu-section-assist-cfg">' . json_encode([
            'api' => AdminUrl::api('admin_hub_endpoint.php'),
            'section' => $this->sectionAssistantSlug(),
            'label' => $this->sectionAssistantLabel(),
        ], JSON_UNESCAPED_UNICODE) . '</script>' . "\n    "
            . '<script defer src="' . AdminUrl::publicAsset('js/admin-section-assistant.js') . '?v=' . $this->publicAssetVersion('js/admin-section-assistant.js') . '"></script>' . "\n    "
        : '') . (str_contains(strtolower($this->contentFile), 'alerts/alerts')
        ? '<script defer src="' . AdminUrl::publicAsset('js/admin-alerts.js') . '?v=20260626-fix"></script>' . "\n    "
        : '') . '<script defer src="/admin/Templates/admin/dist/js/vendors/lucide.js"></script>
    <script defer src="/admin/Templates/admin/dist/js/components/base/lucide.js"></script>
    <script defer src="/admin/Templates/admin/dist/js/vendors/dom.js"></script>
    <script defer src="/admin/Templates/admin/dist/js/vendors/tippy.js"></script>
    <script defer src="/admin/Templates/admin/dist/js/vendors/popper.js"></script>
    <script defer src="/admin/Templates/admin/dist/js/vendors/dropdown.js"></script>
    <script defer src="/admin/Templates/admin/dist/js/vendors/modal.js"></script>
    <script defer src="/admin/Templates/admin/dist/js/vendors/simplebar.js"></script>
    <script defer src="/admin/Templates/admin/dist/js/components/base/page-loader.js"></script>
    <script defer src="/admin/Templates/admin/dist/js/components/base/tippy.js"></script>
    <script defer src="/admin/Templates/admin/dist/js/themes/rubick.js"></script>
    <script defer src="' . AdminUrl::publicAsset('js/admin-side-nav.js') . '?v=20260612-nav-active1"></script>
    <script defer src="' . AdminUrl::publicAsset('js/admin-sidebar-persist.js') . '?v=20260611-layout-stable"></script>
    <script defer src="' . AdminUrl::publicAsset('js/admin-account-menu.js') . '?v=20260610-account-v1"></script>
    <script defer src="/admin/Templates/admin/dist/js/utils/helper.js"></script>
    <script defer src="/admin/Templates/admin/dist/js/components/theme-switcher.js"></script> <!-- END: Vendor JS Assets (shell minimal) -->
</body>
</html>';
    }
    protected function includeContent()
    {
        $file = trim($this->contentFile);

        if ($this->requireContentFile($file)) {
            return;
        }

        $slug = basename(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $resolved = AdminPageResolver::resolveTemplate($slug);
        if ($resolved !== null && $this->requireContentFile($resolved)) {
            return;
        }

        echo '<div class="mt-10 rounded-md border border-warning/40 bg-warning/5 p-5 text-sm">'
            . 'Conținut indisponibil — fișier lipsă: <code>'
            . htmlspecialchars(basename($file))
            . '</code>. Verificați ruta în meniu și sincronizați <code>AdminPageResolver.php</code> + template-urile pe server.</div>';
    }

    private function requireContentFile(string $relativePath): bool
    {
        $absolutePath = $this->resolveLocalPath($relativePath);
        if ($absolutePath === null || !is_file($absolutePath)) {
            return false;
        }

        require $absolutePath;

        return true;
    }
    protected function includePartial(string $key)
    {
        if (!isset($this->partials[$key])) {
            echo "<!-- ⚠️ Partial key '{$key}' nu există în listă -->";
            return;
        }

        $file = $this->resolveLocalPath($this->basePath . $this->partials[$key]);

        if ($file !== null && file_exists($file)) {
            require $file;
        } else {
            echo "<!-- ⚠️ Partial '{$key}' missing at {$file} -->";
        }
    }


    protected function getBreadcrumbMeta(): array
    {
        $file = strtolower(str_replace('\\', '/', trim($this->contentFile)));
        $map = [
            'homepages' => ['section' => 'Admin', 'title' => 'Dashboard'],
            'comenzi/comenzi' => ['section' => 'Comenzi', 'title' => 'Toate comenzile'],
            'comenzi' => ['section' => 'Comenzi', 'title' => 'Comenzi'],
            'produse/produse' => ['section' => 'Produse', 'title' => 'Lista produse'],
            'produse/produse-vitrina' => ['section' => 'Produse', 'title' => 'Vitrina homepage'],
            'produse/produse-scanate' => ['section' => 'Produse', 'title' => 'Produse scanate'],
            'caietcomenzi/caiet-produse' => ['section' => 'Produse', 'title' => 'Caiet comenzi — produse ERP'],
            'produse/addproduse' => ['section' => 'Produse', 'title' => 'Adaugă produs'],
            'furnizori/furnizori' => ['section' => 'Furnizori', 'title' => 'Lista furnizori'],
            'clienti/clienti' => ['section' => 'Clienți', 'title' => 'Clienți'],
            'facturi/facturi' => ['section' => 'Comenzi', 'title' => 'Facturi'],
            'livrare/livrare' => ['section' => 'Comenzi', 'title' => 'Livrare / AWB'],
            'comunicare/comunicare' => ['section' => 'Comunicare', 'title' => 'Hub comunicare'],
            'comunicare/reply-templates' => ['section' => 'Comunicare', 'title' => 'Template-uri răspuns'],
            'comunicare/comunicare-canale' => ['section' => 'Comunicare', 'title' => 'Canale comunicare'],
            'comunicare/comunicare-leads' => ['section' => 'Comunicare', 'title' => 'Lead-uri contact'],
            'comunicare/comunicare-broadcast' => ['section' => 'Comunicare', 'title' => 'Broadcast mesaje'],
            'comunicare/comunicare-archive' => ['section' => 'Comunicare', 'title' => 'Arhivă conversații'],
            'messages/messages' => ['section' => 'Comunicare', 'title' => 'Mesagerie'],
            'bots/bots' => ['section' => 'Automatizare', 'title' => 'Roboți AI'],
            'bots/pieseauto' => ['section' => 'Automatizare', 'title' => 'PieseAuto Scanner'],
            'bots/whatsapp' => ['section' => 'Automatizare', 'title' => 'WhatsApp AI'],
            'bots/facebook' => ['section' => 'Automatizare', 'title' => 'Facebook Sniper'],
            'bots/baselinker' => ['section' => 'Automatizare', 'title' => 'BaseLinker Sync'],
            'bots/monitor' => ['section' => 'Automatizare', 'title' => 'Monitor Roboți'],
            'bots/registry' => ['section' => 'Automatizare', 'title' => 'Registry Roboți'],
            'ai-agent/ai-agent' => ['section' => 'Automatizare', 'title' => 'AI Agent'],
            'export/export' => ['section' => 'Automatizare', 'title' => 'Export date'],
            'settings/settings' => ['section' => 'Sistem', 'title' => 'Setări'],
            'users/users' => ['section' => 'Sistem', 'title' => 'Utilizatori'],
            'scraper/scraper' => ['section' => 'Sistem', 'title' => 'Scraper'],
            'import/import' => ['section' => 'Produse', 'title' => 'Import CSV'],
            'import/importreview' => ['section' => 'Produse', 'title' => 'Coada import'],
            'searchlogs/searchlogs' => ['section' => 'Analiză', 'title' => 'Search Logs'],
            'cron/cron' => ['section' => 'Automatizare', 'title' => 'Cron Sync'],
            'website/website' => ['section' => 'Web site', 'title' => 'Pagini site'],
            'blog/blog' => ['section' => 'Web site', 'title' => 'Blog'],
            'blog/addblog' => ['section' => 'Web site', 'title' => 'Articol nou'],
            'marketplace/marketplace' => ['section' => 'Automatizare', 'title' => 'Marketplace Besoiu'],
            'marketplace/pieseauto' => ['section' => 'Automatizare', 'title' => 'PieseAuto Robot'],
            'marketplace/baselinker' => ['section' => 'Automatizare', 'title' => 'BaseLinker Catalog'],
            'scan/scan' => ['section' => 'Automatizare', 'title' => 'Scanare cereri'],
            'alerts/alerts' => ['section' => 'Sistem', 'title' => 'Alerte'],
            'system-errors/system-errors' => ['section' => 'Sistem', 'title' => 'Jurnal erori'],
            'report/reports' => ['section' => 'Analiză', 'title' => 'Rapoarte'],
            'cross-reference/cross-reference' => ['section' => 'Analiză', 'title' => 'Echivalențe OEM'],
            'supplier-search/supplier-search' => ['section' => 'Comenzi', 'title' => 'Supplier Search'],
            'supplier-search/supplier-cart' => ['section' => 'Comenzi', 'title' => 'Coș furnizori'],
            'categorii/categorii' => ['section' => 'Produse', 'title' => 'Categorii'],
            'adaoscomercial/adaoscomercial' => ['section' => 'Produse', 'title' => 'Adaos comercial'],
            'backup/backup' => ['section' => 'Sistem', 'title' => 'Backup'],
        ];

        foreach ($map as $needle => $meta) {
            if (str_contains($file, $needle)) {
                return $meta;
            }
        }

        $uriPath = strtolower(str_replace('\\', '/', (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '')));
        foreach ($map as $needle => $meta) {
            if ($uriPath !== '' && (str_contains($uriPath, '/' . $needle) || str_ends_with($uriPath, '/' . basename($needle)))) {
                return $meta;
            }
        }

        $base = basename($file, '.php');
        $base = str_replace(['-', '_'], ' ', $base);
        $title = mb_convert_case($base, MB_CASE_TITLE, 'UTF-8');

        return ['section' => 'Admin', 'title' => $title !== '' ? $title : 'Panou'];
    }

    protected function publicAssetVersion(string $relativePath): string
    {
        $full = dirname(__DIR__, 2) . '/public/assets/' . ltrim($relativePath, '/');

        return is_file($full) ? (string) filemtime($full) : '1';
    }

    protected function sectionAssistantEnabled(): bool
    {
        $file = strtolower(str_replace('\\', '/', $this->contentFile));
        foreach (['login/', 'login.php', 'reset-password', 'reg.php', 'reg/'] as $skip) {
            if (str_contains($file, $skip)) {
                return false;
            }
        }

        if (str_contains($file, 'templates/admin/pages/')) {
            return true;
        }

        $path = strtolower(str_replace('\\', '/', (string) ($_SERVER['REQUEST_URI'] ?? '')));
        if (str_contains($path, '/admin/') && !preg_match('#/admin/(login|logout|reg)(/|$|\?)#', $path)) {
            return true;
        }

        return false;
    }

    protected function sectionAssistantSlug(): string
    {
        $file = strtolower(str_replace('\\', '/', $this->contentFile));
        if (str_contains($file, 'adaos')) {
            return 'adaos';
        }
        if (str_contains($file, 'dashboard')) {
            return 'dashboard';
        }
        if (str_contains($file, 'comunicare') || str_contains($file, 'messages')) {
            return 'comunicare';
        }
        if (str_contains($file, 'order') || str_contains($file, 'comenzi') || str_contains($file, 'facturi') || str_contains($file, 'livrare')) {
            return 'comenzi';
        }
        if (str_contains($file, 'furnizor')) {
            return 'furnizori';
        }
        if (str_contains($file, 'import')) {
            return 'import';
        }
        if (str_contains($file, 'categorii')) {
            return 'categorii';
        }
        if (str_contains($file, 'scraper')) {
            return 'scraper';
        }

        return 'produse';
    }

    protected function sectionAssistantLabel(): string
    {
        return match ($this->sectionAssistantSlug()) {
            'adaos' => 'Adaos comercial',
            'dashboard' => 'Dashboard',
            'comunicare' => 'Comunicare',
            'furnizori' => 'Furnizori',
            'import' => 'Import',
            'categorii' => 'Categorii',
            'scraper' => 'Scraper',
            default => 'Produse',
        };
    }

    private function resolveLocalPath(?string $path): ?string
    {
        if (!is_string($path) || trim($path) === '') {
            return null;
        }

        if (preg_match('#^[a-zA-Z]:[\\\\/]#', $path) === 1) {
            return $path;
        }

        $root = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if (!is_string($root) || trim($root) === '') {
            return null;
        }

        return rtrim($root, "\\/") . '/' . ltrim(str_replace('\\', '/', $path), '/');
    }
}