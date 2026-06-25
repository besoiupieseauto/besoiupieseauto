<?php



declare(strict_types=1);



require_once __DIR__ . '/_autoload.php';



use Evasystem\Core\Bootstrap\ApiBootstrap;

use Evasystem\Services\PieseAuto\PieseAutoStatusService;



ApiBootstrap::boot(false);

ApiBootstrap::requireAuthenticatedSession();



header('Content-Type: application/json; charset=utf-8');



$target = trim((string) ($_GET['target'] ?? 'besoiu'));

$live = isset($_GET['live']) && in_array(strtolower((string) $_GET['live']), ['1', 'true', 'yes'], true);

$service = new PieseAutoStatusService();



echo json_encode($service->snapshot($target, $live), JSON_UNESCAPED_UNICODE);


