<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "control_licencia.php";
require_once "conexion.php";
require_once "funciones_inventario.php";
require_once "helpers/inventario.php";


header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id']) || !is_numeric($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesión inválida.']);
    exit;
}

$rol = $_SESSION['rol'] ?? '';
$usuario_id = (int) $_SESSION['id'];
$esAdmin = ($rol === 'admin');
$puedeEditar = in_array($rol, ['admin', 'coordinador', 'tecnico']);

if (!$puedeEditar) {
    http_response_code(403);
    echo json_encode(['error' => 'No tienes permisos para editar máquinas.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

require_once "helpers/inventario.php";

$numero_serie = $_POST['serial'] ?? null;
if (!$numero_serie) {
    http_response_code(400);
    echo json_encode(['error' => 'Número de serie no especificado']);
    exit;
}
$numero_serie = limpiar($numero_serie);

$stmt = $conexion->prepare("SELECT * FROM inventario WHERE numero_serie = ?");
$stmt->bind_param("s", $numero_serie);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Máquina no encontrada']);
    exit;
}
$maquina = $res->fetch_assoc();
$stmt->close();

$id = (int)$maquina['id'];
$ubicacion_actual = $maquina['ubicacion'];
$estado_actual = $maquina['estado'];
$tipo_actual = strtoupper($maquina['tipo_maquina']);
$vlc_actual = $maquina['codigo_vlc'];
$modelo = $maquina['modelo'];

$ubicacion_nueva = strtoupper(limpiar($_POST['ubicacion'] ?? ''));
$estado_nuevo = limpiar($_POST['estado'] ?? '');
$tipo_nuevo = $esAdmin ? strtoupper(limpiar($_POST['tipo_maquina'] ?? '')) : $tipo_actual;
$vlc_nuevo = $esAdmin ? limpiar($_POST['codigo_vlc'] ?? '') : $vlc_actual;
$id_intercambio_almacen = isset($_POST['id_intercambio_almacen']) && $_POST['id_intercambio_almacen'] !== '' ? (int) $_POST['id_intercambio_almacen'] : null;

$tipos_validos = ['ATB', 'BTP', 'PISTOLA', 'MONITOR', 'CPU', 'MSR/OCR', 'DCP', 'LECTOR_BGR'];

if (!esUbicacionValida($ubicacion_nueva)) {
    http_response_code(400);
    echo json_encode(['error' => "Ubicación '$ubicacion_nueva' no válida."]);
    exit;
}
if (!estadoValido($estado_nuevo, $ubicacion_nueva)) {
    http_response_code(400);
    echo json_encode(['error' => "Estado '$estado_nuevo' no permitido en '$ubicacion_nueva'."]);
    exit;
}
if (!in_array($tipo_nuevo, $tipos_validos)) {
    http_response_code(400);
    echo json_encode(['error' => "Tipo de máquina '$tipo_nuevo' no válido."]);
    exit;
}
if ($vlc_nuevo !== $vlc_actual && existeDuplicadoVLC($conexion, $vlc_nuevo, $id)) {
    http_response_code(400);
    echo json_encode(['error' => "El código VLC '$vlc_nuevo' ya existe en otra máquina."]);
    exit;
}
if (!tipoCompatible($tipo_nuevo, $ubicacion_nueva, $modelo)) {
    http_response_code(400);
    echo json_encode(['error' => "Tipo '$tipo_nuevo' incompatible con ubicación '$ubicacion_nueva'."]);
    exit;
}

$conexion->begin_transaction();

try {
    // 🧠 CASO 1: Intercambio entre dos ubicaciones diferentes que NO involucran almacén
    if ($ubicacion_actual !== $ubicacion_nueva && $ubicacion_actual !== 'ALMACÉN' && $ubicacion_nueva !== 'ALMACÉN') {

        $maquina_intercambio = buscarMaquinaParaIntercambio($conexion, $ubicacion_nueva, $tipo_nuevo, $id);
        if (!$maquina_intercambio) {
            throw new Exception("Debe existir una máquina del mismo tipo en '$ubicacion_nueva' para intercambiar.");
        }

        actualizarUbicacion($conexion, $id, $ubicacion_nueva);
        actualizarUbicacion($conexion, $maquina_intercambio['id'], $ubicacion_actual);

        // Obtener tipos simulados para la ubicación después del movimiento
        $tipos_destino = obtenerTiposSimulados($conexion, $ubicacion_nueva, $tipo_nuevo, $id);
        $val1 = validarRestriccionesUbicacionSimulada($tipos_destino, $ubicacion_nueva);
        $val2 = validarRestriccionesUbicacion($conexion, $ubicacion_actual);
        if ($val1 !== true) throw new Exception($val1);
        if ($val2 !== true) throw new Exception($val2);

        // Registrar cambios
        registrarCambio($conexion, $id, $usuario_id, 'ubicacion', $ubicacion_actual, $ubicacion_nueva);
        registrarCambio($conexion, $maquina_intercambio['id'], $usuario_id, 'ubicacion', $ubicacion_nueva, $ubicacion_actual);

        // Tipo, estado y VLC
        if ($tipo_actual !== $tipo_nuevo || $estado_actual !== $estado_nuevo || $vlc_actual !== $vlc_nuevo) {
            actualizarMaquina($conexion, $id, $tipo_nuevo, $vlc_nuevo, $estado_nuevo);
            if ($tipo_actual !== $tipo_nuevo) registrarCambio($conexion, $id, $usuario_id, 'tipo_maquina', $tipo_actual, $tipo_nuevo);
            if ($estado_actual !== $estado_nuevo) registrarCambio($conexion, $id, $usuario_id, 'estado', $estado_actual, $estado_nuevo);
            if ($vlc_actual !== $vlc_nuevo) registrarCambio($conexion, $id, $usuario_id, 'codigo_vlc', $vlc_actual, $vlc_nuevo);
        }

    // 🧠 CASO 2: Edición a ALMACÉN con intercambio
    } elseif ($ubicacion_nueva === 'ALMACÉN' && $id_intercambio_almacen !== null) {
        $stmt = $conexion->prepare("SELECT * FROM inventario WHERE id = ?");
        $stmt->bind_param("i", $id_intercambio_almacen);
        $stmt->execute();
        $res_alm = $stmt->get_result();
        if ($res_alm->num_rows === 0) {
            throw new Exception("Máquina del almacén no encontrada.");
        }
        $m_alm = $res_alm->fetch_assoc();
        $stmt->close();

        if ($m_alm['tipo_maquina'] !== $tipo_nuevo) {
            throw new Exception("Solo se permite intercambio con máquina del mismo tipo desde almacén.");
        }

        // Actualizar máquina editada -> ALMACÉN
        actualizarMaquinaCompleto($conexion, $id, 'ALMACÉN', $tipo_nuevo, $estado_nuevo, $vlc_nuevo);
        registrarCambio($conexion, $id, $usuario_id, 'ubicacion', $ubicacion_actual, 'ALMACÉN');
        if ($tipo_actual !== $tipo_nuevo) registrarCambio($conexion, $id, $usuario_id, 'tipo_maquina', $tipo_actual, $tipo_nuevo);
        if ($estado_actual !== $estado_nuevo) registrarCambio($conexion, $id, $usuario_id, 'estado', $estado_actual, $estado_nuevo);
        if ($vlc_actual !== $vlc_nuevo) registrarCambio($conexion, $id, $usuario_id, 'codigo_vlc', $vlc_actual, $vlc_nuevo);

        // Máquina del almacén -> va a la ubicación que libera
        actualizarUbicacion($conexion, $m_alm['id'], $ubicacion_actual);
        registrarCambio($conexion, $m_alm['id'], $usuario_id, 'ubicacion', 'ALMACÉN', $ubicacion_actual);

// 🔍 Validar que la nueva ubicación ocupada por la máquina del almacén sigue siendo válida
$val = validarRestriccionesUbicacion($conexion, $ubicacion_actual);
if ($val !== true) throw new Exception($val);

 
} elseif ($ubicacion_actual === 'ALMACÉN' && $ubicacion_nueva !== 'ALMACÉN') {
    // 🧠 CASO 3: Máquina EN almacén se edita para ir a ubicación (sin modal, intercambio automático)
    // Buscar máquina ocupante del mismo tipo
    $maquina_ocupante = buscarMaquinaParaIntercambio($conexion, $ubicacion_nueva, $tipo_nuevo, -1);
    if (!$maquina_ocupante) {
        throw new Exception("No se encontró máquina del mismo tipo en '$ubicacion_nueva' para intercambiar desde almacén.");
    }

    // Validar que el intercambio mantiene compatibilidad
    if (!tipoCompatible($maquina_ocupante['tipo_maquina'], 'ALMACÉN', $maquina_ocupante['modelo'])) {
        throw new Exception("La máquina que se movería a almacén no es compatible con ALMACÉN.");
    }

    // Intercambiar ubicaciones
    actualizarUbicacion($conexion, $id, $ubicacion_nueva);
    actualizarUbicacion($conexion, $maquina_ocupante['id'], 'ALMACÉN');

    // 🔍 Validar que la ubicación nueva sigue cumpliendo las restricciones después del intercambio
    $val = validarRestriccionesUbicacion($conexion, $ubicacion_nueva);
    if ($val !== true) throw new Exception($val);

    // Registrar cambios
    registrarCambio($conexion, $id, $usuario_id, 'ubicacion', 'ALMACÉN', $ubicacion_nueva);
    registrarCambio($conexion, $maquina_ocupante['id'], $usuario_id, 'ubicacion', $ubicacion_nueva, 'ALMACÉN');

    // Tipo, estado, VLC
    actualizarMaquina($conexion, $id, $tipo_nuevo, $vlc_nuevo, $estado_nuevo);
    if ($tipo_actual !== $tipo_nuevo) registrarCambio($conexion, $id, $usuario_id, 'tipo_maquina', $tipo_actual, $tipo_nuevo);
    if ($estado_actual !== $estado_nuevo) registrarCambio($conexion, $id, $usuario_id, 'estado', $estado_actual, $estado_nuevo);
    if ($vlc_actual !== $vlc_nuevo) registrarCambio($conexion, $id, $usuario_id, 'codigo_vlc', $vlc_actual, $vlc_nuevo);


    } else {
        // Caso directo sin intercambio
        actualizarMaquinaCompleto($conexion, $id, $ubicacion_nueva, $tipo_nuevo, $estado_nuevo, $vlc_nuevo);
        if ($ubicacion_actual !== $ubicacion_nueva) registrarCambio($conexion, $id, $usuario_id, 'ubicacion', $ubicacion_actual, $ubicacion_nueva);
        if ($estado_actual !== $estado_nuevo) registrarCambio($conexion, $id, $usuario_id, 'estado', $estado_actual, $estado_nuevo);
        if ($tipo_actual !== $tipo_nuevo) registrarCambio($conexion, $id, $usuario_id, 'tipo_maquina', $tipo_actual, $tipo_nuevo);
        if ($vlc_actual !== $vlc_nuevo) registrarCambio($conexion, $id, $usuario_id, 'codigo_vlc', $vlc_actual, $vlc_nuevo);
    }

    $conexion->commit();
    echo json_encode(['mensaje' => 'Actualización correcta']);
} catch (Exception $e) {
    $conexion->rollback();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conexion->close();
