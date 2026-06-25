<?php
/**
 * robot/parser.php
 *
 * Portat 1:1 din C:\laragon\www\aibotpiese.online\parser.php
 * Modificari:
 *  - $statusFile mutat in /data/status.txt
 *  - PIESEAUTO_USER citit din .env (override pentru ?user= GET)
 *  - Restul logicii (cookies, scraping, analiza concurenta) = identic.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_guard.php';

header('Content-Type: application/json');
set_time_limit(150);
ini_set('display_errors', 0);

$statusFile = __DIR__ . '/data/status.txt';
$currentStatus = file_exists($statusFile) ? trim(file_get_contents($statusFile)) : 'run';

if ($currentStatus !== 'run') {
    echo json_encode(['status' => 'stopped']);
    exit;
}

$targetUser = $_GET['user'] ?? (string) env('PIESEAUTO_USER', '');

function requestPieseAuto($url) {
    $ch = curl_init($url);
    $headers = [
        "authority: www.pieseauto.ro",
        "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36",
        "cookie: pbidsc=d3aa92ddgrNLxWNx2dT71IJ82dgkr3g; SI=vp6ssakdbjbmhboflraoa9bsth;"
    ];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_ENCODING, "");
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

function analyzeRequest($urlCerere, $username) {
    $html = requestPieseAuto($urlCerere);
    if (!$html) return null;

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($dom);

    $catalogNode = $xpath->query("//div[contains(@class, 'bottom-item')]//a");
    $linkCatalog = ($catalogNode->length > 0) ? $catalogNode->item(0)->getAttribute('href') : null;

    $result = [
        'match' => false,
        'pret_recomandat' => 0,
        'minim_piata' => 0,
        'total_concurenti' => 0,
        'detalii_stoc' => []
    ];

    if ($linkCatalog) {
        usleep(rand(200000, 400000));
        $htmlGlobal = requestPieseAuto($linkCatalog);
        @$dom->loadHTML(mb_convert_encoding($htmlGlobal, 'HTML-ENTITIES', 'UTF-8'));
        $xpG = new DOMXPath($dom);

        $preturi = [];
        $itemsGlobal = $xpG->query("//div[contains(@class, 'js-sr-item')]");
        foreach ($itemsGlobal as $it) {
            $pRaw = $xpG->evaluate("string(.//div[contains(@class, 'sr-item__price')])", $it);
            $pNum = (int) preg_replace('/[^0-9]/', '', $pRaw);
            if ($pNum > 0) $preturi[] = $pNum;
        }

        if (count($preturi) > 0) {
            sort($preturi);
            $result['minim_piata'] = $preturi[0];
            $result['total_concurenti'] = count($preturi);
            $medie = array_sum($preturi) / count($preturi);
            $result['pret_recomandat'] = round($medie * 0.95);
        }

        if (!empty($username)) {
            $urlTarget = $linkCatalog . (strpos($linkCatalog, '?') !== false ? '&' : '?') . "utilizator=" . urlencode($username);
            $htmlUser = requestPieseAuto($urlTarget);
            @$dom->loadHTML(mb_convert_encoding($htmlUser, 'HTML-ENTITIES', 'UTF-8'));
            $xpU = new DOMXPath($dom);

            $myItems = $xpU->query("//div[contains(@class, 'js-sr-item')]");
            foreach ($myItems as $myItem) {
                $usrFound = trim($xpU->evaluate("string(.//span[contains(@class, 'sr-item__usr-username')])", $myItem));

                if (strcasecmp($usrFound, $username) === 0) {
                    $result['match'] = true;
                    $myPrice = (int) preg_replace('/[^0-9]/', '', $xpU->evaluate("string(.//div[contains(@class, 'sr-item__price')])", $myItem));
                    $result['detalii_stoc'][] = [
                        'titlu' => trim($xpU->evaluate("string(.//div[contains(@class, 'sr-item__title')]/a)", $myItem)),
                        'pret_meu' => $myPrice
                    ];
                }
            }
        }
    }
    return $result;
}

$mainHtml = requestPieseAuto("https://www.pieseauto.ro/cereri-piese-auto/pagina-1?nl=3&active=0");
$dom = new DOMDocument();
libxml_use_internal_errors(true);
@$dom->loadHTML(mb_convert_encoding($mainHtml, 'HTML-ENTITIES', 'UTF-8'));
$xpath = new DOMXPath($dom);
$grupuri = $xpath->query("//div[contains(@class, 'cr-listing-group')]");

$output = [];
foreach ($grupuri as $index => $g) {
    if ($index >= 5) break;

    $car = trim($xpath->evaluate("string(.//div[contains(@class, 'cr-listing-group__car')])", $g));
    $date = trim($xpath->evaluate("string(.//div[contains(@class, 'cr-listing-group__date')])", $g));
    $item = $xpath->query(".//div[contains(@class, 'cr-item')]", $g)->item(0);

    if ($item) {
        $linkNode = $xpath->query("./a", $item)->item(0);
        $urlCerere = $linkNode->getAttribute('href');

        $output[] = [
            'id' => $item->getAttribute('data-cerere-id'),
            'masina' => $car,
            'piesa' => trim($linkNode->getAttribute('title')),
            'url' => $urlCerere,
            'ora' => $date,
            'analysis' => analyzeRequest($urlCerere, $targetUser)
        ];
    }
}

echo json_encode($output);
