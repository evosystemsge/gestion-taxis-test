<?php
// Incluimos el encabezado y la conexión a la base de datos
include '../../layout/header.php';
require '../../config/database.php';

// Configuración de imágenes
$uploadDir = '../../imagenes/productos/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// ==============================
// LÓGICA DE ACCIONES: Agregar / Editar / Eliminar
// ==============================
if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion == 'agregar') {
        // Agregar nuevo producto
        $stmt = $pdo->prepare("INSERT INTO productos (referencia, codigo_barras, nombre, descripcion, stock, stock_minimo, precio, categoria_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['referencia'], $_POST['codigo_barras'], $_POST['nombre'], 
            $_POST['descripcion'], $_POST['stock'], $_POST['stock_minimo'], 
            $_POST['precio'], $_POST['categoria_id']
        ]);
        
        // Obtener el ID del producto recién insertado
        $productoId = $pdo->lastInsertId();
        
        // Procesar cada imagen
        $updateFields = [];
        $updateValues = [];
        
        for ($i = 1; $i <= 4; $i++) {
            $fieldName = "imagen$i";
            if (!empty($_FILES[$fieldName]['name'])) {
                $fileName = uniqid() . '_' . basename($_FILES[$fieldName]['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $targetPath)) {
                    $updateFields[] = "imagen$i = ?";
                    $updateValues[] = $fileName;
                }
            }
        }
        
        // Actualizar el producto con las rutas de las imágenes
        if (!empty($updateFields)) {
            $sql = "UPDATE productos SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $updateValues[] = $productoId;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateValues);
        }
        
        header("Location: productos.php");
        exit;

    } elseif ($accion == 'eliminar') {
        // Eliminar producto
        $id = $_POST['id'];
        
        // Primero eliminar las imágenes asociadas
        $stmt = $pdo->prepare("SELECT imagen1, imagen2, imagen3, imagen4 FROM productos WHERE id = ?");
        $stmt->execute([$id]);
        $producto = $stmt->fetch();
        
        for ($i = 1; $i <= 4; $i++) {
            $imgField = "imagen$i";
            if (!empty($producto[$imgField])) {
                $filePath = $uploadDir . $producto[$imgField];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }
        
        // Eliminar el producto
        $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
        $stmt->execute([$id]);
        echo "<script>alert('Producto eliminado correctamente.'); window.location.href='productos.php';</script>";

    } elseif ($accion == 'editar') {
        $productoId = $_POST['id'];
        
        // Procesar cada imagen
        $updateFields = [];
        $updateValues = [];
        
        for ($i = 1; $i <= 4; $i++) {
            $fieldName = "editar_imagen$i";
            $currentImageField = "imagen_actual$i";
            
            if (!empty($_FILES[$fieldName]['name'])) {
                // Eliminar imagen anterior si existe
                if (!empty($_POST[$currentImageField])) {
                    $oldImagePath = $uploadDir . $_POST[$currentImageField];
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                
                // Subir nueva imagen
                $fileName = uniqid() . '_' . basename($_FILES[$fieldName]['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $targetPath)) {
                    $updateFields[] = "imagen$i = ?";
                    $updateValues[] = $fileName;
                }
            } elseif (!empty($_POST[$currentImageField])) {
                // Mantener la imagen actual
                $updateFields[] = "imagen$i = ?";
                $updateValues[] = $_POST[$currentImageField];
            } else {
                // No hay imagen
                $updateFields[] = "imagen$i = NULL";
            }
        }
        
        // Actualizar campos básicos
        $stmt = $pdo->prepare("UPDATE productos SET referencia=?, codigo_barras=?, nombre=?, descripcion=?, stock=?, stock_minimo=?, precio=?, categoria_id=?" . 
                             (!empty($updateFields) ? ", " . implode(', ', $updateFields) : "") . 
                             " WHERE id=?");
        
        $params = [
            $_POST['referencia'], $_POST['codigo_barras'], $_POST['nombre'], 
            $_POST['descripcion'], $_POST['stock'], $_POST['stock_minimo'], 
            $_POST['precio'], $_POST['categoria_id']
        ];
        
        if (!empty($updateValues)) {
            $params = array_merge($params, $updateValues);
        }
        
        $params[] = $productoId;
        
        $stmt->execute($params);
        header("Location: productos.php");
        exit;
    }
}

// Obtener todos los productos con información de categoría
$productos = $pdo->query("
    SELECT p.*, c.categoria 
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
")->fetchAll();

// Obtener categorías para filtros y formularios
$categorias = $pdo->query("SELECT id, categoria FROM categorias ORDER BY categoria")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos</title>
    <style>
    /* ESTILOS COPIADOS DE VEHICULOS.PHP */
    :root {
        --color-primario: #004b87;
        --color-secundario: #003366;
        --color-texto: #333;
        --color-fondo: #f8f9fa;
        --color-borde: #e2e8f0;
        --color-exito: #28a745;
        --color-advertencia: #ffc107;
        --color-peligro: #dc3545;
        --color-info: #17a2b8;
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f5f5f5;
        color: var(--color-texto);
        line-height: 1.6;
    }
    
    .container {
        max-width: 1250px;
        margin: 20px auto;
        background: #fff;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    
    h2 {
        margin-top:0px;
        color: var(--color-primario);
        font-size: 1.8rem;
        margin-bottom: 5px;
        text-align: center;
        font-weight: 600;
    }
    
    /* ============ TABLA ============ */
    .table-container {
        overflow-x: auto;
        margin-top: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px;
    }
    
    .table th, .table td {
        padding: 14px 12px;
        text-align: left;
        border-bottom: 1px solid var(--color-borde);
    }
    
    .table th {
        background-color: var(--color-primario);
        color: white;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        position: sticky;
        top: 0;
    }
    
    .table td {
        background-color: #fff;
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }
    
    .table tr:hover td {
        background-color: rgba(0, 75, 135, 0.05);
    }
    
    /* Estilo para productos con stock bajo */
    .alerta-stock {
        background-color: #ffebee !important;
        font-weight: bold;
        color:rgb(235, 13, 46)
    }
    
    /* ============ BOTONES ============ */
    .btn {
        padding: 8px 8px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-weight: 500;
    }
    
    .btn-nuevo {
        background-color: var(--color-exito);
        color: white;
    }
    
    .btn-nuevo:hover {
        background-color: #218838;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .btn-editar {
        background-color: var(--color-advertencia);
        color: #212529;
    }
    
    .btn-editar:hover {
        background-color: #e0a800;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .btn-ver {
        background-color: var(--color-info);
        color: white;
    }
    
    .btn-ver:hover {
        background-color: #138496;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .btn-eliminar {
        background-color: var(--color-peligro);
        color: white;
    }
    
    .btn-eliminar:hover {
        background-color: #c82333;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .btn-filtro {
        background-color: white;
        border: 1px solid var(--color-borde);
        color: var(--color-texto);
    }
    
    .btn-filtro:hover {
        background-color: #f1f5f9;
    }
    
    /* ============ CONTROLES DE TABLA ============ */
    .table-controls {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    
    .table-controls input, 
    .table-controls select {
        flex: 1;
        min-width: 200px;
        max-width: 300px;
        padding: 10px 12px;
        border: 1px solid var(--color-borde);
        border-radius: 6px;
        font-size: 0.95rem;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 25px;
        gap: 8px;
        align-items: center;
    }
    
    /* ============ MODALES ============ */
    .modal-product {
        display: none;
        position: fixed;
        z-index: 9999;
        inset: 0;
        font-family: 'Inter', sans-serif;
    }
    
    .modal-product__overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
    }
    
    .modal-product__container {
        position: relative;
        background: white;
        width: 800px;
        max-width: 95%;
        max-height: 95vh;
        margin: auto;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        animation: modalFadeIn 0.3s ease-out;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }
    
    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .modal-product__close {
        position: absolute;
        top: 16px;
        right: 16px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: white;
        border: 1px solid var(--color-borde);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        z-index: 10;
    }
    
    .modal-product__close:hover {
        background: #f1f5f9;
        transform: rotate(90deg);
    }
    
    .modal-product__close svg {
        width: 18px;
        height: 18px;
        color: #64748b;
    }
    
    .modal-product__header {
        padding: 24px 24px 16px;
        border-bottom: 1px solid var(--color-borde);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .modal-product__header-content {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .modal-product__badge {
        margin-left: auto;
    }
    
    .modal-product__badge span {
        display: inline-block;
        padding: 4px 20px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        background: #10b981;
        color: white;
    }
    
    .modal-product__title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }
    
    .modal-product__title--highlight {
        color: var(--color-primario);
        font-weight: 600;
    }
    
    .modal-product__body {
        padding: 0 24px;
        overflow-y: auto;
        flex-grow: 1;
    }
    
    .modal-product__form {
        display: flex;
        flex-direction: column;
        gap: 16px;
        padding: 16px 0;
    }
    
    .modal-product__form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .modal-product__form-row {
        display: flex;
        gap: 16px;
    }
    
    .modal-product__form-row .modal-product__form-group {
        flex: 1;
    }
    
    .modal-product__form-label {
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 600;
    }
    
    .modal-product__form-input {
        padding: 10px 12px;
        border: 1px solid var(--color-borde);
        border-radius: 6px;
        font-size: 0.95rem;
        transition: border-color 0.2s ease;
        width: 90%;
    }
    
    .modal-product__form-input:focus {
        outline: none;
        border-color: var(--color-primario);
        box-shadow: 0 0 0 3px rgba(0, 75, 135, 0.1);
    }
    
    .modal-product__file-input {
        padding: 8px;
        border: 1px solid var(--color-borde);
        border-radius: 6px;
        font-size: 0.9rem;
        width: 100%;
    }
    
    .modal-product__image-preview {
        margin-top: 8px;
        min-height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 5px;
    }
    
    .modal-product__preview-image {
        max-width: 100%;
        max-height: 100px;
        border-radius: 4px;
        border: 1px solid var(--color-borde);
    }
    
    .modal-product__footer {
        padding: 16px 24px;
        border-top: 1px solid var(--color-borde);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .modal-product__action-btn {
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
        background: white;
        border: 1px solid var(--color-borde);
        color: #1e293b;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .modal-product__action-btn:hover {
        background: #f1f5f9;
        transform: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    
    .modal-product__action-btn svg {
        width: 14px;
        height: 14px;
    }
    
    .modal-product__action-btn--primary {
        background: var(--color-primario);
        border-color: var(--color-primario);
        color: white;
    }
    
    .modal-product__action-btn--primary:hover {
        background: var(--color-secundario);
    }
    
    /* ============ TABLA DE DETALLES ============ */
    .modal-product__data-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid var(--color-borde);
        border-radius: 8px;
        overflow: hidden;
    }
    
    .modal-product__data-table tr:not(:last-child) {
        border-bottom: 1px solid var(--color-borde);
    }
    
    .modal-product__data-label {
        padding: 12px 16px;
        font-size: 0.85rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
        background-color: #f8fafc;
        text-align: left;
        border-right: 1px solid var(--color-borde);
        width: 40%;
    }
    
    .modal-product__data-value {
        padding: 12px 16px;
        font-size: 1rem;
        color: #1e293b;
        font-weight: 500;
        background-color: white;
    }
    
    /* ============ CARRUSEL DE IMÁGENES ============ */
    .product-carousel {
        position: relative;
        width: 100%;
        height: 200px;
        overflow: hidden;
        margin-bottom: 20px;
        border-radius: 8px;
        background: #f1f5f9;
    }
    
    .product-carousel__images {
        display: flex;
        height: 100%;
        transition: transform 0.3s ease;
    }
    
    .product-carousel__image {
        min-width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .product-carousel__nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 100%;
        display: flex;
        justify-content: space-between;
        padding: 0 10px;
        z-index: 2;
    }
    
    .product-carousel__btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.8);
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }
    
    .product-carousel__btn svg {
        width: 20px;
        height: 20px;
        color: #1e293b;
    }
    
    .product-carousel__btn:hover {
        background: white;
    }
    
    .product-carousel__counter {
        position: absolute;
        bottom: 10px;
        right: 10px;
        background: rgba(0, 0, 0, 0.5);
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
        z-index: 2;
    }
    
    /* ============ BOTONES FLOTANTES ============ */
    .action-buttons {
        position: fixed;
        bottom: 20px;
        right: 20px;
        display: flex;
        gap: 10px;
        z-index: 100;
    }
    
    .action-button {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--color-primario);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        cursor: pointer;
        transition: all 0.3s;
        border: none;
    }
    
    .action-button:hover {
        transform: scale(1.1);
        background: var(--color-secundario);
    }
    
    /* ============ RESPONSIVE ============ */
    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }
        
        .modal-product__container {
            max-width: 98%;
            max-height: 98vh;
        }
        
        .modal-product__form-row {
            flex-direction: column;
            gap: 16px;
        }
        
        .table-controls input, 
        .table-controls select {
            max-width: 100%;
        }
        
        .modal-product__footer {
            flex-direction: column;
        }
        
        .modal-product__action-btn {
            width: 100%;
            justify-content: center;
        }
        
        .product-carousel {
            height: 150px;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <h2>Lista de Productos</h2>
        
        <!-- Controles de tabla -->
        <div class="table-controls">
            <input type="text" id="searchInput" placeholder="Buscar producto..." class="modal-product__form-input">
            <select id="filterCategoria" class="modal-product__form-input">
                <option value="">Todas las categorías</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['categoria']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filterStock" class="modal-product__form-input">
                <option value="">Todos los estados</option>
                <option value="bajo">Stock bajo</option>
                <option value="critico">Stock crítico</option>
                <option value="ok">Stock suficiente</option>
            </select>
            <select id="filterPrecio" class="modal-product__form-input">
                <option value="">Todos los precios</option>
                <option value="0-100000">0 - 100.000 XAF</option>
                <option value="100000-500000">100.000 - 500.000 XAF</option>
                <option value="500000-1000000">500.000 - 1.000.000 XAF</option>
                <option value="1000000">Más de 1.000.000 XAF</option>
            </select>
            <button id="openModalAgregar" class="btn btn-nuevo">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Nuevo Producto
            </button>
        </div>
        
        <!-- Tabla de productos -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Referencia</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Stock</th>
                        <th>Precio</th>
                        <th>Categoría</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($productos as $prod): ?>
                        <?php
                            $stockStatus = 'ok';
                            if ($prod['stock'] <= $prod['stock_minimo']) {
                                $stockStatus = ($prod['stock'] <= ($prod['stock_minimo'] * 0.5)) ? 'critico' : 'bajo';
                                $clase_alerta = 'alerta-stock';
                            } else {
                                $clase_alerta = '';
                            }
                        ?>
                        <tr class="<?= $clase_alerta ?>" 
                            data-categoria="<?= $prod['categoria_id'] ?>"
                            data-stock="<?= $stockStatus ?>"
                            data-precio="<?= $prod['precio'] ?>">
                            <td><?= $prod['id'] ?></td>
                            <td><?= htmlspecialchars($prod['referencia']) ?></td>
                            <td><?= htmlspecialchars($prod['nombre']) ?></td>
                            <td><?= htmlspecialchars(mb_strimwidth($prod['descripcion'], 0, 50, '...')) ?></td>
                            <td><?= number_format($prod['stock'], 0, ',', '.') ?></td>
                            <td><?= number_format($prod['precio'], 0, ',', '.') ?> XAF</td>
                            <td><?= htmlspecialchars($prod['categoria']) ?></td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <button class="btn btn-ver" onclick="verProducto(<?= htmlspecialchars(json_encode($prod), ENT_QUOTES, 'UTF-8') ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                    <button class="btn btn-editar" onclick="editarProducto(<?= htmlspecialchars(json_encode($prod), ENT_QUOTES, 'UTF-8') ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </button>
                                    <form method="post" style="display:inline;" onsubmit="return confirmarEliminacion();">
                                        <input type="hidden" name="id" value="<?= $prod['id'] ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <button type="submit" class="btn btn-eliminar">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginación -->
        <div class="pagination">
            <button class="btn" id="prevPage" disabled>Anterior</button>
            <span id="pageInfo" style="padding: 8px 15px;">Página 1</span>
            <button class="btn" id="nextPage">Siguiente</button>
        </div>
    </div>
    
    <!-- Botones flotantes -->
    <div class="action-buttons">
        <button class="action-button" id="btnScrollTop" title="Ir arriba">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 15l-6-6-6 6"></path>
            </svg>
        </button>
        <button class="action-button" id="btnAddNew" title="Nuevo producto">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
        </button>
    </div>
    
    <!-- ============ MODALES ============ -->
    
    <!-- Modal Agregar Producto -->
    <div id="modalAgregar" class="modal-product">
        <div class="modal-product__overlay" onclick="cerrarModal('modalAgregar')"></div>
        <div class="modal-product__container">
            <button class="modal-product__close" onclick="cerrarModal('modalAgregar')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal-product__header">
                <h3 class="modal-product__title">Agregar Nuevo Producto</h3>
            </div>
            
            <div class="modal-product__body">
                <form method="post" enctype="multipart/form-data" class="modal-product__form" id="modalAgregarForm">
                    <div class="modal-product__form-row">
                        <div class="modal-product__form-group">
                            <label for="referencia" class="modal-product__form-label">Referencia</label>
                            <input type="text" name="referencia" class="modal-product__form-input" placeholder="Referencia" required>
                        </div>
                        
                        <div class="modal-product__form-group">
                            <label for="codigo_barras" class="modal-product__form-label">Código de Barras</label>
                            <input type="text" name="codigo_barras" class="modal-product__form-input" placeholder="Código de barras">
                        </div>
                    </div>
                    
                    <div class="modal-product__form-group">
                        <label for="nombre" class="modal-product__form-label">Nombre</label>
                        <input type="text" name="nombre" class="modal-product__form-input" placeholder="Nombre" required>
                    </div>
                    
                    <div class="modal-product__form-group">
                        <label for="descripcion" class="modal-product__form-label">Descripción</label>
                        <textarea name="descripcion" class="modal-product__form-input" placeholder="Descripción" rows="3"></textarea>
                    </div>
                    
                    <div class="modal-product__form-row">
                        <div class="modal-product__form-group">
                            <label for="stock" class="modal-product__form-label">Stock</label>
                            <input type="number" name="stock" class="modal-product__form-input" placeholder="Stock" required>
                        </div>
                        
                        <div class="modal-product__form-group">
                            <label for="stock_minimo" class="modal-product__form-label">Stock Mínimo</label>
                            <input type="number" name="stock_minimo" class="modal-product__form-input" placeholder="Stock mínimo" required>
                        </div>
                    </div>
                    
                    <div class="modal-product__form-row">
                        <div class="modal-product__form-group">
                            <label for="precio" class="modal-product__form-label">Precio (XAF)</label>
                            <input type="number" name="precio" class="modal-product__form-input" placeholder="Precio" required>
                        </div>
                        
                        <div class="modal-product__form-group">
                            <label for="categoria_id" class="modal-product__form-label">Categoría</label>
                            <select name="categoria_id" class="modal-product__form-input" required>
                                <option value="">Seleccione una categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['categoria']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-product__form-group">
                        <label class="modal-product__form-label">Imágenes del Producto</label>
                        <div class="modal-product__image-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                <div class="modal-product__image-container">
                                    <label for="imagen<?= $i ?>" class="modal-product__form-label">Imagen <?= $i ?></label>
                                    <input type="file" name="imagen<?= $i ?>" id="imagen<?= $i ?>" class="modal-product__file-input" accept="image/*">
                                    <div class="modal-product__image-preview" id="preview<?= $i ?>" style="display: none;">
                                        <img id="previewImg<?= $i ?>" src="#" alt="Vista previa" class="modal-product__preview-image">
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-product__footer">
                <button type="button" class="modal-product__action-btn" onclick="cerrarModal('modalAgregar')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar
                </button>
                <button type="submit" name="accion" value="agregar" class="modal-product__action-btn modal-product__action-btn--primary" form="modalAgregarForm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Guardar Producto
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Ver Producto -->
    <div id="modalVer" class="modal-product">
        <div class="modal-product__overlay" onclick="cerrarModal('modalVer')"></div>
        <div class="modal-product__container">
            <button class="modal-product__close" onclick="cerrarModal('modalVer')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal-product__header">
                <div class="product-carousel">
                    <!-- Contenido del carrusel de imágenes se insertará aquí -->
                </div>
                
                <div class="modal-product__header-content">
                    <h3 class="modal-product__title">CATEGORÍA: <span id="productoCategoria" class="modal-product__title--highlight"></span></h3>
                    <div class="modal-product__badge">
                        <span id="productoStatusBadge">Activo</span>
                    </div>
                </div>
            </div>
            
            <div class="modal-product__body">
                <div id="detalleProducto" style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Contenido dinámico aquí -->
                </div>
            </div>
            
            <div class="modal-product__footer">
                <button class="modal-product__action-btn" onclick="mostrarHistorial('ventas')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    Historial de Ventas
                </button>
                <button class="modal-product__action-btn modal-product__action-btn--primary" onclick="editarProducto()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Editar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Producto -->
    <div id="modalEditar" class="modal-product">
        <div class="modal-product__overlay" onclick="cerrarModal('modalEditar')"></div>
        <div class="modal-product__container">
            <button class="modal-product__close" onclick="cerrarModal('modalEditar')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal-product__header">
                <h3 class="modal-product__title">Editar Producto</h3>
            </div>
            
            <div class="modal-product__body">
                <form method="post" enctype="multipart/form-data" class="modal-product__form" id="modalEditarForm">
                    <input type="hidden" id="editar_id" name="id">
                    
                    <div class="modal-product__form-row">
                        <div class="modal-product__form-group">
                            <label for="editar_referencia" class="modal-product__form-label">Referencia</label>
                            <input type="text" id="editar_referencia" name="referencia" class="modal-product__form-input" required>
                        </div>
                        
                        <div class="modal-product__form-group">
                            <label for="editar_codigo_barras" class="modal-product__form-label">Código de Barras</label>
                            <input type="text" id="editar_codigo_barras" name="codigo_barras" class="modal-product__form-input">
                        </div>
                    </div>
                    
                    <div class="modal-product__form-group">
                        <label for="editar_nombre" class="modal-product__form-label">Nombre</label>
                        <input type="text" id="editar_nombre" name="nombre" class="modal-product__form-input" required>
                    </div>
                    
                    <div class="modal-product__form-group">
                        <label for="editar_descripcion" class="modal-product__form-label">Descripción</label>
                        <textarea id="editar_descripcion" name="descripcion" class="modal-product__form-input" rows="3"></textarea>
                    </div>
                    
                    <div class="modal-product__form-row">
                        <div class="modal-product__form-group">
                            <label for="editar_stock" class="modal-product__form-label">Stock</label>
                            <input type="number" id="editar_stock" name="stock" class="modal-product__form-input" required>
                        </div>
                        
                        <div class="modal-product__form-group">
                            <label for="editar_stock_minimo" class="modal-product__form-label">Stock Mínimo</label>
                            <input type="number" id="editar_stock_minimo" name="stock_minimo" class="modal-product__form-input" required>
                        </div>
                    </div>
                    
                    <div class="modal-product__form-row">
                        <div class="modal-product__form-group">
                            <label for="editar_precio" class="modal-product__form-label">Precio (XAF)</label>
                            <input type="number" id="editar_precio" name="precio" class="modal-product__form-input" required>
                        </div>
                        
                        <div class="modal-product__form-group">
                            <label for="editar_categoria_id" class="modal-product__form-label">Categoría</label>
                            <select id="editar_categoria_id" name="categoria_id" class="modal-product__form-input" required>
                                <option value="">Seleccione una categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['categoria']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-product__form-group">
                        <label class="modal-product__form-label">Imágenes del Producto</label>
                        <div class="modal-product__image-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                <div class="modal-product__image-container">
                                    <label for="editar_imagen<?= $i ?>" class="modal-product__form-label">Imagen <?= $i ?></label>
                                    <input type="file" name="editar_imagen<?= $i ?>" id="editar_imagen<?= $i ?>" class="modal-product__file-input" accept="image/*">
                                    <input type="hidden" name="imagen_actual<?= $i ?>" id="imagen_actual<?= $i ?>">
                                    <div class="modal-product__image-preview" id="editar_preview<?= $i ?>">
                                        <img id="editar_previewImg<?= $i ?>" src="#" alt="Vista previa" class="modal-product__preview-image" style="display: none;">
                                        <div id="current_image<?= $i ?>"></div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-product__footer">
                <button type="button" class="modal-product__action-btn" onclick="cerrarModal('modalEditar')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar
                </button>
                <button type="submit" name="accion" value="editar" class="modal-product__action-btn modal-product__action-btn--primary" form="modalEditarForm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Actualizar Producto
                </button>
            </div>
        </div>
    </div>
    
    <script>
    // Variables globales
    let currentProducto = null;
    let currentPage = 1;
    const rowsPerPage = 10;
    let filteredData = [];
    let currentImageIndex = 0;
    let totalImages = 0;

    // Inicialización
    document.addEventListener('DOMContentLoaded', function() {
        // Configurar eventos
        initEventListeners();
        // Configurar paginación
        setupPagination();
        // Mostrar primera página
        updateTable();
    });
    
    // Configurar eventos
    function initEventListeners() {
        // Botón agregar
        document.getElementById('openModalAgregar').addEventListener('click', function() {
            abrirModal('modalAgregar');
        });
        
        // Botón flotante agregar
        document.getElementById('btnAddNew').addEventListener('click', function() {
            abrirModal('modalAgregar');
        });
        
        // Botón flotante ir arriba
        document.getElementById('btnScrollTop').addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        // Búsqueda
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentPage = 1;
                updateTable();
            }, 300);
        });
        
        // Filtros
        document.getElementById('filterCategoria').addEventListener('change', function() {
            currentPage = 1;
            updateTable();
        });
        
        document.getElementById('filterStock').addEventListener('change', function() {
            currentPage = 1;
            updateTable();
        });
        
        document.getElementById('filterPrecio').addEventListener('change', function() {
            currentPage = 1;
            updateTable();
        });
        
        // Paginación
        document.getElementById('prevPage').addEventListener('click', function() {
            if (currentPage > 1) {
                currentPage--;
                updateTable();
            }
        });
        
        document.getElementById('nextPage').addEventListener('click', function() {
            const totalPages = Math.ceil(filteredData.length / rowsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                updateTable();
            }
        });
        
        // Previsualización de imágenes (agregar)
        for (let i = 1; i <= 4; i++) {
            document.getElementById(`imagen${i}`)?.addEventListener('change', function(e) {
                const preview = document.getElementById(`preview${i}`);
                const previewImg = document.getElementById(`previewImg${i}`);
                
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        preview.style.display = 'block';
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
            
            // Previsualización de imágenes (editar)
            document.getElementById(`editar_imagen${i}`)?.addEventListener('change', function(e) {
                const preview = document.getElementById(`editar_previewImg${i}`);
                const currentImage = document.getElementById(`current_image${i}`);
                
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                        currentImage.innerHTML = '';
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
    }
    
    // Configurar paginación
    function setupPagination() {
        const totalRows = document.querySelectorAll('#tableBody tr').length;
        const totalPages = Math.ceil(totalRows / rowsPerPage);
        
        document.getElementById('prevPage').disabled = currentPage === 1;
        document.getElementById('nextPage').disabled = currentPage === totalPages;
        document.getElementById('pageInfo').textContent = `Página ${currentPage} de ${totalPages}`;
    }
    
    // Actualizar tabla con filtros y paginación
    function updateTable() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const categoriaFilter = document.getElementById('filterCategoria').value;
        const stockFilter = document.getElementById('filterStock').value;
        const precioFilter = document.getElementById('filterPrecio').value;
        
        const rows = document.querySelectorAll('#tableBody tr');
        filteredData = [];
        
        rows.forEach(row => {
            const categoria = row.getAttribute('data-categoria');
            const stockStatus = row.getAttribute('data-stock');
            const precio = parseFloat(row.getAttribute('data-precio'));
            const texto = row.textContent.toLowerCase();
            
            const matchesSearch = texto.includes(searchTerm);
            const matchesCategoria = !categoriaFilter || categoria === categoriaFilter;
            const matchesStock = !stockFilter || stockStatus === stockFilter;
            
            // Filtro por precio
            let matchesPrecio = true;
            if (precioFilter) {
                const [min, max] = precioFilter.split('-').map(Number);
                if (precioFilter.endsWith('+')) {
                    matchesPrecio = precio >= min;
                } else if (min && max) {
                    matchesPrecio = precio >= min && precio <= max;
                }
            }
            
            if (matchesSearch && matchesCategoria && matchesStock && matchesPrecio) {
                filteredData.push(row);
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        // Paginación
        const startIdx = (currentPage - 1) * rowsPerPage;
        const endIdx = startIdx + rowsPerPage;
        const paginatedData = filteredData.slice(startIdx, endIdx);
        
        // Mostrar solo las filas de la página actual
        rows.forEach(row => {
            if (paginatedData.includes(row)) {
                row.style.display = '';
            } else if (filteredData.includes(row)) {
                row.style.display = 'none';
            }
        });
        
        // Actualizar controles de paginación
        const totalPages = Math.ceil(filteredData.length / rowsPerPage);
        document.getElementById('prevPage').disabled = currentPage === 1;
        document.getElementById('nextPage').disabled = currentPage === totalPages || totalPages === 0;
        document.getElementById('pageInfo').textContent = `Página ${currentPage} de ${totalPages}`;
    }
    
    // Función para abrir modal
    function abrirModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
        // Enfocar el primer input
        setTimeout(() => {
            const firstInput = document.querySelector(`#${modalId} .modal-product__form-input`);
            if (firstInput) firstInput.focus();
        }, 100);
    }
    
    // Función para cerrar modal
    function cerrarModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        // Reiniciar el carrusel al cerrar el modal
        currentImageIndex = 0;
        totalImages = 0;
    }
    
    // Función para ver producto
    function verProducto(producto) {
        currentProducto = producto;
        
        // Configurar categoría
        document.getElementById('productoCategoria').textContent = producto.categoria || 'Sin categoría';
        
        // Configurar estado del badge
        let estado = 'Stock suficiente';
        let badgeColor = '#10b981'; // Verde
        
        if (producto.stock <= producto.stock_minimo * 0.5) {
            estado = 'Stock crítico';
            badgeColor = '#ef4444'; // Rojo
        } else if (producto.stock <= producto.stock_minimo) {
            estado = 'Stock bajo';
            badgeColor = '#f59e0b'; // Amarillo
        }
        
        document.getElementById('productoStatusBadge').textContent = estado;
        document.getElementById('productoStatusBadge').style.background = badgeColor;
        
        // Generar HTML para el carrusel de imágenes
        let carouselHTML = `
            <div class="product-carousel__images" id="productCarouselImages">
        `;
        
        let hasImages = false;
        for (let i = 1; i <= 4; i++) {
            const imgField = `imagen${i}`;
            if (producto[imgField]) {
                hasImages = true;
                carouselHTML += `
                    <img class="product-carousel__image" 
                         src="../../imagenes/productos/${producto[imgField]}" 
                         alt="Producto ${i}">
                `;
            }
        }
        
        if (!hasImages) {
            carouselHTML += `
                <div style="display: flex; justify-content: center; align-items: center; width: 100%; height: 100%; color: #64748b;">
                    No hay imágenes disponibles
                </div>
            `;
        }
        
        carouselHTML += `
            </div>
            ${hasImages ? `
            <div class="product-carousel__nav">
                <button class="product-carousel__btn" onclick="prevImage()">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 18l-6-6 6-6"></path>
                    </svg>
                </button>
                <button class="product-carousel__btn" onclick="nextImage()">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 18l6-6-6-6"></path>
                    </svg>
                </button>
            </div>
            <div class="product-carousel__counter" id="imageCounter">1/${hasImages ? document.querySelectorAll('.product-carousel__image').length : '0'}</div>
            ` : ''}
        `;
        
        // Insertar el carrusel en el header
        const carouselContainer = document.querySelector('.modal-product__header .product-carousel');
        carouselContainer.innerHTML = carouselHTML;
        
        // Datos para las columnas
        const columna1 = [
            { label: 'REFERENCIA', value: producto.referencia || 'N/A' },
            { label: 'CÓDIGO DE BARRAS', value: producto.codigo_barras || 'N/A' },
            { label: 'NOMBRE', value: producto.nombre || 'N/A' },
            { label: 'DESCRIPCIÓN', value: producto.descripcion || 'N/A' },
            { label: 'CATEGORÍA', value: producto.categoria || 'N/A' }
        ];
        
        const columna2 = [
            { label: 'STOCK ACTUAL', value: (producto.stock || '0') + ' unidades' },
            { label: 'STOCK MÍNIMO', value: (producto.stock_minimo || '0') + ' unidades' },
            { label: 'ESTADO', value: estado },
            { label: 'PRECIO', value: (producto.precio || '0') + ' XAF' },
            { label: 'FECHA CREACIÓN', value: new Date(producto.fecha_creacion || '').toLocaleDateString() || 'N/A' }
        ];
        
        // Generar HTML para las tablas
        const html = `
            <div style="flex: 1; min-width: 300px;">
                <table class="modal-product__data-table">
                    ${columna1.map(item => `
                        <tr>
                            <td class="modal-product__data-label">${item.label}</td>
                            <td class="modal-product__data-value">${item.value}</td>
                        </tr>
                    `).join('')}
                </table>
            </div>
            <div style="flex: 1; min-width: 300px;">
                <table class="modal-product__data-table">
                    ${columna2.map(item => `
                        <tr>
                            <td class="modal-product__data-label">${item.label}</td>
                            <td class="modal-product__data-value">${item.value}</td>
                        </tr>
                    `).join('')}
                </table>
            </div>
        `;
        
        // Insertar todo el contenido
        document.getElementById('detalleProducto').innerHTML = html;
        
        // Inicializar el carrusel si hay imágenes
        if (hasImages) {
            initCarousel();
        }
        
        // Mostrar modal
        abrirModal('modalVer');
    }
    
    // Funciones para el carrusel
    function initCarousel() {
        const images = document.querySelectorAll('.product-carousel__image');
        totalImages = images.length;
        updateCounter();
    }
    
    function prevImage() {
        if (currentImageIndex > 0) {
            currentImageIndex--;
        } else {
            currentImageIndex = totalImages - 1;
        }
        updateCarousel();
    }
    
    function nextImage() {
        if (currentImageIndex < totalImages - 1) {
            currentImageIndex++;
        } else {
            currentImageIndex = 0;
        }
        updateCarousel();
    }
    
    function updateCarousel() {
        const carousel = document.getElementById('productCarouselImages');
        carousel.style.transform = `translateX(-${currentImageIndex * 100}%)`;
        updateCounter();
    }
    
    function updateCounter() {
        const counter = document.getElementById('imageCounter');
        if (counter) {
            counter.textContent = `${currentImageIndex + 1}/${totalImages}`;
        }
    }
    
    // Función para editar producto
    function editarProducto(producto = null) {
        if (!producto && currentProducto) {
            producto = currentProducto;
        }
        
        if (producto) {
            document.getElementById('editar_id').value = producto.id;
            document.getElementById('editar_referencia').value = producto.referencia;
            document.getElementById('editar_codigo_barras').value = producto.codigo_barras;
            document.getElementById('editar_nombre').value = producto.nombre;
            document.getElementById('editar_descripcion').value = producto.descripcion;
            document.getElementById('editar_stock').value = producto.stock;
            document.getElementById('editar_stock_minimo').value = producto.stock_minimo;
            document.getElementById('editar_precio').value = producto.precio;
            document.getElementById('editar_categoria_id').value = producto.categoria_id;
            
            // Mostrar imágenes actuales
            for (let i = 1; i <= 4; i++) {
                const imgField = `imagen${i}`;
                const previewDiv = document.getElementById(`current_image${i}`);
                const hiddenInput = document.getElementById(`imagen_actual${i}`);
                
                if (producto[imgField]) {
                    hiddenInput.value = producto[imgField];
                    previewDiv.innerHTML = `
                        <img src="../../imagenes/productos/${producto[imgField]}" 
                             class="modal-product__preview-image"
                             style="max-width: 100px; max-height: 80px;">
                        <div style="font-size: 12px; color: #666; text-align: center; margin-top: 5px;">Imagen actual</div>
                    `;
                } else {
                    previewDiv.innerHTML = '<div style="color: #999; font-size: 12px; text-align: center;">No hay imagen</div>';
                }
            }
            
            cerrarModal('modalVer');
            abrirModal('modalEditar');
        } else {
            // Si no hay producto, abrir modal de agregar
            cerrarModal('modalVer');
            abrirModal('modalAgregar');
        }
    }
    
    // Función para mostrar historial
    function mostrarHistorial(tipo) {
        if (!currentProducto) return;
        
        alert(`Mostrar historial de ${tipo} para el producto ${currentProducto.referencia}`);
        // Aquí puedes implementar la lógica para cargar y mostrar el historial
    }
    
    // Confirmar eliminación
    function confirmarEliminacion() {
        return confirm('¿Estás seguro de que deseas eliminar este producto?');
    }
    
    // Cerrar modal al hacer clic fuera del contenido
    window.onclick = function(event) {
        if (event.target.className === 'modal-product__overlay') {
            const modalId = event.target.parentElement.id;
            cerrarModal(modalId);
        }
    };
    </script>
    
    <?php include '../../layout/footer.php'; ?>
</body>
</html>