<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_once BESOIU_LEGACY . '/page-init.php';
require_once BESOIU_LEGACY . '/site-content.php';
require_once BESOIU_LEGACY . '/besoiu-assets.php';

function blog_article_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$slug = trim((string) ($_GET['slug'] ?? ''));
$post = $slug !== '' ? site_content_blog_post_by_slug($slug) : null;

if ($post === null) {
    http_response_code(404);
    header('Location: blog.php');
    exit;
}

$title = (string) ($post['title'] ?? 'Articol');
$tag = (string) ($post['tag'] ?? 'Articole');
$excerpt = (string) ($post['excerpt'] ?? '');
$bodyHtml = (string) ($post['body_html'] ?? '');
$featuredImage = trim((string) ($post['featured_image'] ?? ''));
$publishedAt = (string) ($post['published_at'] ?? '');
$dateLabel = $publishedAt !== '' ? date('d.m.Y', strtotime($publishedAt)) : '';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= blog_article_h($title) ?> — Besoiu Piese Auto</title>
    <?php if ($excerpt !== ''): ?>
    <meta name="description" content="<?= blog_article_h($excerpt) ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php besoiu_render_styles('minimal', ['assets/css/blog-article-page.css']); ?>
</head>
<body>
<div class="page">
<?php include_once BESOIU_LEGACY . '/header.php'; ?>

<section class="ba-hero">
    <div class="container">
        <nav class="ba-breadcrumb" aria-label="Breadcrumb">
            <a href="index.php">Acasă</a>
            <span>/</span>
            <a href="blog.php">Blog</a>
            <span>/</span>
            <span><?= blog_article_h($title) ?></span>
        </nav>
        <span class="ba-tag"><?= blog_article_h($tag) ?></span>
        <h1 class="ba-title"><?= blog_article_h($title) ?></h1>
        <?php if ($dateLabel !== ''): ?>
        <div class="ba-meta">Publicat la <?= blog_article_h($dateLabel) ?></div>
        <?php endif; ?>
    </div>
</section>

<main class="ba-main">
    <div class="container ba-layout">
        <article class="ba-article">
            <a href="blog.php" class="ba-back"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Înapoi la blog</a>
            <?php if ($featuredImage !== ''): ?>
            <img src="<?= blog_article_h($featuredImage) ?>" alt="<?= blog_article_h($title) ?>" class="ba-featured" width="1200" height="675" loading="eager" fetchpriority="high" decoding="async">
            <?php endif; ?>
            <?php if ($excerpt !== ''): ?>
            <p class="ba-excerpt"><?= blog_article_h($excerpt) ?></p>
            <?php endif; ?>
            <div class="ba-body">
                <?= $bodyHtml !== '' ? $bodyHtml : '<p>Conținutul articolului va fi disponibil în curând.</p>' ?>
            </div>
        </article>

        <aside class="ba-sidebar">
            <div class="ba-side-box">
                <h4>Ai nevoie de ajutor?</h4>
                <p class="ba-side-text">Echipa noastră te ajută să găsești piesa potrivită pentru mașina ta.</p>
                <a href="contact.php" class="ba-cta-btn">Contactează-ne</a>
            </div>
        </aside>
    </div>
</main>

<?php include_once BESOIU_LEGACY . '/footer.php'; ?>
</div>
<?php besoiu_render_scripts('minimal'); ?>
</body>
</html>
