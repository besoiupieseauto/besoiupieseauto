<?php
require_once __DIR__ . '/system/page-init.php';
require_once __DIR__ . '/system/site-content.php';
require_once __DIR__ . '/system/site-live-cms.php';
require_once __DIR__ . '/system/site-builder.php';
require_once __DIR__ . '/system/besoiu-assets.php';

$GLOBALS['bpaCmsPage'] = 'contact';

$contactDefaults = [
    'title' => 'Contact',
    'description' => 'Contactează echipa Besoiu Piese Auto. Răspundem în maxim 2 ore în zilele lucrătoare.',
    'hero_label' => 'Hai să',
    'hero_title' => 'VORBIM',
    'hero_subtitle' => 'Echipa noastră răspunde în maxim 2 ore în zilele lucrătoare. Suntem aici pentru orice întrebare legată de piese auto.',
    'faq' => [
        ['q' => 'Cum știu dacă piesa este compatibilă cu mașina mea?', 'a' => 'Folosește căutarea după numărul de serie (VIN) sau adaugă mașina în contul tău. Echipa noastră verifică compatibilitatea înainte de expediere.'],
        ['q' => 'Care este timpul de livrare?', 'a' => 'Comenzile plasate până la ora 14:00 sunt expediate în aceeași zi lucrătoare. Termenul standard de livrare este de 24-48 de ore prin curierat rapid în toată România.'],
        ['q' => 'Care este politica de retur?', 'a' => 'Ai dreptul de retur în 14 zile calendaristice de la primire, conform legislației. Piesa trebuie să fie în stare originală, neinstalată. Contactează-ne și îți trimitem eticheta de retur gratuit.'],
        ['q' => 'Ce metode de plată acceptați?', 'a' => 'Acceptăm plata ramburs la curier, transfer bancar și plata online cu cardul. Pentru comenzi mari, oferim și posibilitatea plății în rate prin partenerii noștri financiari.'],
        ['q' => 'Cum pot obține suport dacă am o problemă?', 'a' => 'Ne poți contacta telefonic la 0726 498 573 (Luni-Vineri, 09:00-18:00), prin email la contact@besoiupieseauto.ro sau prin formularul de pe această pagină. Răspundem în maxim 2 ore.'],
    ],
];
$contactPage = site_content_page('contact', $contactDefaults);
$contactBlocks = site_content_blocks('contact');
$contactFaq = is_array($contactPage['faq'] ?? null) ? $contactPage['faq'] : [];
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) ($contactPage['title'] ?? 'Contact'), ENT_QUOTES, 'UTF-8') ?> — Besoiu Piese Auto</title>
    <meta name="description" content="<?= htmlspecialchars((string) ($contactPage['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php besoiu_render_styles('minimal', ['assets/css/contact-page.css']); ?>
</head>
<body>
<div class="page">

<?php include_once 'system/header.php'; ?>

<main id="main-content">

<!-- ══ HERO ══ -->
<section class="ct-hero">
    <div class="container">
        <div>
            <?php site_live_cms_tag('contact', 'hero_label', 'div', (string) ($contactPage['hero_label'] ?? ''), ['class' => 'ct-eyebrow']); ?>
            <?php site_live_cms_tag('contact', 'hero_title', 'div', (string) ($contactPage['hero_title'] ?? 'VORBIM'), ['class' => 'ct-headline']); ?>
            <div class="ct-swoosh"></div>
            <?php site_live_cms_tag('contact', 'hero_subtitle', 'p', (string) ($contactPage['hero_subtitle'] ?? ''), ['class' => 'ct-subtitle']); ?>
        </div>
        <div class="ct-visual">
            <div class="ct-blob">
                <span class="ct-blob-icon"><i class="fa-solid fa-phone"></i></span>
            </div>
            <?php foreach (($contactBlocks['float_cards'] ?? []) as $index => $card): ?>
            <?php
                $cardTitle = trim((string) ($card['title'] ?? ''));
                $cardIcon = (string) ($card['icon'] ?? 'fa-phone');
                $cardHref = trim((string) ($card['link_href'] ?? ''));
                if ($cardHref === '' && $cardIcon === 'fa-phone' && $cardTitle !== '') {
                    $cardHref = site_phone_to_tel_href($cardTitle);
                } elseif ($cardHref === '' && $cardIcon === 'fa-envelope' && $cardTitle !== '') {
                    $cardHref = 'mailto:' . $cardTitle;
                }
                $cardLinkId = ($cardIcon === 'fa-phone' && $cardHref !== '' && $index === 0) ? 'contact-float-phone' : '';
            ?>
            <div class="ct-float-card ct-fc<?= $index + 1 ?>">
                <div class="fc-icon"><i class="fa-solid <?= site_cms_h($cardIcon) ?>"></i></div>
                <div><strong><?php if ($cardHref !== ''): ?><a href="<?= site_cms_h($cardHref) ?>" class="ct-float-link"<?= $cardLinkId !== '' ? ' id="' . site_cms_h($cardLinkId) . '"' : '' ?>><?= site_cms_h($cardTitle) ?></a><?php else: ?><?= site_cms_h($cardTitle) ?><?php endif; ?></strong><span><?= site_cms_h($card['subtitle'] ?? '') ?></span></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php site_builder_render_zone('contact', 'after_hero'); ?>
<?php site_builder_render_zone('contact', 'main'); ?>

