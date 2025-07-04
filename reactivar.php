<?php

require_once "control_licencia.php"; // Para usar funciones de cifrado y variables clave

// Variables igual que en control_licencia.php, por seguridad
$key = 'p6c819oRElaVVhzGOm0BFk6OJquPNLxM';
$rutaCodigo = 'C:\\xampp\\licencia\\licencia_codigo.dat';
$rutaExpira = 'C:\\xampp\\licencia\\licencia_expira.txt';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigoIngresado = strtoupper(trim($_POST['codigo'] ?? ''));

    if (empty($codigoIngresado)) {
        $mensaje = "Por favor, ingrese el código de activación.";
    } else {
        if (!file_exists($rutaCodigo)) {
            $mensaje = "No se encontró el archivo de licencia. Contacta al administrador.";
        } else {
            $codigoGuardadoCifrado = file_get_contents($rutaCodigo);
            $codigoGuardado = strtoupper(descifrar($codigoGuardadoCifrado, $key));

            if ($codigoGuardado === $codigoIngresado) {
                // Activar licencia
                $_SESSION['licencia_activa'] = true;

                // Guardar nueva fecha expiración 30 días desde ahora
                file_put_contents($rutaExpira, date('Y-m-d H:i:s', strtotime('+30 days')));

                header('Location: intranet.php'); // O la página principal
                exit;
            } else {
                $mensaje = "Código de activación incorrecto.";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Reactivar Licencia</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background: #2c3e50;
        color: white;
        display: flex;
        height: 100vh;
        justify-content: center;
        align-items: center;
        margin: 0;
    }
    .contenedor {
        background: #34495e;
        padding: 30px 40px;
        border-radius: 10px;
        box-shadow: 0 0 15px rgba(0,0,0,0.5);
        width: 320px;
        text-align: center;
    }
    h1 {
        margin-bottom: 20px;
        color: #e74c3c;
    }
    input[type="text"] {
        width: 100%;
        padding: 10px;
        font-size: 1rem;
        border: none;
        border-radius: 5px;
        margin-bottom: 20px;
        box-sizing: border-box;
    }
    button {
        background: #e74c3c;
        border: none;
        color: white;
        padding: 12px 20px;
        font-size: 1rem;
        border-radius: 5px;
        cursor: pointer;
        width: 100%;
    }
    button:hover {
        background: #c0392b;
    }
    .mensaje {
        margin-top: 15px;
        font-weight: bold;
        color: #f39c12;
    }
</style>
</head>
<body>
<div class="contenedor">
    <h1>Licencia Expirada</h1>
    <p>Ingrese el código de activación para continuar.</p>
    <form method="post" action="">
        <input type="text" name="codigo" maxlength="8" placeholder="Código de 8 caracteres" autofocus />
        <button type="submit">Activar Licencia</button>
    </form>

<button type="button" id="btnGenerarCodigo" style="background:#2980b9;color:#fff;margin-top:10px;">Enviar nuevo código a Discord</button>
<div id="mensajeGenerar" style="margin-top:10px;color:#f39c12;font-weight:bold;"></div>

<script>
document.getElementById('btnGenerarCodigo').addEventListener('click', function() {
    var btn = this;
    btn.disabled = true;
    btn.textContent = 'Generando código...';

    fetch('generar_codigo.php')
        .then(response => response.json())
        .then(data => {
            if(data.success){
                document.getElementById('mensajeGenerar').textContent = data.mensaje;
            } else if(data.error){
                document.getElementById('mensajeGenerar').textContent = 'Error: ' + data.error;
            }
        })
        .catch(() => {
            document.getElementById('mensajeGenerar').textContent = 'Error desconocido al generar código.';
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Enviar nuevo código a Discord';
        });
});
</script>


    <?php if ($mensaje): ?>
        <div class="mensaje"><?=htmlspecialchars($mensaje)?></div>
    <?php endif; ?>
</div>
</body>
</html>
