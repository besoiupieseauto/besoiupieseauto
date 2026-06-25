<?php
declare(strict_types=1);

if (!function_exists('static_page_h')) {
    function static_page_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('static_page_resolve_href')) {
    function static_page_resolve_href(string $href, string $displayLabel = ''): string
    {
        $href = trim($href);
        if ($href === '') {
            return $href;
        }

        if (!function_exists('site_phone_to_tel_href')) {
            require_once __DIR__ . '/site-content.php';
        }

        if (preg_match('/^tel:/i', $href)) {
            return site_phone_to_tel_href($href);
        }

        $digits = preg_replace('/\D+/', '', $href);
        if ($digits !== '' && strlen($digits) >= 9 && !str_contains($href, '.php') && !str_contains($href, '://')) {
            return site_phone_to_tel_href($href);
        }

        if ($displayLabel !== '' && preg_match('/^0?7\d[\d\s]{7,}/', preg_replace('/\D/', '', $displayLabel))) {
            return site_phone_to_tel_href($displayLabel);
        }

        return $href;
    }
}

if (!function_exists('render_static_page')) {
    /**
     * @param array{
     *   title:string,
     *   description?:string,
     *   hero_label?:string,
     *   hero_title:string,
     *   hero_subtitle?:string,
     *   sections?:array<int,array<string,mixed>>,
     *   faq?:array<int,array{q:string,a:string}>,
     *   cta?:array{title:string,subtitle?:string,primary?:array{label:string,href:string},secondary?:array{label:string,href:string}}
     * } $page
     */
    function render_static_page(array $page): void
    {
        require_once __DIR__ . '/page-init.php';
        require_once __DIR__ . '/besoiu-assets.php';
        require_once __DIR__ . '/site-live-cms.php';
        require_once __DIR__ . '/site-builder.php';

        $slug = trim((string) ($page['_slug'] ?? ''));
        if ($slug === '') {
            $slug = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php');
        }
        $GLOBALS['bpaCmsPage'] = $slug;

        $title = (string) ($page['title'] ?? 'Besoiu Piese Auto');
        $description = (string) ($page['description'] ?? '');
        $heroLabel = (string) ($page['hero_label'] ?? 'Besoiu Piese Auto');
        $heroTitle = (string) ($page['hero_title'] ?? $title);
        $heroSubtitle = (string) ($page['hero_subtitle'] ?? '');
        $sections = is_array($page['sections'] ?? null) ? $page['sections'] : [];
        $faq = is_array($page['faq'] ?? null) ? $page['faq'] : [];
        $cta = is_array($page['cta'] ?? null) ? $page['cta'] : null;
        $layoutClass = 'sp-layout';
        if ($faq !== [] && count($sections) <= 1) {
            $layoutClass .= ' sp-layout--faq-focus';
        }
        ?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= static_page_h($title) ?> — Besoiu Piese Auto</title>
    <?php if ($description !== ''): ?>
    <meta name="description" content="<?= static_page_h($description) ?>">
    <?php endif; ?>
    <?php besoiu_render_fonts(false); ?>
    <?php besoiu_render_styles('minimal', ['assets/css/static-pages.css']); ?>
</head>
<body>
<div class="page">
<?php include __DIR__ . '/header.php'; ?>

<section class="sp-hero">
    <div class="container">
        <nav class="sp-breadcrumb" aria-label="Breadcrumb">
            <a href="/">Acasă</a>
            <span>/</span>
            <span><?= static_page_h($heroTitle) ?></span>
        </nav>
        <div class="sp-hero-label"><?php site_live_cms_tag($slug, 'hero_label', 'div', $heroLabel); ?></div>
        <?php site_live_cms_tag($slug, 'hero_title', 'h1', $heroTitle, ['class' => 'sp-hero-title']); ?>
        <?php if ($heroSubtitle !== '' || site_live_edit_mode()): ?>
        <?php site_live_cms_tag($slug, 'hero_subtitle', 'p', $heroSubtitle, ['class' => 'sp-hero-sub']); ?>
        <?php endif; ?>
    </div>
</section>

<?php site_builder_render_zone($slug, 'after_hero'); ?>

<?php
$builderMainActive = site_live_edit_mode() || site_builder_zone_has_blocks($slug, 'main');
?>

<?php if ($builderMainActive): ?>
<?php site_builder_render_zone($slug, 'main'); ?>
<?php else: ?>
<main class="sp-main">
    <div class="container <?= static_page_h($layoutClass) ?>">
        <div class="sp-content">
            <?php foreach ($sections as $section): ?>
                <?php
                    $type = (string) ($section['type'] ?? 'content');
                    $heading = (string) ($section['title'] ?? '');
                ?>
                <?php if ($type === 'content'): ?>
                <article class="sp-block">
                    <?php if ($heading !== ''): ?><h2><?= static_page_h($heading) ?></h2><?php endif; ?>
                    <?php foreach (($section['paragraphs'] ?? []) as $paragraph): ?>
                        <p><?= $paragraph ?></p>
                    <?php endforeach; ?>
                    <?php if (!empty($section['list'])): ?>
                        <<?= !empty($section['ordered']) ? 'ol' : 'ul' ?>>
                            <?php foreach ($section['list'] as $item): ?>
                                <li><?= $item ?></li>
                            <?php endforeach; ?>
                        <<?= !empty($section['ordered']) ? 'ol' : 'ul' ?>>
                    <?php endif; ?>
                </article>
                <?php elseif ($type === 'cards'): ?>
                <article class="sp-block">
                    <?php if ($heading !== ''): ?><h2><?= static_page_h($heading) ?></h2><?php endif; ?>
                    <div class="sp-cards">
                        <?php foreach (($section['items'] ?? []) as $item): ?>
                        <div class="sp-card">
                            <strong><?= static_page_h($item['title'] ?? '') ?></strong>
                            <p><?= $item['text'] ?? '' ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </article>
                <?php elseif ($type === 'steps'): ?>
                <article class="sp-block">
                    <?php if ($heading !== ''): ?><h2><?= static_page_h($heading) ?></h2><?php endif; ?>
                    <div class="sp-steps">
                        <?php foreach (($section['items'] ?? []) as $index => $item): ?>
                        <div class="sp-step">
                            <div class="sp-step-num"><?= (int) $index + 1 ?></div>
                            <div>
                                <strong><?= static_page_h($item['title'] ?? '') ?></strong>
                                <p><?= $item['text'] ?? '' ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </article>
                <?php elseif ($type === 'blog'): ?>
                <article class="sp-block">
                    <?php if ($heading !== ''): ?><h2><?= static_page_h($heading) ?></h2><?php endif; ?>
                    <div class="sp-blog-grid">
                        <?php foreach (($section['items'] ?? []) as $item): ?>
                        <?php
                            $blogSlug = trim((string) ($item['slug'] ?? ''));
                            $blogHref = $blogSlug !== '' ? 'blog-articol.php?slug=' . rawurlencode($blogSlug) : '';
                        ?>
                        <?php if ($blogHref !== ''): ?><a href="<?= static_page_h($blogHref) ?>" class="sp-blog-item sp-blog-item--link"><?php else: ?><div class="sp-blog-item"><?php endif; ?>
                            <span class="sp-blog-tag"><?= static_page_h($item['tag'] ?? 'Articole') ?></span>
                            <h3><?= static_page_h($item['title'] ?? '') ?></h3>
                            <p><?= $item['text'] ?? '' ?></p>
                        <?php if ($blogHref !== ''): ?></a><?php else: ?></div><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </article>
                <?php elseif ($type === 'jobs'): ?>
                <article class="sp-block">
                    <?php if ($heading !== ''): ?><h2><?= static_page_h($heading) ?></h2><?php endif; ?>
                    <div class="sp-jobs">
                        <?php foreach (($section['items'] ?? []) as $item): ?>
                        <div class="sp-job">
                            <div>
                                <h3><?= static_page_h($item['title'] ?? '') ?></h3>
                                <p><?= static_page_h($item['location'] ?? '') ?> · <?= static_page_h($item['type'] ?? '') ?></p>
                            </div>
                            <a href="/contact">Aplică</a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </article>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($faq !== []): ?>
            <article class="sp-block sp-faq-block">
                <h2>Întrebări frecvente</h2>
                <ul class="sp-faq">
                    <?php foreach ($faq as $faqIndex => $item): ?>
                    <li class="sp-faq-item<?= $faqIndex === 0 ? ' open' : '' ?>">
                        <div class="sp-faq-q">
                            <span><?= static_page_h($item['q'] ?? '') ?></span>
                            <span class="sp-faq-toggle">+</span>
                        </div>
                        <div class="sp-faq-a"><?= $item['a'] ?? '' ?></div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </article>
            <?php endif; ?>
        </div>

        <aside class="sp-sidebar">
            <div class="sp-side-box">
                <h4>Informații utile</h4>
                <ul class="sp-side-links">
                    <li><a href="cum-comand.php">Cum comand</a></li>
                    <li><a href="livrare-plata.php">Livrare și plată</a></li>
                    <li><a href="retur-garantie.php">Retur și garanție</a></li>
                    <li><a href="intrebari-frecvente.php">Întrebări frecvente</a></li>
                    <li><a href="termeni-conditii.php">Termeni și condiții</a></li>
                    <li><a href="politica-confidentialitate.php">Confidențialitate</a></li>
                    <li><a href="politica-cookies.php">Cookies</a></li>
                </ul>
            </div>
            <div class="sp-side-box sp-side-contact">
                <h4>Ai nevoie de ajutor?</h4>
                <ul>
                    <li><span class="site-icon site-icon--sm"><i class="fa-solid fa-phone"></i></span> <a href="tel:+40726498573">0726 498 573</a></li>
                    <li><span class="site-icon site-icon--sm"><i class="fa-solid fa-envelope"></i></span> <a href="mailto:contact@besoiupieseauto.ro">contact@besoiupieseauto.ro</a></li>
                    <li><span class="site-icon site-icon--sm"><i class="fa-solid fa-location-dot"></i></span> Timișoara, Str. Stan Vidrighin nr. 14</li>
                </ul>
                <a href="/contact" class="sp-btn-green sp-cta-full">Contactează-ne</a>
            </div>
        </aside>
    </div>
</main>
<?php endif; ?>

<?php if ($cta !== null && !$builderMainActive): ?>
<section class="sp-cta">
    <div class="container">
        <div class="sp-cta-inner">
            <?php site_live_cms_tag($slug, 'cta.title', 'h2', (string) ($cta['title'] ?? 'Ai nevoie de ajutor?')); ?>
            <?php if (!empty($cta['subtitle']) || site_live_edit_mode()): ?>
            <?php site_live_cms_tag($slug, 'cta.subtitle', 'p', (string) ($cta['subtitle'] ?? '')); ?>
            <?php endif; ?>
            <div class="sp-cta-btns">
                <?php if (!empty($cta['primary'])): ?>
                <?php
                    $ctaPrimaryLabel = (string) ($cta['primary']['label'] ?? 'Contact');
                    $ctaPrimaryHref = static_page_resolve_href(
                        (string) ($cta['primary']['href'] ?? 'contact.php'),
                        $ctaPrimaryLabel
                    );
                ?>
                <a href="<?= static_page_h($ctaPrimaryHref) ?>" class="sp-btn-green"><?= static_page_h($ctaPrimaryLabel) ?></a>
                <?php endif; ?>
                <?php if (!empty($cta['secondary'])): ?>
                <?php
                    $ctaSecondaryLabel = (string) ($cta['secondary']['label'] ?? 'Catalog');
                    $ctaSecondaryHref = static_page_resolve_href(
                        (string) ($cta['secondary']['href'] ?? '/catalog'),
                        $ctaSecondaryLabel
                    );
                ?>
                <a href="<?= static_page_h($ctaSecondaryHref) ?>" class="sp-btn-outline"><?= static_page_h($ctaSecondaryLabel) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php site_builder_render_zone($slug, 'before_footer'); ?>

<?php include __DIR__ . '/footer.php'; ?>
</div>
<?php besoiu_render_scripts('minimal', ['assets/js/static-page-faq.js']); ?>
</body>
</html>
        <?php
    }
}
