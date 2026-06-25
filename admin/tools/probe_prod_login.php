<?php
$body = json_encode(['type_product' => 'login', 'login' => '__probe__', 'password' => 'x']);
$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
        'content' => $body,
        'timeout' => 15,
        'ignore_errors' => true,
    ],
]);
$raw = file_get_contents('https://besoiupieseauto.ro/admin/addusersadd', false, $ctx);
echo $raw !== false ? $raw : 'fetch failed';
