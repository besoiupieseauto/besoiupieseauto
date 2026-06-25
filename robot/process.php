<?php
/**
 * robot/process.php
 *
 * Portat 1:1 din C:\laragon\www\aibotpiese.online\process.php
 * Modificari: $apiKey si $groqKey vin din .env. Restul = identic.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_guard.php';

$apiKey  = (string) env('RAPIDAPI_TECDOC_KEY', '');
$groqKey = (string) env('GROQ_KEY', '');
$vin = "WDD1690311J875947";
$cerereClient = "Bara fata";

echo "<h1 style='font-family: Arial;'>🚀 Robot TecDoc: Automatizare Completă (V2)</h1>";

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

// --- PASUL 1: VIN la carId ---
echo "🔍 Pas 1: Verificare VIN... ";
$resVin = callTecDoc(robot_tecdoc_url("vin/tecdoc-vin-check/$vin"), $apiKey);
$carId = $resVin['data']['matchingVehicles']['array'][0]['carId'] ?? 18261;
echo "<b style='color:green;'>Găsit carId: $carId</b><br>";

// --- PASUL 2: CATEGORII ---
echo "📂 Pas 2: Descărcare categorii... ";

$langId = robot_tecdoc_lang_id();
$urlCats = robot_tecdoc_url("category/type-id/1/products-groups-variant-3/$carId/lang-id/$langId");
$resCats = callTecDoc($urlCats, $apiKey);
file_put_contents(__DIR__ . '/data/categorii_vehicul.json', json_encode($resCats, JSON_PRETTY_PRINT));
echo "OK.<br>";


$jsonFile = __DIR__ . '/data/categorii_vehicul.json';
$mesajClient = "Bara fata pentru Mercedes A-Class W169";

$resCats = json_decode(file_get_contents($jsonFile), true);
$data = isset($resCats['categories']) ? $resCats['categories'] : $resCats;

/**
 * PASUL 1: DISPECERUL (AI)
 */
function determinaDirectia($text, $apiKey) {
    $prompt = "Clientul vrea: '$text'. În ce categorie principală se află piesa? 
    Alege doar una din: [Body, Engine, Braking System, Steering, Suspension, Electrics, Accessories].
    Returnează doar cuvântul.";

    $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $apiKey", "Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode([
            "model" => "llama-3.3-70b-versatile",
            "messages" => [["role" => "user", "content" => $prompt]],
            "temperature" => 0
        ])
    ]);
    $res = json_decode(curl_exec($ch), true);
    return trim($res['choices'][0]['message']['content'] ?? 'Body');
}

$directie = determinaDirectia($mesajClient, $groqKey);
echo "📡 <b>Direcția identificată:</b> $directie<br>";

/**
 * PASUL 2: FILTRAREA PHP
 */
$ramuraFiltrata = null;
foreach ($data as $id => $info) {
    if (stripos($info['text'], $directie) !== false) {
        $ramuraFiltrata = [$id => $info];
        break;
    }
}

/**
 * PASUL 3: MAPPING FINAL (AI)
 */
if ($ramuraFiltrata) {
    echo "🔍 <b>Pas 1:</b> Filtrare ramură finalizată.<br>";
    file_put_contents(__DIR__ . '/data/ramura_filtrata.json', json_encode($ramuraFiltrata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    function extrageTermeniiDinJson($data, &$rezultate = []) {
        foreach ($data as $id => $info) {
            if (isset($info['text'])) {
                $rezultate[] = $info['text'];
            }
            if (!empty($info['children'])) {
                extrageTermeniiDinJson($info['children'], $rezultate);
            }
        }
        return array_unique($rezultate);
    }

    $optiuniDisponibile = extrageTermeniiDinJson($ramuraFiltrata);
    $listaTermeniText = implode(", ", $optiuniDisponibile);

    echo "📋 <b>Terminologii găsite în ramură:</b> <small>$listaTermeniText</small><br>";

    $promptAlegere = "Analizează cererea clientului: '$mesajClient'.

Sarcina ta este să selectezi cel mai relevant termen tehnic din lista de mai jos, acționând ca un expert în logistică auto.

LISTA DE TERMENI DISPONIBILI:
$listaTermeniText

LOGICA DE SELECȚIE (Gândire Sistemică):
1. ANCOREAZĂ INTENȚIA: Identifică entitatea fizică principală (ex: 'bara', 'frana', 'far').
2. PRIORITIZEAZĂ ANSAMBLUL: Dacă utilizatorul cere o piesă generală, alege categoria care conține sufixul '/Parts', '/Set' sau '/System'. Acestea sunt 'containerele' principale de piese.
3. EXCLUDE 'ZGOMOTUL': Evită categoriile care conțin: 'Fastening', 'Clips', 'Bulb', 'Mounting', 'Seal', 'Gasket', 'Sensor' sau 'Trim', CU EXCEPȚIA cazului în care clientul a cerut explicit un accesoriu sau un senzor.
4. ANALIZĂ POZIȚIONALĂ: Dacă clientul menționează 'fata', 'spate', 'stanga' sau 'dreapta', caută acești determinanți în ierarhia numelui, dar prioritizează întotdeauna categoria structurală (Ex: 'Bumper/Parts' în loc de 'Front Bumper Cover').
5. MATCH TEHNIC: Alege termenul din listă care reprezintă 'Piesa Mamă'.

Returnează DOAR termenul exact din listă, fără niciun alt caracter.";

    $chK = curl_init("https://api.groq.com/openai/v1/chat/completions");
    curl_setopt_array($chK, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $groqKey", "Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode([
            "model" => "llama-3.3-70b-versatile",
            "messages" => [["role" => "user", "content" => $promptAlegere]],
            "temperature" => 0
        ])
    ]);
    $resK = json_decode(curl_exec($chK), true);
    $termenAles = trim($resK['choices'][0]['message']['content'] ?? '');
    curl_close($chK);

    echo "🔑 <b>Termen ales de AI din catalog:</b> $termenAles<br>";

    function gasesteIdDupaNume($ierarhie, $numeCautat) {
        foreach ($ierarhie as $id => $info) {
            if (trim($info['text']) === trim($numeCautat)) {
                return $id;
            }
            if (!empty($info['children'])) {
                $rezultat = gasesteIdDupaNume($info['children'], $numeCautat);
                if ($rezultat) return $rezultat;
            }
        }
        return null;
    }

    $idFinal = gasesteIdDupaNume($ramuraFiltrata, $termenAles);

    if ($idFinal) {
        echo "<br><div style='background:#2ecc71; color:white; padding:20px; border-radius:10px; font-family:Arial; display:inline-block;'>";
        echo "🎯 <b>ID Final Identificat Direct:</b> $idFinal<br>";
        echo "📦 <b>Piesa:</b> $termenAles";
        echo "</div>";

        file_put_contents(__DIR__ . '/data/id_categorie_final.txt', $idFinal);
    } else {
        echo "❌ Eroare: Deși AI-ul a ales termenul, acesta nu a fost găsit în structura JSON.";
    }
}

$carId = "18261";
$idCategorie = trim(file_get_contents(__DIR__ . '/data/id_categorie_final.txt'));

echo "<h2>📊 Procesare Finală: Filtrare și Afișare Bară</h2>";

$langId = robot_tecdoc_lang_id();
$url = robot_tecdoc_url("articles/list/type-id/1/vehicle-id/$carId/category-id/$idCategorie/lang-id/$langId");

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "x-rapidapi-host: " . robot_tecdoc_host(),
        "x-rapidapi-key: $apiKey"
    ],
    CURLOPT_TIMEOUT => 45,
]);

