<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require_once __DIR__ . '/system/page-init.php';
require_once __DIR__ . '/system/besoiu-assets.php';
require_once __DIR__ . '/system/site-content.php';

$globalContact = site_content_blocks('global');
$dashPhone = trim((string) (($globalContact['header'] ?? [])['phone'] ?? '0726 498 573'));
$dashPhoneHref = site_phone_resolve_href($dashPhone, (string) (($globalContact['header'] ?? [])['phone_href'] ?? ''));
if ($dashPhoneHref === '') {
    $dashPhoneHref = site_phone_to_tel_href($dashPhone);
}

$sessionUser = shop_auth_session_user();
$isLoggedIn = $sessionUser !== null;
$customerRow = null;

if ($isLoggedIn) {
    shop_auth_bootstrap_db();
    $customerRow = shop_auth_find_by_id((int) $sessionUser['id']);
    if ($customerRow === null) {
        shop_auth_logout();
        $isLoggedIn = false;
        $sessionUser = null;
    }
}

$view = (string) ($_GET['view'] ?? '');
if (!$isLoggedIn) {
    $view = in_array($view, ['login', 'register'], true) ? $view : 'login';
} elseif ($view === 'login' || $view === 'register' || $view === '') {
    $view = 'dashboard';
}

$allowedViews = ['dashboard', 'orders', 'profile', 'addresses', 'security', 'favorites'];
if ($isLoggedIn && !in_array($view, $allowedViews, true)) {
    $view = 'dashboard';
}