<!-- ══ INFO STRIP ══ -->
<div class="ct-strip">
    <div class="ct-strip-inner">
        <?php foreach (($contactBlocks['strip'] ?? []) as $stripItem): ?>
        <?php
            $stripTitle = trim((string) ($stripItem['title'] ?? ''));
            $stripIcon = (string) ($stripItem['icon'] ?? 'fa-circle');
            $stripHref = trim((string) ($stripItem['link_href'] ?? ''));
            if ($stripHref === '' && $stripIcon === 'fa-phone' && $stripTitle !== '') {
                $stripHref = site_phone_to_tel_href($stripTitle);
            } elseif ($stripHref === '' && $stripIcon === 'fa-envelope' && $stripTitle !== '') {
                $stripHref = 'mailto:' . $stripTitle;
            }
            $stripLinkId = ($stripIcon === 'fa-phone' && $stripHref !== '') ? 'contact-strip-phone' : '';
        ?>
        <div class="ct-strip-item">
            <div class="si-icon"><i class="fa-solid <?= site_cms_h($stripIcon) ?>"></i></div>
            <div><strong><?php if ($stripHref !== ''): ?><a href="<?= site_cms_h($stripHref) ?>" class="ct-strip-link"<?= $stripLinkId !== '' ? ' id="' . site_cms_h($stripLinkId) . '"' : '' ?>><?= site_cms_h($stripTitle) ?></a><?php else: ?><?= site_cms_h($stripTitle) ?><?php endif; ?></strong><span><?= site_cms_h($stripItem['subtitle'] ?? '') ?></span></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ══ MAIN: FORMULAR + FAQ ══ -->
<section class="ct-main">
    <div class="container">
        <div class="ct-form-section">
            <h2><?= site_cms_h($contactBlocks['form']['title'] ?? 'Trimite-ne un mesaj') ?></h2>
            <p class="ct-form-sub"><?= site_cms_h($contactBlocks['form']['subtitle'] ?? '') ?></p>

            <form class="ct-form" id="contact-form">
                <div class="ct-field">
                    <label for="contact-name">Nume</label>
                    <input type="text" id="contact-name" name="name" placeholder="Numele tău complet" required>
                </div>

                <div class="ct-row">
                    <div class="ct-field">
                        <label for="contact-email">Email</label>
                        <input type="email" id="contact-email" name="email" placeholder="adresa@exemplu.ro" required>
                    </div>
                    <div class="ct-field">
                        <label for="contact-phone">Telefon</label>
                        <input type="tel" id="contact-phone" name="phone" placeholder="07xx xxx xxx">
                    </div>
                </div>

                <div class="ct-field">
                    <label for="contact-subject">Subiect</label>
                    <select id="contact-subject" name="subject">
                        <option value="general">Alege un subiect...</option>
                        <option value="compatibilitate">Verificare compatibilitate</option>
                        <option value="comanda">Comandă existentă</option>
                        <option value="oferta">Cerere de ofertă</option>
                        <option value="retur">Retur / Garanție</option>
                        <option value="altele">Altele</option>
                    </select>
                </div>

                <div class="ct-field">
                    <label for="contact-message">Mesaj</label>
                    <textarea id="contact-message" name="message_body" placeholder="Scrie mesajul tău aici..." required></textarea>
                </div>

                <button type="submit" class="ct-submit" id="contact-form-submit"><?= site_cms_h($contactBlocks['form']['submit'] ?? 'TRIMITE MESAJUL') ?> <i class="fa-solid fa-arrow-right"></i></button>

                <div class="ct-privacy"><i class="fa-solid fa-lock"></i> <?= site_cms_h($contactBlocks['form']['privacy'] ?? '') ?></div>

                <div id="contact-form-status" style="display:none;"></div>
            </form>
        </div>

        <div class="ct-right">
            <div class="ct-map">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2783.8!2d21.2!3d45.75!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zNDXCsDQ1JzAwLjAiTiAyMcKwMTInMDAuMCJF!5e0!3m2!1sro!2sro!4v1" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>

            <h3>Întrebări frecvente</h3>
            <ul class="ct-faq">
                <?php foreach ($contactFaq as $index => $faqItem): ?>
                <li class="ct-faq-item<?= $index === 0 ? ' open' : '' ?>">
                    <div class="ct-faq-q">
                        <span><?= htmlspecialchars((string) ($faqItem['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="ct-faq-toggle"><i class="fa-solid fa-plus"></i></span>
                    </div>
                    <div class="ct-faq-a"><?= $faqItem['a'] ?? '' ?></div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<!-- ══ CTA BOTTOM ══ -->
<section class="ct-cta">
    <div class="container">
        <div class="ct-cta-inner">
            <div class="ct-cta-title"><?= site_cms_html($contactBlocks['cta']['title_html'] ?? 'CONTACTEAZĂ-NE<br>RAPID') ?></div>
            <?php foreach (($contactBlocks['cta']['cards'] ?? []) as $ctaCard): ?>
            <div class="ct-cta-card">
                <div class="ct-cta-icon"><i class="fa-solid <?= site_cms_h($ctaCard['icon'] ?? 'fa-phone') ?>"></i></div>
                <strong><?= site_cms_h($ctaCard['title'] ?? '') ?></strong>
                <span><?= site_cms_h($ctaCard['text'] ?? '') ?></span>
                <a href="<?= site_cms_h($ctaCard['link_href'] ?? '#') ?>" class="ct-cta-link"<?= str_starts_with((string) ($ctaCard['link_href'] ?? ''), 'http') ? ' target="_blank"' : '' ?>><?= site_cms_h($ctaCard['link_label'] ?? '') ?> <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php site_builder_render_zone('contact', 'before_footer'); ?>

</main>

<?php include_once 'system/footer.php'; ?>

</div>

<?php besoiu_render_scripts('minimal', ['assets/js/contact-page.js']); ?>
</body>
</html>
