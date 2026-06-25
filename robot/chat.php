<?php
/**
 * robot/chat.php
 *
 * Portat 1:1 din C:\laragon\www\aibotpiese.online\chat.php
 * Modificari: $instance / $token vin din .env. Restul = identic.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_guard.php';

/**
 * CONFIGURARE
 */
$instance = (string) env('ULTRAMSG_INSTANCE', '');
$token    = (string) env('ULTRAMSG_TOKEN', '');
$file     = __DIR__ . "/data/baza_date.json";

if (!file_exists($file) || filesize($file) == 0) {
    file_put_contents($file, json_encode([]));
}

function curataTelefon($tel) {
    $tel = str_replace(['@c.us', '@s.whatsapp.net', '+', ' '], '', $tel);
    return $tel;
}

/**
 * 1. LOGICĂ WEBHOOK
 */
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (isset($update['data']) && $update['data']['fromMe'] == false) {
    $p = curataTelefon($update['data']['from']);
    $t = isset($update['data']['body']) ? trim($update['data']['body']) : "";

    if(!empty($t)) {
        salveaza("client", $p, $t);
        if (preg_match('/[A-HJ-NPR-Z0-9]{17}/i', $t, $matches)) {
            $vin = strtoupper($matches[0]);
            $raspuns = "✅ Am primit codul VIN: $vin. Revenim imediat!";
        } else {
            $raspuns = "EU va scriu referitor la aviz pentru a va face o oferta vă rugăm să ne trimiteți codul VIN (17 caractere).";
        }
        trimite($update['data']['from'], $raspuns, $instance, $token);
        salveaza("bot", $p, $raspuns);
    }
    exit;
}

/**
 * 2. LOGICĂ API AJAX
 */
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $db = json_decode(file_get_contents($file), true) ?: [];

    if ($_GET['action'] == 'fetch_groups') {
        $groups = [];
        foreach ($db as $m) {
            $pid = curataTelefon($m['p']);
            $groups[$pid] = [
                'phone' => $pid,
                'last_msg' => isset($m['t']) ? (string)$m['t'] : "",
                'time' => isset($m['time']) ? $m['time'] : "--:--"
            ];
        }
        echo json_encode(array_values(array_reverse($groups)));
    }
    elseif ($_GET['action'] == 'fetch_chat' && isset($_GET['phone'])) {
        $phone = curataTelefon($_GET['phone']);
        $chat = array_filter($db, function($m) use ($phone) {
            return curataTelefon($m['p']) == $phone;
        });
        echo json_encode(array_values($chat));
    }
    elseif ($_GET['action'] == 'send') {
        $phone = curataTelefon($_POST['phone']);
        $message = $_POST['msg'];
        trimite($phone . "@c.us", $message, $instance, $token);
        salveaza("admin", $phone, $message);
        echo json_encode(["status" => "ok"]);
    }
    exit;
}

function trimite($to, $msg, $i, $t) {
    $url = "https://api.ultramsg.com/$i/messages/chat";
    $data = ['token' => $t, 'to' => $to, 'body' => $msg];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function salveaza($s, $p, $t) {
    global $file;
    $db = json_decode(file_get_contents($file), true) ?: [];
    $db[] = ["p" => curataTelefon($p), "s" => $s, "t" => (string)$t, "time" => date("H:i")];
    if(count($db) > 1000) array_shift($db);
    file_put_contents($file, json_encode($db));
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Manager Piese Auto</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 20px; }
        .container { max-width: 900px; margin: auto; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #fafafa; }
        .btn { background: #075e54; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; }
        #modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: #e5ddd5; margin: 2vh auto; width: 450px; height: 90vh; border-radius: 15px; display: flex; flex-direction: column; overflow: hidden; }
        .modal-header { background: #075e54; color: white; padding: 15px; display: flex; justify-content: space-between; }
        #chat-history { flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 10px; }
        .msg { padding: 10px; border-radius: 8px; max-width: 85%; font-size: 14px; position: relative; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .client, .bot { background: white; align-self: flex-start; }
        .admin { background: #dcf8c6; align-self: flex-end; }
        .modal-footer { padding: 10px; background: #f0f0f0; display: flex; gap: 5px; }
        .modal-footer input { flex: 1; padding: 10px; border-radius: 20px; border: 1px solid #ccc; outline: none; }
    </style>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>📱 Conversații Clienți</h2>
        <button class="btn" onclick="let p=prompt('Număr:'); if(p) openModal(p)">+ Contact Nou</button>
    </div>
    <table>
        <thead><tr><th>Telefon</th><th>Ultimul Mesaj</th><th>Acțiune</th></tr></thead>
        <tbody id="table-body"></tbody>
    </table>
</div>

<div id="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span id="modal-phone">Chat</span>
            <button onclick="closeModal()" style="color:white; border:none; background:none; cursor:pointer; font-size:20px;">&times;</button>
        </div>
        <div id="chat-history"></div>
        <div class="modal-footer">
            <input type="text" id="msg-input" placeholder="Scrie mesaj...">
            <button onclick="sendMsg()" class="btn">➤</button>
        </div>
    </div>
</div>

<script>
    let currentPhone = "";

    function loadGroups() {
        fetch('?action=fetch_groups')
            .then(r => r.json())
            .then(data => {
                const tbody = document.getElementById('table-body');
                tbody.innerHTML = data.map(g => `
                <tr>
                    <td><strong>+${g.phone}</strong></td>
                    <td>${g.last_msg.substring(0, 30)}...</td>
                    <td><button class="btn" onclick="openModal('${g.phone}')">Vezi Chat</button></td>
                </tr>
            `).join('');
            });
    }

    function openModal(phone) {
        currentPhone = phone;
        document.getElementById('modal-phone').innerText = "+" + phone;
        document.getElementById('modal').style.display = "block";
        loadChat();
    }

    function closeModal() { document.getElementById('modal').style.display = "none"; currentPhone = ""; }

    function loadChat() {
        if(!currentPhone) return;
        fetch('?action=fetch_chat&phone=' + encodeURIComponent(currentPhone))
            .then(r => r.json())
            .then(data => {
                const history = document.getElementById('chat-history');
                history.innerHTML = data.map(m => `
                <div class="msg ${m.s}">
                    <b style="font-size:10px; color:#075e54">${m.s.toUpperCase()}</b><br>${m.t}
                    <div style="font-size:9px; text-align:right; color:#999">${m.time}</div>
                </div>
            `).join('');
                history.scrollTop = history.scrollHeight;
            });
    }

    function sendMsg() {
        const input = document.getElementById('msg-input');
        if(!input.value) return;
        const fd = new FormData();
        fd.append('phone', currentPhone);
        fd.append('msg', input.value);
        fetch('?action=send', { method: 'POST', body: fd }).then(() => { input.value = ''; loadChat(); });
    }

    setInterval(() => { loadGroups(); if(currentPhone) loadChat(); }, 4000);
    loadGroups();
</script>
</body>
</html>
