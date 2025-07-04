<?php
// helpers/cambios.php

/**
 * Inserta un cambio en logs_cambios.
 */
function registrarCambio($conexion, $id_inv, $id_usr, $campo, $antiguo, $nuevo) {
    $stmt = $conexion->prepare("INSERT INTO logs_cambios (inventario_id, usuario_id, campo_modificado, valor_anterior, valor_nuevo, fecha) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisss", $id_inv, $id_usr, $campo, $antiguo, $nuevo);
    $stmt->execute();
    $stmt->close();
}

/**
 * Registra cambios solo si hay diferencia.
 * Recibe array campo => [valor_antiguo, valor_nuevo]
 */
function registrarCambiosSiHay($conexion, $id_inv, $id_usr, $cambios) {
    foreach ($cambios as $campo => [$viejo, $nuevo]) {
        if ($viejo !== $nuevo) {
            registrarCambio($conexion, $id_inv, $id_usr, $campo, $viejo, $nuevo);
        }
    }
}