$customer = $customerRow ? shop_auth_public_customer($customerRow) : null;
$firstName = $customer ? explode(' ', trim($customer['name']))[0] : '';
$userInitials = '';
if ($customer) {
    $nameParts = preg_split('/\s+/u', trim($customer['name'])) ?: [];
    foreach (array_slice($nameParts, 0, 2) as $part) {
        $userInitials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
}
if ($userInitials === '') {
    $userInitials = 'U';
}

$heroCarImages = [
    'img/hero-car.png',
    'img/car1.png',
    'assets/images/products/1.jpg',
    'assets/images/products/2.jpg',
    'assets/images/products/3.jpg',
];
$heroCarImage = $heroCarImages[array_rand($heroCarImages)];
$heroCarFallback = 'img/hero-car.png';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isLoggedIn ? 'Contul meu' : 'Autentificare' ?> — Besoiu Piese Auto</title>
    <meta name="description" content="Cont personal Besoiu Piese Auto — autentificare, înregistrare, comenzi și date de livrare.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php besoiu_render_styles('account', ['assets/css/account-page.css']); ?>
</head>
<body>
<div class="page">

<?php include_once 'system/header.php'; ?>

<?php if (!$isLoggedIn): ?>
<section class="ac-hero">
    <div class="container">
        <div class="ac-hero-label">Cont client</div>
        <h1 class="ac-hero-title">Contul meu</h1>
        <p class="ac-hero-sub">Autentifică-te sau creează un cont pentru a urmări comenzile tale.</p>
    </div>
</section>
<?php endif; ?>

<main class="ac-main<?= $isLoggedIn ? ' is-account' : '' ?>">
    <div class="container">

        <?php if (!$isLoggedIn): ?>
        <div class="ac-auth-wrap">
            <div class="ac-auth-tabs">
                <button type="button" class="ac-auth-tab<?= $view === 'login' ? ' active' : '' ?>" data-tab="login">Intră în cont</button>
                <button type="button" class="ac-auth-tab<?= $view === 'register' ? ' active' : '' ?>" data-tab="register">Înregistrare</button>
            </div>
            <div class="ac-auth-body">
                <div class="ac-auth-panel<?= $view === 'login' ? ' active' : '' ?>" data-panel="login">
                    <h2>Autentificare</h2>
                    <p class="sub">Introdu emailul și parola contului tău.</p>
                    <form class="ac-form" id="ac-login-form" autocomplete="on">
                        <div class="ac-field">
                            <label for="login-email">Email</label>
                            <input type="email" id="login-email" name="email" required placeholder="email@exemplu.ro">
                        </div>
                        <div class="ac-field">
                            <label for="login-password">Parolă</label>
                            <input type="password" id="login-password" name="password" required placeholder="Parola ta">
                        </div>
                        <button type="submit" class="ac-btn">Intră în cont</button>
                    </form>
                    <div class="ac-status" id="ac-login-status"></div>
                </div>

                <div class="ac-auth-panel<?= $view === 'register' ? ' active' : '' ?>" data-panel="register">
                    <h2>Creează cont</h2>
                    <p class="sub">Completează datele pentru un cont personal Besoiu Piese Auto.</p>
                    <form class="ac-form" id="ac-register-form" autocomplete="on">
                        <div class="ac-field">
                            <label for="reg-name">Nume complet</label>
                            <input type="text" id="reg-name" name="name" required placeholder="Nume și prenume">
                        </div>
                        <div class="ac-row">
                            <div class="ac-field">
                                <label for="reg-email">Email</label>
                                <input type="email" id="reg-email" name="email" required placeholder="email@exemplu.ro">
                            </div>
                            <div class="ac-field">
                                <label for="reg-phone">Telefon</label>
                                <input type="tel" id="reg-phone" name="phone" placeholder="07xx xxx xxx">
                            </div>
                        </div>
                        <div class="ac-row">
                            <div class="ac-field">
                                <label for="reg-password">Parolă</label>
                                <input type="password" id="reg-password" name="password" required minlength="8" placeholder="Min. 8 caractere">
                            </div>
                            <div class="ac-field">
                                <label for="reg-password2">Confirmă parola</label>
                                <input type="password" id="reg-password2" name="password_confirm" required minlength="8" placeholder="Repetă parola">
                            </div>
                        </div>
                        <button type="submit" class="ac-btn">Creează contul</button>
                        <p style="font-size:13px;color:var(--muted);margin:0;">Prin înregistrare accepți <a href="termeni-conditii.php" class="ac-link">termenii</a> și <a href="politica-confidentialitate.php" class="ac-link">politica de confidențialitate</a>.</p>
                    </form>
                    <div class="ac-status" id="ac-register-status"></div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <div id="ac-global-status" class="ac-status ac-global-status"></div>

        <div class="ac-layout">
            <aside class="ac-sidebar">
                <div class="ac-user-chip">
                    <div class="ac-user-avatar"><?= shop_auth_h($userInitials) ?></div>
                    <div>
                        <strong><?= shop_auth_h($customer['name']) ?></strong>
                        <span><?= shop_auth_h($customer['email']) ?></span>
                    </div>
                </div>
                <nav class="ac-nav" aria-label="Meniu cont">
                    <button type="button" class="ac-nav-link<?= $view === 'dashboard' ? ' active' : '' ?>" data-view="dashboard"><i class="fa-solid fa-house"></i> Panou principal</button>
                    <button type="button" class="ac-nav-link<?= $view === 'orders' ? ' active' : '' ?>" data-view="orders"><i class="fa-solid fa-clock-rotate-left"></i> Istoric comenzi</button>
                    <button type="button" class="ac-nav-link<?= $view === 'addresses' ? ' active' : '' ?>" data-view="addresses"><i class="fa-solid fa-location-dot"></i> Adresele mele</button>
                    <button type="button" class="ac-nav-link<?= $view === 'profile' ? ' active' : '' ?>" data-view="profile"><i class="fa-solid fa-user"></i> Date cont</button>
                    <span class="ac-nav-link ac-nav-soon" title="În curând"><i class="fa-solid fa-bell"></i> Notificări</span>
                    <button type="button" class="ac-nav-link<?= $view === 'favorites' ? ' active' : '' ?>" data-view="favorites"><i class="fa-solid fa-heart"></i> Lista favorite</button>
                    <span class="ac-nav-link ac-nav-soon" title="În curând"><i class="fa-solid fa-star"></i> Recenzii</span>
                    <button type="button" class="ac-nav-link<?= $view === 'security' ? ' active' : '' ?>" data-view="security"><i class="fa-solid fa-lock"></i> Securitate</button>
                    <button type="button" class="ac-nav-link ac-nav-logout" data-ac-logout><i class="fa-solid fa-right-from-bracket"></i> Deconectare</button>
                </nav>
            </aside>

            <div class="ac-content">
                <section class="ac-panel ac-dash ac-card<?= $view === 'dashboard' ? ' active' : '' ?>" data-view="dashboard"
                    data-profile-name="<?= shop_auth_h($customer['name']) ?>"
                    data-profile-email="<?= shop_auth_h($customer['email']) ?>"
                    data-profile-phone="<?= shop_auth_h($customer['phone']) ?>"
                    data-profile-city="<?= shop_auth_h($customer['city']) ?>"
                    data-profile-address="<?= shop_auth_h($customer['address']) ?>"
                    data-user-first="<?= shop_auth_h($firstName) ?>">

                    <div class="ac-dash-hero">
                        <div class="ac-dash-hero-content">
                            <h2>Salut, <?= shop_auth_h($firstName) ?>! 👋</h2>
                            <p>Gestionează-ți comenzile, datele personale și plățile viitoare.</p>
                        </div>
                        <div class="ac-dash-hero-visual">
                            <img src="<?= shop_auth_h($heroCarImage) ?>" alt="Automobil — Besoiu Piese Auto" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='<?= shop_auth_h($heroCarFallback) ?>';">
                        </div>
                    </div>

                    <div class="ac-dash-body">
                        <div class="ac-dash-kpis">
                            <div class="ac-kpi ac-kpi--green">
                                <div class="ac-kpi-top">
                                    <div class="ac-kpi-icon"><i class="fa-solid fa-box"></i></div>
                                    <div class="ac-kpi-val" id="ac-stat-orders">0</div>
                                </div>
                                <div class="ac-kpi-label">Total comenzi</div>
                                <div class="ac-kpi-chart-wrap"><canvas id="ac-chart-kpi-orders" aria-label="Grafic comenzi"></canvas></div>
                            </div>
                            <div class="ac-kpi ac-kpi--blue">
                                <div class="ac-kpi-top">
                                    <div class="ac-kpi-icon"><i class="fa-solid fa-spinner"></i></div>
                                    <div class="ac-kpi-val" id="ac-stat-active">0</div>
                                </div>
                                <div class="ac-kpi-label">În procesare</div>
                                <div class="ac-kpi-chart-wrap"><canvas id="ac-chart-kpi-active" aria-label="Grafic comenzi active"></canvas></div>
                            </div>
                            <div class="ac-kpi ac-kpi--teal">
                                <div class="ac-kpi-top">
                                    <div class="ac-kpi-icon"><i class="fa-solid fa-truck"></i></div>
                                    <div class="ac-kpi-val" id="ac-stat-delivered">0</div>
                                </div>
                                <div class="ac-kpi-label">Livrate</div>
                                <div class="ac-kpi-chart-wrap"><canvas id="ac-chart-kpi-delivered" aria-label="Grafic comenzi livrate"></canvas></div>
                            </div>
                            <div class="ac-kpi ac-kpi--violet">
                                <div class="ac-kpi-top">
                                    <div class="ac-kpi-icon"><i class="fa-solid fa-wallet"></i></div>
                                    <div class="ac-kpi-val" id="ac-stat-spent">0</div>
                                </div>
                                <div class="ac-kpi-label">Total cheltuit</div>
                                <div class="ac-kpi-chart-wrap"><canvas id="ac-chart-kpi-spent" aria-label="Grafic cheltuieli"></canvas></div>
                            </div>
                        </div>

                        <div class="ac-dash-charts-row">
                            <div class="ac-chart-card">
                                <h4>Comenzi pe luni</h4>
                                <p>Ultimele 6 luni</p>
                                <div class="ac-chart-wrap"><canvas id="ac-chart-monthly-orders"></canvas></div>
                            </div>
                            <div class="ac-chart-card">
                                <h4>Cheltuieli (RON)</h4>
                                <p>Evoluție lunară</p>
                                <div class="ac-chart-wrap"><canvas id="ac-chart-monthly-spent"></canvas></div>
                            </div>
                        </div>

                        <div class="ac-dash-main-row">
                            <div class="ac-dash-block">
                                <div class="ac-dash-block-head">
                                    <h3>Ultima comandă</h3>
                                    <button type="button" class="ac-dash-block-link" data-view="orders">Vezi comanda</button>
                                </div>
                                <div class="ac-dash-block-body" id="ac-dash-last-order">
                                    <div class="ac-dash-empty"><i class="fa-solid fa-spinner fa-spin"></i> Se încarcă...</div>
                                </div>
                            </div>

                            <div class="ac-dash-block">
                                <div class="ac-dash-block-head">
                                    <h3>Profilul tău</h3>
                                    <button type="button" class="ac-dash-block-link" data-view="profile">Editează</button>
                                </div>
                                <div class="ac-dash-block-body">
                                    <div class="ac-dash-profile-row"><i class="fa-solid fa-user"></i><span><?= shop_auth_h($customer['name']) ?></span></div>
                                    <div class="ac-dash-profile-row"><i class="fa-solid fa-envelope"></i><span><?= shop_auth_h($customer['email']) ?></span></div>
                                    <div class="ac-dash-profile-row"><i class="fa-solid fa-phone"></i><span id="ac-dash-phone"><?= $customer['phone'] !== '' ? shop_auth_h($customer['phone']) : 'Necompletat' ?></span></div>
                                    <div class="ac-dash-progress" id="ac-dash-progress">
                                        <div class="ac-dash-progress-top">
                                            <span>Completarea profilului</span>
                                            <strong id="ac-dash-progress-pct">0%</strong>
                                        </div>
                                        <div class="ac-dash-progress-bar"><div class="ac-dash-progress-fill" id="ac-dash-progress-fill" style="width:0%"></div></div>
                                        <p class="ac-dash-progress-hint" id="ac-dash-progress-hint">Completează telefonul și adresa pentru checkout rapid.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h3 class="ac-dash-section-label">Activitatea ta</h3>
                        <div class="ac-dash-activity" id="ac-dash-activity"></div>

                        <div class="ac-dash-help-bar">
                            <div>
                                <h4>Ai nevoie de ajutor?</h4>
                                <p>Suntem aici pentru tine — comenzi, livrări, compatibilitate piese.</p>
                            </div>
                            <div class="ac-dash-help-actions">
                                <a href="<?= site_cms_h($dashPhoneHref) ?>" class="ac-dash-help-btn"><i class="fa-solid fa-phone"></i> <?= site_cms_h($dashPhone) ?></a>
                                <a href="/contact" class="ac-dash-help-btn"><i class="fa-solid fa-envelope"></i> Contact</a>
                                <a href="/catalog" class="ac-dash-help-btn"><i class="fa-solid fa-magnifying-glass"></i> Catalog</a>
                            </div>
                        </div>

                        <div class="ac-dash-quick-row">
                            <button type="button" class="ac-quick-pill" data-view="orders"><i class="fa-solid fa-box"></i> Urmărește comanda</button>
                            <button type="button" class="ac-quick-pill" data-view="orders"><i class="fa-solid fa-clock-rotate-left"></i> Istoric comenzi</button>
                            <button type="button" class="ac-quick-pill" data-view="addresses"><i class="fa-solid fa-location-dot"></i> Adresele mele</button>
                            <button type="button" class="ac-quick-pill" data-view="security"><i class="fa-solid fa-shield-halved"></i> Securitate cont</button>
                        </div>
                    </div>
                </section>

                <section class="ac-panel ac-card<?= $view === 'orders' ? ' active' : '' ?>" data-view="orders">
                    <h2>Istoric comenzi</h2>
                    <p class="ac-card-sub">Istoricul comenzilor plasate cu emailul <?= shop_auth_h($customer['email']) ?>.</p>
                    <div id="ac-orders-empty" class="ac-empty" style="display:none;">
                        <i class="fa-solid fa-box-open"></i>
                        Nu ai comenzi încă. <a href="/catalog" class="ac-link">Explorează magazinul</a>.
                    </div>
                    <div id="ac-orders-list"></div>
                </section>

                <section class="ac-panel ac-card<?= $view === 'profile' ? ' active' : '' ?>" data-view="profile">
                    <h2>Date cont</h2>
                    <p class="ac-card-sub">Actualizează numele și datele de contact.</p>
                    <form class="ac-form" id="ac-profile-form">
                        <div class="ac-field">
                            <label for="profile-name">Nume complet</label>
                            <input type="text" id="profile-name" name="name" required value="<?= shop_auth_h($customer['name']) ?>">
                        </div>
                        <div class="ac-row">
                            <div class="ac-field">
                                <label for="profile-email">Email</label>
                                <input type="email" id="profile-email" value="<?= shop_auth_h($customer['email']) ?>" disabled>
                            </div>
                            <div class="ac-field">
                                <label for="profile-phone">Telefon</label>
                                <input type="tel" id="profile-phone" name="phone" value="<?= shop_auth_h($customer['phone']) ?>" placeholder="07xx xxx xxx">
                            </div>
                        </div>
                        <button type="submit" class="ac-btn">Salvează modificările</button>
                    </form>
                </section>

                <section class="ac-panel ac-card<?= $view === 'addresses' ? ' active' : '' ?>" data-view="addresses">
                    <h2>Adrese de livrare</h2>
                    <p class="ac-card-sub">Adresa principală folosită la comenzi.</p>
                    <form class="ac-form" id="ac-address-form">
                        <div class="ac-row">
                            <div class="ac-field">
                                <label for="profile-city">Oraș</label>
                                <input type="text" id="profile-city" name="city" form="ac-profile-form" value="<?= shop_auth_h($customer['city']) ?>" placeholder="Timișoara">
                            </div>
                            <div class="ac-field">
                                <label for="profile-postal">Cod poștal</label>
                                <input type="text" id="profile-postal" name="postal_code" form="ac-profile-form" value="<?= shop_auth_h($customer['postal_code']) ?>" placeholder="300000">
                            </div>
                        </div>
                        <div class="ac-field">
                            <label for="profile-address">Adresă completă</label>
                            <textarea id="profile-address" name="address" form="ac-profile-form" placeholder="Strada, număr, bloc, apartament"><?= shop_auth_h($customer['address']) ?></textarea>
                        </div>
                        <button type="submit" class="ac-btn" form="ac-profile-form">Salvează adresa</button>
                    </form>
                </section>

                <section class="ac-panel ac-card<?= $view === 'security' ? ' active' : '' ?>" data-view="security">
                    <h2>Securitate</h2>
                    <p class="ac-card-sub">Schimbă parola contului tău.</p>
                    <form class="ac-form" id="ac-password-form">
                        <div class="ac-field">
                            <label for="pwd-current">Parola actuală</label>
                            <input type="password" id="pwd-current" name="current_password" required>
                        </div>
                        <div class="ac-row">
                            <div class="ac-field">
                                <label for="pwd-new">Parolă nouă</label>
                                <input type="password" id="pwd-new" name="new_password" required minlength="8">
                            </div>
                            <div class="ac-field">
                                <label for="pwd-new2">Confirmă parola nouă</label>
                                <input type="password" id="pwd-new2" name="new_password_confirm" required minlength="8">
                            </div>
                        </div>
                        <button type="submit" class="ac-btn">Schimbă parola</button>
                    </form>
                </section>

                <section class="ac-panel ac-card<?= $view === 'favorites' ? ' active' : '' ?>" data-view="favorites">
                    <h2>Lista favorite</h2>
                    <p class="ac-card-sub">Produsele salvate din catalog sau pagina de produs.</p>
                    <div id="ac-favorites-empty" class="ac-empty" style="display:none;">
                        <i class="fa-regular fa-heart"></i>
                        Nu ai produse favorite încă. <a href="/catalog" class="ac-link">Explorează catalogul</a>.
                    </div>
                    <div id="ac-favorites-list" class="ac-favorites-list"></div>
                </section>
            </div>
        </div>
        <?php endif; ?>

    </div>
</main>

<?php include_once 'system/footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
<?php besoiu_render_scripts('account', ['assets/js/cont.js']); ?>
</body>
</html>
