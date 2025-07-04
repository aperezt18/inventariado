<?php
session_start();
require_once "conexion.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';

    if ($usuario === '' || $contrasena === '') {
        $error = "Por favor, completa todos los campos.";
    } else {
        $stmt = $conexion->prepare("SELECT id, nombre, usuario, contrasena, rol FROM usuarios WHERE usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $fila = $resultado->fetch_assoc();
            if (password_verify($contrasena, $fila['contrasena'])) {
                // Usuario válido, guardamos sesión
                $_SESSION['id'] = $fila['id'];
                $_SESSION['usuario'] = $fila['usuario'];
                $_SESSION['nombre'] = $fila['nombre'];
                $_SESSION['rol'] = $fila['rol'];


                $stmtLog = $conexion->prepare("INSERT INTO logs_sesiones (usuario_id) VALUES (?)");
                $stmtLog->bind_param("i", $fila['id']);
                $stmtLog->execute();
                $stmtLog->close();
    

                header("Location: intranet.php");
                exit();
            } else {
                $error = "Contraseña incorrecta.";
            }
        } else {
            $error = "Usuario no encontrado.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
</head>
<body>
    <h2>Iniciar sesión</h2>
    <?php if (!empty($error)): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post" action="login.php">
        <label>Usuario:<br>
            <input type="text" name="usuario" required>
        </label><br><br>
        <label>Contraseña:<br>
            <input type="password" name="contrasena" required>
        </label><br><br>
        <input type="submit" value="Entrar">
    </form>
</body>
</html>
