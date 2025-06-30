<?php
$host = "localhost";
$usuario = "root";
$contrasena = "";
$base_de_datos = "gestion_inventario";

$conexion = new mysqli($host, $usuario, $contrasena, $base_de_datos);
$conexion->set_charset("utf8");

if ($conexion->connect_error) {
    die("Conexión fallida: " . $conexion->connect_error);
}
?>