$response = curl_exec($curl);
curl_close($curl);

file_put_contents(__DIR__ . '/data/articole_brute.json', $response);
echo "✅ Datele brute au fost salvate în <b>data/articole_brute.json</b>.<br>";


$jsonBrut = file_get_contents(__DIR__ . '/data/articole_brute.json');
$data = json_decode($jsonBrut, true);
$articole = $data['articles'] ?? [];

$doarBumper = [];
foreach ($articole as $art) {
    if ($art['articleProductName'] === "Bumper") {
        $doarBumper[] = [
            "id" => $art['articleId'],
            "nume" => $art['articleProductName'],
            "cod" => $art['articleNo'],
            "brand" => $art['supplierName']
        ];
    }
}

echo "<h3>🤖 AI-ul analizează automat " . count($doarBumper) . " bări pentru a găsi FAȚA...</h3>";


$prompt = "Ești un expert în logistică auto. Analizează această listă de piese pentru Mercedes A-Class W169 (2010). 
Identifică piesele care sunt pentru FAȚĂ (FRONT). 
Indiciu: La Mercedes W169, Van Wezel seria 3018 este Front, seria 3017 este Rear. BLIC seria 5510 este Front.
Returnează DOAR un JSON cu 'id_ales' și 'motiv'.
LISTA: " . json_encode(array_slice($doarBumper, 0, 40));

$ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $groqKey", "Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode([
        "model" => "llama-3.3-70b-versatile",
        "messages" => [
            ["role" => "system", "content" => "You are a car parts matching engine. Return only JSON."],
            ["role" => "user", "content" => $prompt]
        ],
        "response_format" => ["type" => "json_object"],
        "temperature" => 0
    ])
]);

$resAI = json_decode(curl_exec($ch), true);
$raspuns = json_decode($resAI['choices'][0]['message']['content'], true);
$idAles = $raspuns['id_ales'];

$idFinal = is_array($idAles) ? $idAles[0] : $idAles;

echo "<h3>🤖 AI a identificat " . (is_array($idAles) ? count($idAles) : 1) . " variante de FAȚĂ.</h3>";

$gasit = false;
foreach ($articole as $art) {
    if ($art['articleId'] == $idFinal) {
        $gasit = true;
        echo "<div style='border:5px solid #27ae60; padding:20px; font-family:Arial; background:#f9fff9; max-width:600px; border-radius:15px;'>";
        echo "<h2 style='color:#27ae60; margin-top:0;'>🎯 Identificare Automată Reușită!</h2>";

        $img = $art['s3image'] ?? 'https://via.placeholder.com/400x300?text=Imagine+Indisponibila';

        echo "<img src='$img' width='100%' style='border-radius:10px; border:1px solid #ddd;'><br><br>";
        echo "<div style='font-size:18px;'>";
        echo "🏢 <b>Brand Furnizor:</b> {$art['supplierName']}<br>";
        echo "🔢 <b>Cod Produs:</b> <span style='background:yellow; padding:2px 5px; font-weight:bold;'>{$art['articleNo']}</span><br>";
        echo "📝 <b>Denumire:</b> {$art['articleProductName']}<br><br>";
        echo "<div style='background:#e8f5e9; padding:10px; border-left:5px solid #2ecc71;'>";
        echo "💡 <b>Logica AI:</b> " . $raspuns['motiv'];
        echo "</div>";
        echo "</div>";
        echo "</div>";
        break;
    }
}

if (!$gasit) {
    echo "<b style='color:red;'>⚠️ Eroare: ID-ul ales de AI ($idFinal) nu a fost găsit în lista locală de articole.</b>";
}
