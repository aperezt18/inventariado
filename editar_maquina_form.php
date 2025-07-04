<?php
require_once "control_licencia.php";
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit;
}
require_once "conexion.php";
require_once "funciones_inventario.php";
require_once "helpers/inventario.php";


$numero_serie = $_GET['serial'] ?? '';
$numero_serie = htmlspecialchars(trim($numero_serie));

if (!$numero_serie) {
    die("Número de serie no especificado.");
}

$stmt = $conexion->prepare("SELECT * FROM inventario WHERE numero_serie = ?");
$stmt->bind_param("s", $numero_serie);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Máquina no encontrada.");
}
$maquina = $res->fetch_assoc();
$stmt->close();

$tipos_validos = ['ATB', 'BTP', 'PISTOLA', 'MONITOR', 'CPU', 'MSR/OCR', 'DCP', 'LECTOR_BGR'];
$estados_validos = ['Uso', 'Garantía', 'Reparación', 'Stock'];
$ubicaciones_validas = [
    // Aquí puedes listar todas o cargar dinámicamente
    'M01','M02','M03','M04','M05','M06',
    'M07','M08','M09','M10','M11','M12',
    'M13','M14','M15','M16','M17','M18',
    'M19','M20','M21','M22','M23','M24',
    'M25','M26','M27','M28','M29','M30',
    'M31','M32','M33','M34','M35','M36',
    'M37','M38','M43','M44','M45','M46',
    'M47','M48','M49','M50','M51','M52',
    'M53','M54','M55','M56','M57','M58',
    'M59','M60','M61','M62',
    'P01A','P01B','P02','P03','P04','P05','P06','P07','P08','P09','P10','P11','P12','P13','P14','P15','P16','P17','P18','P19','P20','P21','P22',
    'PTAG','ALMACÉN','TUNEL'
];

// Extraer datos de la máquina para precargar form
$tipo_maquina = $maquina['tipo_maquina'];
$ubicacion = $maquina['ubicacion'];
$estado = $maquina['estado'];
$codigo_vlc = $maquina['codigo_vlc'];
$modelo = $maquina['modelo'];
$numero_serie = $maquina['numero_serie'];

