<?php 
include '../../layout/header.php';
require '../../config/database.php'; // Usamos la conexión PDO

// Lógica para listar, agregar, editar y eliminar vehículos
if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    if ($accion == 'agregar') {
        $marca = $_POST['marca'];
        $modelo = $_POST['modelo'];
        $matricula = $_POST['matricula'];
        $numero = $_POST['numero'];
        
        // Primero, insertar el vehículo
        $stmt = $pdo->prepare("INSERT INTO vehiculos (marca, modelo, matricula, numero) VALUES (?, ?, ?, ?)");
        $stmt->execute([$marca, $modelo, $matricula, $numero]);

        // Redirigir para recargar la página después de agregar el vehículo
        header("Location: vehiculos.php");
        exit;
    } elseif ($accion == 'eliminar') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM vehiculos WHERE id = ?");
        $stmt->execute([$id]);
    }
}

// Obtener todos los vehículos junto con el conductor asignado
$vehiculos = $pdo->query("SELECT v.*, c.nombre AS conductor_nombre 
                          FROM vehiculos v 
                          LEFT JOIN conductores c ON v.id = c.vehiculo_id")->fetchAll();
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
    <h2 style="text-align: center; color: #004b87; margin-bottom: 20px;">Lista de Vehículos</h2>
    <div class="header-table">
        <button id="openModal" class="btn btn-nuevo">
            <i class="fas fa-plus"></i> Nuevo Vehículo
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
                <th>Marca</th>
                <th>Modelo</th>
                <th>Matricula</th>
                <th>Número</th>
                <th>Conductor Asignado</th>
                <th>Acciones</th>
            </tr>
            <?php foreach ($vehiculos as $vehiculo) { ?>
                <tr>
                    <td><?= $vehiculo['id'] ?></td>
                    <td><?= $vehiculo['marca'] ?></td>
                    <td><?= $vehiculo['modelo'] ?></td>
                    <td><?= $vehiculo['matricula'] ?></td>
                    <td><?= $vehiculo['numero'] ?></td>
                    <td>
                        <?php 
                        // Si tiene conductor asignado, mostrar el nombre
                        if ($vehiculo['conductor_nombre']) {
                            echo $vehiculo['conductor_nombre'];
                        } else {
                            echo "No asignado";
                        }
                        ?>
                    </td>
                    <td>
                        <!-- Botón Ver -->
                        <a href="ver_vehiculo.php?id=<?= $vehiculo['id'] ?>" 
                           style="background: #17a2b8; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; margin-right: 5px;">
                            Ver
                        </a>

                        <!-- Botón Editar -->
                        <a href="editar_vehiculo.php?id=<?= $vehiculo['id'] ?>" 
                           style="background: #ffc107; color: black; padding: 5px 10px; border-radius: 5px; text-decoration: none; margin-right: 5px;">
                            Editar
                        </a>

                        <!-- Botón Eliminar -->
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="id" value="<?= $vehiculo['id'] ?>">
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

<!-- Modal para Agregar Vehículo -->
<div id="modalAgregar" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Agregar Vehículo</h2>
        <form id="formAgregar" method="post">
            <div class="form-group">
                <input type="text" name="marca" placeholder="Marca" required>
            </div>
            <div class="form-group">
                <input type="text" name="modelo" placeholder="Modelo" required>
            </div>
            <div class="form-group">
                <input type="text" name="matricula" placeholder="Matricula" required>
            </div>
            <div class="form-group">
                <input type="text" name="numero" placeholder="Número" required>
            </div>
            <input type="hidden" name="accion" value="agregar">
            <button type="submit" class="btn btn-nuevo">Guardar</button>
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
