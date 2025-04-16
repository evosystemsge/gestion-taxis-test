<?php
include '../../layout/header.php';
require '../../config/database.php'; // Usamos la conexión PDO

// Lógica para listar, agregar, editar y eliminar ingresos
if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    if ($accion == 'agregar') {
        $monto = $_POST['monto'];
        $fecha = $_POST['fecha'];
        $conductor_id = $_POST['conductor_id'];
        $caja_id = $_POST['caja_id'];
        
        // Insertamos el ingreso
        $stmt = $pdo->prepare("INSERT INTO ingresos (monto, fecha, conductor_id, caja_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$monto, $fecha, $conductor_id, $caja_id]);

        // Redirigir para recargar la página después de agregar el ingreso
        header("Location: ingresos.php");
        exit;
    } elseif ($accion == 'eliminar') {
        $id = $_POST['id'];

        // Eliminamos el ingreso
        $stmt = $pdo->prepare("DELETE FROM ingresos WHERE id = ?");
        $stmt->execute([$id]);

        echo "<script>alert('Ingreso eliminado correctamente.'); window.location.href='ingresos.php';</script>";
    }
}

// Obtener todos los ingresos con los datos del conductor y caja asignados
$ingresos = $pdo->query("SELECT i.*, c.nombre AS conductor_nombre, ca.nombre AS caja_nombre 
                         FROM ingresos i 
                         LEFT JOIN conductores c ON i.conductor_id = c.id
                         LEFT JOIN cajas ca ON i.caja_id = ca.id")->fetchAll();
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
    <h2 style="text-align: center; color: #004b87; margin-bottom: 20px;">Lista de Ingresos</h2>
    <div class="header-table">
        <button id="openModal" class="btn btn-nuevo">
            <i class="fas fa-plus"></i> Nuevo Ingreso
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
                <th>Monto</th>
                <th>Fecha</th>
                <th>Conductor</th>
                <th>Caja</th>
                <th>Acciones</th>
            </tr>
            <?php foreach ($ingresos as $ingreso) { ?>
                <tr>
                    <td><?= $ingreso['id'] ?></td>
                    <td><?= $ingreso['monto'] ?></td>
                    <td><?= $ingreso['fecha'] ?></td>
                    <td><?= $ingreso['conductor_nombre'] ?></td>
                    <td><?= $ingreso['caja_nombre'] ?></td>
                    <td>
                        <!-- Botón Ver -->
                        <a href="ver_ingreso.php?id=<?= $ingreso['id'] ?>" 
                           style="background: #17a2b8; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; margin-right: 5px;">
                            Ver
                        </a>

                        <!-- Botón Editar -->
                        <a href="editar_ingreso.php?id=<?= $ingreso['id'] ?>" 
                           style="background: #ffc107; color: black; padding: 5px 10px; border-radius: 5px; text-decoration: none; margin-right: 5px;">
                            Editar
                        </a>

                        <!-- Botón Eliminar -->
                        <form method="post" style="display:inline;" onsubmit="return confirmarEliminacion();">
                            <input type="hidden" name="id" value="<?= $ingreso['id'] ?>">
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

<!-- Modal para Agregar Ingreso -->
<div id="modalAgregar" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Agregar Ingreso</h2>
        <form id="formAgregar" method="post">
            <div class="form-group">
                <input type="number" name="monto" placeholder="Monto" required>
            </div>
            <div class="form-group">
                <input type="date" name="fecha" placeholder="Fecha" required>
            </div>
            <div class="form-group">
                <select name="conductor_id" required>
                    <option value="">Seleccionar Conductor</option>
                    <?php
                    // Obtener todos los conductores
                    $conductores = $pdo->query("SELECT * FROM conductores")->fetchAll();
                    foreach ($conductores as $conductor) {
                        echo "<option value='" . $conductor['id'] . "'>" . $conductor['nombre'] . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <select name="caja_id" required>
                    <option value="">Seleccionar Caja</option>
                    <?php
                    // Obtener todas las cajas
                    $cajas = $pdo->query("SELECT * FROM cajas")->fetchAll();
                    foreach ($cajas as $caja) {
                        echo "<option value='" . $caja['id'] . "'>" . $caja['nombre'] . "</option>";
                    }
                    ?>
                </select>
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

function confirmarEliminacion() {
    return confirm("¿Estás seguro de que deseas eliminar este ingreso?");
}
</script>

<?php include '../../layout/footer.php'; ?>
