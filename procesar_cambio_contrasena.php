<?php
session_start();
if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

require_once 'conexion.php';

if (
    !isset($_POST['contrasena_actual'], $_POST['nueva_contrasena'], $_POST['repetir_contrasena']) ||
    empty($_POST['contrasena_actual']) || empty($_POST['nueva_contrasena']) || empty($_POST['repetir_contrasena'])
) {
    header("Location: cambiar_contrasena.php");
    exit();
}

$usuario = $_SESSION['usuario'];
$contrasena_actual = $_POST['contrasena_actual'];
$nueva_contrasena = $_POST['nueva_contrasena'];
$repetir_contrasena = $_POST['repetir_contrasena'];

// Verificar que las nuevas contraseñas coinciden
if ($nueva_contrasena !== $repetir_contrasena) {
    $mensaje = "Las contraseñas nuevas no coinciden.";
    mostrarMensaje($mensaje, false);
    exit();
}

// Obtener la contraseña actual de la base de datos según el usuario
$stmt = $conexion->prepare("SELECT id, contrasena FROM usuarios WHERE usuario = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $id_usuario = $row['id'];

    if (password_verify($contrasena_actual, $row['contrasena'])) {
        // Contraseña actual correcta, actualizamos a la nueva
        $nueva_hash = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
        $update = $conexion->prepare("UPDATE usuarios SET contrasena = ? WHERE id = ?");
        $update->bind_param("si", $nueva_hash, $id_usuario);
        if ($update->execute()) {
            mostrarMensaje("Contraseña actualizada correctamente. Redirigiendo...", true, 'intranet.php');
        } else {
            mostrarMensaje("Error al actualizar la contraseña.", false);
        }
        $update->close();
    } else {
        mostrarMensaje("Contraseña actual incorrecta.", false);
    }
} else {
    mostrarMensaje("Error en la solicitud.", false);
}

$stmt->close();
$conexion->close();

function mostrarMensaje($mensaje, $exito = true, $redirect = '') {
    $color = $exito ? 'green' : 'red';
    echo "<!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <title>Cambio de Contraseña</title>
    </head>
    <body>
        <p style='color: $color; font-weight: bold;'>".htmlspecialchars($mensaje)."</p>";
    if ($exito && $redirect) {
        echo "<script>
                setTimeout(() => { window.location.href = '".htmlspecialchars($redirect)."'; }, 3000);
              </script>";
    } else {
        echo "<p><a href='cambiar_contrasena.php'>Volver</a></p>";
    }
    echo "</body></html>";
}
?>