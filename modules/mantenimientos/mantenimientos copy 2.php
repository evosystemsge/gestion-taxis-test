<?php
// Incluimos el encabezado y la conexión a la base de datos
include '../../layout/header.php';
require '../../config/database.php';

// Configuración de imágenes
$uploadDir = '../../imagenes/mantenimientos/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// ==============================
// LÓGICA DE ACCIONES: Agregar / Editar / Eliminar
// ==============================
if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion == 'agregar') {
        try {
            $pdo->beginTransaction();
            
            // Insertar el mantenimiento
            /*$stmt = $pdo->prepare("INSERT INTO mantenimientos (
                vehiculo_id, conductor_id, fecha, tipo, descripcion, 
                kilometraje_actual, km_proximo_mantenimiento, estado_antes, 
                estado_despues, taller, costo, afecta_caja
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");*/
            $stmt = $pdo->prepare("INSERT INTO mantenimientos (
                vehiculo_id, conductor_id, fecha, tipo, descripcion, 
                kilometraje_actual, km_proximo_mantenimiento, estado_antes, 
                estado_despues, taller, costo
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            /*$stmt->execute([
                $_POST['vehiculo_id'], $_POST['conductor_id'], $_POST['fecha'], 
                $_POST['tipo'], $_POST['descripcion'], $_POST['kilometraje_actual'],
                $_POST['km_proximo_mantenimiento'], $_POST['estado_antes'], 
                $_POST['estado_despues'], $_POST['taller'], $_POST['costo'],
                isset($_POST['afecta_caja']) ? 1 : 0
            ]);*/
            $stmt->execute([
                $_POST['vehiculo_id'], $_POST['conductor_id'], $_POST['fecha'], 
                $_POST['tipo'], $_POST['descripcion'], $_POST['kilometraje_actual'],
                $_POST['km_proximo_mantenimiento'], $_POST['estado_antes'], 
                $_POST['estado_despues'], $_POST['taller'], $_POST['costo']
            ]);
            
            $mantenimientoId = $pdo->lastInsertId();
            
            // Procesar productos del mantenimiento
            $productos = json_decode($_POST['productos_json'], true);
            $costoProductos = 0;
            
            foreach ($productos as $producto) {
                // Insertar producto en mantenimiento_productos
                $stmt = $pdo->prepare("INSERT INTO mantenimiento_productos (
                    mantenimiento_id, producto_id, cantidad, precio_unitario
                ) VALUES (?, ?, ?, ?)");
                
                $stmt->execute([
                    $mantenimientoId, $producto['id'], $producto['cantidad'], $producto['precio']
                ]);
                
                // Descontar del stock si el producto ya estaba en inventario (precio = 0)
                if ($producto['precio'] == 0) {
                    $stmt = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
                    $stmt->execute([$producto['cantidad'], $producto['id']]);
                }
                
                $costoProductos += ($producto['precio'] * $producto['cantidad']);
            }
            
            // Actualizar costo total si hay productos
            if ($costoProductos > 0) {
                $costoTotal = $_POST['costo'] + $costoProductos;
                $stmt = $pdo->prepare("UPDATE mantenimientos SET costo = ? WHERE id = ?");
                $stmt->execute([$costoTotal, $mantenimientoId]);
            }
            
            // Si es cambio de aceite, actualizar km_aceite del vehículo
            if ($_POST['tipo'] == 'preventivo' && !empty($_POST['km_proximo_mantenimiento'])) {
                $stmt = $pdo->prepare("UPDATE vehiculos SET km_aceite = ? WHERE id = ?");
                $stmt->execute([$_POST['km_proximo_mantenimiento'], $_POST['vehiculo_id']]);
            }
            
            // Si afecta a caja, descontar de la caja predeterminada
            if (isset($_POST['afecta_caja'])) {
                $stmt = $pdo->prepare("UPDATE cajas SET saldo_actual = saldo_actual - ? WHERE predeterminada = 1");
                $stmt->execute([$costoTotal ?? $_POST['costo']]);
            }
            
            // Procesar imágenes/facturas
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
            
            // Actualizar el mantenimiento con las rutas de las imágenes
            if (!empty($updateFields)) {
                $sql = "UPDATE mantenimientos SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $updateValues[] = $mantenimientoId;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($updateValues);
            }
            
            $pdo->commit();
            header("Location: mantenimientos.php");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<script>alert('Error al registrar el mantenimiento: " . addslashes($e->getMessage()) . "'); window.location.href='mantenimientos.php';</script>";
        }

    } elseif ($accion == 'eliminar') {
        // Eliminar mantenimiento
        $id = $_POST['id'];
        
        try {
            $pdo->beginTransaction();
            
            // Primero eliminar las imágenes asociadas
            $stmt = $pdo->prepare("SELECT imagen1, imagen2, imagen3, imagen4 FROM mantenimientos WHERE id = ?");
            $stmt->execute([$id]);
            $mantenimiento = $stmt->fetch();
            
            for ($i = 1; $i <= 4; $i++) {
                $imgField = "imagen$i";
                if (!empty($mantenimiento[$imgField])) {
                    $filePath = $uploadDir . $mantenimiento[$imgField];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }
            
            // Eliminar los productos asociados
            $stmt = $pdo->prepare("DELETE FROM mantenimiento_productos WHERE mantenimiento_id = ?");
            $stmt->execute([$id]);
            
            // Eliminar el mantenimiento
            $stmt = $pdo->prepare("DELETE FROM mantenimientos WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            echo "<script>alert('Mantenimiento eliminado correctamente.'); window.location.href='mantenimientos.php';</script>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<script>alert('Error al eliminar el mantenimiento: " . addslashes($e->getMessage()) . "'); window.location.href='mantenimientos.php';</script>";
        }
    }
}

// Obtener todos los mantenimientos con información de vehículo y conductor
$mantenimientos = $pdo->query("
    SELECT m.*, v.marca, v.modelo, v.matricula, c.nombre AS conductor_nombre 
    FROM mantenimientos m
    LEFT JOIN vehiculos v ON m.vehiculo_id = v.id
    LEFT JOIN conductores c ON m.conductor_id = c.id
    ORDER BY m.fecha DESC
")->fetchAll();

// Obtener vehículos para filtros y formularios
$vehiculos = $pdo->query("SELECT id, marca, modelo, matricula FROM vehiculos ORDER BY marca, modelo")->fetchAll();

// Obtener conductores para formularios
$conductores = $pdo->query("SELECT id, nombre FROM conductores ORDER BY nombre")->fetchAll();

// Obtener productos para formularios
$productos = $pdo->query("SELECT id, referencia, nombre, stock, precio FROM productos ORDER BY nombre")->fetchAll();

// Obtener cajas
$cajas = $pdo->query("SELECT id, nombre FROM cajas WHERE predeterminada = 1")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Mantenimientos</title>
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
    .modal-maintenance {
        display: none;
        position: fixed;
        z-index: 9999;
        inset: 0;
        font-family: 'Inter', sans-serif;
    }
    
    .modal-maintenance__overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
    }
    
    .modal-maintenance__container {
        position: relative;
        background: white;
        width: 900px;
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
    
    .modal-maintenance__close {
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
    
    .modal-maintenance__close:hover {
        background: #f1f5f9;
        transform: rotate(90deg);
    }
    
    .modal-maintenance__close svg {
        width: 18px;
        height: 18px;
        color: #64748b;
    }
    
    .modal-maintenance__header {
        padding: 24px 24px 16px;
        border-bottom: 1px solid var(--color-borde);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .modal-maintenance__header-content {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .modal-maintenance__badge {
        margin-left: auto;
    }
    
    .modal-maintenance__badge span {
        display: inline-block;
        padding: 4px 20px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        background: #10b981;
        color: white;
    }
    
    .modal-maintenance__title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }
    
    .modal-maintenance__title--highlight {
        color: var(--color-primario);
        font-weight: 600;
    }
    
    .modal-maintenance__body {
        padding: 0 24px;
        overflow-y: auto;
        flex-grow: 1;
    }
    
    .modal-maintenance__form {
        display: flex;
        flex-direction: column;
        gap: 16px;
        padding: 16px 0;
    }
    
    .modal-maintenance__form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .modal-maintenance__form-row {
        display: flex;
        gap: 16px;
    }
    
    .modal-maintenance__form-row .modal-maintenance__form-group {
        flex: 1;
    }
    
    .modal-maintenance__form-label {
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 600;
    }
    
    .modal-maintenance__form-input {
        padding: 10px 12px;
        border: 1px solid var(--color-borde);
        border-radius: 6px;
        font-size: 0.95rem;
        transition: border-color 0.2s ease;
        width: 100%;
    }
    
    .modal-maintenance__form-input:focus {
        outline: none;
        border-color: var(--color-primario);
        box-shadow: 0 0 0 3px rgba(0, 75, 135, 0.1);
    }
    
    .modal-maintenance__file-input {
        padding: 8px;
        border: 1px solid var(--color-borde);
        border-radius: 6px;
        font-size: 0.9rem;
        width: 100%;
    }
    
    .modal-maintenance__image-preview {
        margin-top: 8px;
        min-height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 5px;
    }
    
    .modal-maintenance__preview-image {
        max-width: 100%;
        max-height: 100px;
        border-radius: 4px;
        border: 1px solid var(--color-borde);
    }
    
    .modal-maintenance__footer {
        padding: 16px 24px;
        border-top: 1px solid var(--color-borde);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .modal-maintenance__action-btn {
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
    
    .modal-maintenance__action-btn:hover {
        background: #f1f5f9;
        transform: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    
    .modal-maintenance__action-btn svg {
        width: 14px;
        height: 14px;
    }
    
    .modal-maintenance__action-btn--primary {
        background: var(--color-primario);
        border-color: var(--color-primario);
        color: white;
    }
    
    .modal-maintenance__action-btn--primary:hover {
        background: var(--color-secundario);
    }
    
    /* ============ TABLA DE DETALLES ============ */
    .modal-maintenance__data-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid var(--color-borde);
        border-radius: 8px;
        overflow: hidden;
    }
    
    .modal-maintenance__data-table tr:not(:last-child) {
        border-bottom: 1px solid var(--color-borde);
    }
    
    .modal-maintenance__data-label {
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
    
    .modal-maintenance__data-value {
        padding: 12px 16px;
        font-size: 1rem;
        color: #1e293b;
        font-weight: 500;
        background-color: white;
    }
    
    /* ============ CARRUSEL DE IMÁGENES ============ */
    .maintenance-carousel {
        position: relative;
        width: 100%;
        height: 200px;
        overflow: hidden;
        margin-bottom: 20px;
        border-radius: 8px;
        background: #f1f5f9;
    }
    
    .maintenance-carousel__images {
        display: flex;
        height: 100%;
        transition: transform 0.3s ease;
    }
    
    .maintenance-carousel__image {
        min-width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .maintenance-carousel__nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 100%;
        display: flex;
        justify-content: space-between;
        padding: 0 10px;
        z-index: 2;
    }
    
    .maintenance-carousel__btn {
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
    
    .maintenance-carousel__btn svg {
        width: 20px;
        height: 20px;
        color: #1e293b;
    }
    
    .maintenance-carousel__btn:hover {
        background: white;
    }
    
    .maintenance-carousel__counter {
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
    
    /* ============ TABLA DE PRODUCTOS ============ */
    .products-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
    }
    
    .products-table th {
        background-color: #f1f5f9;
        padding: 10px;
        text-align: left;
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 600;
    }
    
    .products-table td {
        padding: 10px;
        border-bottom: 1px solid var(--color-borde);
    }
    
    .products-table input {
        width: 100%;
        padding: 8px;
        border: 1px solid var(--color-borde);
        border-radius: 4px;
    }
    
    .btn-add-product {
        background-color: var(--color-exito);
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        margin-bottom: 10px;
    }
    
    .btn-add-product:hover {
        background-color: #218838;
    }
    
    .btn-remove-product {
        background-color: var(--color-peligro);
        color: white;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    
    .btn-remove-product:hover {
        background-color: #c82333;
    }
    
    .total-cost {
        font-size: 1.1rem;
        font-weight: 600;
        text-align: right;
        margin-top: 10px;
        padding: 10px;
        background-color: #f8fafc;
        border-radius: 6px;
    }
    
    /* ============ RESPONSIVE ============ */
    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }
        
        .modal-maintenance__container {
            max-width: 98%;
            max-height: 98vh;
        }
        
        .modal-maintenance__form-row {
            flex-direction: column;
            gap: 16px;
        }
        
        .table-controls input, 
        .table-controls select {
            max-width: 100%;
        }
        
        .modal-maintenance__footer {
            flex-direction: column;
        }
        
        .modal-maintenance__action-btn {
            width: 100%;
            justify-content: center;
        }
        
        .maintenance-carousel {
            height: 150px;
        }
        
        .products-table {
            font-size: 0.85rem;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <h2>Lista de Mantenimientos</h2>
        
        <!-- Controles de tabla -->
        <div class="table-controls">
            <input type="text" id="searchInput" placeholder="Buscar mantenimiento..." class="modal-maintenance__form-input">
            <select id="filterVehiculo" class="modal-maintenance__form-input">
                <option value="">Todos los vehículos</option>
                <?php foreach ($vehiculos as $veh): ?>
                    <option value="<?= $veh['id'] ?>"><?= htmlspecialchars($veh['marca'] . ' ' . $veh['modelo'] . ' (' . $veh['matricula'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filterTipo" class="modal-maintenance__form-input">
                <option value="">Todos los tipos</option>
                <option value="preventivo">Preventivo</option>
                <option value="reparacion">Reparación</option>
                <option value="incidencia">Incidencia</option>
            </select>
            <button id="openModalAgregar" class="btn btn-nuevo">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Nuevo Mantenimiento
            </button>
        </div>
        
        <!-- Tabla de mantenimientos -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Vehículo</th>
                        <th>Conductor</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Kilometraje</th>
                        <th>Costo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($mantenimientos as $mant): ?>
                        <tr data-vehiculo="<?= $mant['vehiculo_id'] ?>" data-tipo="<?= $mant['tipo'] ?>">
                            <td><?= $mant['id'] ?></td>
                            <td><?= date('d/m/Y', strtotime($mant['fecha'])) ?></td>
                            <td><?= htmlspecialchars($mant['marca'] . ' ' . $mant['modelo'] . ' (' . $mant['matricula'] . ')') ?></td>
                            <td><?= htmlspecialchars($mant['conductor_nombre'] ?? 'No asignado') ?></td>
                            <td>
                                <?php 
                                    $tipoText = '';
                                    switch($mant['tipo']) {
                                        case 'preventivo': $tipoText = 'Preventivo'; break;
                                        case 'reparacion': $tipoText = 'Reparación'; break;
                                        case 'incidencia': $tipoText = 'Incidencia'; break;
                                        default: $tipoText = $mant['tipo'];
                                    }
                                    echo $tipoText;
                                ?>
                            </td>
                            <td><?= htmlspecialchars(mb_strimwidth($mant['descripcion'], 0, 50, '...')) ?></td>
                            <td><?= number_format($mant['kilometraje_actual'], 0, ',', '.') ?> km</td>
                            <td><?= number_format($mant['costo'], 0, ',', '.') ?> XAF</td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <button class="btn btn-ver" onclick="verMantenimiento(<?= htmlspecialchars(json_encode($mant), ENT_QUOTES, 'UTF-8') ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                    <form method="post" style="display:inline;" onsubmit="return confirmarEliminacion();">
                                        <input type="hidden" name="id" value="<?= $mant['id'] ?>">
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
        <button class="action-button" id="btnAddNew" title="Nuevo mantenimiento">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
        </button>
    </div>
    
    <!-- ============ MODALES ============ -->
    
    <!-- Modal Agregar Mantenimiento -->
    <div id="modalAgregar" class="modal-maintenance">
        <div class="modal-maintenance__overlay" onclick="cerrarModal('modalAgregar')"></div>
        <div class="modal-maintenance__container">
            <button class="modal-maintenance__close" onclick="cerrarModal('modalAgregar')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal-maintenance__header">
                <h3 class="modal-maintenance__title">Agregar Nuevo Mantenimiento</h3>
            </div>
            
            <div class="modal-maintenance__body">
                <form method="post" enctype="multipart/form-data" class="modal-maintenance__form" id="modalAgregarForm">
                    <input type="hidden" name="accion" value="agregar">
                    
                    <div class="modal-maintenance__form-row">
                        <div class="modal-maintenance__form-group">
                            <label for="vehiculo_id" class="modal-maintenance__form-label">Vehículo *</label>
                            <select name="vehiculo_id" id="vehiculo_id" class="modal-maintenance__form-input" required>
                                <option value="">Seleccione un vehículo</option>
                                <?php foreach ($vehiculos as $veh): ?>
                                    <option value="<?= $veh['id'] ?>"><?= htmlspecialchars($veh['marca'] . ' ' . $veh['modelo'] . ' (' . $veh['matricula'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="modal-maintenance__form-group">
                            <label for="conductor_id" class="modal-maintenance__form-label">Conductor</label>
                            <select name="conductor_id" id="conductor_id" class="modal-maintenance__form-input">
                                <option value="">No asignado</option>
                                <?php foreach ($conductores as $con): ?>
                                    <option value="<?= $con['id'] ?>"><?= htmlspecialchars($con['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-maintenance__form-row">
                        <div class="modal-maintenance__form-group">
                            <label for="fecha" class="modal-maintenance__form-label">Fecha *</label>
                            <input type="date" name="fecha" id="fecha" class="modal-maintenance__form-input" required value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="modal-maintenance__form-group">
                            <label for="tipo" class="modal-maintenance__form-label">Tipo de mantenimiento *</label>
                            <select name="tipo" id="tipo" class="modal-maintenance__form-input" required>
                                <option value="">Seleccione un tipo</option>
                                <option value="preventivo">Preventivo</option>
                                <option value="reparacion">Reparación</option>
                                <option value="incidencia">Incidencia</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-maintenance__form-row">
                        <div class="modal-maintenance__form-group">
                            <label for="kilometraje_actual" class="modal-maintenance__form-label">Kilometraje actual *</label>
                            <input type="number" name="kilometraje_actual" id="kilometraje_actual" class="modal-maintenance__form-input" required>
                        </div>
                        
                        <div class="modal-maintenance__form-group">
                            <label for="km_proximo_mantenimiento" class="modal-maintenance__form-label">Próximo mantenimiento (km)</label>
                            <input type="number" name="km_proximo_mantenimiento" id="km_proximo_mantenimiento" class="modal-maintenance__form-input">
                        </div>
                    </div>
                    
                    <div class="modal-maintenance__form-group">
                        <label for="descripcion" class="modal-maintenance__form-label">Descripción *</label>
                        <textarea name="descripcion" id="descripcion" class="modal-maintenance__form-input" rows="3" required></textarea>
                    </div>
                    
                    <div class="modal-maintenance__form-row">
                        <div class="modal-maintenance__form-group">
                            <label for="estado_antes" class="modal-maintenance__form-label">Estado antes</label>
                            <textarea name="estado_antes" id="estado_antes" class="modal-maintenance__form-input" rows="2"></textarea>
                        </div>
                        
                        <div class="modal-maintenance__form-group">
                            <label for="estado_despues" class="modal-maintenance__form-label">Estado después</label>
                            <textarea name="estado_despues" id="estado_despues" class="modal-maintenance__form-input" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-maintenance__form-row">
                        <div class="modal-maintenance__form-group">
                            <label for="taller" class="modal-maintenance__form-label">Taller</label>
                            <input type="text" name="taller" id="taller" class="modal-maintenance__form-input">
                        </div>
                        
                        <div class="modal-maintenance__form-group">
                            <label for="costo" class="modal-maintenance__form-label">Costo mano de obra (XAF)</label>
                            <input type="number" name="costo" id="costo" class="modal-maintenance__form-input" value="0" min="0">
                        </div>
                    </div>
                    
                    <div class="modal-maintenance__form-group">
                        <label>
                            <input type="checkbox" name="afecta_caja" id="afecta_caja" checked>
                            Descontar de caja predeterminada
                        </label>
                    </div>
                    
                    <!-- Productos del mantenimiento -->
                    <div class="modal-maintenance__form-group">
                        <label class="modal-maintenance__form-label">Productos utilizados</label>
                        
                        <button type="button" class="btn-add-product" onclick="agregarProducto()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Agregar Producto
                        </button>
                        
                        <div class="table-container">
                            <table class="products-table" id="productosTable">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Precio (XAF)</th>
                                        <th>Subtotal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="productosBody">
                                    <!-- Filas de productos se agregarán aquí -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="total-cost">
                            <span id="totalCosto">Total: 0 XAF</span>
                        </div>
                        
                        <input type="hidden" name="productos_json" id="productos_json">
                    </div>
                    
                    <!-- Facturas/imágenes -->
                    <div class="modal-maintenance__form-group">
                        <label class="modal-maintenance__form-label">Facturas/Imágenes</label>
                        <div class="modal-maintenance__image-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                <div class="modal-maintenance__image-container">
                                    <label for="imagen<?= $i ?>" class="modal-maintenance__form-label">Imagen <?= $i ?></label>
                                    <input type="file" name="imagen<?= $i ?>" id="imagen<?= $i ?>" class="modal-maintenance__file-input" accept="image/*">
                                    <div class="modal-maintenance__image-preview" id="preview<?= $i ?>" style="display: none;">
                                        <img id="previewImg<?= $i ?>" src="#" alt="Vista previa" class="modal-maintenance__preview-image">
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-maintenance__footer">
                <button type="button" class="modal-maintenance__action-btn" onclick="cerrarModal('modalAgregar')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar
                </button>
                <button type="submit" class="modal-maintenance__action-btn modal-maintenance__action-btn--primary" form="modalAgregarForm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Guardar Mantenimiento
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Ver Mantenimiento -->
    <div id="modalVer" class="modal-maintenance">
        <div class="modal-maintenance__overlay" onclick="cerrarModal('modalVer')"></div>
        <div class="modal-maintenance__container">
            <button class="modal-maintenance__close" onclick="cerrarModal('modalVer')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal-maintenance__header">
                <div class="maintenance-carousel">
                    <!-- Contenido del carrusel de imágenes se insertará aquí -->
                </div>
                
                <div class="modal-maintenance__header-content">
                    <h3 class="modal-maintenance__title">CONDUCTOR: <span id="nombreConductor" class="modal-maintenance__title--highlight">No asignado</span></h3>
                    <div class="modal-maintenance__badge">
                        <span id="maintenanceStatusBadge">Activo</span>
                    </div>
                </div>
            </div>
            
            <div class="modal-maintenance__body">
                <div id="detalleMantenimiento" style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Contenido dinámico aquí -->
                </div>
                
                <!-- Productos del mantenimiento -->
                <div style="margin-top: 30px;">
                    <h4 style="color: var(--color-primario); margin-bottom: 15px;">Productos utilizados</h4>
                    <div id="productosMantenimiento">
                        <!-- Contenido dinámico aquí -->
                    </div>
                </div>
            </div>
            
            <div class="modal-maintenance__footer">
                <button class="modal-maintenance__action-btn" onclick="mostrarHistorial('vehiculo')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    Historial del Vehículo
                </button>
            </div>
        </div>
    </div>
    
    <script>
    // Variables globales
    let currentMantenimiento = null;
    let currentPage = 1;
    const rowsPerPage = 10;
    let filteredData = [];
    let currentImageIndex = 0;
    let totalImages = 0;
    let productos = <?= json_encode($productos) ?>;
    let productosMantenimiento = [];
    let nextProductId = 1;

    // Inicialización
    document.addEventListener('DOMContentLoaded', function() {
        // Configurar eventos
        initEventListeners();
        // Configurar paginación
        setupPagination();
        // Mostrar primera página
        updateTable();
        
        // Establecer fecha actual por defecto
        document.getElementById('fecha').valueAsDate = new Date();
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
        document.getElementById('filterVehiculo').addEventListener('change', function() {
            currentPage = 1;
            updateTable();
        });
        
        document.getElementById('filterTipo').addEventListener('change', function() {
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
        
        // Previsualización de imágenes
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
        }
        
        // Actualizar total cuando cambia el costo o los productos
        document.getElementById('costo').addEventListener('input', actualizarTotal);
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
        const vehiculoFilter = document.getElementById('filterVehiculo').value;
        const tipoFilter = document.getElementById('filterTipo').value;
        
        const rows = document.querySelectorAll('#tableBody tr');
        filteredData = [];
        
        rows.forEach(row => {
            const vehiculo = row.getAttribute('data-vehiculo');
            const tipo = row.getAttribute('data-tipo');
            const texto = row.textContent.toLowerCase();
            
            const matchesSearch = texto.includes(searchTerm);
            const matchesVehiculo = !vehiculoFilter || vehiculo === vehiculoFilter;
            const matchesTipo = !tipoFilter || tipo === tipoFilter;
            
            if (matchesSearch && matchesVehiculo && matchesTipo) {
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
            const firstInput = document.querySelector(`#${modalId} .modal-maintenance__form-input`);
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
    
    // Función para ver mantenimiento
    function verMantenimiento(mantenimiento) {
        currentMantenimiento = mantenimiento;
        
        // Configurar conductor
        document.getElementById('nombreConductor').textContent = mantenimiento.conductor_nombre || 'No asignado';
        
        // Configurar estado del badge
        let estado = 'Completado';
        let badgeColor = '#10b981'; // Verde
        
        document.getElementById('maintenanceStatusBadge').textContent = estado;
        document.getElementById('maintenanceStatusBadge').style.background = badgeColor;
        
        // Generar HTML para el carrusel de imágenes
        let carouselHTML = `
            <div class="maintenance-carousel__images" id="maintenanceCarouselImages">
        `;
        
        let hasImages = false;
        for (let i = 1; i <= 4; i++) {
            const imgField = `imagen${i}`;
            if (mantenimiento[imgField]) {
                hasImages = true;
                carouselHTML += `
                    <img class="maintenance-carousel__image" 
                         src="../../imagenes/mantenimientos/${mantenimiento[imgField]}" 
                         alt="Mantenimiento ${i}">
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
            <div class="maintenance-carousel__nav">
                <button class="maintenance-carousel__btn" onclick="prevImage()">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 18l-6-6 6-6"></path>
                    </svg>
                </button>
                <button class="maintenance-carousel__btn" onclick="nextImage()">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 18l6-6-6-6"></path>
                    </svg>
                </button>
            </div>
            <div class="maintenance-carousel__counter" id="imageCounter">1/${hasImages ? document.querySelectorAll('.maintenance-carousel__image').length : '0'}</div>
            ` : ''}
        `;
        
        // Insertar el carrusel en el header
        const carouselContainer = document.querySelector('.modal-maintenance__header .maintenance-carousel');
        carouselContainer.innerHTML = carouselHTML;
        
        // Datos para las columnas
        const columna1 = [
            { label: 'VEHÍCULO', value: mantenimiento.marca + ' ' + mantenimiento.modelo + ' (' + mantenimiento.matricula + ')' },
            { label: 'FECHA', value: new Date(mantenimiento.fecha).toLocaleDateString() },
            { label: 'TIPO', value: mantenimiento.tipo === 'preventivo' ? 'Preventivo' : (mantenimiento.tipo === 'reparacion' ? 'Reparación' : 'Incidencia') },
            { label: 'KILOMETRAJE', value: (mantenimiento.kilometraje_actual || '0') + ' km' },
            { label: 'PRÓXIMO MANTENIMIENTO', value: mantenimiento.km_proximo_mantenimiento ? (mantenimiento.km_proximo_mantenimiento + ' km') : 'No especificado' }
        ];
        
        const columna2 = [
            { label: 'TALLER', value: mantenimiento.taller || 'No especificado' },
            { label: 'COSTO TOTAL', value: (mantenimiento.costo || '0') + ' XAF' },
            { label: 'ESTADO ANTES', value: mantenimiento.estado_antes || 'No especificado' },
            { label: 'ESTADO DESPUÉS', value: mantenimiento.estado_despues || 'No especificado' },
            { label: 'DESCRIPCIÓN', value: mantenimiento.descripcion || 'No especificado' }
        ];
        
        // Generar HTML para las tablas
        const html = `
            <div style="flex: 1; min-width: 300px;">
                <table class="modal-maintenance__data-table">
                    ${columna1.map(item => `
                        <tr>
                            <td class="modal-maintenance__data-label">${item.label}</td>
                            <td class="modal-maintenance__data-value">${item.value}</td>
                        </tr>
                    `).join('')}
                </table>
            </div>
            <div style="flex: 1; min-width: 300px;">
                <table class="modal-maintenance__data-table">
                    ${columna2.map(item => `
                        <tr>
                            <td class="modal-maintenance__data-label">${item.label}</td>
                            <td class="modal-maintenance__data-value">${item.value}</td>
                        </tr>
                    `).join('')}
                </table>
            </div>
        `;
        
        // Insertar todo el contenido
        document.getElementById('detalleMantenimiento').innerHTML = html;
        
        // Cargar productos del mantenimiento
        cargarProductosMantenimiento(mantenimiento.id);
        
        // Inicializar el carrusel si hay imágenes
        if (hasImages) {
            initCarousel();
        }
        
        // Mostrar modal
        abrirModal('modalVer');
    }
    
    // Cargar productos del mantenimiento
    function cargarProductosMantenimiento(mantenimientoId) {
        fetch(`/taxis/modules/api/get_mantenimiento_productos.php?mantenimiento_id=${mantenimientoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    let html = `
                        <div class="table-container">
                            <table class="products-table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Precio Unitario</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    data.forEach(producto => {
                        html += `
                            <tr>
                                <td>${producto.nombre}</td>
                                <td>${producto.cantidad}</td>
                                <td>${producto.precio_unitario.toLocaleString()} XAF</td>
                                <td>${(producto.cantidad * producto.precio_unitario).toLocaleString()} XAF</td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;
                    
                    document.getElementById('productosMantenimiento').innerHTML = html;
                } else {
                    document.getElementById('productosMantenimiento').innerHTML = '<p>No se utilizaron productos en este mantenimiento.</p>';
                }
            })
            .catch(error => {
                console.error('Error al cargar productos:', error);
                document.getElementById('productosMantenimiento').innerHTML = '<p>Error al cargar los productos.</p>';
            });
    }
    
    // Funciones para el carrusel
    function initCarousel() {
        const images = document.querySelectorAll('.maintenance-carousel__image');
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
        const carousel = document.getElementById('maintenanceCarouselImages');
        carousel.style.transform = `translateX(-${currentImageIndex * 100}%)`;
        updateCounter();
    }
    
    function updateCounter() {
        const counter = document.getElementById('imageCounter');
        if (counter) {
            counter.textContent = `${currentImageIndex + 1}/${totalImages}`;
        }
    }
    
    // Función para agregar producto al mantenimiento
    function agregarProducto() {
        const productosBody = document.getElementById('productosBody');
        const productId = nextProductId++;
        
        // Crear fila para el producto
        const row = document.createElement('tr');
        row.id = `producto-${productId}`;
        row.innerHTML = `
            <td>
                <select class="modal-maintenance__form-input producto-select" onchange="actualizarPrecioProducto(${productId})">
                    <option value="">Seleccione un producto</option>
                    ${productos.map(p => `<option value="${p.id}" data-stock="${p.stock}" data-precio="${p.precio}">${p.referencia} - ${p.nombre} (Stock: ${p.stock})</option>`).join('')}
                </select>
            </td>
            <td>
                <input type="number" class="modal-maintenance__form-input cantidad" min="1" value="1" onchange="actualizarSubtotal(${productId})">
            </td>
            <td>
                <input type="number" class="modal-maintenance__form-input precio" min="0" step="0.01" value="0" onchange="actualizarSubtotal(${productId})">
            </td>
            <td class="subtotal">0 XAF</td>
            <td>
                <button type="button" class="btn-remove-product" onclick="eliminarProducto(${productId})">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </td>
        `;
        
        productosBody.appendChild(row);
        actualizarProductosJSON();
    }
    
    // Función para eliminar producto del mantenimiento
    function eliminarProducto(id) {
        const row = document.getElementById(`producto-${id}`);
        if (row) {
            row.remove();
            actualizarProductosJSON();
        }
    }
    
    // Función para actualizar el precio del producto según su stock
    function actualizarPrecioProducto(id) {
        const row = document.getElementById(`producto-${id}`);
        if (row) {
            const select = row.querySelector('.producto-select');
            const precioInput = row.querySelector('.precio');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption && selectedOption.value) {
                const stock = parseInt(selectedOption.getAttribute('data-stock'));
                const precio = parseFloat(selectedOption.getAttribute('data-precio'));
                
                // Si hay stock, el precio es 0 (ya estaba en inventario)
                // Si no hay stock, se muestra el precio normal para comprarlo
                precioInput.value = stock > 0 ? 0 : precio;
                actualizarSubtotal(id);
            }
        }
    }
    
    // Función para actualizar el subtotal de un producto
    function actualizarSubtotal(id) {
        const row = document.getElementById(`producto-${id}`);
        if (row) {
            const cantidad = parseFloat(row.querySelector('.cantidad').value) || 0;
            const precio = parseFloat(row.querySelector('.precio').value) || 0;
            const subtotal = cantidad * precio;
            
            row.querySelector('.subtotal').textContent = subtotal.toLocaleString() + ' XAF';
            actualizarProductosJSON();
        }
    }
    
    // Función para actualizar el JSON de productos
    function actualizarProductosJSON() {
        const productosBody = document.getElementById('productosBody');
        const rows = productosBody.querySelectorAll('tr');
        const productosData = [];
        
        rows.forEach(row => {
            const select = row.querySelector('.producto-select');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption && selectedOption.value) {
                productosData.push({
                    id: selectedOption.value,
                    nombre: selectedOption.text,
                    cantidad: parseFloat(row.querySelector('.cantidad').value) || 0,
                    precio: parseFloat(row.querySelector('.precio').value) || 0
                });
            }
        });
        
        document.getElementById('productos_json').value = JSON.stringify(productosData);
        actualizarTotal();
    }
    
    // Función para actualizar el costo total
    function actualizarTotal() {
        const productosBody = document.getElementById('productosBody');
        const rows = productosBody.querySelectorAll('tr');
        let totalProductos = 0;
        
        rows.forEach(row => {
            const subtotalText = row.querySelector('.subtotal').textContent;
            const subtotal = parseFloat(subtotalText.replace(' XAF', '').replace(/\./g, '').replace(',', '.')) || 0;
            totalProductos += subtotal;
        });
        
        const costoManoObra = parseFloat(document.getElementById('costo').value) || 0;
        const total = totalProductos + costoManoObra;
        
        document.getElementById('totalCosto').textContent = `Total: ${total.toLocaleString()} XAF`;
    }
    
    // Función para mostrar historial
    function mostrarHistorial(tipo) {
        if (!currentMantenimiento) return;
        
        if (tipo === 'vehiculo') {
            window.location.href = `historial_mantenimientos.php?vehiculo_id=${currentMantenimiento.vehiculo_id}`;
        }
    }
    
    // Confirmar eliminación
    function confirmarEliminacion() {
        return confirm('¿Estás seguro de que deseas eliminar este mantenimiento?');
    }
    
    // Cerrar modal al hacer clic fuera del contenido
    window.onclick = function(event) {
        if (event.target.className === 'modal-maintenance__overlay') {
            const modalId = event.target.parentElement.id;
            cerrarModal(modalId);
        }
    };
    </script>
    
    <?php include '../../layout/footer.php'; ?>
</body>
</html>