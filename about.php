<?php
require_once __DIR__ . '/system/page-init.php';
require_once __DIR__ . '/system/site-content.php';
require_once __DIR__ . '/system/site-live-cms.php';
require_once __DIR__ . '/system/site-builder.php';
require_once __DIR__ . '/system/besoiu-assets.php';

$GLOBALS['bpaCmsPage'] = 'about';

$aboutDefaults = [
    'title' => 'Despre Noi',
    'description' => 'Povestea Besoiu Piese Auto — piese originale și aftermarket de calitate, livrare rapidă, suport dedicat.',
    'hero_label' => 'Despre noi',
    'hero_title' => "POVESTEA\nNOASTRĂ",
    'hero_subtitle' => 'Din 2020, livrăm piese auto de calitate în toată România. Peste 15.000 de piese, 8.500 de comenzi livrate cu succes.',
    'cta' => [
        'title' => 'Ai nevoie de o piesă?',
        'subtitle' => 'Sună-ne sau caută în catalogul nostru — răspundem în maxim 2 ore',
        'primary' => ['label' => '0726 498 573', 'href' => 'tel:+40726498573'],
        'secondary' => ['label' => 'Vezi catalogul', 'href' => 'catalog.php'],
    ],
];
$aboutPage = site_content_page('about', $aboutDefaults);
$aboutCtaPhone = trim((string) ($aboutPage['cta']['primary']['label'] ?? '0726 498 573'));
$aboutCtaHref = site_phone_resolve_href(
    $aboutCtaPhone,
    (string) ($aboutPage['cta']['primary']['href'] ?? '')
);
if ($aboutCtaHref === '') {
    $aboutCtaHref = site_phone_to_tel_href($aboutCtaPhone);
}
$aboutBlocks = site_content_blocks('about');
$aboutHero = site_content_hero_parts((string) ($aboutPage['hero_title'] ?? 'POVESTEA|NOASTRĂ'));

$aboutHeroImages = [
    'img/hero-car.png',
    'img/car1.png',
    'img/images_groop.png',
    'img/product_images.png',
    'assets/images/products/1.jpg',
    'assets/images/products/2.jpg',
    'assets/images/products/3.jpg',
];
shuffle($aboutHeroImages);
[$aboutHeroMain, $aboutHeroSmOne, $aboutHeroSmTwo] = array_slice($aboutHeroImages, 0, 3);
$aboutHeroFallback = 'img/hero-car.png';

function about_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= about_h($aboutPage['title'] ?? 'Despre Noi') ?> — Besoiu Piese Auto</title>
    <meta name="description" content="<?= about_h($aboutPage['description'] ?? '') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php besoiu_render_styles('minimal', ['assets/css/about-page.css']); ?>
</head>
<body>
<div class="page">

<?php include_once 'system/header.php'; ?>

<main id="main-content">

<!-- ══ HERO ══ -->
<section class="ab-hero">
    <div class="ab-hero-left">
        <?php site_live_cms_tag('about', 'hero_label', 'div', (string) ($aboutPage['hero_label'] ?? 'Despre noi'), ['class' => 'ab-hero-label']); ?>
        <?php if (site_live_edit_mode()): ?>
            <?php site_live_cms_tag('about', 'hero_title', 'div', (string) ($aboutPage['hero_title'] ?? ''), ['class' => 'ab-hero-title']); ?>
        <?php else: ?>
        <div class="ab-hero-title"><?= about_h($aboutHero['main'] !== '' ? $aboutHero['main'] : 'POVESTEA') ?></div>
        <?php if (($aboutHero['secondary'] ?? '') !== ''): ?>
        <div class="ab-hero-outline"><?= about_h($aboutHero['secondary']) ?></div>
        <?php endif; ?>
        <?php endif; ?>
        <div class="ab-hero-line"></div>
        <?php site_live_cms_tag('about', 'hero_subtitle', 'p', (string) ($aboutPage['hero_subtitle'] ?? ''), ['class' => 'ab-hero-sub']); ?>
    </div>
    <div class="ab-hero-right">
        <div class="ab-hero-visual">
            <div class="ab-photo-main">
                <img src="<?= about_h($aboutHeroMain) ?>" alt="Automobil — Besoiu Piese Auto" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='<?= about_h($aboutHeroFallback) ?>';">
            </div>
            <div class="ab-photo-sm one">
                <img src="<?= about_h($aboutHeroSmOne) ?>" alt="Piese auto" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='<?= about_h($aboutHeroFallback) ?>';">
            </div>
            <div class="ab-photo-sm two">
                <img src="<?= about_h($aboutHeroSmTwo) ?>" alt="Service auto" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='<?= about_h($aboutHeroFallback) ?>';">
            </div>
        </div>
    </div>
</section>

