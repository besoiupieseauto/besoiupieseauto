<?php
/**
 * robot/run.php
 *
 * Portat 1:1 din C:\laragon\www\aibotpiese.online\run.php
 * Modificari: cheile vin din .env. Restul = identic.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_guard.php';

ini_set('memory_limit', '1024M');
set_time_limit(180);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Acces interzis.");

$apiKey  = (string) env('RAPIDAPI_TECDOC_KEY', '');
$groqKey = (string) env('GROQ_KEY', '');

$vin = $_POST['vin'] ?? '';
$mesajClient = $_POST['cerere'] ?? '';

function callTecDoc($url, $key) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["x-rapidapi-host: " . robot_tecdoc_host(), "x-rapidapi-key: $key"],
        CURLOPT_TIMEOUT => 45,
    ]);
    $res = curl_exec($curl);
    curl_close($curl);
    return json_decode($res, true);
}

$resVin = callTecDoc(robot_tecdoc_url("vin/tecdoc-vin-check/$vin"), $apiKey);
$carId = $resVin['data']['matchingVehicles']['array'][0]['carId'] ?? 18261;

function determinaDirectia($text, $apiKey) {
    $prompt = "Clientul vrea: '$text'. În ce categorie principală se află piesa? Alege una: [Body, Engine, Braking System, Steering, Suspension, Electrics, Accessories]. Returnează doar cuvântul.";
    $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $apiKey", "Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode(["model" => "llama-3.3-70b-versatile", "messages" => [["role" => "user", "content" => $prompt]], "temperature" => 0])
    ]);
    $res = json_decode(curl_exec($ch), true);
    return trim($res['choices'][0]['message']['content'] ?? 'Body');
}

$directie = determinaDirectia($mesajClient, $groqKey);

$langId = robot_tecdoc_lang_id();
$urlCats = robot_tecdoc_url("category/type-id/1/products-groups-variant-3/$carId/lang-id/$langId");
$resCats = callTecDoc($urlCats, $apiKey);
$data = isset($resCats['categories']) ? $resCats['categories'] : $resCats;

$idCategorieFinal = 100187;
foreach ($data as $id => $info) {
    if (stripos($info['text'], $directie) !== false) {
        $idCategorieFinal = $id;
        break;
    }
}

$urlArt = robot_tecdoc_url("articles/list/type-id/1/vehicle-id/$carId/category-id/$idCategorieFinal/lang-id/$langId");
$response = callTecDoc($urlArt, $apiKey);
$articole = $response['articles'] ?? [];

$doarBumper = [];
foreach ($articole as $art) {
    if (stripos($art['articleProductName'], 'Bumper') !== false || count($articole) < 50) {
        $doarBumper[] = [
            "id" => $art['articleId'],
            "nume" => $art['articleProductName'],
            "cod" => $art['articleNo'],
            "brand" => $art['supplierName']
        ];
    }
}

$promptFinal = "Ești expert auto. Analizează lista de piese pt Mercedes A-Class W169 (2010). Găsește FRONT (FAȚĂ). Indiciu: Van Wezel 3018=Front, 3017=Rear. BLIC 5510=Front. Return JSON {'id_ales':'...', 'motiv':'...'}. LISTA: " . json_encode(array_slice($doarBumper, 0, 40));

$chFinal = curl_init("https://api.groq.com/openai/v1/chat/completions");
curl_setopt_array($chFinal, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $groqKey", "Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode([
        "model" => "llama-3.3-70b-versatile",
        "messages" => [["role" => "system", "content" => "Return only JSON."], ["role" => "user", "content" => $promptFinal]],
        "response_format" => ["type" => "json_object"]
    ])
]);
$resFinalAI = json_decode(curl_exec($chFinal), true);
$raspunsAI = json_decode($resFinalAI['choices'][0]['message']['content'], true);
$idAles = is_array($raspunsAI['id_ales']) ? $raspunsAI['id_ales'][0] : $raspunsAI['id_ales'];

foreach ($articole as $art) {
    if ($art['articleId'] == $idAles) {
        echo "<div style='border:5px solid #27ae60; padding:20px; font-family:Arial; background:#f9fff9; border-radius:15px;'>";
        echo "<h2 style='color:#27ae60; margin-top:0;'>🎯 Identificare Reușită!</h2>";
        $img = $art['s3image'] ?? 'https://via.placeholder.com/400x300?text=Fara+Imagine';
        echo "<img src='$img' width='100%' style='border-radius:10px; border:1px solid #ddd;'><br><br>";
        echo "<div style='font-size:18px;'>";
        echo "🏢 <b>Brand:</b> {$art['supplierName']}<br>";
        echo "🔢 <b>Cod Produs:</b> <span style='background:yellow; padding:2px 5px; font-weight:bold;'>{$art['articleNo']}</span><br>";
        echo "📝 <b>Denumire:</b> {$art['articleProductName']}<br><br>";
        echo "<div style='background:#e8f5e9; padding:10px; border-left:5px solid #2ecc71;'>";
        echo "💡 <b>Logica AI:</b> " . $raspunsAI['motiv'];
        echo "</div></div></div>";
        exit;
    }
}

echo "<div class='error-box'>AI-ul a ales o piesă, dar aceasta nu a fost găsită în stocul actual de articole.</div>";
