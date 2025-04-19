<?php
require '../../config/database.php';

// Obtener parámetros de filtro
$search = isset($_GET['search']) ? $_GET['search'] : '';
$conductor_id = isset($_GET['conductor_id']) ? intval($_GET['conductor_id']) : null;
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : null;
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : null;
$monto_min = isset($_GET['monto_min']) ? floatval($_GET['monto_min']) : null;
$monto_max = isset($_GET['monto_max']) ? floatval($_GET['monto_max']) : null;

// Ordenación
$orden = isset($_GET['orden']) ? $_GET['orden'] : 'fecha_desc';
$orden_parts = explode('_', $orden);
$campo_orden = in_array($orden_parts[0], ['id', 'fecha', 'conductor_nombre', 'monto', 'saldo_pendiente']) ? $orden_parts[0] : 'fecha';
$direccion_orden = isset($orden_parts[1]) && in_array($orden_parts[1], ['asc', 'desc']) ? $orden_parts[1] : 'desc';

// Paginación
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// Construir consulta base
$sql = "
    SELECT p.*, c.nombre AS conductor_nombre,
           (SELECT COALESCE(SUM(pp.monto), 0) FROM pagos_prestamos pp WHERE pp.prestamo_id = p.id) AS monto_pagado
    FROM prestamos p
    LEFT JOIN conductores c ON p.conductor_id = c.id
    WHERE p.saldo_pendiente > 0 AND p.estado = 'pendiente'
";

$sql_count = "
    SELECT COUNT(*) AS total
    FROM prestamos p
    LEFT JOIN conductores c ON p.conductor_id = c.id
    WHERE p.saldo_pendiente > 0 AND p.estado = 'pendiente'
";

// Aplicar filtros
$params = [];
$params_count = [];

if ($search) {
    $sql .= " AND (c.nombre LIKE ? OR p.descripcion LIKE ? OR p.id LIKE ?)";
    $sql_count .= " AND (c.nombre LIKE ? OR p.descripcion LIKE ? OR p.id LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params_count = array_merge($params_count, $params);
}

if ($conductor_id) {
    $sql .= " AND p.conductor_id = ?";
    $sql_count .= " AND p.conductor_id = ?";
    $params[] = $conductor_id;
    $params_count[] = $conductor_id;
}

if ($fecha_desde) {
    $sql .= " AND p.fecha >= ?";
    $sql_count .= " AND p.fecha >= ?";
    $params[] = $fecha_desde;
    $params_count[] = $fecha_desde;
}

if ($fecha_hasta) {
    $sql .= " AND p.fecha <= ?";
    $sql_count .= " AND p.fecha <= ?";
    $params[] = $fecha_hasta;
    $params_count[] = $fecha_hasta;
}

if ($monto_min) {
    $sql .= " AND p.monto >= ?";
    $sql_count .= " AND p.monto >= ?";
    $params[] = $monto_min;
    $params_count[] = $monto_min;
}

if ($monto_max) {
    $sql .= " AND p.monto <= ?";
    $sql_count .= " AND p.monto <= ?";
    $params[] = $monto_max;
    $params_count[] = $monto_max;
}

// Aplicar ordenación
$sql .= " ORDER BY $campo_orden $direccion_orden, p.conductor_id LIMIT $offset, $por_pagina";

// Preparar y ejecutar consultas
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prestamos = $stmt->fetchAll();

$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params_count);
$total_prestamos = $stmt_count->fetch()['total'];
$total_paginas = ceil($total_prestamos / $por_pagina);

