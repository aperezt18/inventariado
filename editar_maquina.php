<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION['id']) || !is_numeric($_SESSION['id'])) {
    die("Sesión inválida. Vuelve a iniciar sesión.");
}

$rol = $_SESSION['rol'] ?? '';
$usuario_id = (int) $_SESSION['id'];
$esAdmin = ($rol === 'admin');
$puedeEditar = in_array($rol, ['admin', 'coordinador', 'tecnico']);

function limpiar($v) {
    return htmlspecialchars(trim($v));
}

function esUbicacionValida($ubicacion) {
    return preg_match('/^(M(0[1-9]|[1-3][0-9]|4[3-9]|5[0-9]|6[0-2])|P(0[1-9]|1[0-9]|2[0-2])[AB]?|PTAG|ALMACÉN|TUNEL)$/', $ubicacion);
}

/**
 * Devuelve array de tipos permitidos en ubicación dada
 * Según reglas, ATB y BTP son especiales y pueden intercambiarse si modelo compatible.
 */
function tiposPermitidosParaUbicacion($ubicacion) {
    if (in_array($ubicacion, ['ALMACÉN', 'TUNEL'])) return null;
    if (preg_match('/^M/', $ubicacion)) return ['ATB', 'BTP', 'PISTOLA', 'MONITOR', 'CPU', 'MSR/OCR'];
    if (preg_match('/^P/', $ubicacion)) return ['BTP', 'DCP', 'LECTOR_BGR', 'MONITOR', 'CPU', 'MSR/OCR'];
    if ($ubicacion === 'PTAG') return ['BTP', 'DCP', 'LECTOR_BGR', 'MONITOR', 'CPU', 'MSR/OCR'];
    return null;
}

/**
 * Verifica si tipo es compatible con ubicación.
 * Considera excepción ATB <-> BTP si modelo lo permite.
 */
function tipoCompatible($tipo, $ubicacion, $modelo = '') {
    $permitidos = tiposPermitidosParaUbicacion($ubicacion);
    if ($permitidos === null) return true;

    $tipoUpper = strtoupper($tipo);
    $permitidosUpper = array_map('strtoupper', $permitidos);

    if (in_array($tipoUpper, $permitidosUpper)) return true;

    // Excepción ATB <-> BTP si modelo contiene "ATB" o "BTP"
    if (($tipoUpper === 'ATB' && in_array('BTP', $permitidosUpper)) ||
        ($tipoUpper === 'BTP' && in_array('ATB', $permitidosUpper))) {
        if (stripos($modelo, 'ATB') !== false || stripos($modelo, 'BTP') !== false) {
            return true;
        }
    }

    return false;
}

function estadoValido($estado, $ubicacion) {
    if ($ubicacion !== 'ALMACÉN' && in_array($estado, ['Stock', 'Garantía', 'Reparación'])) return false;
    return true;
}

function registrarCambio($conexion, $id_inv, $id_usr, $campo, $antiguo, $nuevo) {
    $stmt = $conexion->prepare("INSERT INTO logs_cambios (inventario_id, usuario_id, campo_modificado, valor_anterior, valor_nuevo, fecha) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisss", $id_inv, $id_usr, $campo, $antiguo, $nuevo);
    $stmt->execute();
    $stmt->close();
}

/**
 * Verifica si existe duplicado del tipo en la ubicación,
 * ignorando la máquina con id $id_actual.
 * Permite excepciones (ejemplo lector_bgr puede repetirse).
 */
