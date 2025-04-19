<?php
//include '../../layout/header.php';
require '../../config/database.php';

// Obtener parámetros de filtro
$search = isset($_GET['search']) ? $_GET['search'] : '';
$tipo_filtro = isset($_GET['tipo_filtro']) ? $_GET['tipo_filtro'] : '';
$conductor_id = isset($_GET['conductor_id']) ? intval($_GET['conductor_id']) : null;
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : null;
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : null;
$monto_min = isset($_GET['monto_min']) ? floatval($_GET['monto_min']) : null;
$monto_max = isset($_GET['monto_max']) ? floatval($_GET['monto_max']) : null;

// Ordenación
$orden = isset($_GET['orden']) ? $_GET['orden'] : 'fecha_desc';
$orden_parts = explode('_', $orden);
$campo_orden = in_array($orden_parts[0], ['id', 'fecha', 'conductor_nombre', 'monto', 'tipo']) ? $orden_parts[0] : 'fecha';
$direccion_orden = isset($orden_parts[1]) && in_array($orden_parts[1], ['asc', 'desc']) ? $orden_parts[1] : 'desc';

// Paginación
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// Construir consulta base para pagos
$sql = "
    (SELECT 
        pp.id,
        pp.prestamo_id as registro_id,
        'prestamo' as tipo,
        p.conductor_id,
        c.nombre as conductor_nombre,
        pp.monto,
        pp.fecha,
        pp.descripcion,
        p.monto as monto_total,
        p.saldo_pendiente as saldo_actual,
        (SELECT COALESCE(SUM(pp2.monto), 0) FROM pagos_prestamos pp2 WHERE pp2.prestamo_id = p.id) as total_pagado
    FROM pagos_prestamos pp
    JOIN prestamos p ON pp.prestamo_id = p.id
    JOIN conductores c ON p.conductor_id = c.id
    WHERE 1=1
";

// Consulta para contar registros
$sql_count = "
    SELECT SUM(cnt) as total FROM (
        (SELECT COUNT(*) as cnt FROM pagos_prestamos pp
        JOIN prestamos p ON pp.prestamo_id = p.id
        JOIN conductores c ON p.conductor_id = c.id
        WHERE 1=1
";

// Aplicar filtros a pagos de préstamos
$params = [];
$params_count = [];

if ($search) {
    $sql .= " AND (c.nombre LIKE ? OR pp.descripcion LIKE ? OR pp.id LIKE ? OR p.id LIKE ?)";
    $sql_count .= " AND (c.nombre LIKE ? OR pp.descripcion LIKE ? OR pp.id LIKE ? OR p.id LIKE ?)";
    $searchTerm = "%$search%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    $params_count = array_merge($params_count, $params);
}

if ($tipo_filtro && $tipo_filtro !== 'prestamo') {
    $sql .= " AND 1=0"; // No mostrar pagos de préstamos si el filtro es solo "amonestacion"
    $sql_count .= " AND 1=0";
}

if ($conductor_id) {
    $sql .= " AND p.conductor_id = ?";
    $sql_count .= " AND p.conductor_id = ?";
    $params[] = $conductor_id;
    $params_count[] = $conductor_id;
}

if ($fecha_desde) {
    $sql .= " AND pp.fecha >= ?";
    $sql_count .= " AND pp.fecha >= ?";
    $params[] = $fecha_desde;
    $params_count[] = $fecha_desde;
}

if ($fecha_hasta) {
    $sql .= " AND pp.fecha <= ?";
    $sql_count .= " AND pp.fecha <= ?";
    $params[] = $fecha_hasta;
    $params_count[] = $fecha_hasta;
}

if ($monto_min) {
    $sql .= " AND pp.monto >= ?";
    $sql_count .= " AND pp.monto >= ?";
    $params[] = $monto_min;
    $params_count[] = $monto_min;
}

if ($monto_max) {
    $sql .= " AND pp.monto <= ?";
    $sql_count .= " AND pp.monto <= ?";
    $params[] = $monto_max;
    $params_count[] = $monto_max;
}

$sql .= ")
UNION ALL
(
    SELECT 
        pa.id,
        pa.amonestacion_id as registro_id,
        'amonestacion' as tipo,
        a.conductor_id,
        c.nombre as conductor_nombre,
        pa.monto,
        pa.fecha,
        pa.descripcion,
        a.monto as monto_total,
        a.saldo_pendiente as saldo_actual,
        (SELECT COALESCE(SUM(pa2.monto), 0) FROM pagos_amonestaciones pa2 WHERE pa2.amonestacion_id = a.id) as total_pagado
    FROM pagos_amonestaciones pa
    JOIN amonestaciones a ON pa.amonestacion_id = a.id
    JOIN conductores c ON a.conductor_id = c.id
    WHERE 1=1
";

$sql_count .= ")
        UNION ALL
        (SELECT COUNT(*) as cnt FROM pagos_amonestaciones pa
        JOIN amonestaciones a ON pa.amonestacion_id = a.id
        JOIN conductores c ON a.conductor_id = c.id
        WHERE 1=1
";

// Aplicar mismos filtros a pagos de amonestaciones
if ($search) {
    $sql .= " AND (c.nombre LIKE ? OR pa.descripcion LIKE ? OR pa.id LIKE ? OR a.id LIKE ?)";
    $sql_count .= " AND (c.nombre LIKE ? OR pa.descripcion LIKE ? OR pa.id LIKE ? OR a.id LIKE ?)";
    $searchTerm = "%$search%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    array_push($params_count, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}

if ($tipo_filtro && $tipo_filtro !== 'amonestacion') {
    $sql .= " AND 1=0"; // No mostrar amonestaciones si el filtro es solo "prestamo"
    $sql_count .= " AND 1=0";
}

if ($conductor_id) {
    $sql .= " AND a.conductor_id = ?";
    $sql_count .= " AND a.conductor_id = ?";
    $params[] = $conductor_id;
    $params_count[] = $conductor_id;
}

if ($fecha_desde) {
    $sql .= " AND pa.fecha >= ?";
    $sql_count .= " AND pa.fecha >= ?";
    $params[] = $fecha_desde;
    $params_count[] = $fecha_desde;
}

if ($fecha_hasta) {
    $sql .= " AND pa.fecha <= ?";
    $sql_count .= " AND pa.fecha <= ?";
    $params[] = $fecha_hasta;
    $params_count[] = $fecha_hasta;
}

if ($monto_min) {
    $sql .= " AND pa.monto >= ?";
    $sql_count .= " AND pa.monto >= ?";
    $params[] = $monto_min;
    $params_count[] = $monto_min;
}

if ($monto_max) {
    $sql .= " AND pa.monto <= ?";
    $sql_count .= " AND pa.monto <= ?";
    $params[] = $monto_max;
    $params_count[] = $monto_max;
}

$sql .= ") ORDER BY $campo_orden $direccion_orden LIMIT $offset, $por_pagina";
$sql_count .= ")
    ) as combined_counts";

// Preparar y ejecutar consultas
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll();

$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params_count);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $por_pagina);

// Obtener conductores activos para el filtro
$conductores = $pdo->query("
    SELECT c.id, c.nombre 
    FROM conductores c
    WHERE c.estado = 'activo'
    ORDER BY c.nombre
")->fetchAll();

// Procesar GET para eliminar pago
if (isset($_GET['eliminar'])) {
    $pago_id = intval($_GET['eliminar']);
    $tipo = $_GET['tipo']; // 'prestamo' o 'amonestacion'
    
    try {
        $pdo->beginTransaction();
        
        // Determinar tablas según tipo
        $tabla_pagos = ($tipo === 'prestamo') ? 'pagos_prestamos' : 'pagos_amonestaciones';
        $tabla_registro = ($tipo === 'prestamo') ? 'prestamos' : 'amonestaciones';
        $campo_id = ($tipo === 'prestamo') ? 'prestamo_id' : 'amonestacion_id';
        $campo_movimiento = ($tipo === 'prestamo') ? 'pago_prestamo_id' : 'pago_amonestacion_id';
        
        // 1. Obtener información del pago
        $pago = $pdo->query("SELECT * FROM $tabla_pagos WHERE id = $pago_id")->fetch();
        
        if (!$pago) {
            throw new Exception("Pago no encontrado");
        }
        
        // 2. Obtener información del registro asociado (préstamo/amonestación)
        $registro = $pdo->query("SELECT * FROM $tabla_registro WHERE id = {$pago[$campo_id]}")->fetch();
        
        if (!$registro) {
            throw new Exception(ucfirst($tipo) . " asociado no encontrado");
        }
        
        // 3. Obtener movimiento de caja asociado
        $movimiento = $pdo->query("SELECT * FROM movimientos_caja WHERE $campo_movimiento = $pago_id")->fetch();
        
        // 4. Revertir el pago en el registro principal
        $nuevo_saldo = $registro['saldo_pendiente'] + $pago['monto'];
        $estado = $nuevo_saldo > 0 ? 'pendiente' : 'pagado';
        
        $pdo->prepare("
            UPDATE $tabla_registro 
            SET saldo_pendiente = ?, estado = ?
            WHERE id = ?
        ")->execute([$nuevo_saldo, $estado, $pago[$campo_id]]);
        
        // 5. Revertir movimiento en caja si existe
        if ($movimiento) {
            // Obtener caja predeterminada
            $caja = $pdo->query("SELECT id, saldo_actual FROM cajas WHERE predeterminada = 1 LIMIT 1")->fetch();
            
            if (!$caja) {
                throw new Exception("No se encontró caja predeterminada");
            }
            
            // Restar el monto del pago de la caja
            $nuevo_saldo_caja = $caja['saldo_actual'] - $pago['monto'];
            $pdo->prepare("UPDATE cajas SET saldo_actual = ? WHERE id = ?")
                ->execute([$nuevo_saldo_caja, $caja['id']]);
            
            // Registrar movimiento de reversión
            $pdo->prepare("
                INSERT INTO movimientos_caja (caja_id, monto, tipo, descripcion, fecha)
                VALUES (?, ?, 'egreso', ?, ?)
            ")->execute([
                $caja['id'],
                $pago['monto'],
                "Reversión de pago de $tipo ID: {$pago[$campo_id]} (Pago ID: $pago_id)",
                date('Y-m-d')
            ]);
            
            // Eliminar movimiento original
            $pdo->prepare("DELETE FROM movimientos_caja WHERE id = ?")->execute([$movimiento['id']]);
        }
        
        // 6. Eliminar el pago
        $pdo->prepare("DELETE FROM $tabla_pagos WHERE id = ?")->execute([$pago_id]);
        
        $pdo->commit();
echo "<script>window.location.href='deudas.php?tab=historial&success=" . urlencode("Pago eliminado correctamente. Saldo actualizado.") . "';</script>";
exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al eliminar el pago: " . $e->getMessage();
        header("Location: historial_pagos.php?error=" . urlencode($error));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Pagos</title>
    <style>
    /* ============ ESTILOS PRINCIPALES ============ */
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
        --color-pagar: #20c997;
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f5f5f5;
        color: var(--color-texto);
        line-height: 1.6;
    }
    /*
    .container {
        max-width: 1250px;
        margin: 20px auto;
        background: #fff;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }*/
    .container {
        max-width: 1400px;
        margin: 0 auto; /* Cambiado para eliminar espacio superior */
        background: #fff;
        padding: 0 25px 25px 25px; /* Eliminado padding superior */
        border-radius: 0 0 10px 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    h1 {
        color: var(--color-primario);
        margin-top: 0px;
        padding-bottom: 0px;
        border-bottom: 1px solid #eee;
        font-size: 1.8rem;
        margin-bottom: 5px;
        text-align: center;
        font-weight: 600;
    }
    
    /* ============ FILTROS ============ */
    .filters-container {
        background: #f8f9fa;
        padding: 0px;
        border-radius: 8px;
        margin: 5px 0;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }
    
    .modal__form {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 15px;
        flex-grow: 1;
    }
    
    .modal__form-group {
        margin-bottom: 0;
    }
    
    .modal__form-label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #2c3e50;
        font-size: 14px;
    }
    
    .modal__form-input, .modal__form-input select {
        width: 100%;
        padding: 10px 18px;
        border: 1px solid var(--color-borde);
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
    }
    
    textarea.modal__form-input {
        min-height: 80px;
    }
    
    /* ============ BOTONES ============ */
    .btn {
        padding: 8px 16px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        font-weight: 500;
        height: 36px;
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
    
    .table th a {
        color: white;
        text-decoration: none;
        display: block;
    }
    
    .table td {
        background-color: #fff;
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }
    
    .table tr:hover td {
        background-color: rgba(0, 75, 135, 0.05);
    }
    
    /* ============ MODALES ============ */
    .modal {
        display: none;
        position: fixed;
        z-index: 9999;
        inset: 0;
        font-family: 'Inter', sans-serif;
    }
    
    .modal__overlay {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.4);
        backdrop-filter: blur(4px);
    }
    
    .modal__container {
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
    
    .modal__close {
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
    
    .modal__close:hover {
        background: #f1f5f9;
        transform: rotate(90deg);
    }
    
    .modal__close svg {
        width: 18px;
        height: 18px;
        color: #64748b;
    }
    
    .modal__header {
        padding: 24px 24px 16px;
        border-bottom: 1px solid var(--color-borde);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .modal__title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }
    
    .modal__body {
        padding: 20px;
        overflow-y: auto;
        flex-grow: 1;
    }
    
    .modal__footer {
        padding: 16px 24px;
        border-top: 1px solid var(--color-borde);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .modal__action-btn {
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
    
    .modal__action-btn:hover {
        background: #f1f5f9;
        transform: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    
    .modal__action-btn svg {
        width: 14px;
        height: 14px;
    }
    
    .modal__action-btn--primary {
        background: var(--color-primario);
        border-color: var(--color-primario);
        color: white;
    }
    
    .modal__action-btn--primary:hover {
        background: var(--color-secundario);
    }
    
    /* ============ PAGINACIÓN ============ */
    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 25px;
        gap: 8px;
        align-items: center;
    }
    
    .pagination a, .pagination span {
        padding: 8px 12px;
        border: 1px solid var(--color-borde);
        border-radius: 4px;
        text-decoration: none;
        color: var(--color-primario);
    }
    
    .pagination a:hover {
        background-color: #f1f1f1;
    }
    
    .pagination span.current {
        background-color: var(--color-primario);
        color: white;
        border-color: var(--color-primario);
    }
    
    /* ============ ALERTAS ============ */
    .alert {
        padding: 10px 15px;
        border-radius: 4px;
        margin-bottom: 15px;
    }
    
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    /* ============ TABLAS DE DATOS EN MODALES ============ */
    .modal__data-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid var(--color-borde);
        border-radius: 8px;
        overflow: hidden;
    }
    
    .modal__data-table tr:not(:last-child) {
        border-bottom: 1px solid var(--color-borde);
    }
    
    .modal__data-label {
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
    
    .modal__data-value {
        padding: 12px 16px;
        font-size: 1rem;
        color: #1e293b;
        font-weight: 500;
        background-color: white;
    }
    
    /* ============ RESPONSIVE ============ */
    @media (max-width: 768px) {
        .modal__form {
            grid-template-columns: 1fr;
        }
        
        .table th, .table td {
            padding: 8px 10px;
            font-size: 13px;
        }
        
        .btn {
            padding: 6px 10px;
            font-size: 12px;
        }
        
        .filters-container {
            flex-direction: column;
            align-items: stretch;
        }
    }
    </style>
</head>
<body>
    <!--<div class="container">
        <h1>Historial de Pagos</h1>-->
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>
        
        <!-- Filtros -->
        <div class="filters-container">
        <form id="filtersForm" method="get" action="deudas.php" class="modal__form">
        <input type="hidden" name="tab" value="historial"> <!-- o 'historial' en historial_pagos.php -->
                <div class="modal__form-group">
                    <label for="search" class="modal__form-label">Buscar</label>
                    <input type="text" name="search" id="search" class="modal__form-input" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Conductor, descripción o ID">
                </div>
                
                <div class="modal__form-group">
                    <label for="tipo_filtro" class="modal__form-label">Tipo</label>
                    <select name="tipo_filtro" id="tipo_filtro" class="modal__form-input">
                        <option value="">Todos</option>
                        <option value="prestamo" <?= $tipo_filtro === 'prestamo' ? 'selected' : '' ?>>Préstamo</option>
                        <option value="amonestacion" <?= $tipo_filtro === 'amonestacion' ? 'selected' : '' ?>>Amonestación</option>
                    </select>
                </div>

                <div class="modal__form-group">
                    <label for="conductor_id" class="modal__form-label">Conductor</label>
                    <select name="conductor_id" id="conductor_id" class="modal__form-input">
                        <option value="">Todos</option>
                        <?php foreach ($conductores as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $conductor_id == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal__form-group">
                    <label for="fecha_desde" class="modal__form-label">Fecha Desde</label>
                    <input type="date" name="fecha_desde" id="fecha_desde" class="modal__form-input" value="<?= $fecha_desde ?>">
                </div>
                
                <div class="modal__form-group">
                    <label for="fecha_hasta" class="modal__form-label">Fecha Hasta</label>
                    <input type="date" name="fecha_hasta" id="fecha_hasta" class="modal__form-input" value="<?= $fecha_hasta ?>">
                </div>
                
                <div class="modal__form-group">
                    <label for="monto_min" class="modal__form-label">Monto Mínimo</label>
                    <input type="number" name="monto_min" id="monto_min" class="modal__form-input" value="<?= $monto_min ?>" min="0" step="0.01">
                </div>
                
                <div class="modal__form-group">
                    <label for="monto_max" class="modal__form-label">Monto Máximo</label>
                    <input type="number" name="monto_max" id="monto_max" class="modal__form-input" value="<?= $monto_max ?>" min="0" step="0.01">
                </div>
            </form>
        </div>
        
        <!-- Tabla de pagos -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>ID Pago</th>
                        <th>ID Registro</th>
                        <th>Fecha</th>
                        <th>Conductor</th>
                        <th>Monto</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registros)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px;">No hay pagos registrados con los filtros aplicados</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($registros as $pago): ?>
                            <tr>
                                <td><?= ucfirst($pago['tipo']) ?></td>
                                <td><?= $pago['id'] ?></td>
                                <td><?= $pago['registro_id'] ?></td>
                                <td><?= $pago['fecha'] ?></td>
                                <td><?= htmlspecialchars($pago['conductor_nombre']) ?></td>
                                <td><?= number_format($pago['monto'], 0, ',', '.') ?> XAF</td>
                                <td><?= htmlspecialchars($pago['descripcion']) ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <!-- Botón VER -->
                                        <button class="btn btn-ver" onclick="verPago(<?= htmlspecialchars(json_encode($pago), ENT_QUOTES, 'UTF-8') ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                        
                                        <!-- Botón ELIMINAR -->
                                        <button class="btn btn-eliminar" onclick="eliminarPago(<?= $pago['id'] ?>, '<?= $pago['tipo'] ?>')">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
                <div class="pagination">
                    <?php if ($pagina > 1): ?>
                        <a href="deudas.php?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1, 'tab' => 'prestamos'])) ?>">&laquo; Anterior</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <?php if ($i == $pagina): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($pagina < $total_paginas): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>">Siguiente &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Ver Detalles -->
    <div id="modalVer" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalVer')"></div>
        <div class="modal__container" style="max-width: 800px;">
            <button class="modal__close" onclick="cerrarModal('modalVer')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Detalles del Pago</h3>
            </div>
            
            <div class="modal__body" style="display: flex; flex-wrap: wrap; gap: 20px;">
                <div id="detallePago"></div>
            </div>
            
            <div class="modal__footer">
                <button type="button" class="modal__action-btn" onclick="cerrarModal('modalVer')">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Confirmar Eliminación -->
    <div id="modalConfirmarEliminar" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalConfirmarEliminar')"></div>
        <div class="modal__container" style="max-width: 400px;">
            <button class="modal__close" onclick="cerrarModal('modalConfirmarEliminar')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Confirmar Eliminación</h3>
            </div>
            
            <div class="modal__body">
                <p>¿Estás seguro de que deseas eliminar este pago?</p>
                <p><strong>Esta acción revertirá el saldo en el registro original y en la caja.</strong></p>
            </div>
            
            <div class="modal__footer">
                <button type="button" class="modal__action-btn" onclick="cerrarModal('modalConfirmarEliminar')">
                    Cancelar
                </button>
                <button type="button" class="modal__action-btn modal__action-btn--primary" onclick="confirmarEliminar()" style="background-color: #e74c3c;">
                    Eliminar
                </button>
            </div>
        </div>
    </div>

    <script>
    // Función para ver detalles del pago
    function verPago(pago) {
        const datos = [
            { label: 'TIPO', value: pago.tipo === 'prestamo' ? 'Préstamo' : 'Amonestación' },
            { label: 'ID PAGO', value: pago.id || 'N/A' },
            { label: 'ID REGISTRO', value: pago.registro_id || 'N/A' },
            { label: 'CONDUCTOR', value: pago.conductor_nombre || 'N/A' },
            { label: 'FECHA', value: pago.fecha || 'N/A' },
            { label: 'MONTO', value: (pago.monto ? numberFormat(pago.monto) + ' XAF' : 'N/A') },
            { label: 'MONTO TOTAL', value: (pago.monto_total ? numberFormat(pago.monto_total) + ' XAF' : 'N/A') },
            { label: 'TOTAL PAGADO', value: (pago.total_pagado ? numberFormat(pago.total_pagado) + ' XAF' : '0 XAF') },
            { label: 'SALDO ACTUAL', value: (pago.saldo_actual ? numberFormat(pago.saldo_actual) + ' XAF' : '0 XAF') },
            { label: 'DESCRIPCIÓN', value: pago.descripcion || 'N/A' }
        ];

        // Dividir los datos en dos columnas
        const mitad = Math.ceil(datos.length / 2);
        const columna1 = datos.slice(0, mitad);
        const columna2 = datos.slice(mitad);

        const html = `
            <div style="display: flex; gap: 20px; width: 100%;">
                <div style="flex: 1;">
                    <table class="modal__data-table" style="width: 100%;">
                        ${columna1.map(item => `
                            <tr>
                                <td class="modal__data-label">${item.label}</td>
                                <td class="modal__data-value">${item.value}</td>
                            </tr>
                        `).join('')}
                    </table>
                </div>
                <div style="flex: 1;">
                    <table class="modal__data-table" style="width: 100%;">
                        ${columna2.map(item => `
                            <tr>
                                <td class="modal__data-label">${item.label}</td>
                                <td class="modal__data-value">${item.value}</td>
                            </tr>
                        `).join('')}
                    </table>
                </div>
            </div>
        `;
        
        document.getElementById('detallePago').innerHTML = html;
        abrirModal('modalVer');
    }

    // Función para abrir modal de confirmación de eliminación
    function eliminarPago(pagoId, tipo) {
        document.getElementById('modalConfirmarEliminar').dataset.pagoId = pagoId;
        document.getElementById('modalConfirmarEliminar').dataset.tipo = tipo;
        abrirModal('modalConfirmarEliminar');
    }

    // Función para confirmar eliminación
    function confirmarEliminar() {
        const pagoId = document.getElementById('modalConfirmarEliminar').dataset.pagoId;
        const tipo = document.getElementById('modalConfirmarEliminar').dataset.tipo;
        
        if (confirm(`¿Estás completamente seguro de que deseas eliminar este pago?\n\nEsta acción revertirá el saldo en el registro original y en la caja.\nEsta acción no se puede deshacer.`)) {
            window.location.href = `historial_pagos.php?eliminar=${pagoId}&tipo=${tipo}`;
        }
    }

    // Funciones auxiliares para modales
    function abrirModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }

    function cerrarModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Función para formatear números
    function numberFormat(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
        if (event.target.className === 'modal__overlay') {
            const modalId = event.target.parentElement.id;
            cerrarModal(modalId);
        }
    };

    // Aplicar filtros automáticamente al cambiar valores
    document.getElementById('search').addEventListener('input', function() {
        document.getElementById('filtersForm').submit();
    });

    document.getElementById('tipo_filtro').addEventListener('change', function() {
        document.getElementById('filtersForm').submit();
    });

    document.getElementById('conductor_id').addEventListener('change', function() {
        document.getElementById('filtersForm').submit();
    });

    document.getElementById('fecha_desde').addEventListener('change', function() {
        document.getElementById('filtersForm').submit();
    });

    document.getElementById('fecha_hasta').addEventListener('change', function() {
        document.getElementById('filtersForm').submit();
    });

    document.getElementById('monto_min').addEventListener('change', function() {
        document.getElementById('filtersForm').submit();
    });

    document.getElementById('monto_max').addEventListener('change', function() {
        document.getElementById('filtersForm').submit();
    });
    </script>

</body>
</html>