<?php
require_once "control_licencia.php";

if (!isset($_SESSION['usuario']) || !isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

require_once "conexion.php";

$rol = $_SESSION['rol'];
$filtro_tipo = $_GET['filtro_tipo'] ?? '';
$filtro_modelo = $_GET['filtro_modelo'] ?? '';
$filtro_estado = $_GET['filtro_estado'] ?? '';
$orden = $_GET['orden'] ?? '';

$tipos_validos = ['ATB', 'BTP', 'DCP', 'BGR', 'BGR MSR', 'PISTOLA', 'MONITOR', 'CPU', 'MSR/OCR', 'TECLADO'];
$estados_validos = ['Uso', 'Garantía', 'Reparación', 'Stock'];

$orden_permitido = [
    '', 'tipo_maquina ASC', 'tipo_maquina DESC',
    'modelo ASC', 'modelo DESC',
    'estado ASC', 'estado DESC'
];

if (!in_array($orden, $orden_permitido, true)) {
    $orden = '';
}

$sql = "SELECT * FROM inventario WHERE ubicacion = 'TUNEL'";
$params = [];
$types = [];
$conditions = [];

if ($filtro_tipo !== '' && in_array($filtro_tipo, $tipos_validos, true)) {
    $conditions[] = "tipo_maquina = ?";
    $params[] = $filtro_tipo;
    $types[] = 's';
}
if ($filtro_modelo !== '') {
    $conditions[] = "modelo = ?";
    $params[] = $filtro_modelo;
    $types[] = 's';
}
if ($filtro_estado !== '' && in_array($filtro_estado, $estados_validos, true)) {
    $conditions[] = "estado = ?";
    $params[] = $filtro_estado;
    $types[] = 's';
}

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

if ($orden !== '') {
    $sql .= " ORDER BY $orden";
}

$stmt = $conexion->prepare($sql);
if (!$stmt) die("Error al preparar: " . $conexion->error);
if (!empty($params)) {
    $stmt->bind_param(implode('', $types), ...$params);
}
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Máquinas en TUNEL</title>
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #aaa; padding: 8px; text-align: left; }
        th { background-color: #ddd; }
    </style>
</head>
<body>
    <h2>Máquinas en TUNEL</h2>
    <p><a href="intranet.php">← Volver a la intranet</a></p>

    <form method="get">
        <label>Tipo:
            <input type="text" name="filtro_tipo" value="<?= htmlspecialchars($filtro_tipo) ?>">
        </label>
        <label>Modelo:
            <input type="text" name="filtro_modelo" value="<?= htmlspecialchars($filtro_modelo) ?>">
        </label>
        <label>Estado:
            <input type="text" name="filtro_estado" value="<?= htmlspecialchars($filtro_estado) ?>">
        </label>
        <label>Ordenar por:
            <select name="orden">
                <?php
                $opcionesOrden = [
                    '' => '-- Sin orden --',
                    'tipo_maquina ASC' => 'Tipo A-Z',
                    'tipo_maquina DESC' => 'Tipo Z-A',
                    'modelo ASC' => 'Modelo A-Z',
                    'modelo DESC' => 'Modelo Z-A',
                    'estado ASC' => 'Estado A-Z',
                    'estado DESC' => 'Estado Z-A'
                ];
                foreach ($opcionesOrden as $valor => $texto) {
                    $selected = ($orden === $valor) ? 'selected' : '';
                    echo "<option value=\"" . htmlspecialchars($valor) . "\" $selected>$texto</option>";
                }
                ?>
            </select>
        </label>
        <input type="submit" value="Filtrar">
    </form>

    <table>
        <thead>
            <tr>
                <th>TIPO</th>
                <th>MODELO</th>
                <th>SERIAL</th>
                <th>VLC</th>
                <th>ESTADO</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($resultado->num_rows === 0): ?>
            <tr><td colspan="5">No hay máquinas en TUNEL según los filtros aplicados.</td></tr>
        <?php else: ?>
            <?php while ($fila = $resultado->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($fila['tipo_maquina']) ?></td>
                    <td><?= htmlspecialchars($fila['modelo']) ?></td>
                    <td><?= htmlspecialchars($fila['numero_serie']) ?></td>
                    <td><?= htmlspecialchars($fila['codigo_vlc']) ?></td>
                    <td><?= htmlspecialchars($fila['estado']) ?></td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
