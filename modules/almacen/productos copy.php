<?php 
include '../../layout/header.php';
require '../../config/database.php';

// AGREGAR PRODUCTO
if (isset($_POST['accion']) && $_POST['accion'] == 'agregar_producto') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $stock = $_POST['stock'];
    $stock_minimo = $_POST['stock_minimo'];

    $stmt = $pdo->prepare("INSERT INTO productos (nombre, descripcion, stock, stock_minimo) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nombre, $descripcion, $stock, $stock_minimo]);
    header("Location: productos.php");
    exit;
}

// ALERTAS DE STOCK BAJO
$alertas = $pdo->query("SELECT * FROM productos WHERE stock <= stock_minimo")->fetchAll();

// LISTAR PRODUCTOS
$productos = $pdo->query("SELECT * FROM productos")->fetchAll();
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
.table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    border: 1px solid #ddd;
    padding: 10px;
    text-align: left;
}
th {
    background-color: #004b87;
    color: white;
    text-transform: uppercase;
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
    padding: 20px;
    border-radius: 10px;
    width: 400px;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
}
.close {
    float: right;
    font-size: 24px;
    cursor: pointer;
    color: red;
}
</style>

<div class="container">
    <h2 style="text-align:center; color:#004b87;">Gestión de Almacén</h2>
    <div class="header-table">
        <button id="btnNuevoProducto" class="btn btn-nuevo">Nuevo Producto</button>
    </div>

    <h3>Alertas de Stock Bajo</h3>
    <?php if (count($alertas) > 0): ?>
        <ul style="color:red;">
            <?php foreach ($alertas as $alerta): ?>
                <li><?= $alerta['nombre'] ?> tiene stock bajo (<?= $alerta['stock'] ?> unidades).</li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No hay alertas.</p>
    <?php endif; ?>

    <h3>Lista de Productos</h3>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Stock</th>
                <th>Stock Mínimo</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productos as $p): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td><?= $p['nombre'] ?></td>
                <td><?= $p['descripcion'] ?></td>
                <td><?= $p['stock'] ?></td>
                <td><?= $p['stock_minimo'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Nuevo Producto -->
<div id="modalNuevoProducto" class="modal">
    <div class="modal-content">
        <span class="close" id="cerrarModal">&times;</span>
        <h2>Nuevo Producto</h2>
        <form method="post">
            <input type="hidden" name="accion" value="agregar_producto">
            <div class="form-group">
                <input type="text" name="nombre" placeholder="Nombre del producto" required>
            </div>
            <div class="form-group">
                <input type="text" name="descripcion" placeholder="Descripción" required>
            </div>
            <div class="form-group">
                <input type="number" name="stock" placeholder="Stock inicial" required>
            </div>
            <div class="form-group">
                <input type="number" name="stock_minimo" placeholder="Stock mínimo" required>
            </div>
            <button type="submit" class="btn btn-nuevo">Guardar</button>
        </form>
    </div>
</div>

<script>
document.getElementById("btnNuevoProducto").addEventListener("click", function() {
    document.getElementById("modalNuevoProducto").style.display = "flex";
});
document.getElementById("cerrarModal").addEventListener("click", function() {
    document.getElementById("modalNuevoProducto").style.display = "none";
});
window.addEventListener("click", function(e) {
    if (e.target == document.getElementById("modalNuevoProducto")) {
        document.getElementById("modalNuevoProducto").style.display = "none";
    }
});
</script>

<?php include '../../layout/footer.php'; ?>
