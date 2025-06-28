<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

require_once 'conexion.php';

$usuario = $_SESSION['usuario'];
$contrasena_actual = $_POST['contrasena_actual'];
$nueva_contrasena = $_POST['nueva_contrasena'];
$repetir_contrasena = $_POST['repetir_contrasena'];

if ($nueva_contrasena !== $repetir_contrasena) {
    echo "❌ Las contraseñas nuevas no coinciden.";
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
        $update->execute();

        echo "✅ Contraseña actualizada correctamente. Redirigiendo...";
        echo "<script>
                setTimeout(() => {
                    window.location.href = 'intranet.php';
                }, 3000);
              </script>";
    } else {
        echo "❌ La contraseña actual es incorrecta.";
    }
} else {
    echo "❌ Usuario no encontrado.";
}
?>
