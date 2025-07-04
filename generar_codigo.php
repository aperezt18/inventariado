<?php
require_once "control_licencia.php";

$key = 'p6c819oRElaVVhzGOm0BFk6OJquPNLxM'; // Igual que en control_licencia.php
$rutaCodigo = 'C:\\xampp\\licencia\\licencia_codigo.dat';
$rutaExpira = 'C:\\xampp\\licencia\\licencia_expira.txt';
$discordWebhook = 'https://discord.com/api/webhooks/1389771876586487830/tj9xPwtKbQwfS6i1hJgdJi0xr9J8yIyPFVUwcLJimgNZSpAjLLVvEL_o7gzcQGuX7C1M';

function cifrar($texto, $key) {
    $iv = random_bytes(16);
    $cifrado = openssl_encrypt($texto, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cifrado);
}

function generarCodigo($longitud = 8) {
    $caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $codigo = '';
    for ($i=0; $i < $longitud; $i++) {
        $codigo .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }
    return $codigo;
}

function enviarDiscord($webhook, $mensaje) {
    $data = json_encode(['content' => $mensaje]);
    $ch = curl_init($webhook);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// Sólo permitir desde localhost o IP local por seguridad
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0 || strpos($ip, '172.') === 0) {
    $codigo = generarCodigo();
    $codigoCifrado = cifrar($codigo, $key);
    if (file_put_contents($rutaCodigo, $codigoCifrado) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Error guardando archivo de licencia.']);
        exit;
    }
    file_put_contents($rutaExpira, date('Y-m-d H:i:s', strtotime('+30 days')));
    $mensaje = "**Código de activación generado:** `$codigo` \n*Válido 30 días desde ahora.*";
    enviarDiscord($discordWebhook, $mensaje);
    echo json_encode(['success' => true, 'mensaje' => 'Código generado y enviado a Discord correctamente.']);
} else {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado para generar código.']);
}
