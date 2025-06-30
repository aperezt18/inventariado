<?php
session_start();
// Verificación estricta: comprobar que 'usuario' existe y no está vacío
if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
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
        <label for="contrasena_actual">Contraseña actual:</label>
        <input type="password" id="contrasena_actual" name="contrasena_actual" required autocomplete="current-password"><br>

        <label for="nueva_contrasena">Nueva contraseña:</label>
        <input type="password" id="nueva_contrasena" name="nueva_contrasena" required minlength="8" autocomplete="new-password"><br>

        <label for="repetir_contrasena">Repetir nueva contraseña:</label>
        <input type="password" id="repetir_contrasena" name="repetir_contrasena" required minlength="8" autocomplete="new-password"><br>

        <button type="submit">Cambiar</button>
    </form>

    <p><a href="intranet.php">Volver</a></p>
</body>
</html>
