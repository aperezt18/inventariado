<?php
// helpers/validaciones.php

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

/**
 * Detecta si el cambio de tipo es especial ATB <-> BTP con modelo compatible.
 */
function esCambioEspecialATBBTP($tipo_actual, $tipo_nuevo, $modelo) {
    $tipo_actual = strtoupper($tipo_actual);
    $tipo_nuevo = strtoupper($tipo_nuevo);

    if ((($tipo_actual === 'ATB' && $tipo_nuevo === 'BTP') || ($tipo_actual === 'BTP' && $tipo_nuevo === 'ATB')) &&
        (stripos($modelo, 'ATB') !== false || stripos($modelo, 'BTP') !== false)) {
        return true;
    }
    return false;
}
?>
