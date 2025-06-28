<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}
require_once "conexion.php";

// Sanear inputs de filtros para evitar inyección SQL
$filtro_tipo = $_GET['filtro_tipo'] ?? '';
$filtro_ubicacion = $_GET['filtro_ubicacion'] ?? '';
$filtro_modelo = $_GET['filtro_modelo'] ?? '';
$orden = $_GET['orden'] ?? '';

// Solo permitir ciertos valores para orden
$orden_permitido = [
    '', 
    'tipo_maquina ASC', 'tipo_maquina DESC',
    'modelo ASC', 'modelo DESC',
    'ubicacion ASC', 'ubicacion DESC'
];

if (!in_array($orden, $orden_permitido, true)) {
    $orden = '';
}

$sql = "SELECT * FROM inventario WHERE 1=1";
$params = [];
$types = '';
$conditions = [];

// Mejor usar consultas preparadas para filtros

if ($filtro_tipo !== '') {
    $conditions[] = "tipo_maquina = ?";
    $params[] = $filtro_tipo;
    $types .= 's';
}
if ($filtro_ubicacion !== '') {
    $conditions[] = "ubicacion = ?";
    $params[] = $filtro_ubicacion;
    $types .= 's';
}
if ($filtro_modelo !== '') {
    $conditions[] = "modelo = ?";
    $params[] = $filtro_modelo;
    $types .= 's';
}

if (count($conditions) > 0) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

if ($orden !== '') {
    $sql .= " ORDER BY $orden";
}

$stmt = mysqli_prepare($conexion, $sql);
if ($stmt === false) {
    die("Error en la consulta: " . mysqli_error($conexion));
}

if (count($params) > 0) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

$esAdmin = ($_SESSION['rol'] === 'admin');
$puedeEditar = in_array($_SESSION['rol'], ['admin', 'tecnico', 'coordinador']);
?>

<!DOCTYPE html>
<html>
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
    <h2>Bienvenido, <?= htmlspecialchars($_SESSION['nombre'] ?? $_SESSION['usuario']); ?> (<?= htmlspecialchars($_SESSION['rol']); ?>)</h2>

    <p>
        <?php if ($esAdmin): ?>
            <a href="crear_usuario_form.php">➕ Añadir usuario</a> |
        <?php endif; ?>
        <a href="cambiar_contrasena.php">Cambiar contraseña</a> |
        <a href="logout.php">Cerrar sesión</a>
    </p>

    <h3>Inventario</h3>

    <?php
    $puedeAñadirRegistro = in_array($_SESSION['rol'], ['admin', 'coordinador']);
    if ($puedeAñadirRegistro): ?>
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
                    echo "<option value=\"$valor\" $selected>$texto</option>";
                }
                ?>
            </select>
        </label>
        <input type="submit" value="Filtrar">
    </form>

    <table>
        <tr>
            <th>TIPO</th>
            <th>MODELO</th>
            <th>SERIAL</th>
            <th>VLC</th>
            <th>UBICACIÓN</th>
            <th>ESTADO</th>
            <?php if ($puedeEditar): ?><th>ACCIÓN</th><?php endif; ?>
        </tr>

        <?php while ($fila = mysqli_fetch_assoc($resultado)) : ?>
        <tr>
            <td><?= htmlspecialchars($fila['tipo_maquina']) ?></td>
            <td><?= htmlspecialchars($fila['modelo']) ?></td>
            <td><?= htmlspecialchars($fila['numero_serie']) ?></td>
            <td><?= htmlspecialchars($fila['codigo_vlc']) ?></td>
            <td><?= htmlspecialchars($fila['ubicacion']) ?></td>
            <td><?= htmlspecialchars($fila['estado']) ?></td>
            <?php if ($puedeEditar): ?>
                <td class="acciones">
                    <form method="post" action="editar_maquina.php" style="display:inline;">
                        <input type="hidden" name="serial" value="<?= htmlspecialchars($fila['numero_serie']) ?>">
                        <input type="submit" value="Editar">
                    </form>
                </td>
            <?php endif; ?>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
