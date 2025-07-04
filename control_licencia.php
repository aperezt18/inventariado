<?php
session_start();

// Configuración
$key = 'p6c819oRElaVVhzGOm0BFk6OJquPNLxM'; // Cambia esta clave por una segura y única
$rutaCodigo = 'C:\\xampp\\licencia\\licencia_codigo.dat';
$rutaExpira = 'C:\\xampp\\licencia\\licencia_expira.txt';
$discordWebhook = 'https://discord.com/api/webhooks/1389771876586487830/tj9xPwtKbQwfS6i1hJgdJi0xr9J8yIyPFVUwcLJimgNZSpAjLLVvEL_o7gzcQGuX7C1M'; // Pon aquí tu webhook de Discord

// Funciones cifrado AES-256-CBC
function cifrar($texto, $key) {
    $iv = random_bytes(16);
    $cifrado = openssl_encrypt($texto, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cifrado);
}

function descifrar($textoCifrado, $key) {
    $textoCifrado = base64_decode($textoCifrado);
    $iv = substr($textoCifrado, 0, 16);
    $cifrado = substr($textoCifrado, 16);
    return openssl_decrypt($cifrado, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
}

// Función para generar código aleatorio 8 caracteres (A-Z, 0-9)
function generarCodigo($longitud = 8) {
    $caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $codigo = '';
    for ($i=0; $i < $longitud; $i++) {
        $codigo .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }
    return $codigo;
}

// Enviar mensaje a Discord vía webhook
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

// FUNCIONALIDAD: Generar código nuevo y enviarlo a Discord si se accede con ?generar=1 (solo local)
if (isset($_GET['generar']) && $_GET['generar'] == '1') {
    // Seguridad: sólo localhost o IP local pueden generar el código
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0 || strpos($ip, '172.') === 0) {
        $codigo = generarCodigo();
        $codigoCifrado = cifrar($codigo, $key);
        if (file_put_contents($rutaCodigo, $codigoCifrado) === false) {
            die("Error guardando archivo de licencia.");
        }
        // Poner expiración 30 días desde ahora
        file_put_contents($rutaExpira, date('Y-m-d H:i:s', strtotime('+30 days')));

        // Enviar mensaje a Discord (¡aquí está la integración!)
        $mensaje = "**Código de activación generado:** `$codigo` \n*Válido 30 días desde ahora.*";
        enviarDiscord($discordWebhook, $mensaje);

        die("Código generado y enviado a Discord correctamente");
    } else {
        die("No autorizado para generar código.");
    }
}

$licencia_activa = false;
if (isset($_SESSION['licencia_activa']) && $_SESSION['licencia_activa'] === true) {
    if (file_exists($rutaExpira)) {
        $fechaExpira = file_get_contents($rutaExpira);
        if (strtotime($fechaExpira) > time()) {
            $licencia_activa = true;
        } else {
            $_SESSION['licencia_activa'] = false;
        }
    }
}

// Si licencia no activa y no estamos en reactivar.php, mostramos bloqueo.php y salimos
$paginaActual = basename($_SERVER['PHP_SELF']);
if (!$licencia_activa && $paginaActual !== 'reactivar.php') {
    include 'bloqueo.php';  
    exit;  
}
