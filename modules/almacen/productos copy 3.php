<?php 
include '../../layout/header.php';
require '../../config/database.php'; // Usamos la conexión PDO

// AGREGAR PRODUCTO
if (isset($_POST['accion']) && $_POST['accion'] == 'agregar_producto') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $descripcion = $_POST['categoria_id'];
    $stock = $_POST['stock'];
    $stock_minimo = $_POST['stock_minimo'];
    $precio = $_POST['precio'];

    $stmt = $pdo->prepare("INSERT INTO productos (nombre, descripcion, categoria_id, stock, stock_minimo, precio) VALUES (?, ?, ?, ?, ?)");
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
    $descripcion = $_POST['categoria_id'];
    $stock = $_POST['stock'];
    $stock_minimo = $_POST['stock_minimo'];
    $precio = $_POST['precio'];

    $stmt = $pdo->prepare("UPDATE productos SET nombre = ?, descripcion = ?, categoria_id = ?, stock = ?, stock_minimo = ?, precio = ? WHERE id = ?");
    $stmt->execute([$nombre, $descripcion, $categoria_id, $stock, $stock_minimo, $precio, $id]);
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
                <th>Categoria</th>
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
                    <td><?= $producto['categoria_id'] ?></td>
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
                <input type="text" name="nombre" placeholder="Nombre del Producto" required>
            </div>
            <div class="form-group">
                <input type="text" name="descripcion" placeholder="Descripción" required>
            </div>
            <div class="form-group">
               <label for="categoria">Categoría</label>
               <select name="categoria_id" required>
                 <option value="">Selecciona una categoría</option>
                 <?php foreach ($categorias as $categoria) { ?>
                <option value="<?= $categoria['id'] ?>"><?= $categoria['nombre'] ?></option>
                 <?php } ?>
               </select>
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

<!-- Modal para Actualizar Stock -->
<div id="modalActualizar" class="modal">
    <div class="modal-content">
        <span class="close" id="closeModalActualizar">&times;</span>
        <h2>Actualizar Stock</h2>
        <form id="formActualizar" method="post">
            <input type="hidden" name="id" id="producto_id">
            <div class="form-group">
                <input type="number" name="nuevo_stock" id="nuevo_stock" min="0" required>
            </div>
            <input type="hidden" name="accion" value="actualizar_stock">
            <button type="submit" class="btn btn-nuevo">Actualizar</button>
        </form>
    </div>
</div>

<!-- Modal para Editar Producto -->
<div id="modalEditar" class="modal">
    <div class="modal-content-editar">
        <span class="close" id="closeModalEditar">&times;</span>
        <h2>Editar Producto</h2>
        <form id="formEditar" method="post">
            <input type="hidden" name="id" id="editar_producto_id">

            <!-- Nombre -->
            <div class="form-group-editar">
                <label for="editar_nombre">Nombre: </label>
                <input type="text" name="nombre" id="editar_nombre" required>
            </div>

            <!-- Descripción -->
            <div class="form-group-editar">
                <label for="editar_descripcion">Descripción: </label>
                <input type="text" name="descripcion" id="editar_descripcion" required>
            </div>
            <div class="form-group-editar">
                <label for="editar_categoria">Categoría</label>
                <select name="categoria_id" id="editar_categoria" required>
                    <?php foreach ($categorias as $categoria) { ?>
                <option value="<?= $categoria['id'] ?>" <?= $categoria['id'] == $producto['categoria_id'] ? 'selected' : '' ?>><?= $categoria['nombre'] ?></option>
                    <?php } ?>
                </select>
             </div>
            <!-- Stock 
            <div class="form-group-editar">
                <label for="editar_stock">Stock: </label>
                <input type="number" name="stock" id="editar_stock" required>
            </div>-->

            <!-- Stock Mínimo -->
            <div class="form-group-editar">
                <label for="editar_stock_minimo">Stock Mínimo: </label>
                <input type="number" name="stock_minimo" id="editar_stock_minimo" required>
            </div>

            <!-- Precio -->
            <div class="form-group-editar">
                <label for="editar_precio">Precio: </label>
                <input type="number" name="precio" id="editar_precio" required>
            </div>

            <input type="hidden" name="accion" value="editar_producto">
            <button type="submit" class="btn btn-nuevo">Guardar Cambios</button>
        </form>
    </div>
</div>
<style>
    /* Estilos del Modal Editar */
    .form-group-editar {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
        align-items: center;
    }

    .form-group-editar label {
        width: 30%;
        font-weight: bold;
        color: #333;
    }

    .form-group-editar input {
        width: 65%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 14px;
    }

    .modal-content-editar {
        background: white;
        padding: 30px;
        border-radius: 10px;
        width: 450px;
        text-align: left;
        box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
    }

</style>

<script>
    // Abrir modal de Agregar Producto
    document.getElementById("openModalNuevo").onclick = function () {
        document.getElementById("modalAgregar").style.display = "flex";
    };

    document.getElementById("closeModalAgregar").onclick = function () {
        document.getElementById("modalAgregar").style.display = "none";
    };

    // Abrir modal de Editar Producto
    function openModalEditar(id, nombre, descripcion, stock, stock_minimo, precio) {
        document.getElementById("editar_producto_id").value = id;
        document.getElementById("editar_nombre").value = nombre;
        document.getElementById("editar_descripcion").value = descripcion;
        //document.getElementById("editar_stock").value = stock;
        document.getElementById("editar_stock_minimo").value = stock_minimo;
        document.getElementById("editar_precio").value = precio;

        document.getElementById("modalEditar").style.display = "flex";
    }

    document.getElementById("closeModalEditar").onclick = function () {
        document.getElementById("modalEditar").style.display = "none";
    };

    // 🛠️ FALTA: Abrir modal de Actualizar Stock
    function openModalActualizar(id, stockActual) {
        document.getElementById("producto_id").value = id;
        document.getElementById("nuevo_stock").value = stockActual;
        document.getElementById("modalActualizar").style.display = "flex";
    }

    document.getElementById("closeModalActualizar").onclick = function () {
        document.getElementById("modalActualizar").style.display = "none";
    };

    // Cerrar modales al hacer clic fuera del contenido
    window.onclick = function (event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = "none";
        }
    };

    // Confirmación para eliminar
    function confirmarEliminacion() {
        return confirm("¿Estás seguro de que deseas eliminar este producto?");
    }
</script>
<?php include '../../layout/footer.php'; ?>

