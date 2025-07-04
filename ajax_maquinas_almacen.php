<?php
require_once "control_licencia.php";
if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'], ['admin', 'coordinador', 'tecnico'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

require_once 'conexion.php';

$tipo = $_GET['tipo'] ?? '';
$ubicacion_origen = $_GET['origen'] ?? '';

$tipo = strtoupper(trim($tipo));
$ubicacion_origen = strtoupper(trim($ubicacion_origen));

if ($tipo === '' || $ubicacion_origen === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros incompletos']);
    exit();
}

// Tipos compatibles
$tipos_validos = [
    'ATB' => ['ATB', 'BTP'],
    'BTP' => ['ATB', 'BTP'],
];

$tipos_compatibles = $tipos_validos[$tipo] ?? [$tipo];

$placeholders = implode(',', array_fill(0, count($tipos_compatibles), '?'));
$params = $tipos_compatibles;
$types = str_repeat('s', count($params));

$sql = "SELECT modelo, numero_serie, tipo_maquina, estado FROM inventario 
        WHERE ubicacion = 'ALMACÉN' AND tipo_maquina IN ($placeholders)";

$stmt = $conexion->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$maquinas = [];
while ($row = $res->fetch_assoc()) {
    $maquinas[] = $row;
}

echo json_encode($maquinas);
