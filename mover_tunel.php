<?php
require_once "control_licencia.php";
if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'], ['admin', 'coordinador', /*TEMPORAL*/'tecnico'])) {
    header("Location: intranet.php");
    exit();
}

require_once "conexion.php";

$mensaje = '';
$errores = [];
$serial = $_POST['serial'] ?? '';
$reemplazo_serial = $_POST['reemplazo_serial'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    // Confirmación final de movimiento
    $stmt = $conexion->prepare("SELECT * FROM inventario WHERE numero_serie = ?");
    $stmt->bind_param('s', $serial);
    $stmt->execute();
    $res = $stmt->get_result();
    $origen = $res->fetch_assoc();
    $stmt->close();

    if (!$origen) {
        $errores[] = "No se encontró la máquina a mover.";
    } else {
        $ubicacion_original = $origen['ubicacion'];

        if ($ubicacion_original !== 'ALMACÉN') {
            // Necesita reemplazo
            $stmt = $conexion->prepare("SELECT * FROM inventario WHERE numero_serie = ?");
            $stmt->bind_param('s', $reemplazo_serial);
            $stmt->execute();
            $res = $stmt->get_result();
            $reemplazo = $res->fetch_assoc();
            $stmt->close();

            if (!$reemplazo || $reemplazo['ubicacion'] !== 'ALMACÉN') {
                $errores[] = "La máquina seleccionada no está en el ALMACÉN.";
            } else {
                // Validar compatibilidad de tipo
                $compatibles = [
                    'ATB' => ['ATB', 'BTP'],
                    'BTP' => ['ATB', 'BTP'],
                ];
                $tipo_origen = $origen['tipo_maquina'];
                $tipo_reemplazo = $reemplazo['tipo_maquina'];
                $grupo = $compatibles[$tipo_origen] ?? [$tipo_origen];
                if (!in_array($tipo_reemplazo, $grupo)) {
                    $errores[] = "La máquina de reemplazo no es compatible con el tipo requerido.";
                }
            }
        }
    }

    if (empty($errores)) {
        // Transacción
        $conexion->begin_transaction();

        try {
            // Mover original a TUNEL
            $stmt = $conexion->prepare("UPDATE inventario SET ubicacion = 'TUNEL' WHERE numero_serie = ?");
            $stmt->bind_param('s', $serial);
            $stmt->execute();

            if ($ubicacion_original !== 'ALMACÉN') {
                // Mover reemplazo a la ubicación original, forzando estado a 'Uso'
                $stmt = $conexion->prepare("UPDATE inventario SET ubicacion = ?, estado = 'Uso' WHERE numero_serie = ?");
                $stmt->bind_param('ss', $ubicacion_original, $reemplazo_serial);
                $stmt->execute();
            }

            // Insertar logs
            $usuario_id = $_SESSION['id'];
            $now = date('Y-m-d H:i:s');

            $stmt = $conexion->prepare("INSERT INTO logs_cambios (inventario_id, usuario_id, campo_modificado, valor_anterior, valor_nuevo, fecha) 
                                        VALUES (?, ?, ?, ?, ?, ?)");

            // Log para la original
            $stmt->bind_param('iissss', $origen['id'], $usuario_id, $campo1, $valor_anterior1, $valor_nuevo1, $now);
            $campo1 = 'ubicacion';
            $valor_anterior1 = $ubicacion_original;
            $valor_nuevo1 = 'TUNEL';
            $stmt->execute();

            if ($ubicacion_original !== 'ALMACÉN') {
                // Log para la reemplazo
                $stmt->bind_param('iissss', $reemplazo['id'], $usuario_id, $campo2, $valor_anterior2, $valor_nuevo2, $now);
                $campo2 = 'ubicacion';
                $valor_anterior2 = 'ALMACÉN';
                $valor_nuevo2 = $ubicacion_original;
                $stmt->execute();

                // Log de estado si ha cambiado a 'Uso'
                if ($reemplazo['estado'] !== 'Uso') {
                    $campo2 = 'estado';
                    $valor_anterior2 = $reemplazo['estado'];
                    $valor_nuevo2 = 'Uso';
                    $stmt->execute();
                }
            }

            $conexion->commit();
            $mensaje = "Máquina movida a TUNEL correctamente.";
        } catch (Exception $e) {
            $conexion->rollback();
            $errores[] = "Error en la operación: " . $e->getMessage();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['serial'])) {
    // Vista previa
    $stmt = $conexion->prepare("SELECT * FROM inventario WHERE numero_serie = ?");
    $stmt->bind_param('s', $serial);
    $stmt->execute();
    $res = $stmt->get_result();
    $maquina = $res->fetch_assoc();
    $stmt->close();
} else {
    header("Location: intranet.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirmar movimiento a TUNEL</title>
    <script>
    function cargarCompatibles(tipo, origen) {
        fetch('ajax_maquinas_almacen.php?tipo=' + tipo + '&origen=' + origen)
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('reemplazo_select');
                select.innerHTML = '';
                data.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.numero_serie;
                    opt.textContent = m.tipo_maquina + ' - ' + m.modelo + ' (' + m.numero_serie + ')';
                    select.appendChild(opt);
                });
                document.getElementById('modal').style.display = 'block';
            });
    }
    </script>
</head>
<body>
    <h2>Confirmar movimiento a TUNEL</h2>
    <p>
        <a href="intranet.php" style="text-decoration: none; color: red;">⟵ Cancelar y volver a intranet</a>
    </p>
    <?php if (!empty($mensaje)): ?>
        <p style="color:green;"><?= htmlspecialchars($mensaje) ?></p>
        <p><a href="intranet.php">← Volver</a></p>
    <?php elseif (!empty($errores)): ?>
        <ul style="color:red;">
            <?php foreach ($errores as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
        <p><a href="intranet.php">← Volver</a></p>
    <?php elseif (!empty($maquina)): ?>
        <p><strong><?= htmlspecialchars($maquina['tipo_maquina']) ?></strong> - <?= htmlspecialchars($maquina['modelo']) ?> (<?= htmlspecialchars($maquina['numero_serie']) ?>)</p>
        <p>Ubicación actual: <strong><?= htmlspecialchars($maquina['ubicacion']) ?></strong></p>

        <form method="post">
            <input type="hidden" name="serial" value="<?= htmlspecialchars($maquina['numero_serie']) ?>">
            <?php if ($maquina['ubicacion'] !== 'ALMACÉN'): ?>
                <p>Debes elegir una máquina compatible del ALMACÉN para sustituirla:</p>
                <button type="button" onclick="cargarCompatibles('<?= htmlspecialchars($maquina['tipo_maquina']) ?>', '<?= htmlspecialchars($maquina['ubicacion']) ?>')">
                    Seleccionar reemplazo del ALMACÉN
                </button>
                <div id="modal" style="display:none;">
                    <select name="reemplazo_serial" id="reemplazo_select" required></select><br><br>
                    <input type="submit" name="confirmar" value="Confirmar movimiento">
                </div>
            <?php else: ?>
                <input type="submit" name="confirmar" value="Confirmar movimiento a TUNEL">
            <?php endif; ?>
        </form>
    <?php endif; ?>


</body>
</html>
