<?php 
include '../../layout/header.php';
require '../../config/database.php'; // Usamos la conexión PDO

// AGREGAR PRODUCTO
if (isset($_POST['accion']) && $_POST['accion'] == 'agregar_producto') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $stock = $_POST['stock'];
    $stock_minimo = $_POST['stock_minimo'];
    $precio = $_POST['precio'];

    $stmt = $pdo->prepare("INSERT INTO productos (nombre, descripcion, stock, stock_minimo, precio) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$nombre, $descripcion, $stock, $stock_minimo, $precio]);
    header("Location: productos.php");
    exit;
}

// ELIMINAR PRODUCTO
if (isset($_GET['accion']) && $_GET['accion'] == 'eliminar' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: productos.php");
    exit;
}

// ACTUALIZAR STOCK
if (isset($_POST['accion']) && $_POST['accion'] == 'actualizar_stock' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $nuevo_stock = $_POST['nuevo_stock'];
    $stmt = $pdo->prepare("UPDATE productos SET stock = ? WHERE id = ?");
    $stmt->execute([$nuevo_stock, $id]);
    header("Location: productos.php");
    exit;
}

// Obtener todos los productos
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
    <h2 style="text-align: center; color: #004b87; margin-bottom: 20px;">Lista de Productos</h2>
    <div class="header-table">
        <button id="openModal" class="btn btn-nuevo">
            <i class="fas fa-plus"></i> Nuevo Producto
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
                <th>Descripción</th>
                <th>Stock</th>
                <th>Stock Mínimo</th>
                <th>Precio</th>
                <th>Acciones</th>
            </tr>
            <?php foreach ($productos as $producto) { ?>
                <tr>
                    <td><?= $producto['id'] ?></td>
                    <td><?= $producto['nombre'] ?></td>
                    <td><?= $producto['descripcion'] ?></td>
                    <td><?= $producto['stock'] ?></td>
                    <td><?= $producto['stock_minimo'] ?></td>
                    <td><?= $producto['precio'] ?> $</td>
                    <td>
                        <!-- Botón Editar -->
                        <a href="editar_producto.php?id=<?= $producto['id'] ?>" 
                           style="background: #ffc107; color: black; padding: 5px 10px; border-radius: 5px; text-decoration: none; margin-right: 5px;">
                            Editar
                        </a>

                        <!-- Botón Eliminar -->
                        <form method="get" style="display:inline;" onsubmit="return confirmarEliminacion();">
                            <input type="hidden" name="id" value="<?= $producto['id'] ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <button type="submit"
                                  style="background: red; color: white; padding: 5px 10px; border-radius: 5px; border: none; cursor: pointer;">
                                Eliminar
                            </button>
                        </form>

                        <!-- Botón Actualizar Stock -->
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="id" value="<?= $producto['id'] ?>">
                            <input type="number" name="nuevo_stock" value="<?= $producto['stock'] ?>" min="0" required>
                            <input type="hidden" name="accion" value="actualizar_stock">
                            <button type="submit"
                                  style="background: #17a2b8; color: white; padding: 5px 10px; border-radius: 5px; border: none; cursor: pointer;">
                                Actualizar Stock
                            </button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </div>
</div>

<!-- Modal para Agregar Producto -->
<div id="modalAgregar" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Agregar Producto</h2>
        <form id="formAgregar" method="post">
            <div class="form-group">
                <input type="text" name="nombre" placeholder="Nombre del Producto" required>
            </div>
            <div class="form-group">
                <input type="text" name="descripcion" placeholder="Descripción" required>
            </div>
            <div class="form-group">
                <input type="number" name="stock" placeholder="Stock" required>
            </div>
            <div class="form-group">
                <input type="number" name="stock_minimo" placeholder="Stock Mínimo" required>
            </div>
            <div class="form-group">
                <input type="number" name="precio" placeholder="Precio" required>
            </div>
            <input type="hidden" name="accion" value="agregar_producto">
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
    return confirm("¿Estás seguro de que deseas eliminar este producto?");
}
</script>

<?php include '../../layout/footer.php'; ?>
