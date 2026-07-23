<?php
/**
 * Fragment comun <head> — fonts + CSS shell (layout-01: link extern)
 * Variabile opționale înainte de include: $pageExtraCss (string|array)
 */
$pageExtraCss = $pageExtraCss ?? [];
if (is_string($pageExtraCss)) {
    $pageExtraCss = [$pageExtraCss];
}
?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    require_once __DIR__ . '/besoiu-assets.php';
    besoiu_render_fonts(false);
    besoiu_render_styles('minimal', $pageExtraCss);
    ?>
