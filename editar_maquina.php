<?php
session_start();
require 'conexion.php';

// Obtener rol del usuario de sesión
$rol = $_SESSION['rol'] ?? 'usuario';

// Variables para controlar permisos según rol
$esAdmin = ($rol === 'admin');
$puedeEditar = in_array($rol, ['admin', 'coordinador', 'tecnico']);

// Función para limpiar entradas de usuario y evitar XSS
function limpiar($dato) {
    return htmlspecialchars(trim($dato));
}

// Si es POST, procesar la actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    var_dump($_POST);
    exit;
    // Comprobar que se recibieron todos los campos necesarios
    if (
        isset($_POST['id'], $_POST['tipo_maquina'], $_POST['modelo'], $_POST['numero_serie'], 
              $_POST['codigo_vlc'], $_POST['ubicacion'], $_POST['estado'])
    ) {
        $id = (int)$_POST['id'];
        $tipo_maquina = limpiar($_POST['tipo_maquina']);  // No editable, se mantendrá original
        $modelo = limpiar($_POST['modelo']);              // No editable, se mantendrá original
        $numero_serie = limpiar($_POST['numero_serie']);  // No editable, se mantendrá original
        $codigo_vlc = limpiar($_POST['codigo_vlc']);
        $ubicacion = limpiar($_POST['ubicacion']);
        $estado = limpiar($_POST['estado']);

        // Validar que todos los campos obligatorios no estén vacíos
        if ($tipo_maquina === '' || $modelo === '' || $numero_serie === '' || $codigo_vlc === '' || $ubicacion === '' || $estado === '') {
            die("Error: Todos los campos son obligatorios.");
        }

        // Recuperar los datos originales para mantener los no editables
        $stmt = $conexion->prepare("SELECT tipo_maquina, modelo, numero_serie, codigo_vlc, ubicacion, estado FROM inventario WHERE id = ?");
        if (!$stmt) die("Error en consulta: " . $conexion->error);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 0) {
            die("Error: Máquina no encontrada.");
        }

        $maquina_original = $resultado->fetch_assoc();
        $stmt->close();

        // Forzamos que tipo_maquina, modelo y numero_serie se mantengan originales
        $tipo_maquina = $maquina_original['tipo_maquina'];
        $modelo = $maquina_original['modelo'];
        $numero_serie = $maquina_original['numero_serie'];

        // Si no es admin, mantener el codigo_vlc original
        if (!$esAdmin) {
            $codigo_vlc = $maquina_original['codigo_vlc'];
        }

        // Si no puede editar ubicación y estado, mantener originales
        if (!$puedeEditar) {
            $ubicacion = $maquina_original['ubicacion'];
            $estado = $maquina_original['estado'];
        }

        // Preparar la actualización
        $stmt = $conexion->prepare("UPDATE inventario SET tipo_maquina = ?, modelo = ?, numero_serie = ?, codigo_vlc = ?, ubicacion = ?, estado = ? WHERE id = ?");
        if (!$stmt) die("Error en consulta UPDATE: " . $conexion->error);

        $stmt->bind_param("ssssssi", $tipo_maquina, $modelo, $numero_serie, $codigo_vlc, $ubicacion, $estado, $id);

        if ($stmt->execute()) {
            echo "Máquina actualizada correctamente.<br><a href='lista_maquinas.php'>Volver a la lista</a>";
        } else {
            // Detectar error de clave duplicada si existiera
            if ($conexion->errno === 1062) {
                echo "Error: El número de serie ya existe en otra máquina.";
            } else {
                echo "Error al actualizar: " . $stmt->error;
            }
        }
        $stmt->close();
        $conexion->close();
        exit;
    } else {
        die("Error: Datos incompletos en el formulario.");
    }
}

// Si no es POST, mostrar el formulario con datos actuales
if (!isset($_GET['id'])) {
    die("Error: No se especificó la máquina a editar.");
}

$id = (int)$_GET['id'];

// Obtener datos actuales de la máquina
$stmt = $conexion->prepare("SELECT tipo_maquina, modelo, numero_serie, codigo_vlc, ubicacion, estado FROM inventario WHERE id = ?");
if (!$stmt) die("Error en consulta SELECT: " . $conexion->error);

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Error: No se encontró la máquina con ID $id.");
}

$maquina = $result->fetch_assoc();
$stmt->close();
$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Máquina</title>
</head>
<body>
    <h1>Editar Máquina ID <?= $id ?></h1>
    <form action="editar_maquina.php" method="post">
        <input type="hidden" name="id" value="<?= $id ?>">

        <label>Tipo de Máquina:<br>
            <input type="text" name="tipo_maquina" value="<?= htmlspecialchars($maquina['tipo_maquina']) ?>" readonly required>
        </label><br><br>

        <label>Modelo:<br>
            <input type="text" name="modelo" value="<?= htmlspecialchars($maquina['modelo']) ?>" readonly required>
        </label><br><br>

        <label>Número de Serie:<br>
            <input type="text" name="numero_serie" value="<?= htmlspecialchars($maquina['numero_serie']) ?>" readonly required>
        </label><br><br>

        <label>Código VLC:<br>
            <input type="text" name="codigo_vlc" value="<?= htmlspecialchars($maquina['codigo_vlc']) ?>" <?= $esAdmin ? '' : 'readonly' ?> required>
        </label><br><br>

        <label>Ubicación:<br>
            <input type="text" name="ubicacion" value="<?= htmlspecialchars($maquina['ubicacion']) ?>" <?= $puedeEditar ? '' : 'readonly' ?> required>
        </label><br><br>

        <label>Estado:<br>
            <input type="text" name="estado" value="<?= htmlspecialchars($maquina['estado']) ?>" <?= $puedeEditar ? '' : 'readonly' ?> required>
        </label><br><br>

        <input type="submit" value="Guardar Cambios">
    </form>
    <br>
    <a href="lista_maquinas.php">Volver a la lista de máquinas</a>
</body>
</html>