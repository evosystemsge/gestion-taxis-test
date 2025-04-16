<?php
include '../../layout/header.php';
require '../../config/database.php';

// ==============================
// LÓGICA DE ACCIONES: Agregar / Editar / Eliminar
// ==============================
if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion == 'agregar') {
        // Iniciar transacción para asegurar la integridad de los datos
        $pdo->beginTransaction();
        
        try {
            // Insertar el mantenimiento principal
            $stmt = $pdo->prepare("INSERT INTO mantenimientos 
                (vehiculo_id, conductor_id, fecha, tipo, descripcion, kilometraje_actual, 
                 km_proximo_mantenimiento, estado_antes, estado_despues, taller, 
                 metodo_pago, costo, afecta_caja, creado_en) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->execute([
                $_POST['vehiculo_id'] ?: null,
                $_POST['conductor_id'] ?: null,
                $_POST['fecha'],
                $_POST['tipo'],
                $_POST['descripcion'],
                $_POST['kilometraje_actual'],
                $_POST['km_proximo_mantenimiento'] ?: null,
                $_POST['estado_antes'],
                $_POST['estado_despues'],
                $_POST['taller'],
                $_POST['metodo_pago'],
                $_POST['costo'],
                $_POST['afecta_caja'] ?? 0
            ]);
            
            $mantenimientoId = $pdo->lastInsertId();
            
            // Procesar productos del mantenimiento
            if (!empty($_POST['productos'])) {
                foreach ($_POST['productos'] as $producto) {
                    if (!empty($producto['producto_id']) && $producto['cantidad'] > 0) {
                        // Insertar en mantenimiento_productos
                        $stmtProducto = $pdo->prepare("INSERT INTO mantenimiento_productos 
                            (mantenimiento_id, producto_id, cantidad, precio_unitario) 
                            VALUES (?, ?, ?, ?)");
                        
                        $stmtProducto->execute([
                            $mantenimientoId,
                            $producto['producto_id'],
                            $producto['cantidad'],
                            $producto['precio_unitario']
                        ]);
                        
                        // Descontar del stock si el producto ya estaba en inventario
                        if ($producto['precio_unitario'] == 0) {
                            $stmtUpdateStock = $pdo->prepare("UPDATE productos 
                                SET stock = stock - ? 
                                WHERE id = ?");
                            $stmtUpdateStock->execute([
                                $producto['cantidad'],
                                $producto['producto_id']
                            ]);
                        }
                    }
                }
            }
            
            // Descontar de la caja si afecta_caja es true
            if ($_POST['afecta_caja'] == 1) {
                $stmtCaja = $pdo->prepare("UPDATE cajas 
                    SET saldo_actual = saldo_actual - ? 
                    WHERE predeterminada = 1");
                $stmtCaja->execute([$_POST['costo']]);
            }
            
            // Actualizar km_aceite del vehículo si es un cambio de aceite
            if ($_POST['tipo'] == 'cambio_aceite' && !empty($_POST['km_proximo_mantenimiento'])) {
                $stmtVehiculo = $pdo->prepare("UPDATE vehiculos 
                    SET km_aceite = ? 
                    WHERE id = ?");
                $stmtVehiculo->execute([
                    $_POST['km_proximo_mantenimiento'],
                    $_POST['vehiculo_id']
                ]);
            }
            
            $pdo->commit();
            header("Location: mantenimientos.php");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<script>alert('Error al registrar el mantenimiento: " . addslashes($e->getMessage()) . "'); window.location.href='mantenimientos.php';</script>";
        }

    } elseif ($accion == 'eliminar') {
        $id = $_POST['id'];
        
        // Primero verificar si se puede eliminar (dependiendo de tus reglas de negocio)
        // Luego eliminar los productos asociados
        $pdo->beginTransaction();
        
        try {
            // Eliminar productos asociados
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

    } elseif ($accion == 'editar') {
        // Similar a agregar pero con UPDATE en lugar de INSERT
        // Implementar según necesidades específicas
    }
}

// Obtener todos los mantenimientos con información relacionada
$mantenimientos = $pdo->query("
    SELECT m.*, 
           v.marca AS vehiculo_marca, v.modelo AS vehiculo_modelo, v.matricula AS vehiculo_matricula,
           c.nombre AS conductor_nombre, c.dip AS conductor_dip
    FROM mantenimientos m
    LEFT JOIN vehiculos v ON m.vehiculo_id = v.id
    LEFT JOIN conductores c ON m.conductor_id = c.id
    ORDER BY m.fecha DESC
")->fetchAll();

// Obtener vehículos para select
$vehiculos = $pdo->query("SELECT id, marca, modelo, matricula FROM vehiculos ORDER BY marca")->fetchAll();

// Obtener conductores para select
$conductores = $pdo->query("SELECT id, nombre, dip FROM conductores ORDER BY nombre")->fetchAll();

// Obtener productos para select
$productos = $pdo->query("SELECT id, nombre, stock, precio FROM productos ORDER BY nombre")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Mantenimientos</title>
    <style>
    /* ESTILOS COPIADOS DE PRODUCTOS.PHP */
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
    
    /* Tabla de productos en mantenimiento */
    .products-table {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
    }
    
    .products-table th {
        background-color: var(--color-primario);
        color: white;
        padding: 10px;
        text-align: left;
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
        border: none;
        border-radius: 4px;
        cursor: pointer;
        margin-bottom: 15px;
    }
    
    .btn-add-product:hover {
        background-color: #218838;
    }
    
    .btn-remove-product {
        background-color: var(--color-peligro);
        color: white;
        border: none;
        border-radius: 4px;
        padding: 5px 8px;
        cursor: pointer;
    }
    
    .btn-remove-product:hover {
        background-color: #c82333;
    }
    
    .total-section {
        display: flex;
        justify-content: flex-end;
        margin-top: 15px;
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 6px;
        font-weight: bold;
        font-size: 1.1rem;
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
                <?php foreach ($vehiculos as $vehiculo): ?>
                    <option value="<?= $vehiculo['id'] ?>">
                        <?= htmlspecialchars($vehiculo['marca']) ?> <?= htmlspecialchars($vehiculo['modelo']) ?> - <?= htmlspecialchars($vehiculo['matricula']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select id="filterTipo" class="modal-maintenance__form-input">
                <option value="">Todos los tipos</option>
                <option value="preventivo">Preventivo</option>
                <option value="reparacion">Reparación</option>
                <option value="incidencia">Incidencia</option>
                <option value="cambio_aceite">Cambio de aceite</option>
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
                        <tr data-vehiculo="<?= $mant['vehiculo_id'] ?: '' ?>" data-tipo="<?= $mant['tipo'] ?>">
                            <td><?= $mant['id'] ?></td>
                            <td><?= htmlspecialchars($mant['fecha']) ?></td>
                            <td>
                                <?php if ($mant['vehiculo_id']): ?>
                                    <?= htmlspecialchars($mant['vehiculo_marca']) ?> <?= htmlspecialchars($mant['vehiculo_modelo']) ?> - <?= htmlspecialchars($mant['vehiculo_matricula']) ?>
                                <?php else: ?>
                                    No asignado
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($mant['conductor_id']): ?>
                                    <?= htmlspecialchars($mant['conductor_nombre']) ?> (<?= htmlspecialchars($mant['conductor_dip']) ?>)
                                <?php else: ?>
                                    No asignado
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(ucfirst($mant['tipo'])) ?></td>
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
                                    <button class="btn btn-editar" onclick="editarMantenimiento(<?= htmlspecialchars(json_encode($mant), ENT_QUOTES, 'UTF-8') ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
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
                <form method="post" class="modal-maintenance__form" id="modalAgregarForm">
                    <div class="modal-maintenance__form-row">
                        <div class="modal-maintenance__form-group">
                            <label for="fecha" class="modal-maintenance__form-label">Fecha</label>
                            <input type="date" name="fecha" class="modal-maintenance__form-input" required>
                        </div>
                        
                        <div class="modal-maintenance__form-group">
                            <label for="tipo" class="modal-maintenance__form-label">Tipo</label>
                            <select name="tipo" class="modal-maintenance__form-input" required>
                                <option value="">Seleccione un tipo</option>
                                <option value="preventivo">Preventivo</option>
                                <option value="reparacion">Reparación</option>
                                <option value="incidencia">Incidencia</option>
                                <option value="cambio_aceite">Cambio de aceite</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-maintenance__form-row">
                        <div class="modal-maintenance__form-group">
                            <label for="vehiculo_id" class="modal-maintenance__form-label">Vehículo</label>
                            <select name="vehiculo_id" class="modal-maintenance__form-input" required>
                                <option value="">Seleccione un vehículo</option>
                                <?php foreach ($vehiculos as $vehiculo): ?>
                                    <option value="<?= $vehiculo['id'] ?>">
                                        <?= htmlspecialchars($vehiculo['marca']) ?> <?= htmlspecialchars($vehiculo['modelo']) ?> - <?= htmlspecialchars($vehiculo['matricula']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="modal-maintenance__form-group">
                            <label for="conductor_id" class="modal-maintenance__form-label">Conductor (opcional)</label>
                            <select name="conductor_id" class="modal-maintenance__form-input">
                                <option value="">Seleccione un conductor</option>
                                <?php foreach ($conductores as $conductor): ?>
                                    <option value="<?= $conductor['id'] ?>">
                                        <?= htmlspecialchars($conductor['nombre']) ?> (<?= htmlspecialchars($conductor['dip']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-maintenance__form-row">
                        <div class="modal-maintenance__form-group">
                            <label for="kilometraje_actual" class="modal-maintenance__form-label">Kilometraje Actual</label>
                            <input type="number" name="kilometraje_actual" class="modal-maintenance__form-input" required>
                        </div>
                        
                        <div class="modal-maintenance__form-group">
                            <label for="km_proximo_mantenimiento" class="modal-maintenance__form-label">Próximo Mantenimiento (km)</label>
                            <input type="number" name="km_proximo_mantenimiento" class="modal-maintenance__form-input">
                        </div>
                    </div>
                    
                    <div class="modal-maintenance__form-group">
                        <label for="descripcion" class="modal-maintenance__form-label">Descripción</label>
                        <textarea name="descripcion" class="modal-maintenance__form-input" rows="3" required></textarea>
                    </div>
                    
                    <div class="modal-maintenance__form-row">
                        <div class="modal-maintenance__form-group">
                            <label for="estado_antes" class="modal-maintenance__form-label">Estado Antes</label>
                            <textarea name="estado_antes" class="modal-maintenance__form-input" rows="2" required></textarea>
                        </div>
                        
                        <div class="modal-maintenance__form-group">
                            <label for="estado_despues" class="modal-maintenance__form-label">Estado Después</label>
                            <textarea name="estado_despues" class="modal-maintenance__form-input" rows="2" required></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-maintenance__form-row">
                        <div class="modal-maintenance__form-group">
                            <label for="taller" class="modal-maintenance__form-label">Taller</label>
                            <input type="text" name="taller" class="modal-maintenance__form-input" required>
                        </div>
                        
                        <div class="modal-maintenance__form-group">
                            <label for="metodo_pago" class="modal-maintenance__form-label">Método de Pago</label>
                            <select name="metodo_pago" class="modal-maintenance__form-input" required>
                                <option value="">Seleccione método</option>
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>
                    
                    <h4>Productos Utilizados</h4>
                    <button type="button" class="btn-add-product" onclick="agregarProducto()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Agregar Producto
                    </button>
                    
                    <table class="products-table" id="productosTable">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio Unitario</th>
                                <th>Subtotal</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="productosBody">
                            <!-- Filas de productos se agregarán aquí dinámicamente -->
                        </tbody>
                    </table>
                    
                    <div class="modal-maintenance__form-row">
                        <div class="modal-maintenance__form-group">
                            <label for="costo" class="modal-maintenance__form-label">Costo Total (Mano de Obra)</label>
                            <input type="number" name="costo" id="costo" class="modal-maintenance__form-input" required>
                        </div>
                        
                        <div class="modal-maintenance__form-group">
                            <label for="afecta_caja" class="modal-maintenance__form-label">Afecta a Caja</label>
                            <select name="afecta_caja" class="modal-maintenance__form-input" required>
                                <option value="1">Sí</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="total-section">
                        <span>Total General: </span>
                        <span id="totalGeneral">0 XAF</span>
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
                <button type="submit" name="accion" value="agregar" class="modal-maintenance__action-btn modal-maintenance__action-btn--primary" form="modalAgregarForm">
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
                <div class="modal-maintenance__header-content">
                    <h3 class="modal-maintenance__title">TIPO: <span id="mantenimientoTipo" class="modal-maintenance__title--highlight"></span></h3>
                    <div class="modal-maintenance__badge">
                        <span id="mantenimientoStatusBadge">Registrado</span>
                    </div>
                </div>
            </div>
            
            <div class="modal-maintenance__body">
                <div id="detalleMantenimiento" style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Contenido dinámico aquí -->
                </div>
                
                <!-- Sección de productos -->
                <div style="margin-top: 30px;">
                    <h4>Productos Utilizados</h4>
                    <div id="productosMantenimiento">
                        <!-- Lista de productos se cargará aquí -->
                    </div>
                </div>
            </div>
            
            <div class="modal-maintenance__footer">
                <button class="modal-maintenance__action-btn modal-maintenance__action-btn--primary" onclick="editarMantenimiento()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Editar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Mantenimiento -->
    <div id="modalEditar" class="modal-maintenance">
        <!-- Similar al modal de agregar pero con datos precargados -->
    </div>
    
    <script>
    // Variables globales
    let currentMantenimiento = null;
    let currentPage = 1;
    const rowsPerPage = 10;
    let filteredData = [];
    let productos = <?= json_encode($productos) ?>;
    let contadorProductos = 0;

    // Inicialización
    document.addEventListener('DOMContentLoaded', function() {
        // Configurar eventos
        initEventListeners();
        // Configurar paginación
        setupPagination();
        // Mostrar primera página
        updateTable();
        
        // Establecer fecha actual por defecto
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('input[name="fecha"]').value = today;
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
        
        // Actualizar total general cuando cambia el costo
        document.getElementById('costo')?.addEventListener('input', calcularTotalGeneral);
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
            const texto = row.textContent.toLowerCase();
            const vehiculoId = row.getAttribute('data-vehiculo');
            const tipo = row.getAttribute('data-tipo');
            
            const matchesSearch = texto.includes(searchTerm);
            const matchesVehiculo = !vehiculoFilter || vehiculoId === vehiculoFilter;
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
    }
    
    // Función para ver mantenimiento
    function verMantenimiento(mantenimiento) {
        currentMantenimiento = mantenimiento;
        
        // Configurar tipo
        document.getElementById('mantenimientoTipo').textContent = mantenimiento.tipo ? mantenimiento.tipo.charAt(0).toUpperCase() + mantenimiento.tipo.slice(1) : 'N/A';
        
        // Configurar estado (puedes personalizar según tus necesidades)
        const estado = 'Registrado'; // Puedes cambiar esto según tu lógica
        let badgeColor = '#10b981'; // Verde por defecto
        
        document.getElementById('mantenimientoStatusBadge').textContent = estado;
        document.getElementById('mantenimientoStatusBadge').style.background = badgeColor;
        
        // Datos para las columnas
        const columna1 = [
            { label: 'FECHA', value: mantenimiento.fecha || 'N/A' },
            { label: 'VEHÍCULO', value: mantenimiento.vehiculo_marca ? `${mantenimiento.vehiculo_marca} ${mantenimiento.vehiculo_modelo} - ${mantenimiento.vehiculo_matricula}` : 'No asignado' },
            { label: 'CONDUCTOR', value: mantenimiento.conductor_nombre ? `${mantenimiento.conductor_nombre} (${mantenimiento.conductor_dip})` : 'No asignado' },
            { label: 'KILOMETRAJE ACTUAL', value: (mantenimiento.kilometraje_actual || '0') + ' km' },
            { label: 'PRÓXIMO MANTENIMIENTO', value: mantenimiento.km_proximo_mantenimiento ? mantenimiento.km_proximo_mantenimiento + ' km' : 'No especificado' }
        ];
        
        const columna2 = [
            { label: 'TALLER', value: mantenimiento.taller || 'N/A' },
            { label: 'MÉTODO DE PAGO', value: mantenimiento.metodo_pago ? mantenimiento.metodo_pago.charAt(0).toUpperCase() + mantenimiento.metodo_pago.slice(1) : 'N/A' },
            { label: 'COSTO TOTAL', value: (mantenimiento.costo || '0') + ' XAF' },
            { label: 'ESTADO ANTES', value: mantenimiento.estado_antes || 'N/A' },
            { label: 'ESTADO DESPUÉS', value: mantenimiento.estado_despues || 'N/A' }
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
        
        // Cargar productos del mantenimiento (aquí deberías hacer una petición AJAX para obtener los productos reales)
        // Por ahora usaremos datos de ejemplo
        const productosHTML = `
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
                    <tr>
                        <td>Filtro de aceite</td>
                        <td>1</td>
                        <td>15,000 XAF</td>
                        <td>15,000 XAF</td>
                    </tr>
                    <tr>
                        <td>Aceite de motor</td>
                        <td>4</td>
                        <td>10,000 XAF</td>
                        <td>40,000 XAF</td>
                    </tr>
                </tbody>
            </table>
        `;
        
        document.getElementById('productosMantenimiento').innerHTML = productosHTML;
        
        // Mostrar modal
        abrirModal('modalVer');
    }
    
    // Función para editar mantenimiento
    function editarMantenimiento(mantenimiento = null) {
        if (!mantenimiento && currentMantenimiento) {
            mantenimiento = currentMantenimiento;
        }
        
        if (mantenimiento) {
            // Implementar lógica para precargar datos en el modal de edición
            // Similar a verMantenimiento pero con campos editables
            alert('Editar mantenimiento ' + mantenimiento.id);
        } else {
            // Si no hay mantenimiento, abrir modal de agregar
            cerrarModal('modalVer');
            abrirModal('modalAgregar');
        }
    }
    
    // Función para agregar producto al mantenimiento
    function agregarProducto() {
        contadorProductos++;
        const rowId = 'producto-' + contadorProductos;
        
        const selectProductos = productos.map(p => 
            `<option value="${p.id}" data-stock="${p.stock}" data-precio="${p.precio}">${p.nombre} (Stock: ${p.stock})</option>`
        ).join('');
        
        const nuevaFila = `
            <tr id="${rowId}">
                <td>
                    <select name="productos[${contadorProductos}][producto_id]" class="modal-maintenance__form-input producto-select" required>
                        <option value="">Seleccione un producto</option>
                        ${selectProductos}
                    </select>
                </td>
                <td>
                    <input type="number" name="productos[${contadorProductos}][cantidad]" class="modal-maintenance__form-input cantidad" min="1" value="1" required>
                </td>
                <td>
                    <input type="number" name="productos[${contadorProductos}][precio_unitario]" class="modal-maintenance__form-input precio" min="0" step="0.01" required>
                </td>
                <td class="subtotal">0 XAF</td>
                <td>
                    <button type="button" class="btn-remove-product" onclick="eliminarProducto('${rowId}')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </td>
            </tr>
        `;
        
        document.getElementById('productosBody').insertAdjacentHTML('beforeend', nuevaFila);
        
        // Agregar eventos a los nuevos campos
        const nuevaFilaElement = document.getElementById(rowId);
        nuevaFilaElement.querySelector('.producto-select').addEventListener('change', function() {
            const productoId = this.value;
            const producto = productos.find(p => p.id == productoId);
            
            if (producto) {
                const precioInput = this.closest('tr').querySelector('.precio');
                // Si hay stock, precio es 0 (ya estaba comprado)
                if (producto.stock > 0) {
                    precioInput.value = 0;
                    precioInput.readOnly = true;
                } else {
                    precioInput.value = producto.precio;
                    precioInput.readOnly = false;
                }
                calcularSubtotal(this.closest('tr'));
            }
        });
        
        nuevaFilaElement.querySelector('.cantidad').addEventListener('input', function() {
            calcularSubtotal(this.closest('tr'));
        });
        
        nuevaFilaElement.querySelector('.precio').addEventListener('input', function() {
            calcularSubtotal(this.closest('tr'));
        });
    }
    
    // Función para eliminar producto del mantenimiento
    function eliminarProducto(rowId) {
        document.getElementById(rowId).remove();
        calcularTotalGeneral();
    }
    
    // Función para calcular subtotal de un producto
    function calcularSubtotal(row) {
        const cantidad = parseFloat(row.querySelector('.cantidad').value) || 0;
        const precio = parseFloat(row.querySelector('.precio').value) || 0;
        const subtotal = cantidad * precio;
        
        row.querySelector('.subtotal').textContent = subtotal.toLocaleString('es-ES') + ' XAF';
        calcularTotalGeneral();
    }
    
    // Función para calcular el total general del mantenimiento
    function calcularTotalGeneral() {
        let totalProductos = 0;
        const filasProductos = document.querySelectorAll('#productosBody tr');
        
        filasProductos.forEach(fila => {
            const subtotal = parseFloat(fila.querySelector('.subtotal').textContent) || 0;
            totalProductos += subtotal;
        });
        
        const costoManoObra = parseFloat(document.getElementById('costo').value) || 0;
        const totalGeneral = totalProductos + costoManoObra;
        
        document.getElementById('totalGeneral').textContent = totalGeneral.toLocaleString('es-ES') + ' XAF';
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