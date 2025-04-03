<?php 
include '../../layout/header.php';
require '../../config/database.php'; // Usamos la conexión PDO

// Lógica para listar, agregar, editar y eliminar conductores
if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    if ($accion == 'agregar') {
        $nombre = $_POST['nombre'];
        $telefono = $_POST['telefono'];
        $dip = $_POST['dip'];
        $vehiculo_id = $_POST['vehiculo_id'] ? $_POST['vehiculo_id'] : NULL; // Permite que el vehiculo_id sea NULL si no se selecciona vehículo
        
        // Insertar el conductor
        $stmt = $pdo->prepare("INSERT INTO conductores (nombre, telefono, dip, vehiculo_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nombre, $telefono, $dip, $vehiculo_id]);
    } elseif ($accion == 'eliminar') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM conductores WHERE id = ?");
        $stmt->execute([$id]);
    }
}

// Obtener todos los conductores
$conductores = $pdo->query("SELECT * FROM conductores")->fetchAll();

// Obtener la lista de vehículos disponibles
$vehiculos = $pdo->query("SELECT * FROM vehiculos WHERE id NOT IN (SELECT vehiculo_id FROM conductores WHERE vehiculo_id IS NOT NULL)")->fetchAll();
?>

<style>
/* Estilos para la interfaz */
.container {
    max-width: 1100px;
    margin: 5px auto;
    background: white;
    padding: 15px;
    border-radius: 5px;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
}
.header-table {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.filtros {
    display: flex;
    gap: 10px;
}
.btn {
    padding: 10px 10px;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    transition: 0.3s;
    display: flex;
    align-items: center;
    gap: 5px;
}
.btn-nuevo {
    background: #28a745;
    color: white;
    font-weight: bold;
}
.btn-nuevo:hover {
    background: #218838;
}
.btn-filtro {
    background: #f8f9fa;
    border: 1px solid #ddd;
    color: #333;
}
.btn-filtro:hover {
    background: #e2e6ea;
}
.table {
    width: 100%;
    border-collapse: collapse;
    border-radius: 10px;
    overflow: hidden;
}
th, td {
    border: 1px solid #ddd;
    padding: 12px;
    text-align: left;
}
th {
    background-color: #004b87;
    color: white;
    text-transform: uppercase;
}
button {
    background: red;
    color: white;
    padding: 7px 12px;
    border-radius: 5px;
    border: none;
    cursor: pointer;
}
button:hover {
    background: darkred;
}
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    justify-content: center;
    align-items: center;
}
.modal-content {
    background: white;
    padding: 30px;
    border-radius: 10px;
    width: 400px;
    text-align: center;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
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
.form-group {
    margin-bottom: 10px;
}
input, select {
    width: 90%;
    padding: 10px;
    margin-top: 5px;
    border: 1px solid #ccc;
    border-radius: 5px;
}
</style>

<div class="container">
    <h2 style="text-align: center; color: #004b87; margin-bottom: 20px;">Lista de Conductores</h2>
    <div class="header-table">
        <button id="openModal" class="btn btn-nuevo">
            <i class="fas fa-plus"></i> Nuevo Conductor
        </button>
        <div class="filtros">
            <button class="btn btn-filtro">
                <i class="fas fa-filter"></i> Filtros
            </button>
        </div>
    </div>
    <div class="table-container">
        <table class="table">
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Telefono</th>
                <th>Dip</th>
                <th>Vehiculo Asignado</th>
                <th>Acciones</th>
            </tr>
            <?php foreach ($conductores as $conductor) {
                  // Verificar si el conductor tiene un vehículo asignado
                  if ($conductor['vehiculo_id']) {
                      // Si tiene vehículo asignado, obtener los detalles del vehículo
                      $stmt_vehiculo = $pdo->prepare("SELECT matricula FROM vehiculos WHERE id = ?");
                      $stmt_vehiculo->execute([$conductor['vehiculo_id']]);
                      $vehiculo = $stmt_vehiculo->fetch();
                      $vehiculo_asignado = $vehiculo ? $vehiculo['matricula'] : 'Sin Vehículo';
                  } else {
                      // Si no tiene vehículo asignado, mostrar "Sin Vehículo"
                      $vehiculo_asignado = 'Sin Vehículo';
                  }
            ?>
                <tr>
                    <td><?= $conductor['id'] ?></td>
                    <td><?= $conductor['nombre'] ?></td>
                    <td><?= $conductor['telefono'] ?></td>
                    <td><?= $conductor['dip'] ?></td>
                    <td><?= $vehiculo_asignado ?></td>
                    <td>
                        <!-- Botón Ver -->
                        <a href="ver_conductor.php?id=<?= $conductor['id'] ?>" 
                           style="background: #17a2b8; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; margin-right: 5px;">
                            Ver
                        </a>

                        <!-- Botón Editar -->
                        <a href="editar_conductor.php?id=<?= $conductor['id'] ?>" 
                           style="background: #ffc107; color: black; padding: 5px 10px; border-radius: 5px; text-decoration: none; margin-right: 5px;">
                            Editar
                        </a>

                        <!-- Botón Eliminar -->
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="id" value="<?= $conductor['id'] ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <button type="submit" 
                                    style="background: red; color: white; padding: 5px 10px; border-radius: 5px; border: none; cursor: pointer;">
                                Eliminar
                            </button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </div>
</div>

<!-- Modal para Agregar Conductor -->
<div id="modalAgregar" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Agregar Conductor</h2>
        <form id="formAgregar" method="post">
            <div class="form-group">
                <input type="text" name="nombre" placeholder="Nombre" required>
            </div>
            <div class="form-group">
                <input type="text" name="telefono" placeholder="Teléfono" required>
            </div>
            <div class="form-group">
                <input type="text" name="dip" placeholder="DIP" required>
            </div>
            <div class="form-group">
                <select name="vehiculo_id">
                    <option value="">Seleccione un vehículo</option>
                    <?php foreach ($vehiculos as $vehiculo) { ?>
                        <option value="<?= $vehiculo['id'] ?>"><?= $vehiculo['matricula'] ?></option>
                    <?php } ?>
                </select>
            </div>
            <button type="submit" class="btn btn-nuevo">
                Guardar
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById("openModal").addEventListener("click", function() {
    document.getElementById("modalAgregar").style.display = "flex";
});
document.querySelector(".close").addEventListener("click", function() {
    document.getElementById("modalAgregar").style.display = "none";
});
window.addEventListener("click", function(event) {
    let modal = document.getElementById("modalAgregar");
    if (event.target === modal) {
        modal.style.display = "none";
    }
});
</script>

<?php include '../../layout/footer.php'; ?>
