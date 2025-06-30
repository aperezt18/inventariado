<?php
session_start();

if (!isset($_SESSION['usuario']) || !isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

require_once "conexion.php";

$rol = $_SESSION['rol'];
$esAdmin = ($rol === 'admin');
$puedeEditar = in_array($rol, ['admin', 'tecnico', 'coordinador']);
$puedeAñadirRegistro = in_array($rol, ['admin', 'coordinador']);

// Filtros
$filtro_tipo = $_GET['filtro_tipo'] ?? '';
$filtro_ubicacion = $_GET['filtro_ubicacion'] ?? '';
$filtro_modelo = $_GET['filtro_modelo'] ?? '';
$filtro_estado = $_GET['filtro_estado'] ?? '';
$orden = $_GET['orden'] ?? '';

// Validaciones
$tipos_validos = ['ATB', 'BTP', 'DCP', 'BGR', 'BGR MSR', 'PISTOLA', 'MONITOR', 'CPU', 'MSR/OCR', 'TECLADO'];
$estados_validos = ['Uso', 'Garantía', 'Reparación', 'Stock'];

$orden_permitido = [
    '',
    'tipo_maquina ASC', 'tipo_maquina DESC',
    'modelo ASC', 'modelo DESC',
    'ubicacion ASC', 'ubicacion DESC'
];

if (!in_array($orden, $orden_permitido, true)) {
    $orden = '';
}

$sql = "SELECT * FROM inventario WHERE ubicacion != 'TUNEL'";
$params = [];
$types = [];
$conditions = [];

if ($filtro_tipo !== '' && in_array($filtro_tipo, $tipos_validos, true)) {
    $conditions[] = "tipo_maquina = ?";
    $params[] = $filtro_tipo;
    $types[] = 's';
}
if ($filtro_ubicacion !== '') {
    $conditions[] = "ubicacion = ?";
    $params[] = $filtro_ubicacion;
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

$stmt = mysqli_prepare($conexion, $sql);
if (!$stmt) die("Error en la consulta: " . mysqli_error($conexion));

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, implode('', $types), ...$params);
}

mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Intranet - Inventario</title>
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #aaa; padding: 8px; text-align: left; }
        th { background-color: #ddd; }
        .filtros { margin-bottom: 15px; }
        .acciones { white-space: nowrap; }
    </style>
</head>
<body>
    <h2>Bienvenido, <?= htmlspecialchars($_SESSION['nombre'] ?? $_SESSION['usuario']) ?> (<?= htmlspecialchars($rol) ?>)</h2>

    <p>
        <?php if ($esAdmin): ?>
            <a href="crear_usuario_form.php">➕ Añadir usuario</a> |
        <?php endif; ?>
        <a href="cambiar_contrasena.php">Cambiar contraseña</a> |
        <a href="logout.php">Cerrar sesión</a>
    </p>

    <h3>Inventario</h3>

    <?php if ($puedeAñadirRegistro): ?>
        <form action="nuevo_registro.php" method="get" style="margin-bottom: 15px;">
            <button type="submit">➕ Añadir nuevo registro</button>
        </form>
    <?php endif; ?>

    <form method="get" class="filtros">
        <label>Tipo:
            <input type="text" name="filtro_tipo" value="<?= htmlspecialchars($filtro_tipo) ?>">
        </label>
        <label>Ubicación:
            <input type="text" name="filtro_ubicacion" value="<?= htmlspecialchars($filtro_ubicacion) ?>">
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
                    'ubicacion ASC' => 'Ubicación A-Z',
                    'ubicacion DESC' => 'Ubicación Z-A',
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
                <th>UBICACIÓN</th>
                <th>ESTADO</th>
                <?php if ($puedeEditar): ?><th>ACCIÓN</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($resultado) === 0): ?>
            <tr><td colspan="7">No hay resultados para los filtros seleccionados.</td></tr>
        <?php else: ?>
            <?php while ($fila = mysqli_fetch_assoc($resultado)): ?>
                <tr>
                    <td><?= htmlspecialchars($fila['tipo_maquina']) ?></td>
                    <td><?= htmlspecialchars($fila['modelo']) ?></td>
                    <td><?= htmlspecialchars($fila['numero_serie']) ?></td>
                    <td><?= htmlspecialchars($fila['codigo_vlc']) ?></td>
                    <td><?= htmlspecialchars($fila['ubicacion']) ?></td>
                    <td><?= htmlspecialchars($fila['estado']) ?></td>
                    <?php if ($puedeEditar): ?>
                        <td class="acciones">
                            <form method="get" action="editar_maquina.php" style="display:inline;">
                                <input type="hidden" name="serial" value="<?= htmlspecialchars($fila['numero_serie']) ?>">
                                <input type="submit" value="Editar">
                            </form>
                            <form method="post" action="mover_tunel.php" style="display:inline;" onsubmit="return confirm('¿Seguro que deseas mover esta máquina a TUNEL?');">
                                <input type="hidden" name="serial" value="<?= htmlspecialchars($fila['numero_serie']) ?>">
                                <input type="submit" value="Mover a TUNEL">
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
