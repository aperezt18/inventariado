<?php
// funciones_inventario.php

function limpiar($v) {
    return htmlspecialchars(trim($v));
}

function esUbicacionValida($ubicacion) {
    return preg_match('/^(M(0[1-9]|[1-3][0-9]|4[3-9]|5[0-9]|6[0-2])|P(0[1-9]|1[0-9]|2[0-2])[AB]?|PTAG|ALMACÉN|TUNEL)$/', $ubicacion);
}

function tiposPermitidosParaUbicacion($ubicacion) {
    if (in_array($ubicacion, ['ALMACÉN', 'TUNEL'])) return null;
    if (preg_match('/^M/', $ubicacion)) return ['ATB', 'BTP', 'PISTOLA', 'MONITOR', 'CPU', 'MSR/OCR'];
    if (preg_match('/^P/', $ubicacion)) return ['BTP', 'DCP', 'LECTOR_BGR', 'MONITOR', 'CPU', 'MSR/OCR'];
    if ($ubicacion === 'PTAG') return ['BTP', 'DCP', 'LECTOR_BGR', 'MONITOR', 'CPU', 'MSR/OCR'];
    return null;
}

function tipoCompatible($tipo, $ubicacion, $modelo = '') {
    $permitidos = tiposPermitidosParaUbicacion($ubicacion);
    if ($permitidos === null) return true;

    $tipoUpper = strtoupper($tipo);
    $permitidosUpper = array_map('strtoupper', $permitidos);

    if (in_array($tipoUpper, $permitidosUpper)) return true;

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

function listarMaquinasAlmacenCompatibles($conexion, $tipo, $modelo) {
    $tipos_compatibles = [$tipo];
    if ($tipo === 'ATB') $tipos_compatibles[] = 'BTP';
    if ($tipo === 'BTP') $tipos_compatibles[] = 'ATB';

    $placeholders = implode(',', array_fill(0, count($tipos_compatibles), '?'));
    $tipos_compatibles_param = $tipos_compatibles;

    $sql = "SELECT id, tipo_maquina, modelo, numero_serie, estado FROM inventario WHERE ubicacion = 'ALMACÉN' AND tipo_maquina IN ($placeholders)";
    $stmt = $conexion->prepare($sql);

    $types = str_repeat('s', count($tipos_compatibles_param));
    $stmt->bind_param($types, ...$tipos_compatibles_param);

    $stmt->execute();
    $result = $stmt->get_result();
    $maquinas = [];
    while ($row = $result->fetch_assoc()) {
        $maquinas[] = $row;
    }
    return $maquinas;
}

function validarRestriccionesUbicacionSimulada(array $tipos, string $ubicacion) {
    $ubicacion = strtoupper($ubicacion);

    $esM = preg_match('/^M(0[1-9]|[1-3][0-9]|4[3-9]|5[0-9]|6[0-2])$/', $ubicacion);
    $esP = preg_match('/^P(0[1-9]|1[0-9]|2[0-2])[AB]?$/', $ubicacion);
    $esPTAG = ($ubicacion === 'PTAG');

    $cantidad = count($tipos);
    $conteo = array_count_values(array_map('strtoupper', $tipos));

    if ($esM) {
        if ($cantidad !== 6) return "La ubicación $ubicacion debe tener exactamente 6 máquinas.";
        $permitidos = ['ATB', 'BTP', 'PISTOLA', 'MONITOR', 'CPU', 'MSR/OCR'];
        foreach ($conteo as $tipo => $count) {
            if (!in_array($tipo, $permitidos)) return "Tipo '$tipo' no permitido en $ubicacion.";
            if (in_array($tipo, ['ATB', 'BTP'])) {
                if ($count > 1) return "Solo se permite 1 unidad de '$tipo' en $ubicacion.";
            } else {
                if ($count > 1) return "No se permiten duplicados del tipo '$tipo' en $ubicacion.";
            }
        }
        if (!in_array('PISTOLA', $tipos)) return "Debe haber una PISTOLA en $ubicacion.";

    } elseif ($esP) {
        if ($cantidad !== 7) return "La ubicación $ubicacion debe tener exactamente 7 máquinas.";
        $permitidos = ['BTP', 'DCP', 'LECTOR_BGR', 'MONITOR', 'CPU'];
        foreach ($conteo as $tipo => $count) {
            if (!in_array($tipo, $permitidos)) return "Tipo '$tipo' no permitido en $ubicacion.";
            if ($tipo === 'LECTOR_BGR' && $count > 2) {
                return "Solo se permiten hasta 2 'LECTOR_BGR' en $ubicacion.";
            } elseif ($tipo !== 'LECTOR_BGR' && $count > 1) {
                return "No se permiten duplicados del tipo '$tipo' en $ubicacion.";
            }
        }
        if (!in_array('BTP', $tipos)) return "Debe haber una BTP en $ubicacion.";
        if (!in_array('DCP', $tipos)) return "Debe haber una DCP en $ubicacion.";

    } elseif ($esPTAG) {
        if ($cantidad !== 6) return "La ubicación PTAG debe tener exactamente 6 máquinas.";
        $permitidos = ['ATB', 'DCP', 'LECTOR_BGR', 'MONITOR', 'CPU', 'MSR/OCR'];
        foreach ($conteo as $tipo => $count) {
            if (!in_array($tipo, $permitidos)) return "Tipo '$tipo' no permitido en PTAG.";
            if ($count > 1) return "No se permiten duplicados del tipo '$tipo' en PTAG.";
        }
        foreach ($permitidos as $req) {
            if (!in_array($req, $tipos)) return "Debe haber una máquina de tipo '$req' en PTAG.";
        }
    }

    return true;
}

function obtenerTiposSimulados($conexion, $ubicacion, $tipo_nuevo, $id_maquina_editada) {
    $ubicacion = strtoupper($ubicacion);

    // Obtenemos todos los registros en esa ubicación
    $stmt = $conexion->prepare("SELECT id, tipo_maquina FROM inventario WHERE ubicacion = ?");
    $stmt->bind_param("s", $ubicacion);
    $stmt->execute();
    $result = $stmt->get_result();

    $tipos = [];
    $ids_en_ubicacion = [];

    while ($row = $result->fetch_assoc()) {
        $ids_en_ubicacion[] = (int)$row['id'];
    }

    // Ahora recorremos otra vez para armar el array con tipos, simulando el cambio
    // Como $result ya se leyó, hacemos una segunda consulta (o guardamos la info arriba)
    // Mejor hacer la consulta solo una vez guardando toda la info:
    $stmt->close();

    // Segunda consulta para obtener los datos completos:
    $stmt = $conexion->prepare("SELECT id, tipo_maquina FROM inventario WHERE ubicacion = ?");
    $stmt->bind_param("s", $ubicacion);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $id_maquina = (int)$row['id'];
        $tipo_actual = strtoupper($row['tipo_maquina']);

        if ($id_maquina === $id_maquina_editada) {
            $tipos[] = strtoupper($tipo_nuevo);
        } else {
            $tipos[] = $tipo_actual;
        }
    }
    $stmt->close();

    // Si la máquina no estaba en esa ubicación, la añadimos con el tipo nuevo
    if (!in_array($id_maquina_editada, $ids_en_ubicacion)) {
        $tipos[] = strtoupper($tipo_nuevo);
    }

    return $tipos;
}
