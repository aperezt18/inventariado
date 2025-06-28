<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cambiar contraseña</title>
</head>
<body>
    <h2>Cambiar contraseña</h2>
    <form action="procesar_cambio_contrasena.php" method="post">
        <label>Contraseña actual:</label>
        <input type="password" name="contrasena_actual" required><br>

        <label>Nueva contraseña:</label>
        <input type="password" name="nueva_contrasena" required><br>

        <label>Repetir nueva contraseña:</label>
        <input type="password" name="repetir_contrasena" required><br>

        <button type="submit">Cambiar</button>
    </form>

    <p><a href="intranet.php">Volver</a></p>
</body>
</html>
