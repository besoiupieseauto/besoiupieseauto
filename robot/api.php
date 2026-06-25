<?php
/**
 * robot/api.php
 *
 * Portat 1:1 din C:\laragon\www\aibotpiese.online\api.php
 * Modificari: instance_id si token din .env. baza_date.json mutat in /data/.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_guard.php';

$instance = (string) env('ULTRAMSG_INSTANCE', '');
$token    = (string) env('ULTRAMSG_TOKEN', '');
$json_file = __DIR__ . "/data/baza_date.json";

if (isset($_POST['action']) && $_POST['action'] == 'send') {
    $to = $_POST['phone'];
    $msg = $_POST['message'];

    file_get_contents("https://api.ultramsg.com/{$instance}/messages/chat?token={$token}&to={$to}&body=" . urlencode($msg));

    $db = json_decode(file_get_contents($json_file), true) ?: [];
    $db[] = ["phone" => $to, "sender" => "admin", "text" => $msg, "time" => date("H:i")];
    file_put_contents($json_file, json_encode($db));
    echo json_encode(["status" => "ok"]);
}

if (isset($_GET['action']) && $_GET['action'] == 'fetch') {
    $db = json_decode(file_get_contents($json_file), true) ?: [];
    echo json_encode(array_reverse($db));
}