<?php site_builder_render_zone('about', 'after_hero'); ?>
<?php site_builder_render_zone('about', 'main'); ?>

<!-- ══ STATS ══ -->
<div class="ab-stats">
    <?php foreach (($aboutBlocks['stats'] ?? []) as $stat): ?>
    <div class="ab-stat">
        <div class="ab-stat-icon"><i class="fa-solid <?= about_h($stat['icon'] ?? 'fa-star') ?>"></i></div>
        <div class="ab-stat-num"><?= about_h($stat['value'] ?? '') ?><?php if (!empty($stat['suffix_icon'])): ?><i class="fa-solid <?= about_h($stat['suffix_icon']) ?>" style="font-size:.65em;margin-left:2px;"></i><?php endif; ?></div>
        <div class="ab-stat-label"><?= about_h($stat['label'] ?? '') ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ══ STORY + TIMELINE ══ -->
<section class="ab-story">
    <div class="container">
        <div class="ab-story-left">
            <div class="ab-story-quote-bg">"</div>
            <div class="ab-story-label"><?= about_h($aboutBlocks['story']['label'] ?? 'Povestea noastră') ?></div>
            <p class="ab-story-p1"><?= about_h($aboutBlocks['story']['paragraph1'] ?? '') ?></p>
            <div class="ab-story-divider"></div>
            <p class="ab-story-p2"><?= about_h($aboutBlocks['story']['paragraph2'] ?? '') ?></p>
            <div class="ab-pullquote"><?= about_h($aboutBlocks['story']['pullquote'] ?? '') ?></div>
        </div>

        <div>
            <div class="ab-timeline-label"><?= about_h($aboutBlocks['timeline_label'] ?? 'Drumul nostru') ?></div>
            <div class="ab-timeline">
                <?php foreach (($aboutBlocks['timeline'] ?? []) as $tlItem): ?>
                <div class="ab-tl-item">
                    <div class="ab-tl-dot"></div>
                    <span class="ab-tl-year"><?= about_h($tlItem['year'] ?? '') ?></span>
                    <div class="ab-tl-title"><?= about_h($tlItem['title'] ?? '') ?></div>
                    <p class="ab-tl-desc"><?= about_h($tlItem['text'] ?? '') ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ══ WHY US ══ -->
<section class="ab-why">
    <div class="container">
        <h2 class="ab-why-title"><?= site_cms_html($aboutBlocks['why']['title_html'] ?? 'De ce să <span>ne alegi</span>') ?></h2>
        <div class="ab-why-grid">
            <?php foreach (($aboutBlocks['why']['cards'] ?? []) as $whyCard): ?>
            <div class="ab-why-card">
                <div class="ab-why-icon"><i class="fa-solid <?= about_h($whyCard['icon'] ?? 'fa-check') ?>"></i></div>
                <h3><?= about_h($whyCard['title'] ?? '') ?></h3>
                <p><?= about_h($whyCard['text'] ?? '') ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══ TRUST ══ -->
<section class="ab-trust">
    <div class="container">
        <div class="ab-trust-inner">
            <div>
                <h2 class="ab-trust-title"><?= about_h($aboutBlocks['trust']['title'] ?? '') ?></h2>
            </div>
            <div>
                <ul class="ab-trust-checks">
                    <?php foreach (($aboutBlocks['trust']['checks'] ?? []) as $check): ?>
                    <li><?= about_h($check) ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="ab-brands">
                    <?php foreach (($aboutBlocks['trust']['brands'] ?? []) as $brand): ?>
                    <span class="ab-brand" style="color:<?= about_h($brand['color'] ?? '#333') ?>"><?= about_h($brand['name'] ?? '') ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══ CTA ══ -->
<section class="ab-cta">
    <div class="container">
        <div class="ab-cta-inner">
            <?php site_live_cms_tag('about', 'cta.title', 'h2', (string) ($aboutPage['cta']['title'] ?? 'Ai nevoie de o piesă?')); ?>
            <?php site_live_cms_tag('about', 'cta.subtitle', 'p', (string) ($aboutPage['cta']['subtitle'] ?? '')); ?>
            <div class="ab-cta-btns">
                <a href="<?= about_h($aboutCtaHref) ?>" class="ab-btn-green"><i class="fa-solid fa-phone"></i> <?= about_h($aboutCtaPhone) ?></a>
                <a href="<?= about_h($aboutPage['cta']['secondary']['href'] ?? 'catalog.php') ?>" class="ab-btn-outline"><?= about_h($aboutPage['cta']['secondary']['label'] ?? 'Vezi catalogul') ?> <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</section>

<?php site_builder_render_zone('about', 'before_footer'); ?>

</main>

<?php include_once 'system/footer.php'; ?>

</div>
<?php besoiu_render_scripts('minimal'); ?>
</body>
</html>
