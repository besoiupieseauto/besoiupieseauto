<?php

declare(strict_types=1);

use Evasystem\Core\AdminUrl;
use Evasystem\Core\Auth\AdminWorkspace;
use Evasystem\Core\Auth\AdminWorkspaceCatalog;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: ' . AdminUrl::path('login'), true, 302);
    exit;
}

$allowed = AdminWorkspace::allowedWorkspacesForSession();
if ($allowed === []) {
    http_response_code(403);
    echo 'Nu ai acces la niciun departament admin.';
    exit;
}

$forceSelect = isset($_GET['force']) && $_GET['force'] === '1';
if (!$forceSelect && count($allowed) === 1) {
    AdminWorkspace::setCurrent($allowed[0]);
    header('Location: ' . AdminWorkspaceCatalog::dashboardPath($allowed[0]), true, 302);
    exit;
}

/** Icon SVG inline — 32px, fără CDN. */
function besoiu_workspace_icon_svg(string $id): string
{
    $common = 'width="32" height="32" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"';

    $icons = [
        'orders' => "<svg {$common}>
            <circle cx=\"17\" cy=\"38\" r=\"3\" stroke=\"currentColor\" stroke-width=\"2.2\"/>
            <circle cx=\"33\" cy=\"38\" r=\"3\" stroke=\"currentColor\" stroke-width=\"2.2\"/>
            <path d=\"M6 8h4l2.8 14H34l3-12H12\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/>
            <circle cx=\"34\" cy=\"16\" r=\"5\" stroke=\"currentColor\" stroke-width=\"2.2\"/>
            <path d=\"M34 14v4M32 16h4\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\"/>
        </svg>",
        'suppliers' => "<svg {$common}>
            <rect x=\"10\" y=\"12\" width=\"28\" height=\"22\" rx=\"3\" stroke=\"currentColor\" stroke-width=\"2.2\"/>
            <path d=\"M10 18h28M18 12v22M30 12v22\" stroke=\"currentColor\" stroke-width=\"2.2\"/>
            <path d=\"M8 20h26l-3 14H11L8 20z\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linejoin=\"round\" opacity=\".5\"/>
        </svg>",
        'ai' => "<svg {$common}>
            <rect x=\"11\" y=\"16\" width=\"26\" height=\"20\" rx=\"6\" stroke=\"currentColor\" stroke-width=\"2.2\"/>
            <circle cx=\"20\" cy=\"26\" r=\"2.5\" fill=\"currentColor\"/>
            <circle cx=\"28\" cy=\"26\" r=\"2.5\" fill=\"currentColor\"/>
            <path d=\"M18 31c1.2 1.6 3 2.5 6 2.5s4.8-.9 6-2.5\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linecap=\"round\"/>
            <path d=\"M24 8v6M17 10l2 3M31 10l-2 3\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linecap=\"round\"/>
        </svg>",
        'marketing' => "<svg {$common}>
            <path d=\"M8 22v8l8-4 10 6V16l-10 6-8-4z\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linejoin=\"round\"/>
            <path d=\"M32 12l8-4v20l-8-4\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linejoin=\"round\"/>
            <path d=\"M14 32v6\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linecap=\"round\"/>
        </svg>",
        'shop' => "<svg {$common}>
            <rect x=\"10\" y=\"14\" width=\"28\" height=\"20\" rx=\"3\" stroke=\"currentColor\" stroke-width=\"2.2\"/>
            <path d=\"M10 20h28\" stroke=\"currentColor\" stroke-width=\"2.2\"/>
            <circle cx=\"16\" cy=\"17\" r=\"1.5\" fill=\"currentColor\"/>
            <circle cx=\"20\" cy=\"17\" r=\"1.5\" fill=\"currentColor\"/>
            <path d=\"M14 26h12M14 30h8\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linecap=\"round\"/>
        </svg>",
        'social' => "<svg {$common}>
            <path d=\"M14 18c2 2 8 2 10 0 2-2 2-6 0-8-2-2-8-2-10 0-2 2-2 6 0 8z\" stroke=\"currentColor\" stroke-width=\"2.2\"/>
            <path d=\"M12 32c3 2 9 2 12 0\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linecap=\"round\"/>
            <circle cx=\"34\" cy=\"14\" r=\"2\" fill=\"currentColor\"/>
            <path d=\"M32 28h8M36 24v8\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linecap=\"round\"/>
        </svg>",
        'company' => "<svg {$common}>
            <circle cx=\"24\" cy=\"24\" r=\"14\" stroke=\"currentColor\" stroke-width=\"2.2\"/>
            <circle cx=\"24\" cy=\"24\" r=\"5\" stroke=\"currentColor\" stroke-width=\"2.2\"/>
            <path d=\"M24 10v4M24 34v4M10 24h4M34 24h4\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linecap=\"round\"/>
        </svg>",
    ];

    return $icons[$id] ?? $icons['orders'];
}

