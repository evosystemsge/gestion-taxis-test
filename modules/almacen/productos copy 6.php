<?php 
include '../../layout/header.php';
require '../../config/database.php'; // Usamos la conexión PDO

// Obtener todas las categorías para el dropdown
$categorias = $pdo->query("SELECT * FROM categorias")->fetchAll();

// AGREGAR PRODUCTO
if (isset($_POST['accion']) && $_POST['accion'] == 'agregar_producto') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $categoria_id = $_POST['categoria_id']; // Corregido
    $stock = $_POST['stock'];
    $stock_minimo = $_POST['stock_minimo'];
    $precio = $_POST['precio'];

    $stmt = $pdo->prepare("INSERT INTO productos (nombre, descripcion, categoria_id, stock, stock_minimo, precio) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nombre, $descripcion, $categoria_id, $stock, $stock_minimo, $precio]);
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

// EDITAR PRODUCTO
if (isset($_POST['accion']) && $_POST['accion'] == 'editar_producto' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $categoria_id = $_POST['categoria_id']; // Corregido
    $stock = $_POST['stock'];
    $stock_minimo = $_POST['stock_minimo'];
    $precio = $_POST['precio'];

    $stmt = $pdo->prepare("UPDATE productos SET nombre = ?, descripcion = ?, categoria_id = ?, stock = ?, stock_minimo = ?, precio = ? WHERE id = ?");
    $stmt->execute([$nombre, $descripcion, $categoria_id, $stock, $stock_minimo, $precio, $id]);
    header("Location: productos.php");
    exit;
}

// Obtener todos los productos
// Obtener todos los productos y unirlos con las categorías
$productos = $pdo->query("SELECT productos.*, categorias.nombre AS categoria_nombre FROM productos LEFT JOIN categorias ON productos.categoria_id = categorias.id")->fetchAll();
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
        <button id="openModalNuevo" class="btn btn-nuevo">
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
                <th>Categoría</th>
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
                    <td><?= $producto['categoria_nombre'] ?></td>
                    <td style="display: flex; justify-content: space-between; align-items: center;"><span><?= $producto['stock'] ?></span>
                        <!-- Botón Actualizar Stock -->
                        <button onclick="openModalActualizar(<?= $producto['id'] ?>, <?= $producto['stock'] ?>)" 
                                style="background: #17a2b8; color: white; padding: 5px 10px; border-radius: 5px; border: none;">
                                actualizar
                        </button>
                    </td>
                    <td><?= $producto['stock_minimo'] ?></td>
                    <td><?= $producto['precio'] ?> $</td>
                    <td>
                        <!-- Botón Editar -->
                        <button onclick="openModalEditar(<?= $producto['id'] ?>, '<?= $producto['nombre'] ?>', '<?= $producto['descripcion'] ?>', <?= $producto['stock'] ?>, <?= $producto['stock_minimo'] ?>, <?= $producto['precio'] ?>)" 
                                style="background: #ffc107; color: black; padding: 5px 10px; border-radius: 5px; border: none; margin-right: 5px;">
                                Editar
                        </button>

                        <!-- Botón Eliminar -->
                        <form method="get" style="display:inline;" onsubmit="return confirmarEliminacion();">
                            <input type="hidden" name="id" value="<?= $producto['id'] ?>">
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

<!-- Modal para Agregar Producto -->
<div id="modalAgregar" class="modal">
    <div class="modal-content">
        <span class="close" id="closeModalAgregar">&times;</span>
        <h2>Agregar Producto</h2>
        <form id="formAgregar" method="post">
            <div class="form-group">
                <label for="nombre">Nombre</label>
                <input type="text" name="nombre" required>
            </div>
            <div class="form-group">
                <label for="descripcion">Descripción</label>
                <input type="text" name="descripcion" required>
            </div>
            <div class="form-group">
                <label for="categoria_id">Categoría</label>
                <select name="categoria_id" required>
                    <?php foreach ($categorias as $categoria) { ?>
                        <option value="<?= $categoria['id'] ?>"><?= $categoria['nombre'] ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-group">
                <label for="stock">Stock</label>
                <input type="number" name="stock" required>
            </div>
            <div class="form-group">
                <label for="stock_minimo">Stock Mínimo</label>
                <input type="number" name="stock_minimo" required>
            </div>
            <div class="form-group">
                <label for="precio">Precio</label>
                <input type="number" name="precio" required>
            </div>
            <input type="hidden" name="accion" value="agregar_producto">
            <button type="submit">Agregar Producto</button>
        </form>
    </div>
</div>

<!-- Modal para Editar Producto -->
<div id="modalEditar" class="modal">
    <div class="modal-content">
        <span class="close" id="closeModalEditar">&times;</span>
        <h2>Editar Producto</h2>
        <form id="formEditar" method="post">
            <div class="form-group">
                <label for="nombre">Nombre</label>
                <input type="text" name="nombre" id="nombreEditar" required>
            </div>
            <div class="form-group">
                <label for="descripcion">Descripción</label>
                <input type="text" name="descripcion" id="descripcionEditar" required>
            </div>
            <div class="form-group">
                <label for="categoria_id">Categoría</label>
                <select name="categoria_id" id="categoriaEditar" required>
                    <?php foreach ($categorias as $categoria) { ?>
                        <option value="<?= $categoria['id'] ?>"><?= $categoria['nombre'] ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-group">
                <label for="stock">Stock</label>
                <input type="number" name="stock" id="stockEditar" required>
            </div>
            <div class="form-group">
                <label for="stock_minimo">Stock Mínimo</label>
                <input type="number" name="stock_minimo" id="stockMinimoEditar" required>
            </div>
            <div class="form-group">
                <label for="precio">Precio</label>
                <input type="number" name="precio" id="precioEditar" required>
            </div>
            <input type="hidden" name="accion" value="editar_producto">
            <input type="hidden" name="id" id="idEditar">
            <button type="submit">Actualizar Producto</button>
        </form>
    </div>
</div>

<!-- Modal para Actualizar Stock -->
<div id="modalActualizarStock" class="modal">
    <div class="modal-content">
        <span class="close" id="closeModalActualizarStock">&times;</span>
        <h2>Actualizar Stock</h2>
        <form id="formActualizarStock" method="post">
            <div class="form-group">
                <label for="nuevo_stock">Nuevo Stock</label>
                <input type="number" name="nuevo_stock" id="nuevo_stock" required>
            </div>
            <input type="hidden" name="accion" value="actualizar_stock">
            <input type="hidden" name="id" id="idActualizarStock">
            <button type="submit">Actualizar Stock</button>
        </form>
    </div>
</div>

<script>
// Mostrar modal Agregar
document.getElementById('openModalNuevo').onclick = function() {
    document.getElementById('modalAgregar').style.display = 'flex';
}

// Cerrar modal Agregar
document.getElementById('closeModalAgregar').onclick = function() {
    document.getElementById('modalAgregar').style.display = 'none';
}

// Función para abrir modal de editar
function openModalEditar(id, nombre, descripcion, stock, stock_minimo, precio) {
    document.getElementById('idEditar').value = id;
    document.getElementById('nombreEditar').value = nombre;
    document.getElementById('descripcionEditar').value = descripcion;
    document.getElementById('stockEditar').value = stock;
    document.getElementById('stockMinimoEditar').value = stock_minimo;
    document.getElementById('precioEditar').value = precio;
    document.getElementById('modalEditar').style.display = 'flex';
}

// Cerrar modal Editar
document.getElementById('closeModalEditar').onclick = function() {
    document.getElementById('modalEditar').style.display = 'none';
}

// Función para abrir modal de actualizar stock
function openModalActualizar(id, stock) {
    document.getElementById('idActualizarStock').value = id;
    document.getElementById('nuevo_stock').value = stock;
    document.getElementById('modalActualizarStock').style.display = 'flex';
}

// Cerrar modal Actualizar Stock
document.getElementById('closeModalActualizarStock').onclick = function() {
    document.getElementById('modalActualizarStock').style.display = 'none';
}

// Confirmar eliminación
function confirmarEliminacion() {
    return confirm("¿Estás seguro de que deseas eliminar este producto?");
}
</script>

<?php include '../../layout/footer.php'; ?>
