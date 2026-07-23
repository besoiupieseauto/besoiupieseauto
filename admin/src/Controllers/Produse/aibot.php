<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $titluCerere = $data['titlu_cerere'] ?? '';
    $masina = $data['detalii_masina'] ?? '';
    $textCerere = mb_strtolower($titluCerere . ' ' . $masina);

    // 1. Extragere coduri OEM din cerere
    preg_match_all('/[a-z0-9]{7,15}/', $textCerere, $matches);
    $coduriCautate = $matches[0] ?? [];

    // 2. Încărcare Stoc JSON
    $pathJson = __DIR__ . '/produse.json';
    if (!file_exists($pathJson)) throw new Exception("Fișierul produse.json lipsește.");
    $totStocul = json_decode(file_get_contents($pathJson), true);

    $produseIdentificate = [];
    $branduri_standard = ['vw', 'volkswagen', 'audi', 'bmw', 'mercedes', 'ford', 'opel', 'renault', 'skoda', 'toyota', 'dacia', 'hyundai', 'kia', 'peugeot', 'citroen', 'fiat', 'seat', 'volvo', 'nissan'];

    foreach ($totStocul as $p) {
        $numeOriginal = $p['titlu'] ?? '';
        if (empty($numeOriginal)) continue;

        $numePiesa = mb_strtolower($numeOriginal);
        $scor = 0;
        $matchCod = false;

        // A. VERIFICARE COD OEM (Prioritate Absolută)
        foreach ($coduriCautate as $cod) {
            if (strpos($numePiesa, $cod) !== false) {
                $scor += 250;
                $matchCod = true;
            }
        }

        // B. VERIFICARE BRAND (Filtru obligatoriu)
        $brandFound = false;
        foreach ($branduri_standard as $b) {
            if (strpos($textCerere, $b) !== false && strpos($numePiesa, $b) !== false) {
                $scor += 60;
                $brandFound = true;
                break;
            }
        }

        // C. VERIFICARE SPECIFICITATE (Ex: Cabrio, Tourer, Facelift)
        // Dacă clientul cere ceva specific și piesa NU are acel cuvânt, penalizăm dur
        $termeniRestrictivi = ['cabrio', 'tourer', 'quattro', '4x4', 'facelift', 'hatchback', 'sedan'];
        foreach ($termeniRestrictivi as $t) {
            if (strpos($textCerere, $t) !== false) {
                if (strpos($numePiesa, $t) !== false) $scor += 80;
                else $scor -= 100; // Penalizare - elimină rezultate gen Golf 6 simplu la cerere de Cabrio
            }
        }

        // D. POTRIVIRE MODEL (Ex: Golf 6, Astra J)
        $cuvinteModel = explode(' ', $masina);
        foreach ($cuvinteModel as $cm) {
            if (strlen($cm) >= 1 && strpos($numePiesa, mb_strtolower($cm)) !== false) {
                $scor += 40;
            }
        }

        // PRAG DE CALITATE RIDICAT (Să nu mai dea piese greșite)
        if ($scor >= 130 || $matchCod) {
            // EXTRAGERE PREȚ DIN JSON (Salvat de noul robot sub cheia 'price')
            $pretRaw = $p['price'] ?? '0';
            $pretCurat = preg_replace('/[^0-9]/', '', $pretRaw);

            $produseIdentificate[] = [
                'nume' => $numeOriginal,
                'link' => $p['link'] ?? '#',
                'pret' => $pretCurat ?: "Contact",
                'scor' => $scor
            ];
        }
    }

    // Sortăm cele mai bune potriviri primele
    usort($produseIdentificate, fn($a, $b) => $b['scor'] <=> $a['scor']);

    if (empty($produseIdentificate)) {
        echo json_encode(["status" => "nu_exista", "mesaj_ai" => "Nu s-a găsit piesa exactă în stoc."]);
        exit;
    }

    // Luăm piesa cea mai relevantă
    $piesaGasita = $produseIdentificate[0];

    // Trimitere către AI pentru generare mesaj politicos
    $apiKey = trim((string) (getenv('GROQ_KEY') ?: ($_ENV['GROQ_KEY'] ?? '')));
    if ($apiKey === '') {
        echo json_encode(['status' => 'eroare', 'mesaj_ai' => 'GROQ_KEY lipsă în admin/.env']);
        exit;
    }
    $context = "Produs: {$piesaGasita['nume']}, Pret: {$piesaGasita['pret']} RON, Link: {$piesaGasita['link']}";

    $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apiKey", "Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "model" => "llama-3.3-70b-versatile",
        "messages" => [
            ["role" => "system", "content" => "Ești asistent vânzări. Răspunzi doar JSON."],
            ["role" => "user", "content" => "Clientul caută '$titluCerere' pentru '$masina'. Avem: $context. Generați un mesaj de ofertă scurt. JSON format: {\"status\":\"succes\",\"mesaj_ai\":\"...\",\"mesaj_oferta\":\"...\",\"pret_recomandat\":\"{$piesaGasita['pret']}\"}"]
        ],
        "temperature" => 0.1,
        "response_format" => ["type" => "json_object"]
    ]));

    $res = curl_exec($ch);
    echo $res ? json_encode(json_decode(json_decode($res, true)['choices'][0]['message']['content'], true)) : json_encode(["status"=>"eroare"]);

} catch (\Throwable $e) {
    echo json_encode(["status" => "eroare", "mesaj" => $e->getMessage()]);
}