$workspaces = AdminWorkspaceCatalog::all();
$rawUserName = (string) ($_SESSION['user_name'] ?? $_SESSION['user_login'] ?? 'Administrator');
$userName = htmlspecialchars($rawUserName, ENT_QUOTES, 'UTF-8');
$userInitial = strtoupper(function_exists('mb_substr') ? mb_substr($rawUserName, 0, 1, 'UTF-8') : substr($rawUserName, 0, 1));
$cssLogin = AdminUrl::publicAsset('css/admin-login.css');
$cssWorkspace = AdminUrl::publicAsset('css/admin-workspace.css');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Alege departamentul — Besoiu Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($cssLogin, ENT_QUOTES, 'UTF-8') ?>?v=20260610-login-v1">
    <link rel="stylesheet" href="<?= htmlspecialchars($cssWorkspace, ENT_QUOTES, 'UTF-8') ?>?v=20260624f">
    <style>
      .besoiu-workspace-page svg { max-width: 100%; height: auto; flex-shrink: 0; }
      .besoiu-ws-card__icon svg { width: 32px !important; height: 32px !important; max-width: 32px !important; }
      .besoiu-login__logo-mark svg { width: 18px !important; height: 18px !important; }
      .besoiu-ws-card__btn svg { width: 16px !important; height: 16px !important; }
    </style>
</head>
<body class="besoiu-login-page besoiu-workspace-page">

<div class="besoiu-login besoiu-ws-layout">
    <aside class="besoiu-login__brand">
        <div class="besoiu-login__brand-inner">
            <a class="besoiu-login__logo" href="<?= htmlspecialchars(AdminUrl::path('workspace'), ENT_QUOTES, 'UTF-8') ?>">
                <span class="besoiu-login__logo-mark" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                </span>
                Besoiu <span>Piese Auto</span>
            </a>

            <div class="besoiu-login__hero">
                <span class="besoiu-login__badge">Portal departamente</span>
                <h2 class="besoiu-login__title">Unde lucrezi azi?</h2>
                <p class="besoiu-login__subtitle">
                    7 zone de lucru — fiecare cu meniul ei. Schimbi oricând din bara de sus.
                </p>

                <ul class="besoiu-ws-legend">
                    <?php foreach ($workspaces as $lid => $lws):
                        if (!in_array($lid, $allowed, true)) {
                            continue;
                        }
                    ?>
                    <li><span class="besoiu-ws-legend__dot" style="background:<?= htmlspecialchars((string) ($lws['accent'] ?? '#1abc9c'), ENT_QUOTES, 'UTF-8') ?>"></span><?= htmlspecialchars((string) $lws['label'], ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <p class="besoiu-login__brand-foot">&copy; <?= date('Y') ?> Besoiu Piese Auto — acces restricționat</p>
        </div>
    </aside>

    <main class="besoiu-login__panel besoiu-ws-panel">
        <div class="besoiu-ws-topbar">
            <div class="besoiu-ws-topbar__user">
                <span class="besoiu-ws-topbar__avatar" aria-hidden="true"><?= htmlspecialchars($userInitial, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="besoiu-ws-topbar__name"><?= $userName ?></span>
            </div>
            <a href="<?= htmlspecialchars(AdminUrl::path('logout'), ENT_QUOTES, 'UTF-8') ?>" class="besoiu-ws-topbar__logout" title="Deconectare">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Ieșire
            </a>
        </div>

        <div class="besoiu-ws-panel__head">
            <h1>Alege zona de lucru</h1>
            <p>Selectează departamentul — vei vedea doar meniul relevant.</p>
        </div>

        <div class="besoiu-ws-grid">
            <?php foreach ($workspaces as $id => $ws):
                if (!in_array($id, $allowed, true)) {
                    continue;
                }
                $label = htmlspecialchars($ws['label'], ENT_QUOTES, 'UTF-8');
                $desc = htmlspecialchars($ws['desc'], ENT_QUOTES, 'UTF-8');
                $accent = htmlspecialchars((string) ($ws['accent'] ?? '#1abc9c'), ENT_QUOTES, 'UTF-8');
                $accent2 = htmlspecialchars((string) ($ws['accent2'] ?? $ws['accent'] ?? '#0d9488'), ENT_QUOTES, 'UTF-8');
                $tags = $ws['tags'] ?? [];
            ?>
            <form
                class="besoiu-ws-card besoiu-ws-card--<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"
                method="post"
                action="<?= htmlspecialchars(AdminUrl::path('workspace'), ENT_QUOTES, 'UTF-8') ?>"
                style="--ws-a: <?= $accent ?>; --ws-a2: <?= $accent2 ?>"
            >
                <input type="hidden" name="workspace" value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">

                <div class="besoiu-ws-card__head">
                    <div class="besoiu-ws-card__icon">
                        <?= besoiu_workspace_icon_svg($id) ?>
                    </div>
                    <h2 class="besoiu-ws-card__title"><?= $label ?></h2>
                </div>
                <p class="besoiu-ws-card__desc"><?= $desc ?></p>

                <button type="submit" class="besoiu-ws-card__btn">
                    <span>Intră</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>
            <?php endforeach; ?>
        </div>

        <p class="besoiu-ws-panel__foot">Acces restricționat — doar personal autorizat</p>
    </main>
</div>

</body>
</html>
