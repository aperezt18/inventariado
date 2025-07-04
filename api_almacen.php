<?php
require_once "control_licencia.php";
require_once "conexion.php";
require_once "funciones_inventario.php";
require_once "helpers/inventario.php";


header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$tipo = $_GET['tipo'] ?? '';
$modelo = $_GET['modelo'] ?? '';

if (!$tipo || !$modelo) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan parámetros tipo o modelo']);
    exit;
}

$maquinas = listarMaquinasAlmacenCompatibles($conexion, strtoupper($tipo), $modelo);

echo json_encode(['maquinas' => $maquinas]);
