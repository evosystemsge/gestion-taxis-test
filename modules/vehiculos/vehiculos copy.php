<?php 
include '../../layout/header.php';
require '../../config/database.php';

if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    if ($accion == 'agregar') {
        $marca = $_POST['marca'];
        $modelo = $_POST['modelo'];
        $matricula = $_POST['matricula'];
        $numero = $_POST['numero'];
        $km_inicial = $_POST['km_inicial'];
        $km_actual = $_POST['km_actual'];
        $km_aceite = $_POST['km_aceite'];
        $stmt = $pdo->prepare("INSERT INTO vehiculos (marca, modelo, matricula, numero, km_inicial, km_actual, km_aceite ) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$marca, $modelo, $matricula, $numero, $km_inicial, $km_actual, $km_aceite]);
        header("Location: vehiculos.php");
        exit;
    } elseif ($accion == 'eliminar') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("SELECT * FROM conductores WHERE vehiculo_id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            echo "<script>alert('Este vehículo está asignado a un conductor y no se puede eliminar hasta desvincularlo.'); window.location.href='vehiculos.php';</script>";
        } else {
            $stmt = $pdo->prepare("DELETE FROM vehiculos WHERE id = ?");
            $stmt->execute([$id]);
            echo "<script>alert('Vehículo eliminado correctamente.'); window.location.href='vehiculos.php';</script>";
        }
    } elseif ($accion == 'editar') {
        $id = $_POST['id'];
        $marca = $_POST['marca'];
        $modelo = $_POST['modelo'];
        $matricula = $_POST['matricula'];
        $numero = $_POST['numero'];
        $km_inicial = $_POST['km_inicial'];
        $km_actual = $_POST['km_actual'];
        $km_aceite = $_POST['km_aceite'];
        $stmt = $pdo->prepare("UPDATE vehiculos SET marca=?, modelo=?, matricula=?, numero=?, km_inicial=?, km_actual=?, km_aceite=? WHERE id=?");
        $stmt->execute([$marca, $modelo, $matricula, $numero, $km_inicial, $km_actual, $km_aceite, $id]);
        header("Location: vehiculos.php");
        exit;
    }
}

$vehiculos = $pdo->query("SELECT v.*, c.nombre AS conductor_nombre FROM vehiculos v LEFT JOIN conductores c ON v.id = c.vehiculo_id")->fetchAll();
?>

<style>
.container {
    max-width: 1100px;
    margin: 5px auto;
    background: white;
    padding: 15px;
    border-radius: 5px;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
}
.table {
    width: 100%;
    border-collapse: collapse;
    border-radius: 10px;
    overflow: hidden;
}
.table th, .table td {
    border: 1px solid #ccc;
    padding: 6px;
    text-align: center;
}
.table th {
    background-color: #004b87;
    color: white;
    text-transform: uppercase;
}
.btn {
    background: red;
    color: white;
    padding: 4px 7px;
    border-radius: 5px;
    border: none;
    cursor: pointer;
}
.btn-nuevo {
    background-color: #28a745;
    color: white;
}
.btn-editar {
    background-color: #ffc107;
    color: black;
    padding: 4px 7px;
    border-radius: 5px;
    border: none;
    cursor: pointer;
}
.btn-ver {
    background-color: #17a2b8;
    color: white;
    padding: 4px 7px;
    border-radius: 5px;
    border: none;
    cursor: pointer;
}
.modal {
    display: none;
    position: fixed;
    z-index: 1;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
    justify-content: center;
    align-items: center;
}
.modal-content {
    background-color: #fff;
    padding: 20px;
    border-radius: 5px;
    width: 400px;
    position: relative;
}
.modal-content input {
    width: 100%;
    padding: 10px;
    margin: 5px 0;
    border: 1px solid #ccc;
    border-radius: 4px;
}
.modal-content button {
    margin-top: 10px;
    width: 100%;
}
.close {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 20px;
    cursor: pointer;
}
.close {
    color: red;
    float: right;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
}
.close:hover {
    color: darkred;
}
.header-table {
    text-align: right;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;

}
.alerta-mantenimiento {
    background-color: #ffcccc !important; /* rojo claro */
}
</style>

<div class="container">
    <h2 style="text-align: center; color: #004b87; margin-bottom: 20px;">Lista de Vehículos</h2>
    <div class="header-table">
        <button id="openModalAgregar" class="btn btn-nuevo">
            <i class="fas fa-plus"></i> Nuevo Vehículo
        </button>
    </div>
    <table class="table">
        <tr>
            <th>ID</th>
            <th>Marca</th>
            <th>Modelo</th>
            <th>Matricula</th>
            <th>Número</th>
            <th>Km Inicial</th>
            <th>Km Actual</th>
            <th>Prox Mantenimiento</th>
            <th>Conductor</th>
            <th>Acciones</th>
        </tr>
        <?php foreach ($vehiculos as $vehiculo): ?>
        <?php
        $diferencia_km = $vehiculo['km_aceite'] - $vehiculo['km_actual'];
        $clase_alerta = ($diferencia_km <= 500) ? 'alerta-mantenimiento' : '';
         ?>
        <tr class="<?= $clase_alerta ?>">

                <td><?= $vehiculo['id'] ?></td>
                <td><?= $vehiculo['marca'] ?></td>
                <td><?= $vehiculo['modelo'] ?></td>
                <td><?= $vehiculo['matricula'] ?></td>
                <td><?= $vehiculo['numero'] ?></td>
                <td><?= number_format($vehiculo['km_inicial'], 0, ',', '.') ?> km</td>
                <td><?= number_format($vehiculo['km_actual'], 0, ',', '.') ?> km</td>
                <td><?= number_format($vehiculo['km_aceite'], 0, ',', '.') ?> km</td>                
                <td><?= $vehiculo['conductor_nombre'] ?? 'No asignado' ?></td>
                <td>
                    <button class="btn-ver" onclick='verVehiculo(<?= json_encode($vehiculo) ?>)'>Ver</button>
                    <button class="btn-editar" onclick='editarVehiculo(<?= json_encode($vehiculo) ?>)'>Editar</button>
                    <form method="post" style="display:inline;" onsubmit="return confirmarEliminacion();">
                        <input type="hidden" name="id" value="<?= $vehiculo['id'] ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<!-- Modales -->