// Obtener conductores activos para el filtro
$conductores = $pdo->query("
    SELECT c.id, c.nombre 
    FROM conductores c
    WHERE c.estado = 'activo'
    ORDER BY c.nombre
")->fetchAll();

// Procesar POST para agregar o pagar préstamos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        try {
            $pdo->beginTransaction();
            
            if ($_POST['accion'] === 'agregar') {
                // Agregar nuevo préstamo
                $conductor_id = intval($_POST['conductor_id']);
                $monto = floatval($_POST['monto']);
                $descripcion = $_POST['descripcion'];
                $fecha = $_POST['fecha'] ?: date('Y-m-d');
                
                // Insertar préstamo
                $stmt = $pdo->prepare("
                    INSERT INTO prestamos (conductor_id, monto, saldo_pendiente, descripcion, fecha, estado)
                    VALUES (?, ?, ?, ?, ?, 'pendiente')
                ");
                $stmt->execute([$conductor_id, $monto, $monto, $descripcion, $fecha]);
                $prestamo_id = $pdo->lastInsertId();
                
                // Obtener caja predeterminada
                $caja = $pdo->query("SELECT id, saldo_actual FROM cajas WHERE predeterminada = 1 LIMIT 1")->fetch();
                
                if (!$caja) {
                    throw new Exception("No se encontró caja predeterminada");
                }
                
                // Verificar saldo suficiente
                if ($caja['saldo_actual'] < $monto) {
                    throw new Exception("Saldo insuficiente en la caja predeterminada");
                }
                
                // Actualizar caja
                $nuevo_saldo = $caja['saldo_actual'] - $monto;
                $pdo->prepare("UPDATE cajas SET saldo_actual = ? WHERE id = ?")
                    ->execute([$nuevo_saldo, $caja['id']]);
                
                // Registrar movimiento de caja
                $pdo->prepare("
                    INSERT INTO movimientos_caja (caja_id, monto, tipo, descripcion, fecha, prestamo_id)
                    VALUES (?, ?, 'egreso', ?, ?, ?)
                ")->execute([
                    $caja['id'],
                    $monto,
                    "Préstamo a conductor ID: $conductor_id",
                    $fecha,
                    $prestamo_id
                ]);
                
                $mensaje = "Préstamo registrado correctamente";
                
            } elseif ($_POST['accion'] === 'pagar') {
                // Procesar pago de préstamo
                $prestamo_id = intval($_POST['prestamo_id']);
                $monto = floatval($_POST['monto']);
                $descripcion = $_POST['descripcion'] ?: "Pago parcial de préstamo";
                $fecha = $_POST['fecha'] ?: date('Y-m-d');
                
                // Obtener préstamo
                $prestamo = $pdo->query("SELECT * FROM prestamos WHERE id = $prestamo_id")->fetch();
                
                if (!$prestamo) {
                    throw new Exception("Préstamo no encontrado");
                }
                
                // Validar monto
                if ($monto <= 0) {
                    throw new Exception("El monto debe ser mayor que cero");
                }
                
                if ($monto > $prestamo['saldo_pendiente']) {
                    throw new Exception("El monto no puede ser mayor que el saldo pendiente");
                }
                
                // Obtener caja predeterminada
                $caja = $pdo->query("SELECT id, saldo_actual FROM cajas WHERE predeterminada = 1 LIMIT 1")->fetch();
                
                if (!$caja) {
                    throw new Exception("No se encontró caja predeterminada");
                }
                
                // Registrar pago
                $stmtPago = $pdo->prepare("
                    INSERT INTO pagos_prestamos (prestamo_id, monto, descripcion, fecha)
                    VALUES (?, ?, ?, ?)
                ");
                $stmtPago->execute([$prestamo_id, $monto, $descripcion, $fecha]);
                $pago_id = $pdo->lastInsertId();
                
                // Actualizar saldo pendiente del préstamo
                $nuevo_saldo = $prestamo['saldo_pendiente'] - $monto;
                $estado = $nuevo_saldo <= 0 ? 'pagado' : 'pendiente';
                
                $pdo->prepare("
                    UPDATE prestamos 
                    SET saldo_pendiente = ?, estado = ?
                    WHERE id = ?
                ")->execute([$nuevo_saldo, $estado, $prestamo_id]);
                
                // Actualizar caja
                $nuevo_saldo_caja = $caja['saldo_actual'] + $monto;
                $pdo->prepare("UPDATE cajas SET saldo_actual = ? WHERE id = ?")
                    ->execute([$nuevo_saldo_caja, $caja['id']]);
                
                // Registrar movimiento de caja
                $pdo->prepare("
                    INSERT INTO movimientos_caja (caja_id, monto, tipo, descripcion, fecha, pago_prestamo_id)
                    VALUES (?, ?, 'ingreso', ?, ?, ?)
                ")->execute([
                    $caja['id'],
                    $monto,
                    "Pago de préstamo ID: $prestamo_id",
                    $fecha,
                    $pago_id
                ]);
                
                $mensaje = "Pago registrado correctamente";
            }
            
            $pdo->commit();
            header("Location: prestamos.php?success=" . urlencode($mensaje));
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
            header("Location: prestamos.php?error=" . urlencode($error));
            exit;
        }
    }
}

