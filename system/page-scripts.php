<?php
/**
 * Scripturi comune footer — înlocuit de besoiu_render_scripts() pe fiecare pagină.
 * Păstrat pentru compatibilitate: coș lite (pagini fără carduri).
 */
require_once __DIR__ . '/besoiu-assets.php';
besoiu_render_scripts('minimal');
