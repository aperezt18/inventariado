<?php
session_start();
if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'], ['admin', 'coordinador'])) {
    header("Location: intranet.php");
    exit();
}

require_once "conexion.php";

$errores = [];
$exito = false;

// Tipos válidos
$tipos_validos = [
    'ATB', 'BTP', 'DCP', 'BGR', 'BGR MSR', 'PISTOLA', 
    'MONITOR', 'CPU', 'MSR/OCR', 'TECLADO'
];

// Función para validar ubicación
function esUbicacionValida($ubicacion) {
    for ($i = 1; $i <= 38; $i++) {
        if ($ubicacion === 'M' . str_pad($i, 2, '0', STR_PAD_LEFT)) return true;
    }
    for ($i = 43; $i <= 62; $i++) {
        if ($ubicacion === 'M' . $i) return true;
    }
    if ($ubicacion === 'P01A' || $ubicacion === 'P01B') return true;
    for ($i = 2; $i <= 22; $i++) {
        if ($ubicacion === 'P' . str_pad($i, 2, '0', STR_PAD_LEFT)) return true;
    }
    if ($ubicacion === 'PTAG' || $ubicacion === 'ALMACÉN' || $ubicacion === 'TUNEL') return true;
    return false;
}

function contarMaquinas($conexion, $ubicacion) {
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM inventario WHERE ubicacion = ?");
    $stmt->bind_param('s', $ubicacion);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count;
}

function tipoUbicacion($ubicacion) {
    if (preg_match('/^M\d{2}$/', $ubicacion)) return 'M';
    if (preg_match('/^P(0[1-9]|1[0-9]|2[0-2])[AB]?$/', $ubicacion)) return 'P';
    if ($ubicacion === 'PTAG') return 'PTAG';
    return 'LIBRE';
}

