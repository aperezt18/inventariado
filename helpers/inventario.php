<?php
// helpers/inventario.php

/**
 * Obtiene máquina por número de serie.
 */
function obtenerMaquinaPorSerial($conexion, $serial) {
    $stmt = $conexion->prepare("SELECT * FROM inventario WHERE numero_serie = ?");
    $stmt->bind_param("s", $serial);
    $stmt->execute();
    $result = $stmt->get_result();
    $maquina = $result->fetch_assoc();
    $stmt->close();
    return $maquina ?: null;
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

/**
 * Actualiza solo tipo, codigo_vlc y estado (misma ubicación).
 */
function actualizarMaquina($conexion, $id, $tipo, $vlc, $estado) {
    $stmt = $conexion->prepare("UPDATE inventario SET tipo_maquina = ?, codigo_vlc = ?, estado = ? WHERE id = ?");
    $stmt->bind_param("sssi", $tipo, $vlc, $estado, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Actualiza todos los campos principales: ubicación, tipo, estado, vlc.
 */
function actualizarMaquinaCompleto($conexion, $id, $ubicacion, $tipo, $estado, $vlc) {
    $stmt = $conexion->prepare("UPDATE inventario SET ubicacion = ?, tipo_maquina = ?, estado = ?, codigo_vlc = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $ubicacion, $tipo, $estado, $vlc, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Actualiza solo ubicación (intercambio).
 */
function actualizarUbicacion($conexion, $id, $ubicacion) {
    $stmt = $conexion->prepare("UPDATE inventario SET ubicacion = ? WHERE id = ?");
    $stmt->bind_param("si", $ubicacion, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Busca máquina para intercambio (mismo tipo, diferente id).
 */
function buscarMaquinaParaIntercambio($conexion, $ubicacion, $tipo, $id_actual) {
    $stmt = $conexion->prepare("SELECT * FROM inventario WHERE ubicacion = ? AND tipo_maquina = ? AND id != ? LIMIT 1");
    $stmt->bind_param("ssi", $ubicacion, $tipo, $id_actual);
    $stmt->execute();
    $result = $stmt->get_result();
    $maquina = $result->fetch_assoc();
    $stmt->close();
    return $maquina ?: null;
}
