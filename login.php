<?php
session_start();
require_once 'conexion.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];

    $stmt = $conexion->prepare("SELECT id, contrasena, rol FROM usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $hash_guardado, $rol);
        $stmt->fetch();

        if (password_verify($contrasena, $hash_guardado)) {
            $_SESSION['usuario_id'] = $id;
            $_SESSION['usuario'] = $usuario;
            $_SESSION['rol'] = $rol;

            // Registrar inicio de sesión
            $conexion->query("INSERT INTO logs_sesiones (usuario_id) VALUES ($id)");

            header("Location: intranet.php");
            exit;
        } else {
            $mensaje = "Contraseña incorrecta.";
        }
    } else {
        $mensaje = "Usuario no encontrado.";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login Inventario</title>
</head>
<body>
    <h2>Iniciar Sesión</h2>
    <form method="POST">
        <input type="text" name="usuario" placeholder="Usuario" required><br>
        <input type="password" name="contrasena" placeholder="Contraseña" required><br>
        <input type="submit" value="Entrar">
    </form>
    <p style="color:red"><?= $mensaje ?></p>
</body>
</html>