// Procesar GET para eliminar préstamo
if (isset($_GET['eliminar'])) {
    $prestamo_id = intval($_GET['eliminar']);
    
    try {
        $pdo->beginTransaction();
        
        // 1. Obtener información del préstamo
        $prestamo = $pdo->query("SELECT * FROM prestamos WHERE id = $prestamo_id")->fetch();
        
        if (!$prestamo) {
            throw new Exception("Préstamo no encontrado");
        }
        
        // 2. Verificar si tiene movimientos de caja asociados
        $movimiento = $pdo->query("SELECT * FROM movimientos_caja WHERE prestamo_id = $prestamo_id")->fetch();
        
        if ($movimiento) {
            // 3. Obtener caja predeterminada
            $caja = $pdo->query("SELECT id, saldo_actual FROM cajas WHERE predeterminada = 1 LIMIT 1")->fetch();
            
            if (!$caja) {
                throw new Exception("No se encontró caja predeterminada");
            }
            
            // 4. Devolver el monto a la caja
            $nuevo_saldo = $caja['saldo_actual'] + $movimiento['monto'];
            $pdo->prepare("UPDATE cajas SET saldo_actual = ? WHERE id = ?")
                ->execute([$nuevo_saldo, $caja['id']]);
            
            // 5. Registrar movimiento de devolución
            $pdo->prepare("
                INSERT INTO movimientos_caja (caja_id, monto, tipo, descripcion, fecha)
                VALUES (?, ?, 'ingreso', ?, ?)
            ")->execute([
                $caja['id'],
                $movimiento['monto'],
                "Devolución por eliminación de préstamo ID: $prestamo_id",
                date('Y-m-d')
            ]);
        }
        
        // 6. Eliminar los movimientos de caja asociados
        $pdo->prepare("DELETE FROM movimientos_caja WHERE prestamo_id = ?")->execute([$prestamo_id]);
        
        // 7. Verificar si tiene pagos asociados
        $tiene_pagos = $pdo->query("SELECT COUNT(*) FROM pagos_prestamos WHERE prestamo_id = $prestamo_id")->fetchColumn();
        
        if ($tiene_pagos) {
            // 8. Si tiene pagos, primero eliminar los movimientos de caja de esos pagos
            $pdo->prepare("DELETE mc FROM movimientos_caja mc 
                          JOIN pagos_prestamos pp ON mc.pago_prestamo_id = pp.id 
                          WHERE pp.prestamo_id = ?")->execute([$prestamo_id]);
            
            // 9. Luego eliminar los pagos
            $pdo->prepare("DELETE FROM pagos_prestamos WHERE prestamo_id = ?")->execute([$prestamo_id]);
        }
        
        // 10. Finalmente eliminar el préstamo
        $pdo->prepare("DELETE FROM prestamos WHERE id = ?")->execute([$prestamo_id]);
        
        $pdo->commit();
        header("Location: prestamos.php?success=Préstamo eliminado correctamente");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al eliminar el préstamo: " . $e->getMessage();
        header("Location: prestamos.php?error=" . urlencode($error));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Préstamos</title>
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
    
    .container {
        max-width: 1250px;
        margin: 20px auto;
        background: #fff;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    
    h1 {
        color: var(--color-primario);
        margin-top: -30px;
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
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
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
    
    .btn-pagar {
        background-color: var(--color-pagar);
        color: white;
    }
    
    .btn-pagar:hover {
        background-color: #1aa179;
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
    
    .btn-agregar {
        background-color: var(--color-exito);
        color: white;
        font-weight: 600;
        min-width: 150px;
    }
    
    .btn-agregar:hover {
        background-color: #218838;
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
        
        .btn-agregar {
            width: 100%;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <h1>Préstamos Pendientes</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>
        
        <!-- Filtros y botón Agregar -->
        <div class="filters-container">
            <form id="filtersForm" method="get" class="modal__form">
                <div class="modal__form-group">
                    <label for="search" class="modal__form-label">Buscar</label>
                    <input type="text" name="search" id="search" class="modal__form-input" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Conductor, descripción o ID">
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
            
            <button class="btn btn-agregar" onclick="abrirModal('modalAgregar')">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Agregar
            </button>
        </div>
        
        <!-- Tabla de préstamos pendientes -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>
                            <a href="?<?= http_build_query(array_merge($_GET, ['orden' => 'id_' . ($campo_orden === 'id' && $direccion_orden === 'asc' ? 'desc' : 'asc')])) ?>">
                                ID <?= $campo_orden === 'id' ? ($direccion_orden === 'asc' ? '↑' : '↓') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?= http_build_query(array_merge($_GET, ['orden' => 'fecha_' . ($campo_orden === 'fecha' && $direccion_orden === 'asc' ? 'desc' : 'asc')])) ?>">
                                Fecha <?= $campo_orden === 'fecha' ? ($direccion_orden === 'asc' ? '↑' : '↓') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?= http_build_query(array_merge($_GET, ['orden' => 'conductor_nombre_' . ($campo_orden === 'conductor_nombre' && $direccion_orden === 'asc' ? 'desc' : 'asc')])) ?>">
                                Conductor <?= $campo_orden === 'conductor_nombre' ? ($direccion_orden === 'asc' ? '↑' : '↓') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?= http_build_query(array_merge($_GET, ['orden' => 'monto_' . ($campo_orden === 'monto' && $direccion_orden === 'asc' ? 'desc' : 'asc')])) ?>">
                                Monto Prestado <?= $campo_orden === 'monto' ? ($direccion_orden === 'asc' ? '↑' : '↓') : '' ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?= http_build_query(array_merge($_GET, ['orden' => 'saldo_pendiente_' . ($campo_orden === 'saldo_pendiente' && $direccion_orden === 'asc' ? 'desc' : 'asc')])) ?>">
                                Pendiente <?= $campo_orden === 'saldo_pendiente' ? ($direccion_orden === 'asc' ? '↑' : '↓') : '' ?>
                            </a>
                        </th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prestamos)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px;">No hay préstamos pendientes con los filtros aplicados</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($prestamos as $prestamo): ?>
                            <tr>
                                <td><?= $prestamo['id'] ?></td>
                                <td><?= $prestamo['fecha'] ?></td>
                                <td><?= htmlspecialchars($prestamo['conductor_nombre']) ?></td>
                                <td><?= number_format($prestamo['monto'], 0, ',', '.') ?> XAF</td>
                                <td><?= number_format($prestamo['saldo_pendiente'], 0, ',', '.') ?> XAF</td>
                                <td><?= htmlspecialchars($prestamo['descripcion']) ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <!-- Botón VER -->
                                        <button class="btn btn-ver" onclick="verPrestamo(<?= htmlspecialchars(json_encode($prestamo), ENT_QUOTES, 'UTF-8') ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                        
                                        <!-- Botón PAGAR -->
                                        <button class="btn btn-pagar" onclick="pagarPrestamo(<?= $prestamo['id'] ?>, <?= $prestamo['saldo_pendiente'] ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                            </svg>
                                        </button>
                                        
                                        <!-- Botón ELIMINAR -->
                                        <button class="btn btn-eliminar" onclick="eliminarPrestamo(<?= $prestamo['id'] ?>)">
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
                        <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>">&laquo; Anterior</a>
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
    
    <!-- Modal Agregar Préstamo -->
    <div id="modalAgregar" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalAgregar')"></div>
        <div class="modal__container">
            <button class="modal__close" onclick="cerrarModal('modalAgregar')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Agregar Nuevo Préstamo</h3>
            </div>
            
            <div class="modal__body">
                <form id="formAgregar" method="post" class="modal__form">
                    <input type="hidden" name="accion" value="agregar">
                    
                    <div class="modal__form-group">
                        <label for="agregarConductorId" class="modal__form-label">Conductor</label>
                        <select name="conductor_id" id="agregarConductorId" class="modal__form-input" required>
                            <option value="">Seleccionar conductor</option>
                            <?php foreach ($conductores as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="agregarMonto" class="modal__form-label">Monto</label>
                        <input type="number" name="monto" id="agregarMonto" class="modal__form-input" required min="1" step="0.01">
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="agregarFecha" class="modal__form-label">Fecha</label>
                        <input type="date" name="fecha" id="agregarFecha" class="modal__form-input" value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="agregarDescripcion" class="modal__form-label">Descripción</label>
                        <textarea name="descripcion" id="agregarDescripcion" class="modal__form-input" rows="3"></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal__footer">
                <button type="button" class="modal__action-btn" onclick="cerrarModal('modalAgregar')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar
                </button>
                <button type="button" class="modal__action-btn modal__action-btn--primary" onclick="confirmarAgregar()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    Guardar Préstamo
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Ver Préstamo -->
    <div id="modalVer" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalVer')"></div>
        <div class="modal__container">
            <button class="modal__close" onclick="cerrarModal('modalVer')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Detalles del Préstamo</h3>
            </div>
            
            <div class="modal__body">
                <div id="detallePrestamo" style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Contenido dinámico aquí -->
                </div>
                
                <!-- Historial de pagos 
                <div id="historialPagos" style="margin-top: 20px;">
                    <h4 style="margin-bottom: 15px;">Historial de Pagos</h4>
                    <table class="table" id="tablaPagos">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Monto</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            Contenido dinámico aquí
                        </tbody>
                    </table>
                </div>-->
            </div>
        </div>
    </div>
    
    <!-- Modal Pagar Préstamo -->
    <div id="modalPagar" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalPagar')"></div>
        <div class="modal__container">
            <button class="modal__close" onclick="cerrarModal('modalPagar')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Registrar Pago de Préstamo</h3>
            </div>
            
            <div class="modal__body">
                <form id="formPagar" method="post" class="modal__form">
                    <input type="hidden" name="accion" value="pagar">
                    <input type="hidden" name="prestamo_id" id="pagarPrestamoId">
                    
                    <div class="modal__form-group">
                        <label class="modal__form-label">Saldo Pendiente</label>
                        <input type="text" id="pagarSaldoPendiente" class="modal__form-input" readonly>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="pagarMonto" class="modal__form-label">Monto a Pagar</label>
                        <input type="number" name="monto" id="pagarMonto" class="modal__form-input" required min="1" step="0.01">
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="pagarFecha" class="modal__form-label">Fecha</label>
                        <input type="date" name="fecha" id="pagarFecha" class="modal__form-input" value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="pagarDescripcion" class="modal__form-label">Descripción</label>
                        <textarea name="descripcion" id="pagarDescripcion" class="modal__form-input" rows="3"></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal__footer">
                <button type="button" class="modal__action-btn" onclick="cerrarModal('modalPagar')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar
                </button>
                <button type="button" class="modal__action-btn modal__action-btn--primary" onclick="confirmarPago()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    Registrar Pago
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
                <p>¿Estás seguro de que deseas eliminar este préstamo?</p>
                <p><strong>Esta acción no se puede deshacer.</strong></p>
            </div>
            
            <div class="modal__footer">
                <button type="button" class="modal__action-btn" onclick="cerrarModal('modalConfirmarEliminar')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar
                </button>
                <button type="button" class="modal__action-btn modal__action-btn--primary" onclick="confirmarEliminar()" style="background-color: #e74c3c;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    Eliminar
                </button>
            </div>
        </div>
    </div>

<script>
// Función para ver detalles del préstamo
function verPrestamo(prestamo) {
    // Mostrar detalles del préstamo
    const columna1 = [
        { label: 'CONDUCTOR', value: prestamo.conductor_nombre || 'N/A' },
        { label: 'FECHA', value: prestamo.fecha || 'N/A' },
        { label: 'MONTO PRESTADO', value: (prestamo.monto ? numberFormat(prestamo.monto) + ' XAF' : 'N/A') }
    ];
    
    const columna2 = [
        { label: 'MONTO PAGADO', value: (prestamo.monto_pagado ? numberFormat(prestamo.monto_pagado) + ' XAF' : '0 XAF') },
        { label: 'SALDO PENDIENTE', value: (prestamo.saldo_pendiente ? numberFormat(prestamo.saldo_pendiente) + ' XAF' : '0 XAF') },
        { label: 'ESTADO', value: prestamo.estado === 'pendiente' ? 'Pendiente' : 'Pagado' }
    ];
    
    const html = `
        <div style="flex: 1; min-width: 300px;">
            <table class="modal__data-table">
                ${columna1.map(item => `
                    <tr>
                        <td class="modal__data-label">${item.label}</td>
                        <td class="modal__data-value">${item.value}</td>
                    </tr>
                `).join('')}
            </table>
        </div>
        <div style="flex: 1; min-width: 300px;">
            <table class="modal__data-table">
                ${columna2.map(item => `
                    <tr>
                        <td class="modal__data-label">${item.label}</td>
                        <td class="modal__data-value">${item.value}</td>
                    </tr>
                `).join('')}
            </table>
        </div>
        <div style="flex: 100%; min-width: 300px; margin-top: 20px;">
            <table class="modal__data-table">
                <tr>
                    <td class="modal__data-label">DESCRIPCIÓN</td>
                    <td class="modal__data-value">${prestamo.descripcion || 'N/A'}</td>
                </tr>
            </table>
        </div>
    `;
    
    document.getElementById('detallePrestamo').innerHTML = html;
    
    // Cargar historial de pagos
    fetch(`/taxis/modules/prestamos/get_pagos.php?prestamo_id=${prestamo.id}`)
        .then(response => response.json())
        .then(pagos => {
            const tbody = document.querySelector('#tablaPagos tbody');
            if (pagos.length > 0) {
                tbody.innerHTML = pagos.map(pago => `
                    <tr>
                        <td>${pago.fecha}</td>
                        <td>${numberFormat(pago.monto)} XAF</td>
                        <td>${pago.descripcion || ''}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align: center;">No hay pagos registrados</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error al cargar pagos:', error);
            document.querySelector('#tablaPagos tbody').innerHTML = 
                '<tr><td colspan="3" style="text-align: center;">Error al cargar pagos</td></tr>';
        });
    
    abrirModal('modalVer');
}

// Función para abrir modal de pago
function pagarPrestamo(prestamoId, saldoPendiente) {
    document.getElementById('pagarPrestamoId').value = prestamoId;
    document.getElementById('pagarSaldoPendiente').value = numberFormat(saldoPendiente) + ' XAF';
    document.getElementById('pagarMonto').value = saldoPendiente;
    document.getElementById('pagarMonto').max = saldoPendiente;
    abrirModal('modalPagar');
}

// Función para confirmar el pago
function confirmarPago() {
    const form = document.getElementById('formPagar');
    const montoInput = document.getElementById('pagarMonto');
    const montoPagar = parseFloat(montoInput.value);
    
    // Validar monto
    if (isNaN(montoPagar)) {
        alert('El monto debe ser un número válido');
        montoInput.focus();
        return;
    }
    
    if (montoPagar <= 0) {
        alert('El monto a pagar debe ser mayor que cero');
        montoInput.focus();
        return;
    }
    
    // Obtener saldo pendiente (eliminar formato XAF y puntos)
    const saldoText = document.getElementById('pagarSaldoPendiente').value;
    const saldoPendiente = parseFloat(saldoText.replace(/[^0-9]/g, ''));
    
    if (montoPagar > saldoPendiente) {
        alert('El monto a pagar no puede ser mayor que el saldo pendiente');
        montoInput.focus();
        return;
    }
    
    // Validar fecha
    const fechaInput = document.getElementById('pagarFecha');
    if (!fechaInput.value) {
        alert('Debe especificar una fecha');
        fechaInput.focus();
        return;
    }
    
    // Mostrar mensaje de confirmación
    if (!confirm(`¿Confirmas el pago de ${montoPagar} XAF?`)) {
        return;
    }
    
    // Enviar formulario de manera tradicional
    form.submit();
}

// Función para abrir modal de confirmación de eliminación
function eliminarPrestamo(prestamoId) {
    document.getElementById('modalConfirmarEliminar').dataset.prestamoId = prestamoId;
    abrirModal('modalConfirmarEliminar');
}

// Función para confirmar eliminación
function confirmarEliminar() {
    const prestamoId = document.getElementById('modalConfirmarEliminar').dataset.prestamoId;
    if (confirm('¿Estás completamente seguro de que deseas eliminar este préstamo? Esta acción no se puede deshacer.')) {
        window.location.href = `prestamos.php?eliminar=${prestamoId}`;
    }
}

// Función para confirmar agregar préstamo
function confirmarAgregar() {
    const form = document.getElementById('formAgregar');
    const conductorId = document.getElementById('agregarConductorId').value;
    const monto = document.getElementById('agregarMonto').value;
    
    if (!conductorId) {
        alert('Debes seleccionar un conductor');
        return;
    }
    
    if (!monto || parseFloat(monto) <= 0) {
        alert('El monto debe ser mayor que cero');
        return;
    }
    
    form.submit();
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