function existeDuplicadoTipo($conexion, $ubicacion, $tipo, $id_actual = null) {
    $tipoUpper = strtoupper($tipo);
    $ubicPrefix = strtoupper(substr($ubicacion, 0, 1));

    $tiposConDuplicados = [
        'P' => ['LECTOR_BGR'],
        'PTAG' => ['LECTOR_BGR'],
    ];

    if (in_array($ubicacion, ['ALMACÉN', 'TUNEL'])) return false;

    if (($ubicPrefix === 'P' || $ubicacion === 'PTAG') &&
        isset($tiposConDuplicados[$ubicPrefix]) &&
        in_array($tipoUpper, $tiposConDuplicados[$ubicPrefix])) {
        return false;
    }

    if ($id_actual === null) {
        $stmt = $conexion->prepare("SELECT COUNT(*) AS cnt FROM inventario WHERE ubicacion = ? AND tipo_maquina = ?");
        $stmt->bind_param("ss", $ubicacion, $tipo);
    } else {
        $stmt = $conexion->prepare("SELECT COUNT(*) AS cnt FROM inventario WHERE ubicacion = ? AND tipo_maquina = ? AND id != ?");
        $stmt->bind_param("ssi", $ubicacion, $tipo, $id_actual);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ($result['cnt'] > 0);
}

/**
 * Verifica si existe duplicado del código VLC en el inventario,
 * ignorando la máquina con id $id_actual.
 */
function existeDuplicadoVLC($conexion, $codigo_vlc, $id_actual = null) {
    if ($id_actual === null) {
        $stmt = $conexion->prepare("SELECT COUNT(*) AS cnt FROM inventario WHERE codigo_vlc = ?");
        $stmt->bind_param("s", $codigo_vlc);
    } else {
        $stmt = $conexion->prepare("SELECT COUNT(*) AS cnt FROM inventario WHERE codigo_vlc = ? AND id != ?");
        $stmt->bind_param("si", $codigo_vlc, $id_actual);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ($result['cnt'] > 0);
}

// Obtener máquina
$numero_serie = $_POST['serial'] ?? $_GET['serial'] ?? null;
if (!$numero_serie) die("Número de serie no especificado.");
$numero_serie = limpiar($numero_serie);

$stmt = $conexion->prepare("SELECT * FROM inventario WHERE numero_serie = ?");
$stmt->bind_param("s", $numero_serie);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) die("Máquina no encontrada.");
$maquina = $res->fetch_assoc();
$stmt->close();

$id = (int)$maquina['id'];
$ubicacion_actual = $maquina['ubicacion'];
$estado_actual = $maquina['estado'];
$tipo_actual = strtoupper($maquina['tipo_maquina']);
$vlc_actual = $maquina['codigo_vlc'];
$modelo = $maquina['modelo'];
$serial = $maquina['numero_serie'];

// Tipos válidos para el select (los que usa el sistema)
$tipos_validos = ['ATB', 'BTP', 'PISTOLA', 'MONITOR', 'CPU', 'MSR/OCR', 'DCP', 'LECTOR_BGR'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ubicacion_nueva = strtoupper(limpiar($_POST['ubicacion'] ?? ''));
    $estado_nuevo = limpiar($_POST['estado'] ?? '');
    $tipo_nuevo = $esAdmin ? strtoupper(limpiar($_POST['tipo_maquina'])) : $tipo_actual;
    $vlc_nuevo = $esAdmin ? limpiar($_POST['codigo_vlc']) : $vlc_actual;

    if (!esUbicacionValida($ubicacion_nueva) || !$estado_nuevo) {
        die("Ubicación o estado no válidos.");
    }

    if (!estadoValido($estado_nuevo, $ubicacion_nueva)) {
        die("Estado '$estado_nuevo' no permitido en '$ubicacion_nueva'.");
    }

    if (!in_array($tipo_nuevo, $tipos_validos)) {
        die("Tipo de máquina '$tipo_nuevo' no válido.");
    }

    // NUEVO: validar duplicado VLC
    if ($vlc_nuevo !== $vlc_actual && existeDuplicadoVLC($conexion, $vlc_nuevo, $id)) {
        die("El código VLC '$vlc_nuevo' ya existe en otra máquina del inventario.");
    }

    // Comprobar compatibilidad tipo-ubicación
    if (!tipoCompatible($tipo_nuevo, $ubicacion_nueva, $modelo)) {
        die("El tipo '$tipo_nuevo' no es compatible con la ubicación '$ubicacion_nueva'.");
    }

    // Caso 1: misma ubicación
    if ($ubicacion_nueva === $ubicacion_actual) {
        // Validar duplicado tipo, excepto si es la misma máquina (se actualiza)
        if (existeDuplicadoTipo($conexion, $ubicacion_nueva, $tipo_nuevo, $id)) {
            die("No se puede tener más de una máquina del tipo '$tipo_nuevo' en la ubicación '$ubicacion_nueva'.");
        }

        // Actualizar datos
        $stmt = $conexion->prepare("UPDATE inventario SET tipo_maquina = ?, codigo_vlc = ?, estado = ? WHERE id = ?");
        $stmt->bind_param("sssi", $tipo_nuevo, $vlc_nuevo, $estado_nuevo, $id);

        if ($stmt->execute()) {
            if ($estado_nuevo !== $estado_actual) registrarCambio($conexion, $id, $usuario_id, 'estado', $estado_actual, $estado_nuevo);
            if ($esAdmin && $tipo_nuevo !== $tipo_actual) registrarCambio($conexion, $id, $usuario_id, 'tipo_maquina', $tipo_actual, $tipo_nuevo);
            if ($esAdmin && $vlc_nuevo !== $vlc_actual) registrarCambio($conexion, $id, $usuario_id, 'codigo_vlc', $vlc_actual, $vlc_nuevo);
            echo "Registro actualizado correctamente.<br><a href='intranet.php'>Volver</a>";
        } else {
            echo "Error al actualizar: " . $stmt->error;
        }
        $stmt->close();
        $conexion->close();
        exit;
    }

    // Caso 2: cambia ubicación
    // Comprobar si cambio tipo ATB <-> BTP con modelo compatible
    $cambioTipoATBBTP =
        (($tipo_actual === 'ATB' && $tipo_nuevo === 'BTP') || ($tipo_actual === 'BTP' && $tipo_nuevo === 'ATB')) &&
        (stripos($modelo, 'ATB') !== false || stripos($modelo, 'BTP') !== false);

    if ($cambioTipoATBBTP) {
        // Permitir cambio directo sin intercambio, validar que no haya duplicado en ubicación nueva
        if (existeDuplicadoTipo($conexion, $ubicacion_nueva, $tipo_nuevo, $id)) {
            die("No se puede tener más de una máquina del tipo '$tipo_nuevo' en la ubicación '$ubicacion_nueva'.");
        }

        // Actualizar ubicación, tipo, estado, vlc
        $stmt = $conexion->prepare("UPDATE inventario SET ubicacion = ?, tipo_maquina = ?, estado = ?, codigo_vlc = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $ubicacion_nueva, $tipo_nuevo, $estado_nuevo, $vlc_nuevo, $id);

        if ($stmt->execute()) {
            registrarCambio($conexion, $id, $usuario_id, 'ubicacion', $ubicacion_actual, $ubicacion_nueva);
            if ($estado_nuevo !== $estado_actual) registrarCambio($conexion, $id, $usuario_id, 'estado', $estado_actual, $estado_nuevo);
            if ($esAdmin && $tipo_nuevo !== $tipo_actual) registrarCambio($conexion, $id, $usuario_id, 'tipo_maquina', $tipo_actual, $tipo_nuevo);
            if ($esAdmin && $vlc_nuevo !== $vlc_actual) registrarCambio($conexion, $id, $usuario_id, 'codigo_vlc', $vlc_actual, $vlc_nuevo);

            echo "Registro actualizado correctamente (cambio ATB/BTP).<br><a href='intranet.php'>Volver</a>";
        } else {
            echo "Error al actualizar: " . $stmt->error;
        }
        $stmt->close();
        $conexion->close();
        exit;
    }

    // Si no es ese cambio especial, hacemos intercambio atómico
    $conexion->begin_transaction();

    // Buscar máquina para intercambio (mismo tipo) en ubicación nueva
    $stmt = $conexion->prepare("SELECT * FROM inventario WHERE ubicacion = ? AND tipo_maquina = ? AND id != ? LIMIT 1");
    $stmt->bind_param("ssi", $ubicacion_nueva, $tipo_nuevo, $id);
    $stmt->execute();
    $otra = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$otra) {
        $conexion->rollback();
        die("No hay máquina del mismo tipo disponible para intercambio en '$ubicacion_nueva'.");
    }

    // Validar compatibilidad tipo ubicación para la máquina de intercambio
    if (!tipoCompatible($otra['tipo_maquina'], $ubicacion_actual, $otra['modelo'])) {
        $conexion->rollback();
        die("La máquina '{$otra['tipo_maquina']}' no es compatible con la ubicación '$ubicacion_actual'.");
    }

    // Validar no duplicados después del intercambio
    if (existeDuplicadoTipo($conexion, $ubicacion_nueva, $tipo_nuevo, $otra['id'])) {
        $conexion->rollback();
        die("No se puede tener más de una máquina del tipo '$tipo_nuevo' en la ubicación '$ubicacion_nueva' tras el intercambio.");
    }
    if (existeDuplicadoTipo($conexion, $ubicacion_actual, $otra['tipo_maquina'], $id)) {
        $conexion->rollback();
        die("No se puede tener más de una máquina del tipo '{$otra['tipo_maquina']}' en la ubicación '$ubicacion_actual' tras el intercambio.");
    }

    // NUEVO: validar duplicado VLC en intercambio para la máquina editada
    if ($vlc_nuevo !== $vlc_actual && existeDuplicadoVLC($conexion, $vlc_nuevo, $id)) {
        $conexion->rollback();
        die("El código VLC '$vlc_nuevo' ya existe en otra máquina del inventario.");
    }

    // Realizar intercambio
    $stmt1 = $conexion->prepare("UPDATE inventario SET ubicacion = ?, estado = ?, tipo_maquina = ?, codigo_vlc = ? WHERE id = ?");
    $stmt1->bind_param("ssssi", $ubicacion_nueva, $estado_nuevo, $tipo_nuevo, $vlc_nuevo, $id);

    $stmt2 = $conexion->prepare("UPDATE inventario SET ubicacion = ? WHERE id = ?");
    $stmt2->bind_param("si", $ubicacion_actual, $otra['id']);

    if ($stmt1->execute() && $stmt2->execute()) {
        registrarCambio($conexion, $id, $usuario_id, 'ubicacion', $ubicacion_actual, $ubicacion_nueva);
        if ($estado_nuevo !== $estado_actual) registrarCambio($conexion, $id, $usuario_id, 'estado', $estado_actual, $estado_nuevo);
        if ($esAdmin && $tipo_nuevo !== $tipo_actual) registrarCambio($conexion, $id, $usuario_id, 'tipo_maquina', $tipo_actual, $tipo_nuevo);
        if ($esAdmin && $vlc_nuevo !== $vlc_actual) registrarCambio($conexion, $id, $usuario_id, 'codigo_vlc', $vlc_actual, $vlc_nuevo);
        registrarCambio($conexion, $otra['id'], $usuario_id, 'ubicacion', $ubicacion_nueva, $ubicacion_actual);

        $conexion->commit();

        echo "<div style='
            position: fixed;
            top: 40%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #e0ffe0;
            border: 2px solid #0a0;
            padding: 20px;
            font-family: Arial, sans-serif;
            font-size: 16px;
            box-shadow: 0 0 10px #0a0;
            z-index: 9999;
        '>Intercambio realizado correctamente.<br><br><a href='intranet.php'>Volver al inventario</a></div>";
    } else {
        $conexion->rollback();
        echo "Error al realizar el intercambio.";
    }

    $stmt1->close();
    $stmt2->close();
    $conexion->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Editar Máquina</title>
</head>
<body>
<h2>Editar Máquina</h2>
<form method="post">
    <input type="hidden" name="serial" value="<?= htmlspecialchars($serial) ?>">

    <label>Tipo:<br>
        <select name="tipo_maquina" <?= $esAdmin ? '' : 'disabled' ?>>
            <?php
            foreach ($tipos_validos as $tipo) {
                $sel = ($tipo === $tipo_actual) ? 'selected' : '';
                echo "<option value=\"$tipo\" $sel>$tipo</option>";
            }
            ?>
        </select>
    </label><br><br>

    <label>Modelo:<br>
        <input type="text" value="<?= htmlspecialchars($modelo) ?>" readonly>
    </label><br><br>

    <label>Serie:<br>
        <input type="text" value="<?= htmlspecialchars($serial) ?>" readonly>
    </label><br><br>

    <label>VLC:<br>
        <input type="text" name="codigo_vlc" value="<?= htmlspecialchars($vlc_actual) ?>" <?= $esAdmin ? '' : 'readonly' ?>>
    </label><br><br>

    <label>Ubicación:<br>
        <select name="ubicacion">
            <?php
            $ubicaciones = array_merge(
                array_map(fn($i) => 'M' . str_pad($i, 2, '0', STR_PAD_LEFT), range(1, 38)),
                array_map(fn($i) => 'M' . $i, range(43, 62)),
                ['P01A', 'P01B'],
                array_map(fn($i) => 'P' . str_pad($i, 2, '0', STR_PAD_LEFT), range(2, 22)),
                ['PTAG', 'ALMACÉN']
            );
            foreach ($ubicaciones as $ub) {
                $sel = ($ubicacion_actual === $ub) ? 'selected' : '';
                echo "<option value=\"$ub\" $sel>$ub</option>";
            }
            ?>
        </select>
    </label><br><br>

    <label>Estado:<br>
        <select name="estado">
            <?php
            foreach (['Uso', 'Garantía', 'Reparación', 'Stock'] as $op) {
                $sel = ($estado_actual === $op) ? 'selected' : '';
                echo "<option value=\"$op\" $sel>$op</option>";
            }
            ?>
        </select>
    </label><br><br>

    <input type="submit" value="Guardar cambios">
</form>
<p><a href="intranet.php">← Volver al inventario</a></p>
</body>
</html>