<div id="modalAgregar" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal('modalAgregar')">&times;</span>
        <h2>Agregar Vehículo</h2>
        <form method="post">
            <input type="text" name="marca" placeholder="Marca" required>
            <input type="text" name="modelo" placeholder="Modelo" required>
            <input type="text" name="matricula" placeholder="Matricula" required>
            <input type="text" name="numero" placeholder="Número" required>
            <input type="number" name="km_inicial" placeholder="Kilometros Iniciales" required>
            <input type="number" name="km_actual" placeholder="Kilometros Actuales" required>
            <input type="number" name="km_aceite" placeholder="Km del Prox Mantenimiento" required>
            <input type="hidden" name="accion" value="agregar">
            <button type="submit" class="btn btn-nuevo">Guardar</button>
        </form>
    </div>
</div>

<div id="modalEditar" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal('modalEditar')">&times;</span>
        <h2>Editar Vehículo</h2>
        <form method="post">
            <input type="hidden" name="id" id="editar_id">
            <label for="editar_marca">Marca</label>
            <input type="text" name="marca" id="editar_marca" required>
            <label for="editar_modelo">Modelo</label>
            <input type="text" name="modelo" id="editar_modelo" required>
            <label for="editar_matricula">Matrícula</label>
            <input type="text" name="matricula" id="editar_matricula" required>
            <label for="editar_numero">Número</label>
            <input type="text" name="numero" id="editar_numero" required>
            <label for="editar_km_inicial">Kilometraje Inicial</label>
            <input type="number" name="km_inicial" id="editar_km_inicial" required>
            <label for="editar_km_actual">Kilometraje Actual</label>
            <input type="number" name="km_actual" id="editar_km_actual" required>
            <label for="editar_km_aceite">Próximo Mantenimiento (km)</label>
            <input type="number" name="km_aceite" id="editar_km_aceite" required>
            <input type="hidden" name="accion" value="editar">
            <button type="submit" class="btn btn-nuevo">Actualizar</button>
        </form>
    </div>
</div>

<div id="modalVer" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal('modalVer')">&times;</span>
        <h2>Detalles del Vehículo</h2>
        <p><strong>Marca:</strong> <span id="ver_marca"></span></p>
        <p><strong>Modelo:</strong> <span id="ver_modelo"></span></p>
        <p><strong>Matricula:</strong> <span id="ver_matricula"></span></p>
        <p><strong>Número:</strong> <span id="ver_numero"></span></p>
        <p><strong>Kilometraje Inicial:</strong> <span id="ver_km_inicial"></span></p>
        <p><strong>Kilometraje Actual:</strong> <span id="ver_km_actual"></span></p>
        <p><strong>Proximo mantenimiento:</strong> <span id="ver_km_aceite"></span></p>
        <p><strong>Conductor Asignado:</strong> <span id="ver_conductor_nombre"></span></p>
    </div>
</div>

<script>
function confirmarEliminacion() {
    return confirm("¿Estás seguro de que deseas eliminar este vehículo?");
}

function cerrarModal(id) {
    document.getElementById(id).style.display = "none";
}

function verVehiculo(v) {
    document.getElementById("ver_marca").textContent = v.marca;
    document.getElementById("ver_modelo").textContent = v.modelo;
    document.getElementById("ver_matricula").textContent = v.matricula;
    document.getElementById("ver_numero").textContent = v.numero;
    document.getElementById("ver_km_inicial").textContent = v.km_inicial;
    document.getElementById("ver_km_actual").textContent = v.km_actual;
    document.getElementById("ver_km_aceite").textContent = v.km_aceite;
    document.getElementById("ver_conductor_nombre").textContent = v.conductor_nombre ?? "No asignado";;

    document.getElementById("modalVer").style.display = "flex";
}

function editarVehiculo(v) {
    document.getElementById("editar_id").value = v.id;
    document.getElementById("editar_marca").value = v.marca;
    document.getElementById("editar_modelo").value = v.modelo;
    document.getElementById("editar_matricula").value = v.matricula;
    document.getElementById("editar_numero").value = v.numero;
    document.getElementById("editar_km_inicial").value = v.km_inicial;
    document.getElementById("editar_km_actual").value = v.km_actual;
    document.getElementById("editar_km_aceite").value = v.km_aceite;

    document.getElementById("modalEditar").style.display = "flex";
}

document.getElementById("openModalAgregar").addEventListener("click", () => {
    document.getElementById("modalAgregar").style.display = "flex";
});

window.onclick = function(event) {
    ["modalAgregar", "modalEditar", "modalVer"].forEach(id => {
        const modal = document.getElementById(id);
        if (event.target === modal) {
            modal.style.display = "none";
        }
    });
}
</script>

<?php include '../../layout/footer.php'; ?>
