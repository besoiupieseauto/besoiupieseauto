<?php
declare(strict_types=1);

/**
 * Alias legacy /admin/searching → căutare furnizori B2B.
 */
header('Location: /admin/supplier-search', true, 302);
exit;