$rol = $_SESSION['rol'] ?? '';
$esAdmin = ($rol === 'admin');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Editar Máquina - <?=htmlspecialchars($numero_serie)?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        label { display: block; margin-top: 10px; }
        select, input[type="text"] { width: 250px; padding: 5px; margin-top: 5px; }
        button { margin-top: 15px; padding: 10px 15px; }
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; top: 0; width: 100%; height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 600px;
        }
        .close {
            color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 10px;}
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center;}
        th { background-color: #eee; }
    </style>
</head>
<body>
<h2>Editar Máquina <?=htmlspecialchars($numero_serie)?></h2>

<form id="editarMaquinaForm">
    <input type="hidden" name="serial" value="<?=htmlspecialchars($numero_serie)?>" />
    
    <label>Tipo de Máquina:
        <?php if ($esAdmin): ?>
            <select name="tipo_maquina" id="tipo_maquina">
                <?php foreach ($tipos_validos as $tipo): ?>
                    <option value="<?=htmlspecialchars($tipo)?>" <?= $tipo === $tipo_maquina ? 'selected' : '' ?>><?=htmlspecialchars($tipo)?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <input type="text" value="<?=htmlspecialchars($tipo_maquina)?>" disabled />
            <input type="hidden" name="tipo_maquina" value="<?=htmlspecialchars($tipo_maquina)?>" />
        <?php endif; ?>
    </label>

    <label>Código VLC:
        <?php if ($esAdmin): ?>
            <input type="text" name="codigo_vlc" id="codigo_vlc" value="<?=htmlspecialchars($codigo_vlc)?>" />
        <?php else: ?>
            <input type="text" value="<?=htmlspecialchars($codigo_vlc)?>" disabled />
            <input type="hidden" name="codigo_vlc" value="<?=htmlspecialchars($codigo_vlc)?>" />
        <?php endif; ?>
    </label>

    <label>Ubicación:
        <select name="ubicacion" id="ubicacion">
            <?php foreach ($ubicaciones_validas as $u): ?>
                <option value="<?=htmlspecialchars($u)?>" <?= $u === $ubicacion ? 'selected' : '' ?>><?=htmlspecialchars($u)?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Estado:
        <select name="estado" id="estado">
            <?php foreach ($estados_validos as $e): ?>
                <option value="<?=htmlspecialchars($e)?>" <?= $e === $estado ? 'selected' : '' ?>><?=htmlspecialchars($e)?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <button type="submit">Guardar Cambios</button>
</form>

<a href="intranet.php"><button>Volver al inventario</button></a>

<!-- Modal para elegir máquina en ALMACÉN -->
<div id="modalAlmacen" class="modal">
    <div class="modal-content">
        <span class="close" id="cerrarModal">&times;</span>
        <h3>Seleccione máquina del ALMACÉN para intercambiar</h3>
        <table id="tablaMaquinasAlmacen">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Modelo</th>
                    <th>Número Serie</th>
                    <th>Estado</th>
                    <th>Seleccionar</th>
                </tr>
            </thead>
            <tbody>
                <!-- Se llena con JS -->
            </tbody>
        </table>
        <button id="confirmarIntercambio" disabled>Confirmar intercambio</button>
    </div>
</div>

<div id="mensaje" style="margin-top:15px; color:red;"></div>

<script>
    const form = document.getElementById('editarMaquinaForm');
    const modal = document.getElementById('modalAlmacen');
    const cerrarModalBtn = document.getElementById('cerrarModal');
    const tablaCuerpo = document.querySelector('#tablaMaquinasAlmacen tbody');
    const confirmarBtn = document.getElementById('confirmarIntercambio');
    const mensajeDiv = document.getElementById('mensaje');

    let maquinaParaIntercambiarId = null;

    function mostrarMensaje(texto, esError=true) {
        mensajeDiv.style.color = esError ? 'red' : 'green';
        mensajeDiv.textContent = texto;
    }

    cerrarModalBtn.onclick = () => {
        modal.style.display = 'none';
        confirmarBtn.disabled = true;
        tablaCuerpo.innerHTML = '';
        maquinaParaIntercambiarId = null;
    };

    window.onclick = (event) => {
        if (event.target === modal) {
            modal.style.display = 'none';
            confirmarBtn.disabled = true;
            tablaCuerpo.innerHTML = '';
            maquinaParaIntercambiarId = null;
        }
    };

    function cargarMaquinasAlmacen(tipo, modelo) {
        mostrarMensaje('Cargando máquinas del almacén...', false);
        fetch(`api_almacen.php?tipo=${encodeURIComponent(tipo)}&modelo=${encodeURIComponent(modelo)}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    mostrarMensaje("Error: " + data.error);
                    return;
                }
                tablaCuerpo.innerHTML = '';
                data.maquinas.forEach(m => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${m.tipo_maquina}</td>
                        <td>${m.modelo}</td>
                        <td>${m.numero_serie}</td>
                        <td>${m.estado}</td>
                        <td><input type="radio" name="maquina_almacen" value="${m.id}"></td>
                    `;
                    tablaCuerpo.appendChild(tr);
                });
                if (data.maquinas.length === 0) {
                    tablaCuerpo.innerHTML = '<tr><td colspan="5">No hay máquinas compatibles en almacén.</td></tr>';
                    confirmarBtn.disabled = true;
                } else {
                    confirmarBtn.disabled = true;
                    mensajeDiv.textContent = '';
                }
                modal.style.display = 'block';
            })
            .catch(e => {
                mostrarMensaje("Error cargando máquinas del almacén.");
                console.error(e);
            });
    }

    // Detectar cambios en ubicación para mostrar modal si se cambia a ALMACÉN
    form.addEventListener('submit', function(e){
        e.preventDefault();
        mensajeDiv.textContent = '';

        const formData = new FormData(form);
        const ubicacionNueva = formData.get('ubicacion').toUpperCase();
        const ubicacionActual = "<?=strtoupper(htmlspecialchars($ubicacion))?>";
        const tipoMaquina = formData.get('tipo_maquina');
        const modelo = "<?=htmlspecialchars($modelo)?>";

        // Si se cambia a ALMACÉN y no es la ubicación actual, abrir modal para seleccionar máquina del almacén con la que intercambiar
        if (ubicacionNueva === 'ALMACÉN' && ubicacionNueva !== ubicacionActual) {
            cargarMaquinasAlmacen(tipoMaquina, modelo);
        } else {
            // Enviar directamente la edición
            enviarEdicion(formData);
        }
    });

    // Escuchar selección en la tabla
    tablaCuerpo.addEventListener('change', function(e){
        if (e.target.name === 'maquina_almacen') {
            maquinaParaIntercambiarId = e.target.value;
            confirmarBtn.disabled = false;
        }
    });

    confirmarBtn.addEventListener('click', function(){
        if (!maquinaParaIntercambiarId) return;

        const formData = new FormData(form);
        formData.append('id_intercambio_almacen', maquinaParaIntercambiarId);
        modal.style.display = 'none';

        enviarEdicion(formData);
    });

    function enviarEdicion(formData) {
        mostrarMensaje('Guardando cambios...', false);
        fetch('editar_maquina.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                mostrarMensaje(data.error);
            } else if (data.mensaje) {
                mostrarMensaje(data.mensaje, false);
                setTimeout(() => { window.location.href = 'intranet.php'; }, 1500);
            } else {
                mostrarMensaje('Respuesta desconocida');
            }
        })
        .catch(err => {
            mostrarMensaje('Error comunicándose con el servidor');
            console.error(err);
        });
    }
</script>

</body>
</html>
