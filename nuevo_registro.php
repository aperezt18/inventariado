<?php
session_start();
if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'], ['admin', 'coordinador'])) {
    header("Location: intranet.php");
    exit();
}

require_once "conexion.php";

$errores = [];
$exito = false;

// Función para validar la ubicación
function esUbicacionValida($ubicacion) {
    // Rangos M01-M38 y M43-M62
    for ($i = 1; $i <= 38; $i++) {
        if ($ubicacion === 'M' . str_pad($i, 2, '0', STR_PAD_LEFT)) return true;
    }
    for ($i = 43; $i <= 62; $i++) {
        if ($ubicacion === 'M' . $i) return true;
    }
    // P01A, P01B
    if ($ubicacion === 'P01A' || $ubicacion === 'P01B') return true;
    // P02 - P22
    for ($i = 2; $i <= 22; $i++) {
        if ($ubicacion === 'P' . str_pad($i, 2, '0', STR_PAD_LEFT)) return true;
    }
    // PTAG y ALMACÉN
    if ($ubicacion === 'PTAG' || $ubicacion === 'ALMACÉN') return true;

    return false;
}

// Función para contar máquinas en una ubicación
function contarMaquinas($conexion, $ubicacion) {
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM inventario WHERE ubicacion = ?");
    $stmt->bind_param('s', $ubicacion);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count;
}

// Validación de límites por tipo de ubicación
function puedeAñadirEnUbicacion($conexion, $ubicacion) {
    $count = contarMaquinas($conexion, $ubicacion);
    // M y PTAG deben tener 6 máquinas exactas, no más
    if (preg_match('/^M\d{2}$/', $ubicacion) || $ubicacion === 'PTAG') {
        return $count < 6;
    }
    // P (excepto PTAG) deben tener máximo 7 máquinas
    if (preg_match('/^P(0[1-9]|1[0-9]|2[0-2])[AB]?$/', $ubicacion) && $ubicacion !== 'PTAG') {
        return $count < 7;
    }
    // ALMACÉN sin límite
    if ($ubicacion === 'ALMACÉN') {
        return true;
    }
    // Por defecto, rechazar si no coincide con ninguno
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_maquina = trim($_POST['tipo_maquina'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $numero_serie = trim($_POST['numero_serie'] ?? '');
    $codigo_vlc = trim($_POST['codigo_vlc'] ?? '');
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $estado = trim($_POST['estado'] ?? '');

    // Validaciones básicas
    if ($tipo_maquina === '') $errores[] = "El campo 'Tipo' es obligatorio.";
    if ($modelo === '') $errores[] = "El campo 'Modelo' es obligatorio.";
    if ($numero_serie === '') $errores[] = "El campo 'Número de Serie' es obligatorio.";
    if ($codigo_vlc === '') $errores[] = "El campo 'Código VLC' es obligatorio.";
    if ($ubicacion === '') $errores[] = "El campo 'Ubicación' es obligatorio.";
    else if (!esUbicacionValida($ubicacion)) $errores[] = "Ubicación inválida.";

    if ($estado === '') $estado = 'Disponible'; // Valor por defecto

    // Comprobar duplicados
    if (empty($errores)) {
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM inventario WHERE numero_serie = ? OR codigo_vlc = ?");
        $stmt->bind_param('ss', $numero_serie, $codigo_vlc);
        $stmt->execute();
        $stmt->bind_result($duplicados);
        $stmt->fetch();
        $stmt->close();

        if ($duplicados > 0) {
            $errores[] = "Ya existe una máquina con ese Número de Serie o Código VLC.";
        }
    }

    // Comprobar límites de ubicación
    if (empty($errores) && !puedeAñadirEnUbicacion($conexion, $ubicacion)) {
        $errores[] = "Se ha alcanzado el límite máximo de máquinas para la ubicación seleccionada.";
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
    <title>Nuevo Registro - Inventario</title>
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

    <form method="post" action="nuevo_registro.php">
        <label>Tipo:
            <input type="text" name="tipo_maquina" value="<?= htmlspecialchars($_POST['tipo_maquina'] ?? '') ?>" required>
        </label><br><br>

        <label>Modelo:
            <input type="text" name="modelo" value="<?= htmlspecialchars($_POST['modelo'] ?? '') ?>" required>
        </label><br><br>

        <label>Número de Serie:
            <input type="text" name="numero_serie" value="<?= htmlspecialchars($_POST['numero_serie'] ?? '') ?>" required>
        </label><br><br>

        <label>Código VLC:
            <input type="text" name="codigo_vlc" value="<?= htmlspecialchars($_POST['codigo_vlc'] ?? '') ?>" required>
        </label><br><br>

        <label>Ubicación:
            <select name="ubicacion" required>
                <option value="">-- Selecciona ubicación --</option>

                <?php
                // M01-M38
                for ($i = 1; $i <= 38; $i++) {
                    $v = 'M' . str_pad($i, 2, '0', STR_PAD_LEFT);
                    $sel = (($_POST['ubicacion'] ?? '') === $v) ? 'selected' : '';
                    echo "<option value=\"$v\" $sel>$v</option>";
                }
                // M43-M62
                for ($i = 43; $i <= 62; $i++) {
                    $v = 'M' . $i;
                    $sel = (($_POST['ubicacion'] ?? '') === $v) ? 'selected' : '';
                    echo "<option value=\"$v\" $sel>$v</option>";
                }

                // P01A, P01B
                $p01options = ['P01A', 'P01B'];
                foreach ($p01options as $v) {
                    $sel = (($_POST['ubicacion'] ?? '') === $v) ? 'selected' : '';
                    echo "<option value=\"$v\" $sel>$v</option>";
                }

                // P02 - P22
                for ($i = 2; $i <= 22; $i++) {
                    $v = 'P' . str_pad($i, 2, '0', STR_PAD_LEFT);
                    $sel = (($_POST['ubicacion'] ?? '') === $v) ? 'selected' : '';
                    echo "<option value=\"$v\" $sel>$v</option>";
                }

                // PTAG y ALMACÉN
                $extras = ['PTAG', 'ALMACÉN'];
                foreach ($extras as $v) {
                    $sel = (($_POST['ubicacion'] ?? '') === $v) ? 'selected' : '';
                    echo "<option value=\"$v\" $sel>$v</option>";
                }
                ?>
            </select>

        </label><br><br>

        <label>Estado:
            <select name="estado" required>
                <?php
                $estados = ['Disponible', 'En uso', 'Mantenimiento', 'Fuera de servicio'];
                foreach ($estados as $e) {
                    $sel = (($_POST['estado'] ?? '') === $e) ? 'selected' : '';
                    echo "<option value=\"$e\" $sel>$e</option>";
                }
                ?>
            </select>
        </label><br><br>

        <input type="submit" value="Añadir registro">
    </form>
</body>
</html>
