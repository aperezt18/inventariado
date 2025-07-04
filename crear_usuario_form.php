<?php
require_once "control_licencia.php";
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once "conexion.php";
$errores = [];
$exito = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST['nombre'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';
    $rol = $_POST['rol'] ?? 'invitado';

    // Validación básica
    if ($nombre === '' || $usuario === '' || $contrasena === '' || !in_array($rol, ['admin','coordinador','tecnico','invitado'])) {
        $errores[] = "Todos los campos son obligatorios y el rol debe ser válido.";
    } else {
        // Comprobar que no existe ya ese usuario
        $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errores[] = "El nombre de usuario ya está en uso.";
        } else {
            $hash = password_hash($contrasena, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, usuario, contrasena, rol) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nombre, $usuario, $hash, $rol);
            if ($stmt->execute()) {
                $exito = true;
            } else {
                $errores[] = "Error al crear usuario: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear usuario nuevo</title>
</head>
<body>
    <h2>Crear usuario nuevo</h2>
    <p><a href="intranet.php">← Volver a intranet</a></p>

    <?php if ($exito): ?>
        <p style="color:green;">✅ Usuario creado correctamente.</p>
    <?php endif; ?>

    <?php if (!empty($errores)): ?>
        <ul style="color:red;">
            <?php foreach ($errores as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post">
        <label>Nombre completo:<br>
            <input type="text" name="nombre" required>
        </label><br><br>

        <label>Nombre de usuario:<br>
            <input type="text" name="usuario" required>
        </label><br><br>

        <label>Contraseña:<br>
            <input type="password" name="contrasena" required>
        </label><br><br>

        <label>Rol:<br>
            <select name="rol" required>
                <option value="admin">Administrador</option>
                <option value="coordinador">Coordinador</option>
                <option value="tecnico">Técnico</option>
                <option value="invitado" selected>Invitado</option>
            </select>
        </label><br><br>

        <button type="submit">Crear usuario</button>
    </form>
</body>
</html>