function puedeAñadirEnUbicacion($conexion, $ubicacion) {
    $count = contarMaquinas($conexion, $ubicacion);
    $tipo = tipoUbicacion($ubicacion);
    if ($tipo === 'M' || $tipo === 'PTAG') return $count < 6;
    if ($tipo === 'P') return $count < 7;
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_maquina = strtoupper(trim($_POST['tipo_maquina'] ?? ''));
    $modelo = trim($_POST['modelo'] ?? '');
    $numero_serie = trim($_POST['numero_serie'] ?? '');
    $codigo_vlc = trim($_POST['codigo_vlc'] ?? '');
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $estado = trim($_POST['estado'] ?? '');

    if (!in_array($tipo_maquina, $tipos_validos)) {
        $errores[] = "Tipo de máquina inválido.";
    }

    if (!esUbicacionValida($ubicacion)) {
        $errores[] = "Ubicación inválida.";
    }

    $tipo = tipoUbicacion($ubicacion);

    // Restricciones por tipo y ubicación
    if ($tipo === 'P' && $tipo_maquina === 'ATB') {
        $errores[] = "No se permite añadir impresoras ATB en ubicaciones tipo P.";
    }

    if ($tipo === 'PTAG' && $tipo_maquina === 'BTP') {
        $errores[] = "No se permite añadir impresoras BTP en la ubicación PTAG.";
    }

    if ($tipo_maquina === 'DCP' && $tipo !== 'P' && $tipo !== 'PTAG') {
        $errores[] = "La impresora DCP solo puede añadirse en ubicaciones tipo P o PTAG.";
    }

    if ($tipo_maquina === 'PISTOLA' && $tipo !== 'M') {
        $errores[] = "La pistola solo puede añadirse en ubicaciones tipo M.";
    }

    if ($estado !== '' && $tipo !== 'LIBRE' && in_array($estado, ['Stock', 'Reparación', 'Garantía']) && $ubicacion !== 'ALMACÉN') {
        $errores[] = "El estado '$estado' solo está permitido en la ubicación ALMACÉN.";
    }

    // Validaciones básicas
    if ($modelo === '') $errores[] = "El campo 'Modelo' es obligatorio.";
    if ($numero_serie === '') $errores[] = "El campo 'Número de Serie' es obligatorio.";
    if ($codigo_vlc === '') $errores[] = "El campo 'Código VLC' es obligatorio.";

    // Unicidad
    if (empty($errores)) {
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM inventario WHERE numero_serie = ? OR codigo_vlc = ?");
        $stmt->bind_param('ss', $numero_serie, $codigo_vlc);
        $stmt->execute();
        $stmt->bind_result($duplicados);
        $stmt->fetch();
        $stmt->close();
        if ($duplicados > 0) {
            $errores[] = "Ya existe una máquina con ese número de serie o código VLC.";
        }
    }

    // Límite de unidades
    if (empty($errores) && !puedeAñadirEnUbicacion($conexion, $ubicacion)) {
        $errores[] = "Se ha alcanzado el máximo de máquinas permitidas en la ubicación.";
    }

    // Duplicado por tipo (excepto BGR en P)
    if (empty($errores)) {
        $query = "SELECT COUNT(*) FROM inventario WHERE tipo_maquina = ? AND ubicacion = ?";
        $stmt = $conexion->prepare($query);
        $stmt->bind_param('ss', $tipo_maquina, $ubicacion);
        $stmt->execute();
        $stmt->bind_result($count_tipo);
        $stmt->fetch();
        $stmt->close();

        if ($tipo_maquina === 'BGR' && $tipo === 'P' && $count_tipo >= 2) {
            $errores[] = "Solo se permiten dos lectores BGR en ubicaciones tipo P.";
        } elseif ($tipo_maquina !== 'BGR' && $count_tipo > 0 && !in_array($ubicacion, ['ALMACÉN', 'TUNEL'])) {
            $errores[] = "Ya hay una máquina de tipo $tipo_maquina en la ubicación $ubicacion.";
        }
    }

    if (empty($errores)) {
        $stmt = $conexion->prepare("INSERT INTO inventario (tipo_maquina, modelo, numero_serie, codigo_vlc, ubicacion, estado) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssss', $tipo_maquina, $modelo, $numero_serie, $codigo_vlc, $ubicacion, $estado);
        if ($stmt->execute()) {
            $exito = true;
        } else {
            $errores[] = "Error al guardar el registro: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Nuevo Registro</title>
</head>
<body>
    <h2>Nuevo Registro</h2>
    <p><a href="intranet.php">← Volver al inventario</a></p>

    <?php if ($exito): ?>
        <p style="color: green;">Registro añadido correctamente.</p>
    <?php endif; ?>

    <?php if (!empty($errores)): ?>
        <ul style="color: red;">
            <?php foreach ($errores as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post">
        <label>Tipo:
            <select name="tipo_maquina" required>
                <option value="">-- Selecciona tipo --</option>
                <?php foreach ($tipos_validos as $t): ?>
                    <option value="<?= $t ?>" <?= ($t == ($_POST['tipo_maquina'] ?? '')) ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </label><br><br>

        <label>Modelo:
            <input type="text" name="modelo" value="<?= htmlspecialchars($_POST['modelo'] ?? '') ?>" required>
        </label><br><br>

        <label>Número de Serie:
            <input type="text" name="numero_serie" value="<?= htmlspecialchars($_POST['numero_serie'] ?? '') ?>" required>
        </label><br><br>

        <label>Código VLC:
            <input type="text" name="codigo_vlc" value="<?= htmlspecialchars($_POST['codigo_vlc'] ?? 'VLC-') ?>" required>
        </label><br><br>

        <label>Ubicación:
            <select name="ubicacion" required>
                <option value="">-- Selecciona ubicación --</option>
                <?php
                for ($i = 1; $i <= 38; $i++) {
                    $v = 'M' . str_pad($i, 2, '0', STR_PAD_LEFT);
                    $sel = ($v === ($_POST['ubicacion'] ?? '')) ? 'selected' : '';
                    echo "<option value=\"$v\" $sel>$v</option>";
                }
                for ($i = 43; $i <= 62; $i++) {
                    $v = 'M' . $i;
                    $sel = ($v === ($_POST['ubicacion'] ?? '')) ? 'selected' : '';
                    echo "<option value=\"$v\" $sel>$v</option>";
                }
                foreach (['P01A', 'P01B'] as $v) {
                    $sel = ($v === ($_POST['ubicacion'] ?? '')) ? 'selected' : '';
                    echo "<option value=\"$v\" $sel>$v</option>";
                }
                for ($i = 2; $i <= 22; $i++) {
                    $v = 'P' . str_pad($i, 2, '0', STR_PAD_LEFT);
                    $sel = ($v === ($_POST['ubicacion'] ?? '')) ? 'selected' : '';
                    echo "<option value=\"$v\" $sel>$v</option>";
                }
                foreach (['PTAG', 'ALMACÉN', 'TUNEL'] as $v) {
                    $sel = ($v === ($_POST['ubicacion'] ?? '')) ? 'selected' : '';
                    echo "<option value=\"$v\" $sel>$v</option>";
                }
                ?>
            </select>
        </label><br><br>

        <label>Estado:
            <select name="estado" required>
                <?php
                $opciones_estado = ['Uso', 'Garantía', 'Reparación', 'Stock'];
                foreach ($opciones_estado as $e) {
                    $sel = ($e === ($_POST['estado'] ?? 'Uso')) ? 'selected' : '';
                    echo "<option value=\"$e\" $sel>$e</option>";
                }
                ?>
            </select>
        </label><br><br>

        <input type="submit" value="Añadir registro">
    </form>
</body>
</html>