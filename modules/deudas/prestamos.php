<?php
//include '../../layout/header.php';
require '../../config/database.php';

// Obtener parámetros de filtro
$search = isset($_GET['search']) ? $_GET['search'] : '';
// Después de los otros parámetros de filtro (search, conductor_id, etc.)
$tipo_filtro = isset($_GET['tipo_filtro']) ? $_GET['tipo_filtro'] : '';
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

// Construir consulta base para préstamos y amonestaciones
$sql = "
    (SELECT 
        p.id, 
        p.conductor_id, 
        c.nombre AS conductor_nombre,
        p.monto, 
        p.saldo_pendiente, 
        p.fecha, 
        p.descripcion,
        p.estado,
        (SELECT COALESCE(SUM(pp.monto), 0) FROM pagos_prestamos pp WHERE pp.prestamo_id = p.id) AS monto_pagado,
        'prestamo' AS tipo
    FROM prestamos p
    LEFT JOIN conductores c ON p.conductor_id = c.id
    WHERE p.saldo_pendiente > 0 AND p.estado = 'pendiente'
";

// Consulta para contar registros
$sql_count = "
    SELECT SUM(cnt) as total FROM (
        (SELECT COUNT(*) as cnt FROM prestamos p
        LEFT JOIN conductores c ON p.conductor_id = c.id
        WHERE p.saldo_pendiente > 0 AND p.estado = 'pendiente'
";

// Aplicar filtros a préstamos
$params = [];
$params_count = [];

if ($search) {
    $sql .= " AND (c.nombre LIKE ? OR p.descripcion LIKE ? OR p.id LIKE ?)";
    $sql_count .= " AND (c.nombre LIKE ? OR p.descripcion LIKE ? OR p.id LIKE ?)";
    $searchTerm = "%$search%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm);
    $params_count = array_merge($params_count, $params);
}
// En la parte de PRÉSTAMOS (dentro del WHERE):
if ($tipo_filtro && $tipo_filtro !== 'prestamo') {
    $sql .= " AND 1=0"; // No mostrar préstamos si el filtro es solo "amonestacion"
    $sql_count .= " AND 1=0";
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

$sql .= ")
UNION ALL
(
    SELECT 
        a.id, 
        a.conductor_id, 
        c.nombre AS conductor_nombre,
        a.monto, 
        a.saldo_pendiente, 
        a.fecha, 
        a.descripcion,
        a.estado,
        (SELECT COALESCE(SUM(pa.monto), 0) FROM pagos_amonestaciones pa WHERE pa.amonestacion_id = a.id) AS monto_pagado,
        'amonestacion' AS tipo
    FROM amonestaciones a
    LEFT JOIN conductores c ON a.conductor_id = c.id
    WHERE a.saldo_pendiente > 0 AND a.estado = 'pendiente'
";

$sql_count .= ")
        UNION ALL
        (SELECT COUNT(*) as cnt FROM amonestaciones a
        LEFT JOIN conductores c ON a.conductor_id = c.id
        WHERE a.saldo_pendiente > 0 AND a.estado = 'pendiente'
";

// Aplicar mismos filtros a amonestaciones
if ($search) {
    $sql .= " AND (c.nombre LIKE ? OR a.descripcion LIKE ? OR a.id LIKE ?)";
    $sql_count .= " AND (c.nombre LIKE ? OR a.descripcion LIKE ? OR a.id LIKE ?)";
    $searchTerm = "%$search%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm);
    array_push($params_count, $searchTerm, $searchTerm, $searchTerm);
}
// En la parte de AMONESTACIONES (dentro del WHERE):
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
    $sql .= " AND a.fecha >= ?";
    $sql_count .= " AND a.fecha >= ?";
    $params[] = $fecha_desde;
    $params_count[] = $fecha_desde;
}

if ($fecha_hasta) {
    $sql .= " AND a.fecha <= ?";
    $sql_count .= " AND a.fecha <= ?";
    $params[] = $fecha_hasta;
    $params_count[] = $fecha_hasta;
}

if ($monto_min) {
    $sql .= " AND a.monto >= ?";
    $sql_count .= " AND a.monto >= ?";
    $params[] = $monto_min;
    $params_count[] = $monto_min;
}

if ($monto_max) {
    $sql .= " AND a.monto <= ?";
    $sql_count .= " AND a.monto <= ?";
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

// Procesar POST para agregar o pagar registros
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        try {
            $pdo->beginTransaction();
            
            if ($_POST['accion'] === 'agregar') {
                $tipo = $_POST['tipo'];
                $conductor_id = intval($_POST['conductor_id']);
                $monto = floatval($_POST['monto']);
                $descripcion = $_POST['descripcion'];
                $fecha = $_POST['fecha'] ?: date('Y-m-d');
                
                if ($tipo === 'prestamo') {
                    // Insertar préstamo
                    $stmt = $pdo->prepare("
                        INSERT INTO prestamos (conductor_id, monto, saldo_pendiente, descripcion, fecha, estado)
                        VALUES (?, ?, ?, ?, ?, 'pendiente')
                    ");
                    $stmt->execute([$conductor_id, $monto, $monto, $descripcion, $fecha]);
                    $registro_id = $pdo->lastInsertId();
                    
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
                        $registro_id
                    ]);
                    
                    $mensaje = "Préstamo registrado correctamente";
                } else {
                    // Insertar amonestación
                    $stmt = $pdo->prepare("
                        INSERT INTO amonestaciones (conductor_id, monto, saldo_pendiente, descripcion, fecha, estado)
                        VALUES (?, ?, ?, ?, ?, 'pendiente')
                    ");
                    $stmt->execute([$conductor_id, $monto, $monto, $descripcion, $fecha]);
                    $mensaje = "Amonestación registrada correctamente";
                }
                
            } elseif ($_POST['accion'] === 'pagar') {
                $tipo = $_POST['tipo'];
                $registro_id = intval($_POST['registro_id']);
                $monto = floatval($_POST['monto']);
                $descripcion = $_POST['descripcion'] ?: "Pago parcial";
                $fecha = $_POST['fecha'] ?: date('Y-m-d');
                
                // Determinar tablas según tipo
                $tabla_registro = ($tipo === 'prestamo') ? 'prestamos' : 'amonestaciones';
                $tabla_pagos = ($tipo === 'prestamo') ? 'pagos_prestamos' : 'pagos_amonestaciones';
                $campo_id = ($tipo === 'prestamo') ? 'prestamo_id' : 'amonestacion_id';
                
                // Obtener registro
                $registro = $pdo->query("SELECT * FROM $tabla_registro WHERE id = $registro_id")->fetch();
                
                if (!$registro) {
                    throw new Exception(ucfirst($tipo) . " no encontrado");
                }
                
                // Validar monto
                if ($monto <= 0) {
                    throw new Exception("El monto debe ser mayor que cero");
                }
                
                if ($monto > $registro['saldo_pendiente']) {
                    throw new Exception("El monto no puede ser mayor que el saldo pendiente");
                }
                
                // Obtener caja predeterminada
                $caja = $pdo->query("SELECT id, saldo_actual FROM cajas WHERE predeterminada = 1 LIMIT 1")->fetch();
                
                if (!$caja) {
                    throw new Exception("No se encontró caja predeterminada");
                }
                
                // Registrar pago
                $stmtPago = $pdo->prepare("
                    INSERT INTO $tabla_pagos ($campo_id, monto, descripcion, fecha)
                    VALUES (?, ?, ?, ?)
                ");
                $stmtPago->execute([$registro_id, $monto, $descripcion, $fecha]);
                $pago_id = $pdo->lastInsertId();
                
                // Actualizar saldo pendiente
                $nuevo_saldo = $registro['saldo_pendiente'] - $monto;
                $estado = $nuevo_saldo <= 0 ? 'pagado' : 'pendiente';
                
                $pdo->prepare("
                    UPDATE $tabla_registro 
                    SET saldo_pendiente = ?, estado = ?
                    WHERE id = ?
                ")->execute([$nuevo_saldo, $estado, $registro_id]);
                
                // Actualizar caja (sumar el pago)
                $nuevo_saldo_caja = $caja['saldo_actual'] + $monto;
                $pdo->prepare("UPDATE cajas SET saldo_actual = ? WHERE id = ?")
                    ->execute([$nuevo_saldo_caja, $caja['id']]);
                
                // Registrar movimiento de caja
                $campo_movimiento = ($tipo === 'prestamo') ? 'pago_prestamo_id' : 'pago_amonestacion_id';
                $pdo->prepare("
                    INSERT INTO movimientos_caja (caja_id, monto, tipo, descripcion, fecha, $campo_movimiento)
                    VALUES (?, ?, 'ingreso', ?, ?, ?)
                ")->execute([
                    $caja['id'],
                    $monto,
                    "Pago de $tipo ID: $registro_id",
                    $fecha,
                    $pago_id
                ]);
                
                $mensaje = "Pago registrado correctamente";
            }
            
            $pdo->commit();
echo "<script>window.location.href='deudas.php?tab=prestamos&success=" . urlencode($mensaje) . "';</script>";
exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
            echo "<script>window.location.href='deudas.php?tab=prestamos&error=" . urlencode($error) . "';</script>";
            exit;
        }
    }
}

// Procesar GET para eliminar registro
if (isset($_GET['eliminar'])) {
    $registro_id = intval($_GET['eliminar']);
    $tipo = $_GET['tipo']; // 'prestamo' o 'amonestacion'
    
    try {
        $pdo->beginTransaction();
        
        // Determinar tablas según tipo
        $tabla_registro = ($tipo === 'prestamo') ? 'prestamos' : 'amonestaciones';
        $tabla_pagos = ($tipo === 'prestamo') ? 'pagos_prestamos' : 'pagos_amonestaciones';
        $campo_id = ($tipo === 'prestamo') ? 'prestamo_id' : 'amonestacion_id';
        
        // 1. Obtener información del registro
        $registro = $pdo->query("SELECT * FROM $tabla_registro WHERE id = $registro_id")->fetch();
        
        if (!$registro) {
            throw new Exception(ucfirst($tipo) . " no encontrado");
        }
        
        // 2. Verificar si tiene pagos parciales
        $monto_pagado = $pdo->query("
            SELECT COALESCE(SUM(monto), 0) FROM $tabla_pagos 
            WHERE $campo_id = $registro_id
        ")->fetchColumn();
        
        if ($monto_pagado > 0) {
            throw new Exception("No se puede eliminar un $tipo con pagos registrados. Elimina primero los pagos asociados.");
        }
        
        // 3. Solo para préstamos: verificar movimientos de caja y revertir
        if ($tipo === 'prestamo') {
            $movimiento = $pdo->query("SELECT * FROM movimientos_caja WHERE prestamo_id = $registro_id")->fetch();
            
            if ($movimiento) {
                // Obtener caja predeterminada
                $caja = $pdo->query("SELECT id, saldo_actual FROM cajas WHERE predeterminada = 1 LIMIT 1")->fetch();
                
                if (!$caja) {
                    throw new Exception("No se encontró caja predeterminada");
                }
                
                // Devolver el monto a la caja
                $nuevo_saldo = $caja['saldo_actual'] + $movimiento['monto'];
                $pdo->prepare("UPDATE cajas SET saldo_actual = ? WHERE id = ?")
                    ->execute([$nuevo_saldo, $caja['id']]);
                
                // Registrar movimiento de devolución
                $pdo->prepare("
                    INSERT INTO movimientos_caja (caja_id, monto, tipo, descripcion, fecha)
                    VALUES (?, ?, 'ingreso', ?, ?)
                ")->execute([
                    $caja['id'],
                    $movimiento['monto'],
                    "Devolución por eliminación de préstamo ID: $registro_id",
                    date('Y-m-d')
                ]);
            }
            
            // Eliminar movimientos de caja asociados
            $pdo->prepare("DELETE FROM movimientos_caja WHERE prestamo_id = ?")->execute([$registro_id]);
        }
        
        // 4. Eliminar el registro
        $pdo->prepare("DELETE FROM $tabla_registro WHERE id = ?")->execute([$registro_id]);
        
        $pdo->commit();
echo "<script>window.location.href='deudas.php?tab=prestamos&success=" . urlencode(ucfirst($tipo) . " eliminado correctamente") . "';</script>";
exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al eliminar el $tipo: " . $e->getMessage();
        echo "<script>window.location.href='deudas.php?tab=prestamos&error=" . urlencode($error) . "';</script>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préstamos y Amonestaciones</title>
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
        font-weight: 400;
        min-width: 50px;
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
    <!--<div class="container">
        <h1>Préstamos y Amonestaciones Pendientes</h1>-->
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>
        
        <!-- Filtros y botón Agregar -->
        <div class="filters-container">
        <form id="filtersForm" method="get" action="deudas.php" class="modal__form">
        <input type="hidden" name="tab" value="prestamos">
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
            
            <button class="btn btn-agregar" onclick="abrirModal('modalAgregar')">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Agregar
            </button>
        </div>
        
        <!-- Tabla de registros pendientes -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Conductor</th>
                        <th>Monto</th>
                        <th>Pendiente</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registros)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px;">No hay registros pendientes con los filtros aplicados</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($registros as $registro): ?>
                            <tr>
                                <td><?= ucfirst($registro['tipo']) ?></td>
                                <td><?= $registro['id'] ?></td>
                                <td><?= $registro['fecha'] ?></td>
                                <td><?= htmlspecialchars($registro['conductor_nombre']) ?></td>
                                <td><?= number_format($registro['monto'], 0, ',', '.') ?> XAF</td>
                                <td><?= number_format($registro['saldo_pendiente'], 0, ',', '.') ?> XAF</td>
                                <td><?= htmlspecialchars($registro['descripcion']) ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <!-- Botón VER -->
                                        <button class="btn btn-ver" onclick="verRegistro(<?= htmlspecialchars(json_encode($registro), ENT_QUOTES, 'UTF-8') ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                        
                                        <!-- Botón PAGAR -->
                                        <button class="btn btn-pagar" onclick="pagarRegistro(<?= $registro['id'] ?>, <?= $registro['saldo_pendiente'] ?>, '<?= $registro['tipo'] ?>')">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                            </svg>
                                        </button>
                                        
                                        <!-- Botón ELIMINAR -->
                                        <button class="btn btn-eliminar" onclick="eliminarRegistro(<?= $registro['id'] ?>, '<?= $registro['tipo'] ?>')">
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
    
    <!-- Modal Agregar -->
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
                <h3 class="modal__title">Agregar Nuevo Registro</h3>
            </div>
            
            <div class="modal__body">
                <form id="formAgregar" method="post" class="modal__form">
                    <input type="hidden" name="accion" value="agregar">
                    <input type="hidden" name="tipo" id="agregarTipo" value="prestamo">
                    
                    <div class="modal__form-group">
                        <label for="agregarTipoRegistro" class="modal__form-label">Tipo</label>
                        <select name="tipo" id="agregarTipoRegistro" class="modal__form-input" required>
                            <option value="prestamo">Préstamo</option>
                            <option value="amonestacion">Amonestación</option>
                        </select>
                    </div>
                    
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
                    Cancelar
                </button>
                <button type="button" class="modal__action-btn modal__action-btn--primary" onclick="confirmarAgregar()">
                    Guardar
                </button>
            </div>
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
                <h3 class="modal__title">Detalles del Registro</h3>
            </div>
            
            <div class="modal__body" style="display: flex; flex-wrap: wrap; gap: 20px;">
                <div id="detallePrestamo"></div>
            </div>
            
            <div class="modal__footer">
                <button type="button" class="modal__action-btn" onclick="cerrarModal('modalVer')">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Pagar -->
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
                <h3 class="modal__title">Registrar Pago</h3>
            </div>
            
            <div class="modal__body">
                <form id="formPagar" method="post" class="modal__form">
                    <input type="hidden" name="accion" value="pagar">
                    <input type="hidden" name="registro_id" id="pagarRegistroId">
                    <input type="hidden" name="tipo" id="pagarTipo">
                    
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
                    Cancelar
                </button>
                <button type="button" class="modal__action-btn modal__action-btn--primary" onclick="confirmarPago()">
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
                <p>¿Estás seguro de que deseas eliminar este registro?</p>
                <p><strong>Esta acción no se puede deshacer.</strong></p>
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
    // Función para ver detalles del registro
    function verRegistro(registro) {
    const datos = [
        { label: 'TIPO', value: registro.tipo === 'prestamo' ? 'Préstamo' : 'Amonestación' },
        { label: 'CONDUCTOR', value: registro.conductor_nombre || 'N/A' },
        { label: 'FECHA', value: registro.fecha || 'N/A' },
        { label: 'MONTO TOTAL', value: (registro.monto ? numberFormat(registro.monto) + ' XAF' : 'N/A') },
        { label: 'MONTO PAGADO', value: (registro.monto_pagado ? numberFormat(registro.monto_pagado) + ' XAF' : '0 XAF') },
        { label: 'SALDO PENDIENTE', value: (registro.saldo_pendiente ? numberFormat(registro.saldo_pendiente) + ' XAF' : '0 XAF') },
        { label: 'ESTADO', value: registro.estado === 'pendiente' ? 'Pendiente' : 'Pagado' },
        { label: 'DESCRIPCIÓN', value: registro.descripcion || 'N/A' }
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
    
    document.getElementById('detallePrestamo').innerHTML = html;
    abrirModal('modalVer');
}

    // Función para abrir modal de pago
    function pagarRegistro(registroId, saldoPendiente, tipo) {
        document.getElementById('pagarRegistroId').value = registroId;
        document.getElementById('pagarTipo').value = tipo;
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
        
        const saldoText = document.getElementById('pagarSaldoPendiente').value;
        const saldoPendiente = parseFloat(saldoText.replace(/[^0-9]/g, ''));
        
        if (montoPagar > saldoPendiente) {
            alert('El monto a pagar no puede ser mayor que el saldo pendiente');
            montoInput.focus();
            return;
        }
        
        const fechaInput = document.getElementById('pagarFecha');
        if (!fechaInput.value) {
            alert('Debe especificar una fecha');
            fechaInput.focus();
            return;
        }
        
        if (!confirm(`¿Confirmas el pago de ${montoPagar} XAF?`)) {
            return;
        }
        
        form.submit();
    }

    // Función para abrir modal de confirmación de eliminación
    function eliminarRegistro(registroId, tipo) {
        document.getElementById('modalConfirmarEliminar').dataset.registroId = registroId;
        document.getElementById('modalConfirmarEliminar').dataset.tipo = tipo;
        abrirModal('modalConfirmarEliminar');
    }

    // Función para confirmar eliminación
    function confirmarEliminar() {
        const registroId = document.getElementById('modalConfirmarEliminar').dataset.registroId;
        const tipo = document.getElementById('modalConfirmarEliminar').dataset.tipo;
        
        if (confirm(`¿Estás completamente seguro de que deseas eliminar este ${tipo}?\n\nNOTA: No podrás eliminar registros que ya tienen pagos registrados.\nEsta acción no se puede deshacer.`)) {
            window.location.href = `prestamos.php?eliminar=${registroId}&tipo=${tipo}`;
        }
    }

    // Función para confirmar agregar registro
    function confirmarAgregar() {
        const form = document.getElementById('formAgregar');
        const tipo = document.getElementById('agregarTipoRegistro').value;
        const conductorId = document.getElementById('agregarConductorId').value;
        const monto = document.getElementById('agregarMonto').value;
        
        document.getElementById('agregarTipo').value = tipo;
        
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

    // Actualizar tipo al cambiar selección
    document.getElementById('agregarTipoRegistro').addEventListener('change', function() {
        document.getElementById('agregarTipo').value = this.value;
    });

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