<?php
require_once __DIR__ . '/site-content.php';
$global = site_content_blocks('global');
$footerDefaults = site_defaults_blocks('global')['footer'];
$footer = $global['footer'] ?? $footerDefaults;
$footerSocial = is_array($footer['social'] ?? null) ? $footer['social'] : ($footerDefaults['social'] ?? []);
$footerPhone = trim((string) ($footer['phone'] ?? ''));
$footerPhoneHref = site_phone_resolve_href($footerPhone, trim((string) ($footer['phone_href'] ?? '')));
if ($footerPhoneHref === '' && $footerPhone !== '') {
    $footerPhoneHref = site_phone_to_tel_href($footerPhone);
} elseif ($footerPhoneHref === '') {
    $footerPhoneHref = site_phone_to_tel_href('0726498573');
}
$footerEmail = trim((string) ($footer['email'] ?? ''));
$footerEmailHref = $footerEmail !== '' ? 'mailto:' . $footerEmail : '';
?>
<footer class="footer">
    <div class="container">
        <div class="footer-main">
            <div class="footer-block footer-brand">
                <a class="logo" href="/">
                    <img src="img/logo.png" alt="Besoiu Piese Auto" class="logo-img">
                </a>
                <p><?= site_cms_h($footer['description'] ?? '') ?></p>
                <div class="social" id="footer-social-links">
                    <?php foreach ($footerSocial as $si => $socialItem): ?>
                        <?php
                            $socialHref = trim((string) ($socialItem['href'] ?? ''));
                            $socialLabel = trim((string) ($socialItem['label'] ?? 'Social'));
                            $socialIcon = trim((string) ($socialItem['icon'] ?? ''));
                            if ($socialHref === '' || $socialHref === '#') {
                                continue;
                            }
                        ?>
                    <a href="<?= site_cms_h(besoiu_normalize_href($socialHref)) ?>" aria-label="<?= site_cms_h($socialLabel) ?>" target="_blank" rel="noopener noreferrer"><?php if (function_exists('site_live_cms_image_tag')): ?><?php site_live_cms_image_tag('global', 'footer.social.' . $si . '.icon', $socialIcon, ['class' => 'footer-social-icon', 'alt' => '', 'role' => 'presentation', 'data-cms-variant' => 'icon']); ?><?php else: ?><img src="<?= site_cms_h($socialIcon) ?>" alt="" class="footer-social-icon" role="presentation"><?php endif; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="footer-block">
                <h4>Informații</h4>
                <ul>
                    <li><a href="/despre">Despre noi</a></li>
                    <li><a href="/blog">Blog</a></li>
                    <li><a href="/cariere">Cariere</a></li>
                    <li><a href="/contact">Contact</a></li>
                    <li><a href="/termeni-conditii">Termeni și condiții</a></li>
                    <li><a href="/politica-confidentialitate">Confidențialitate</a></li>
                    <li><a href="/politica-cookies">Cookies</a></li>
                </ul>
            </div>
            <div class="footer-block">
                <h4>Suport clienți</h4>
                <ul>
                    <li><a href="/cum-comand">Cum comand</a></li>
                    <li><a href="/livrare-plata">Livrare și plată</a></li>
                    <li><a href="/retur-garantie">Retur și garanție</a></li>
                    <li><a href="/intrebari-frecvente">Întrebări frecvente</a></li>
                </ul>
            </div>
            <div class="footer-block footer-contact">
                <h4>Contact</h4>
                <ul>
                    <li><img src="img/icons/12_telefon.svg" alt="" class="footer-contact-icon" role="presentation"><?php if ($footerPhone !== ''): ?><a href="<?= site_cms_h($footerPhoneHref) ?>"><?= site_cms_h($footerPhone) ?></a><?php endif; ?></li>
                    <li><img src="img/icons/29_email_plic.svg" alt="" class="footer-contact-icon" role="presentation"><?php if ($footerEmail !== '' && $footerEmailHref !== ''): ?><a href="<?= site_cms_h($footerEmailHref) ?>"><?= site_cms_h($footerEmail) ?></a><?php endif; ?></li>
                    <li><img src="img/icons/28_locatie_pin.svg" alt="" class="footer-contact-icon" role="presentation"><?= site_cms_h($footer['address'] ?? '') ?></li>
                </ul>
            </div>
        </div>
        <div class="copy">
            <span>© <?= date('Y') ?> <?= site_cms_h($footer['copyright'] ?? 'Besoiu Piese Auto. Toate drepturile rezervate.') ?></span>
            <span><?= site_cms_h($footer['tagline'] ?? '') ?></span>
        </div>
    </div>
</footer>
<?php
if (function_exists('site_live_edit_mode') && site_live_edit_mode()) {
    require __DIR__ . '/cms-edit-bar.php';
}
if (!function_exists('besoiu_admin_storefront_context')) {
    require_once __DIR__ . '/storefront-context.php';
}
echo '<script>window.BESOIU_ADMIN_CTX=' . (besoiu_admin_storefront_context() ? 'true' : 'false') . ';</script>' . "\n";
?>
