<?php
include '../../layout/header.php';
require '../../config/database.php';

// ==============================
// LÓGICA DE ACCIONES: Agregar / Editar / Eliminar
// ==============================
if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    // Agregar préstamo
    if ($accion == 'agregar_prestamo') {
        $fecha = $_POST['fecha'];
        $conductor_id = $_POST['conductor_id'];
        $monto = $_POST['monto'];
        $descripcion = $_POST['descripcion'];

        $pdo->beginTransaction();
        try {
            // Insertar préstamo
            $stmt = $pdo->prepare("
                INSERT INTO prestamos (fecha, conductor_id, monto, descripcion, saldo_pendiente, estado)
                VALUES (?, ?, ?, ?, ?, 'pendiente')
            ");
            $stmt->execute([$fecha, $conductor_id, $monto, $descripcion, $monto]);
            
            $pdo->commit();
            header("Location: deudas.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("<script>alert('Error al registrar el préstamo: " . addslashes($e->getMessage()) . "'); window.location.href='deudas.php';</script>");
        }
    }

    // Agregar pago de préstamo
    elseif ($accion == 'agregar_pago_prestamo') {
        $prestamo_id = $_POST['prestamo_id'];
        $fecha = $_POST['fecha'];
        $monto = $_POST['monto'];
        $descripcion = $_POST['descripcion'];

        $pdo->beginTransaction();
        try {
            // Insertar pago
            $stmt = $pdo->prepare("
                INSERT INTO pagos_prestamos (prestamo_id, fecha, monto, descripcion)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$prestamo_id, $fecha, $monto, $descripcion]);

            // Actualizar saldo pendiente del préstamo
            $stmt = $pdo->prepare("
                UPDATE prestamos 
                SET saldo_pendiente = saldo_pendiente - ?
                WHERE id = ?
            ");
            $stmt->execute([$monto, $prestamo_id]);

            // Verificar si el préstamo está completamente pagado
            $stmt = $pdo->prepare("SELECT saldo_pendiente FROM prestamos WHERE id = ?");
            $stmt->execute([$prestamo_id]);
            $saldo = $stmt->fetchColumn();

            if ($saldo <= 0) {
                $stmt = $pdo->prepare("UPDATE prestamos SET estado = 'pagado' WHERE id = ?");
                $stmt->execute([$prestamo_id]);
            }

            $pdo->commit();
            header("Location: deudas.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("<script>alert('Error al registrar el pago: " . addslashes($e->getMessage()) . "'); window.location.href='deudas.php';</script>");
        }
    }

    // Agregar amonestación
    elseif ($accion == 'agregar_amonestacion') {
        $fecha = $_POST['fecha'];
        $conductor_id = $_POST['conductor_id'];
        $monto = $_POST['monto'];
        $descripcion = $_POST['descripcion'];

        $stmt = $pdo->prepare("
            INSERT INTO amonestaciones (fecha, conductor_id, monto, descripcion, estado)
            VALUES (?, ?, ?, ?, 'activa')
        ");
        $stmt->execute([$fecha, $conductor_id, $monto, $descripcion]);
        
        header("Location: deudas.php");
        exit;
    }

    // Agregar pago de amonestación
    elseif ($accion == 'agregar_pago_amonestacion') {
        $amonestacion_id = $_POST['amonestacion_id'];
        $fecha = $_POST['fecha'];
        $monto = $_POST['monto'];
        $descripcion = $_POST['descripcion'];

        $pdo->beginTransaction();
        try {
            // Insertar pago
            $stmt = $pdo->prepare("
                INSERT INTO pagos_amonestaciones (amonestacion_id, fecha, monto, descripcion)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$amonestacion_id, $fecha, $monto, $descripcion]);

            // Actualizar estado de la amonestación si se paga completo
            $stmt = $pdo->prepare("SELECT monto FROM amonestaciones WHERE id = ?");
            $stmt->execute([$amonestacion_id]);
            $monto_amonestacion = $stmt->fetchColumn();

            if ($monto >= $monto_amonestacion) {
                $stmt = $pdo->prepare("UPDATE amonestaciones SET estado = 'pagada' WHERE id = ?");
                $stmt->execute([$amonestacion_id]);
            }

            $pdo->commit();
            header("Location: deudas.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("<script>alert('Error al registrar el pago: " . addslashes($e->getMessage()) . "'); window.location.href='deudas.php';</script>");
        }
    }

    // Pagar ingreso pendiente
    elseif ($accion == 'pagar_ingreso_pendiente') {
        $conductor_id = $_POST['conductor_id'];
        $fecha = $_POST['fecha'];
        $monto = $_POST['monto'];
        $descripcion = $_POST['descripcion'];

        $pdo->beginTransaction();
        try {
            // Registrar pago
            $stmt = $pdo->prepare("
                INSERT INTO pagos_ingresos_pendientes (conductor_id, fecha, monto, descripcion)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$conductor_id, $fecha, $monto, $descripcion]);

            // Actualizar ingresos pendientes del conductor
            $stmt = $pdo->prepare("
                UPDATE ingresos 
                SET monto_pendiente = monto_pendiente - ?
                WHERE conductor_id = ? AND monto_pendiente > 0
                ORDER BY fecha DESC
                LIMIT 1
            ");
            $stmt->execute([$monto, $conductor_id]);

            $pdo->commit();
            header("Location: deudas.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("<script>alert('Error al registrar el pago: " . addslashes($e->getMessage()) . "'); window.location.href='deudas.php';</script>");
        }
    }

    // Cambiar estado de amonestación
    elseif ($accion == 'cambiar_estado_amonestacion') {
        $id = $_POST['id'];
        $estado = $_POST['estado'];

        $stmt = $pdo->prepare("UPDATE amonestaciones SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $id]);
        
        header("Location: deudas.php");
        exit;
    }

    // Eliminar préstamo
    elseif ($accion == 'eliminar_prestamo') {
        $id = $_POST['id'];
        
        $pdo->beginTransaction();
        try {
            // Eliminar pagos asociados
            $stmt = $pdo->prepare("DELETE FROM pagos_prestamos WHERE prestamo_id = ?");
            $stmt->execute([$id]);
            
            // Eliminar préstamo
            $stmt = $pdo->prepare("DELETE FROM prestamos WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            echo "<script>alert('Préstamo eliminado correctamente.'); window.location.href='deudas.php';</script>";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<script>alert('Error al eliminar el préstamo: " . addslashes($e->getMessage()) . "'); window.location.href='deudas.php';</script>";
        }
    }

    // Eliminar amonestación
    elseif ($accion == 'eliminar_amonestacion') {
        $id = $_POST['id'];
        
        $pdo->beginTransaction();
        try {
            // Eliminar pagos asociados
            $stmt = $pdo->prepare("DELETE FROM pagos_amonestaciones WHERE amonestacion_id = ?");
            $stmt->execute([$id]);
            
            // Eliminar amonestación
            $stmt = $pdo->prepare("DELETE FROM amonestaciones WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            echo "<script>alert('Amonestación eliminada correctamente.'); window.location.href='deudas.php';</script>";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<script>alert('Error al eliminar la amonestación: " . addslashes($e->getMessage()) . "'); window.location.href='deudas.php';</script>";
        }
    }
}

// Obtener conductores para selects
$conductores = $pdo->query("SELECT id, nombre FROM conductores ORDER BY nombre")->fetchAll();

// Obtener resumen de deudas por conductor
$resumenDeudas = $pdo->query("
    SELECT 
        c.id AS conductor_id,
        c.nombre AS conductor_nombre,
        COALESCE(SUM(i.monto_pendiente), 0) AS total_pendiente,
        COALESCE(SUM(p.saldo_pendiente), 0) AS total_prestamos,
        COALESCE(SUM(CASE WHEN a.estado = 'activa' THEN a.monto ELSE 0 END), 0) AS total_amonestaciones
    FROM conductores c
    LEFT JOIN ingresos i ON c.id = i.conductor_id AND i.monto_pendiente > 0
    LEFT JOIN prestamos p ON c.id = p.conductor_id AND p.estado = 'pendiente'
    LEFT JOIN amonestaciones a ON c.id = a.conductor_id AND a.estado = 'activa'
    GROUP BY c.id, c.nombre
    HAVING total_pendiente > 0 OR total_prestamos > 0 OR total_amonestaciones > 0
")->fetchAll();

// Obtener ingresos pendientes
$ingresosPendientes = $pdo->query("
    SELECT i.*, c.nombre AS conductor_nombre
    FROM ingresos i
    JOIN conductores c ON i.conductor_id = c.id
    WHERE i.monto_pendiente > 0
    ORDER BY i.fecha DESC
")->fetchAll();

// Obtener préstamos
$prestamos = $pdo->query("
    SELECT p.*, c.nombre AS conductor_nombre
    FROM prestamos p
    JOIN conductores c ON p.conductor_id = c.id
    ORDER BY p.fecha DESC
")->fetchAll();

// Obtener amonestaciones
$amonestaciones = $pdo->query("
    SELECT a.*, c.nombre AS conductor_nombre
    FROM amonestaciones a
    JOIN conductores c ON a.conductor_id = c.id
    ORDER BY a.fecha DESC
")->fetchAll();

// Obtener historial de pagos
$historialPagos = $pdo->query("
    (SELECT 'prestamo' AS tipo, pp.id, pp.fecha, pp.monto, pp.descripcion, c.nombre AS conductor_nombre, p.id AS referencia_id
     FROM pagos_prestamos pp
     JOIN prestamos p ON pp.prestamo_id = p.id
     JOIN conductores c ON p.conductor_id = c.id)
    
    UNION ALL
    
    (SELECT 'amonestacion' AS tipo, pa.id, pa.fecha, pa.monto, pa.descripcion, c.nombre AS conductor_nombre, a.id AS referencia_id
     FROM pagos_amonestaciones pa
     JOIN amonestaciones a ON pa.amonestacion_id = a.id
     JOIN conductores c ON a.conductor_id = c.id)
    
    UNION ALL
    
    (SELECT 'ingreso' AS tipo, pip.id, pip.fecha, pip.monto, pip.descripcion, c.nombre AS conductor_nombre, NULL AS referencia_id
     FROM pagos_ingresos_pendientes pip
     JOIN conductores c ON pip.conductor_id = c.id)
    
    ORDER BY fecha DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Deudas</title>
    <style>
    /* ============ ESTILOS PRINCIPALES (COPIADOS DE INGRESOS.PHP) ============ */
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
    
    /* ============ PESTAÑAS ============ */
    .tabs {
        display: flex;
        border-bottom: 1px solid var(--color-borde);
        margin-bottom: 20px;
    }
    
    .tab {
        padding: 10px 20px;
        cursor: pointer;
        border: 1px solid transparent;
        border-bottom: none;
        border-radius: 4px 4px 0 0;
        margin-right: 5px;
        font-weight: 500;
        transition: all 0.2s;
    }
    
    .tab:hover {
        background-color: rgba(0, 75, 135, 0.05);
    }
    
    .tab.active {
        border-color: var(--color-borde);
        border-bottom-color: #fff;
        background: #fff;
        color: var(--color-primario);
        font-weight: 600;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
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
    
    /* Estilo para filas con pendientes */
    .table tr.alerta-pendiente td {
        background-color: #ffebee !important;
        color: #dc3545 !important;
        font-weight: bold;
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
    
    .modal__title--highlight {
        color: var(--color-primario);
        font-weight: 600;
    }
    
    .modal__body {
        padding: 0 24px;
        overflow-y: auto;
        flex-grow: 1;
    }
    
    .modal__form {
        display: flex;
        flex-direction: column;
        gap: 16px;
        padding: 16px 0;
    }
    
    .modal__form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .modal__form-row {
        display: flex;
        gap: 16px;
    }
    
    .modal__form-row .modal__form-group {
        flex: 1;
    }
    
    .modal__form-label {
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 600;
    }
    
    .modal__form-input {
        padding: 10px 12px;
        border: 1px solid var(--color-borde);
        border-radius: 6px;
        font-size: 0.95rem;
        transition: border-color 0.2s ease;
        width: 100%;
    }
    
    .modal__form-input:focus {
        outline: none;
        border-color: var(--color-primario);
        box-shadow: 0 0 0 3px rgba(0, 75, 135, 0.1);
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
    
    /* ============ TABLA DE DETALLES ============ */
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
        
        .modal__container {
            max-width: 98%;
            max-height: 98vh;
        }
        
        .modal__form-row {
            flex-direction: column;
            gap: 16px;
        }
        
        .table-controls input, 
        .table-controls select {
            max-width: 100%;
        }
        
        .modal__footer {
            flex-direction: column;
        }
        
        .modal__action-btn {
            width: 100%;
            justify-content: center;
        }
        
        .tabs {
            flex-wrap: wrap;
        }
        
        .tab {
            flex: 1;
            min-width: 120px;
            text-align: center;
        }
    }
    
    /* ============ RESÚMENES ============ */
    .resumen-container {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid var(--color-borde);
    }
    
    .resumen-title {
        color: var(--color-primario);
        margin-bottom: 15px;
        font-size: 1.1rem;
        font-weight: 600;
    }
    
    .resumen-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 15px;
    }
    
    .resumen-item {
        background: white;
        border-radius: 6px;
        padding: 15px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        border: 1px solid var(--color-borde);
    }
    
    .resumen-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .resumen-item-title {
        font-weight: 600;
        color: var(--color-primario);
    }
    
    .resumen-item-value {
        font-size: 1.2rem;
        font-weight: 600;
    }
    
    .resumen-item-value.pendiente {
        color: var(--color-peligro);
    }
    
    .resumen-item-value.pagado {
        color: var(--color-exito);
    }
    
    .resumen-item-details {
        font-size: 0.85rem;
        color: #64748b;
    }
    
    /* ============ BADGES DE ESTADO ============ */
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .badge-pendiente {
        background-color: #ffebee;
        color: #dc3545;
    }
    
    .badge-pagado {
        background-color: #e8f5e9;
        color: #28a745;
    }
    
    .badge-anulado {
        background-color: #e3f2fd;
        color: #2196f3;
    }
    
    .badge-activo {
        background-color: #fff8e1;
        color: #ff9800;
    }
    </style>
</head>
<body>
    <div class="container">
        <h2>Gestión de Deudas</h2>
        
        <!-- Resumen de deudas por conductor -->
        <div class="resumen-container">
            <h3 class="resumen-title">Resumen de Deudas por Conductor</h3>
            <div class="resumen-grid">
                <?php foreach ($resumenDeudas as $resumen): ?>
                    <div class="resumen-item">
                        <div class="resumen-item-header">
                            <span class="resumen-item-title"><?= htmlspecialchars($resumen['conductor_nombre']) ?></span>
                            <button class="btn btn-ver" onclick="verDetalleConductor(<?= $resumen['conductor_id'] ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                        <div class="resumen-item-details">
                            <div>Ingresos pendientes: <span class="resumen-item-value <?= $resumen['total_pendiente'] > 0 ? 'pendiente' : '' ?>">
                                <?= number_format($resumen['total_pendiente'], 0, ',', '.') ?> XAF</span></div>
                            <div>Préstamos pendientes: <span class="resumen-item-value <?= $resumen['total_prestamos'] > 0 ? 'pendiente' : '' ?>">
                                <?= number_format($resumen['total_prestamos'], 0, ',', '.') ?> XAF</span></div>
                            <div>Amonestaciones: <span class="resumen-item-value <?= $resumen['total_amonestaciones'] > 0 ? 'pendiente' : '' ?>">
                                <?= number_format($resumen['total_amonestaciones'], 0, ',', '.') ?> XAF</span></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Pestañas -->
        <div class="tabs">
            <div class="tab active" data-tab="ingresos-pendientes">Ingresos Pendientes</div>
            <div class="tab" data-tab="prestamos">Préstamos</div>
            <div class="tab" data-tab="amonestaciones">Amonestaciones</div>
            <div class="tab" data-tab="historial">Historial de Pagos</div>
        </div>
        
        <!-- Controles de tabla -->
        <div class="table-controls">
            <input type="text" id="searchInput" placeholder="Buscar..." class="modal__form-input">
            <select id="filterConductor" class="modal__form-input">
                <option value="">Todos los conductores</option>
                <?php foreach ($conductores as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filterEstado" class="modal__form-input" style="display: none;">
                <option value="">Todos los estados</option>
                <option value="pendiente">Pendiente</option>
                <option value="pagado">Pagado</option>
                <option value="anulado">Anulado</option>
                <option value="activo">Activo</option>
            </select>
            <button id="openModalNuevo" class="btn btn-nuevo" style="display: none;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Nuevo
            </button>
        </div>
        
        <!-- Contenido de pestañas -->
        
        <!-- Pestaña Ingresos Pendientes -->
        <div class="tab-content active" id="ingresos-pendientes-content">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Conductor</th>
                            <th>Monto Esperado</th>
                            <th>Monto Ingresado</th>
                            <th>Pendiente</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tableBodyIngresos">
                        <?php foreach ($ingresosPendientes as $ingreso): ?>
                            <tr data-conductor="<?= $ingreso['conductor_id'] ?>">
                                <td><?= $ingreso['id'] ?></td>
                                <td><?= $ingreso['fecha'] ?></td>
                                <td><?= htmlspecialchars($ingreso['conductor_nombre']) ?></td>
                                <td><?= number_format($ingreso['monto_esperado'], 0, ',', '.') ?> XAF</td>
                                <td><?= number_format($ingreso['monto_ingresado'], 0, ',', '.') ?> XAF</td>
                                <td><?= number_format($ingreso['monto_pendiente'], 0, ',', '.') ?> XAF</td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <button class="btn btn-ver" onclick="verIngreso(<?= htmlspecialchars(json_encode($ingreso), ENT_QUOTES, 'UTF-8') ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                        <button class="btn btn-nuevo" onclick="abrirModalPagoIngreso(<?= $ingreso['conductor_id'] ?>, <?= $ingreso['monto_pendiente'] ?>, '<?= htmlspecialchars($ingreso['conductor_nombre']) ?>')">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 2v20M2 12h20"></path>
                                            </svg>
                                            Pagar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pestaña Préstamos -->
        <div class="tab-content" id="prestamos-content">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Conductor</th>
                            <th>Monto</th>
                            <th>Saldo Pendiente</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tableBodyPrestamos">
                        <?php foreach ($prestamos as $prestamo): ?>
                            <tr data-conductor="<?= $prestamo['conductor_id'] ?>" data-estado="<?= $prestamo['estado'] ?>">
                                <td><?= $prestamo['id'] ?></td>
                                <td><?= $prestamo['fecha'] ?></td>
                                <td><?= htmlspecialchars($prestamo['conductor_nombre']) ?></td>
                                <td><?= number_format($prestamo['monto'], 0, ',', '.') ?> XAF</td>
                                <td><?= number_format($prestamo['saldo_pendiente'], 0, ',', '.') ?> XAF</td>
                                <td>
                                    <span class="badge badge-<?= $prestamo['estado'] ?>">
                                        <?= ucfirst($prestamo['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <button class="btn btn-ver" onclick="verPrestamo(<?= htmlspecialchars(json_encode($prestamo), ENT_QUOTES, 'UTF-8') ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                        <button class="btn btn-nuevo" onclick="abrirModalPagoPrestamo(<?= $prestamo['id'] ?>, <?= $prestamo['saldo_pendiente'] ?>, '<?= htmlspecialchars($prestamo['conductor_nombre']) ?>')">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 2v20M2 12h20"></path>
                                            </svg>
                                            Pagar
                                        </button>
                                        <button class="btn btn-editar" onclick="editarPrestamo(<?= $prestamo['id'] ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </button>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar este préstamo?')">
                                            <input type="hidden" name="id" value="<?= $prestamo['id'] ?>">
                                            <input type="hidden" name="accion" value="eliminar_prestamo">
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
        </div>
        
        <!-- Pestaña Amonestaciones -->
        <div class="tab-content" id="amonestaciones-content">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Conductor</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tableBodyAmonestaciones">
                        <?php foreach ($amonestaciones as $amonestacion): ?>
                            <tr data-conductor="<?= $amonestacion['conductor_id'] ?>" data-estado="<?= $amonestacion['estado'] ?>">
                                <td><?= $amonestacion['id'] ?></td>
                                <td><?= $amonestacion['fecha'] ?></td>
                                <td><?= htmlspecialchars($amonestacion['conductor_nombre']) ?></td>
                                <td><?= number_format($amonestacion['monto'], 0, ',', '.') ?> XAF</td>
                                <td>
                                    <span class="badge badge-<?= $amonestacion['estado'] ?>">
                                        <?= ucfirst($amonestacion['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <button class="btn btn-ver" onclick="verAmonestacion(<?= htmlspecialchars(json_encode($amonestacion), ENT_QUOTES, 'UTF-8') ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                        <button class="btn btn-nuevo" onclick="abrirModalPagoAmonestacion(<?= $amonestacion['id'] ?>, <?= $amonestacion['monto'] ?>, '<?= htmlspecialchars($amonestacion['conductor_nombre']) ?>')">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 2v20M2 12h20"></path>
                                            </svg>
                                            Pagar
                                        </button>
                                        <button class="btn btn-editar" onclick="editarAmonestacion(<?= $amonestacion['id'] ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </button>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar esta amonestación?')">
                                            <input type="hidden" name="id" value="<?= $amonestacion['id'] ?>">
                                            <input type="hidden" name="accion" value="eliminar_amonestacion">
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
        </div>
        
        <!-- Pestaña Historial de Pagos -->
        <div class="tab-content" id="historial-content">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Conductor</th>
                            <th>Tipo</th>
                            <th>Monto</th>
                            <th>Descripción</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tableBodyHistorial">
                        <?php foreach ($historialPagos as $pago): ?>
                            <tr data-conductor="<?= $pago['conductor_id'] ?>" data-tipo="<?= $pago['tipo'] ?>">
                                <td><?= $pago['id'] ?></td>
                                <td><?= $pago['fecha'] ?></td>
                                <td><?= htmlspecialchars($pago['conductor_nombre']) ?></td>
                                <td><?= ucfirst($pago['tipo']) ?></td>
                                <td><?= number_format($pago['monto'], 0, ',', '.') ?> XAF</td>
                                <td><?= htmlspecialchars($pago['descripcion']) ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <button class="btn btn-ver" onclick="verPago('<?= $pago['tipo'] ?>', <?= $pago['id'] ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
        <button class="action-button" id="btnAddNew" title="Nuevo registro" style="display: none;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
        </button>
    </div>
    
    <!-- ============ MODALES ============ -->
    
    <!-- Modal Agregar Préstamo -->
    <div id="modalAgregarPrestamo" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalAgregarPrestamo')"></div>
        <div class="modal__container">
            <button class="modal__close" onclick="cerrarModal('modalAgregarPrestamo')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Agregar Nuevo Préstamo</h3>
            </div>
            
            <div class="modal__body">
                <form method="post" class="modal__form">
                    <input type="hidden" name="accion" value="agregar_prestamo">
                    
                    <div class="modal__form-group">
                        <label for="prestamo_fecha" class="modal__form-label">Fecha</label>
                        <input type="date" name="fecha" id="prestamo_fecha" class="modal__form-input" required>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="prestamo_conductor_id" class="modal__form-label">Conductor</label>
                        <select name="conductor_id" id="prestamo_conductor_id" class="modal__form-input" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($conductores as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="modal__form-row">
                        <div class="modal__form-group">
                            <label for="prestamo_monto" class="modal__form-label">Monto</label>
                            <input type="number" name="monto" id="prestamo_monto" class="modal__form-input" min="1" required>
                        </div>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="prestamo_descripcion" class="modal__form-label">Descripción</label>
                        <textarea name="descripcion" id="prestamo_descripcion" class="modal__form-input" rows="3"></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal__footer">
                <button type="button" class="modal__action-btn" onclick="cerrarModal('modalAgregarPrestamo')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar
                </button>
                <button type="submit" class="modal__action-btn modal__action-btn--primary" form="modalAgregarPrestamoForm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Guardar Préstamo
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Pago de Préstamo -->
    <div id="modalPagoPrestamo" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalPagoPrestamo')"></div>
        <div class="modal__container">
            <button class="modal__close" onclick="cerrarModal('modalPagoPrestamo')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Registrar Pago de Préstamo</h3>
                <h4 class="modal__title--highlight" id="prestamoConductorNombre"></h4>
            </div>
            
            <div class="modal__body">
                <form method="post" class="modal__form" id="formPagoPrestamo">
                    <input type="hidden" name="accion" value="agregar_pago_prestamo">
                    <input type="hidden" name="prestamo_id" id="pago_prestamo_id">
                    
                    <div class="modal__form-group">
                        <label for="pago_prestamo_fecha" class="modal__form-label">Fecha</label>
                        <input type="date" name="fecha" id="pago_prestamo_fecha" class="modal__form-input" required>
                    </div>
                    
                    <div class="modal__form-row">
                        <div class="modal__form-group">
                            <label for="pago_prestamo_monto" class="modal__form-label">Monto</label>
                            <input type="number" name="monto" id="pago_prestamo_monto" class="modal__form-input" min="1" required>
                        </div>
                        <div class="modal__form-group">
                            <label for="pago_prestamo_saldo" class="modal__form-label">Saldo Pendiente</label>
                            <input type="number" id="pago_prestamo_saldo" class="modal__form-input" readonly>
                        </div>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="pago_prestamo_descripcion" class="modal__form-label">Descripción</label>
                        <textarea name="descripcion" id="pago_prestamo_descripcion" class="modal__form-input" rows="3"></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal__footer">
                <button type="button" class="modal__action-btn" onclick="cerrarModal('modalPagoPrestamo')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar
                </button>
                <button type="submit" class="modal__action-btn modal__action-btn--primary" form="formPagoPrestamo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Registrar Pago
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Ver Préstamo -->
    <div id="modalVerPrestamo" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalVerPrestamo')"></div>
        <div class="modal__container">
            <button class="modal__close" onclick="cerrarModal('modalVerPrestamo')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Detalles del Préstamo</h3>
                <h4 class="modal__title--highlight" id="verPrestamoConductorNombre"></h4>
            </div>
            
            <div class="modal__body">
                <div id="detallePrestamo" style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Contenido dinámico aquí -->
                </div>
                
                <!-- Historial de pagos -->
                <div class="historial-container">
                    <h4 class="historial-title">Historial de Pagos</h4>
                    <div id="historialPagosPrestamo" class="historial-content">
                        <!-- Contenido dinámico aquí -->
                    </div>
                </div>
            </div>
            
            <div class="modal__footer">
                <button class="modal__action-btn modal__action-btn--primary" onclick="editarPrestamo()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Editar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Agregar Amonestación -->
    <div id="modalAgregarAmonestacion" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalAgregarAmonestacion')"></div>
        <div class="modal__container">
            <button class="modal__close" onclick="cerrarModal('modalAgregarAmonestacion')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Agregar Nueva Amonestación</h3>
            </div>
            
            <div class="modal__body">
                <form method="post" class="modal__form">
                    <input type="hidden" name="accion" value="agregar_amonestacion">
                    
                    <div class="modal__form-group">
                        <label for="amonestacion_fecha" class="modal__form-label">Fecha</label>
                        <input type="date" name="fecha" id="amonestacion_fecha" class="modal__form-input" required>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="amonestacion_conductor_id" class="modal__form-label">Conductor</label>
                        <select name="conductor_id" id="amonestacion_conductor_id" class="modal__form-input" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($conductores as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="modal__form-row">
                        <div class="modal__form-group">
                            <label for="amonestacion_monto" class="modal__form-label">Monto</label>
                            <input type="number" name="monto" id="amonestacion_monto" class="modal__form-input" min="1" required>
                        </div>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="amonestacion_descripcion" class="modal__form-label">Descripción</label>
                        <textarea name="descripcion" id="amonestacion_descripcion" class="modal__form-input" rows="3"></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal__footer">
                <button type="button" class="modal__action-btn" onclick="cerrarModal('modalAgregarAmonestacion')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar
                </button>
                <button type="submit" class="modal__action-btn modal__action-btn--primary" form="modalAgregarAmonestacionForm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Guardar Amonestación
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Pago de Amonestación -->
    <div id="modalPagoAmonestacion" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalPagoAmonestacion')"></div>
        <div class="modal__container">
            <button class="modal__close" onclick="cerrarModal('modalPagoAmonestacion')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Registrar Pago de Amonestación</h3>
                <h4 class="modal__title--highlight" id="amonestacionConductorNombre"></h4>
            </div>
            
            <div class="modal__body">
                <form method="post" class="modal__form" id="formPagoAmonestacion">
                    <input type="hidden" name="accion" value="agregar_pago_amonestacion">
                    <input type="hidden" name="amonestacion_id" id="pago_amonestacion_id">
                    
                    <div class="modal__form-group">
                        <label for="pago_amonestacion_fecha" class="modal__form-label">Fecha</label>
                        <input type="date" name="fecha" id="pago_amonestacion_fecha" class="modal__form-input" required>
                    </div>
                    
                    <div class="modal__form-row">
                        <div class="modal__form-group">
                            <label for="pago_amonestacion_monto" class="modal__form-label">Monto</label>
                            <input type="number" name="monto" id="pago_amonestacion_monto" class="modal__form-input" min="1" required>
                        </div>
                        <div class="modal__form-group">
                            <label for="pago_amonestacion_total" class="modal__form-label">Monto Total</label>
                            <input type="number" id="pago_amonestacion_total" class="modal__form-input" readonly>
                        </div>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="pago_amonestacion_descripcion" class="modal__form-label">Descripción</label>
                        <textarea name="descripcion" id="pago_amonestacion_descripcion" class="modal__form-input" rows="3"></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal__footer">
                <button type="button" class="modal__action-btn" onclick="cerrarModal('modalPagoAmonestacion')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar
                </button>
                <button type="submit" class="modal__action-btn modal__action-btn--primary" form="formPagoAmonestacion">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Registrar Pago
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Ver Amonestación -->
    <div id="modalVerAmonestacion" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalVerAmonestacion')"></div>
        <div class="modal__container">
            <button class="modal__close" onclick="cerrarModal('modalVerAmonestacion')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Detalles de la Amonestación</h3>
                <h4 class="modal__title--highlight" id="verAmonestacionConductorNombre"></h4>
            </div>
            
            <div class="modal__body">
                <div id="detalleAmonestacion" style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Contenido dinámico aquí -->
                </div>
                
                <!-- Historial de pagos -->
                <div class="historial-container">
                    <h4 class="historial-title">Historial de Pagos</h4>
                    <div id="historialPagosAmonestacion" class="historial-content">
                        <!-- Contenido dinámico aquí -->
                    </div>
                </div>
            </div>
            
            <div class="modal__footer">
                <button class="modal__action-btn" onclick="cambiarEstadoAmonestacion('anulado')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                    </svg>
                    Anular
                </button>
                <button class="modal__action-btn" onclick="cambiarEstadoAmonestacion('pagada')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    Marcar como Pagada
                </button>
                <button class="modal__action-btn modal__action-btn--primary" onclick="editarAmonestacion()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Editar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Pago de Ingreso Pendiente -->
    <div id="modalPagoIngreso" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalPagoIngreso')"></div>
        <div class="modal__container">
            <button class="modal__close" onclick="cerrarModal('modalPagoIngreso')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Registrar Pago de Ingreso Pendiente</h3>
                <h4 class="modal__title--highlight" id="ingresoConductorNombre"></h4>
            </div>
            
            <div class="modal__body">
                <form method="post" class="modal__form" id="formPagoIngreso">
                    <input type="hidden" name="accion" value="pagar_ingreso_pendiente">
                    <input type="hidden" name="conductor_id" id="pago_ingreso_conductor_id">
                    
                    <div class="modal__form-group">
                        <label for="pago_ingreso_fecha" class="modal__form-label">Fecha</label>
                        <input type="date" name="fecha" id="pago_ingreso_fecha" class="modal__form-input" required>
                    </div>
                    
                    <div class="modal__form-row">
                        <div class="modal__form-group">
                            <label for="pago_ingreso_monto" class="modal__form-label">Monto</label>
                            <input type="number" name="monto" id="pago_ingreso_monto" class="modal__form-input" min="1" required>
                        </div>
                        <div class="modal__form-group">
                            <label for="pago_ingreso_pendiente" class="modal__form-label">Pendiente</label>
                            <input type="number" id="pago_ingreso_pendiente" class="modal__form-input" readonly>
                        </div>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="pago_ingreso_descripcion" class="modal__form-label">Descripción</label>
                        <textarea name="descripcion" id="pago_ingreso_descripcion" class="modal__form-input" rows="3"></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal__footer">
                <button type="button" class="modal__action-btn" onclick="cerrarModal('modalPagoIngreso')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar
                </button>
                <button type="submit" class="modal__action-btn modal__action-btn--primary" form="formPagoIngreso">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Registrar Pago
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Ver Pago -->
    <div id="modalVerPago" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalVerPago')"></div>
        <div class="modal__container">
            <button class="modal__close" onclick="cerrarModal('modalVerPago')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Detalles del Pago</h3>
            </div>
            
            <div class="modal__body">
                <div id="detallePago" style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Contenido dinámico aquí -->
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Variables globales
    let currentPage = 1;
    const rowsPerPage = 10;
    let filteredData = [];
    let currentTab = 'ingresos-pendientes';
    let currentPrestamo = null;
    let currentAmonestacion = null;
    let currentPago = null;
    
    // Inicialización al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        // Configurar eventos
        initEventListeners();
        // Configurar paginación
        setupPagination();
        // Mostrar primera página
        updateTable();
        
        // Establecer fecha actual por defecto en todos los modales
        const today = new Date().toISOString().split('T')[0];
        document.querySelectorAll('input[type="date"]').forEach(input => {
            if (!input.value) input.value = today;
        });
    });
    
    // Configurar eventos
    function initEventListeners() {
        // Pestañas
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                cambiarPestaña(tabId);
            });
        });
        
        // Botón "Nuevo"
        document.getElementById('openModalNuevo').addEventListener('click', function() {
            if (currentTab === 'prestamos') {
                abrirModal('modalAgregarPrestamo');
            } else if (currentTab === 'amonestaciones') {
                abrirModal('modalAgregarAmonestacion');
            }
        });
        
        // Botón flotante agregar
        document.getElementById('btnAddNew').addEventListener('click', function() {
            if (currentTab === 'prestamos') {
                abrirModal('modalAgregarPrestamo');
            } else if (currentTab === 'amonestaciones') {
                abrirModal('modalAgregarAmonestacion');
            }
        });
        
        // Botón flotante ir arriba
        document.getElementById('btnScrollTop').addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        // Eventos de filtros
        document.getElementById('filterConductor').addEventListener('change', function() {
            currentPage = 1;
            updateTable();
        });
        
        document.getElementById('filterEstado').addEventListener('change', function() {
            currentPage = 1;
            updateTable();
        });
        
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentPage = 1;
                updateTable();
            }, 300);
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
    }
    
    // Cambiar pestaña
    function cambiarPestaña(tabId) {
        currentTab = tabId;
        
        // Actualizar pestañas activas
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
            if (tab.getAttribute('data-tab') === tabId) {
                tab.classList.add('active');
            }
        });
        
        // Actualizar contenido de pestañas
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
            if (content.id === tabId + '-content') {
                content.classList.add('active');
            }
        });
        
        // Mostrar/ocultar controles según la pestaña
        const filterEstado = document.getElementById('filterEstado');
        const btnNuevo = document.getElementById('openModalNuevo');
        const btnAddNew = document.getElementById('btnAddNew');
        
        if (tabId === 'amonestaciones') {
            filterEstado.style.display = 'block';
            btnNuevo.style.display = 'inline-flex';
            btnAddNew.style.display = 'flex';
            document.getElementById('filterEstado').innerHTML = `
                <option value="">Todos los estados</option>
                <option value="activa">Activa</option>
                <option value="pagada">Pagada</option>
                <option value="anulado">Anulada</option>
            `;
        } else if (tabId === 'prestamos') {
            filterEstado.style.display = 'block';
            btnNuevo.style.display = 'inline-flex';
            btnAddNew.style.display = 'flex';
            document.getElementById('filterEstado').innerHTML = `
                <option value="">Todos los estados</option>
                <option value="pendiente">Pendiente</option>
                <option value="pagado">Pagado</option>
            `;
        } else {
            filterEstado.style.display = 'none';
            btnNuevo.style.display = 'none';
            btnAddNew.style.display = 'none';
        }
        
        // Reiniciar paginación
        currentPage = 1;
        updateTable();
    }
    
    // Configurar paginación
    function setupPagination() {
        const totalRows = document.querySelectorAll(`#${currentTab}-content #tableBody${currentTab.charAt(0).toUpperCase() + currentTab.slice(1)} tr`).length;
        const totalPages = Math.ceil(totalRows / rowsPerPage);
        
        document.getElementById('prevPage').disabled = currentPage === 1;
        document.getElementById('nextPage').disabled = currentPage === totalPages;
        document.getElementById('pageInfo').textContent = `Página ${currentPage} de ${totalPages}`;
    }
    
    // Actualizar tabla con filtros y paginación
    function updateTable() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const conductorFilter = document.getElementById('filterConductor').value;
        const estadoFilter = document.getElementById('filterEstado')?.value || '';
        
        let tableBodyId;
        if (currentTab === 'ingresos-pendientes') {
            tableBodyId = 'tableBodyIngresos';
        } else if (currentTab === 'prestamos') {
            tableBodyId = 'tableBodyPrestamos';
        } else if (currentTab === 'amonestaciones') {
            tableBodyId = 'tableBodyAmonestaciones';
        } else if (currentTab === 'historial') {
            tableBodyId = 'tableBodyHistorial';
        }
        
        const rows = document.querySelectorAll(`#${tableBodyId} tr`);
        filteredData = [];
        
        rows.forEach(row => {
            const conductor = row.getAttribute('data-conductor');
            const estado = row.getAttribute('data-estado') || '';
            const texto = row.textContent.toLowerCase();
            
            const matchesSearch = texto.includes(searchTerm);
            const matchesConductor = !conductorFilter || conductor === conductorFilter;
            const matchesEstado = !estadoFilter || estado === estadoFilter;
            
            if (matchesSearch && matchesConductor && matchesEstado) {
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
    
    // Abrir modal
    function abrirModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }
    
    // Cerrar modal
    function cerrarModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    // Abrir modal de pago de ingreso pendiente
    function abrirModalPagoIngreso(conductorId, montoPendiente, conductorNombre) {
        document.getElementById('pago_ingreso_conductor_id').value = conductorId;
        document.getElementById('pago_ingreso_pendiente').value = montoPendiente;
        document.getElementById('pago_ingreso_monto').max = montoPendiente;
        document.getElementById('pago_ingreso_monto').value = montoPendiente;
        document.getElementById('ingresoConductorNombre').textContent = conductorNombre;
        
        abrirModal('modalPagoIngreso');
    }
    
    // Abrir modal de pago de préstamo
    function abrirModalPagoPrestamo(prestamoId, saldoPendiente, conductorNombre) {
        document.getElementById('pago_prestamo_id').value = prestamoId;
        document.getElementById('pago_prestamo_saldo').value = saldoPendiente;
        document.getElementById('pago_prestamo_monto').max = saldoPendiente;
        document.getElementById('pago_prestamo_monto').value = saldoPendiente;
        document.getElementById('prestamoConductorNombre').textContent = conductorNombre;
        
        abrirModal('modalPagoPrestamo');
    }
    
    // Abrir modal de pago de amonestación
    function abrirModalPagoAmonestacion(amonestacionId, montoTotal, conductorNombre) {
        document.getElementById('pago_amonestacion_id').value = amonestacionId;
        document.getElementById('pago_amonestacion_total').value = montoTotal;
        document.getElementById('pago_amonestacion_monto').max = montoTotal;
        document.getElementById('pago_amonestacion_monto').value = montoTotal;
        document.getElementById('amonestacionConductorNombre').textContent = conductorNombre;
        
        abrirModal('modalPagoAmonestacion');
    }
    
    // Ver préstamo
    function verPrestamo(prestamo) {
        currentPrestamo = prestamo;
        
        // Datos para las columnas
        const columna1 = [
            { label: 'FECHA', value: prestamo.fecha || 'N/A' },
            { label: 'CONDUCTOR', value: prestamo.conductor_nombre || 'N/A' },
            { label: 'MONTO TOTAL', value: (prestamo.monto ? numberFormat(prestamo.monto) + ' XAF' : 'N/A') },
            { label: 'ESTADO', value: `<span class="badge badge-${prestamo.estado}">${ucfirst(prestamo.estado)}</span>` }
        ];
        
        const columna2 = [
            { label: 'SALDO PENDIENTE', value: (prestamo.saldo_pendiente ? numberFormat(prestamo.saldo_pendiente) + ' XAF' : 'N/A') },
            { label: 'DESCRIPCIÓN', value: prestamo.descripcion || 'N/A' },
            { label: 'REGISTRO', value: prestamo.id || 'N/A' }
        ];
        
        // Generar HTML para las tablas
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
        `;
        
        // Insertar todo el contenido
        document.getElementById('detallePrestamo').innerHTML = html;
        document.getElementById('verPrestamoConductorNombre').textContent = prestamo.conductor_nombre;
        
        // Cargar historial de pagos (simulado)
        let historialHtml = '<p>No hay pagos registrados</p>';
        if (prestamo.id) {
            // En una aplicación real, harías una petición AJAX para obtener los pagos
            historialHtml = `
                <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--color-borde);">
                    <div style="display: flex; justify-content: space-between;">
                        <span><strong>Fecha:</strong> 15/06/2023</span>
                        <span><strong>Monto:</strong> 50,000 XAF</span>
                    </div>
                    <div style="margin-top: 5px;"><strong>Descripción:</strong> Pago parcial</div>
                </div>
                <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--color-borde);">
                    <div style="display: flex; justify-content: space-between;">
                        <span><strong>Fecha:</strong> 01/06/2023</span>
                        <span><strong>Monto:</strong> 100,000 XAF</span>
                    </div>
                    <div style="margin-top: 5px;"><strong>Descripción:</strong> Pago inicial</div>
                </div>
            `;
        }
        
        document.getElementById('historialPagosPrestamo').innerHTML = historialHtml;
        
        // Mostrar modal
        abrirModal('modalVerPrestamo');
    }
    
    // Ver amonestación
    function verAmonestacion(amonestacion) {
        currentAmonestacion = amonestacion;
        
        // Datos para las columnas
        const columna1 = [
            { label: 'FECHA', value: amonestacion.fecha || 'N/A' },
            { label: 'CONDUCTOR', value: amonestacion.conductor_nombre || 'N/A' },
            { label: 'MONTO TOTAL', value: (amonestacion.monto ? numberFormat(amonestacion.monto) + ' XAF' : 'N/A') },
            { label: 'ESTADO', value: `<span class="badge badge-${amonestacion.estado}">${ucfirst(amonestacion.estado)}</span>` }
        ];
        
        const columna2 = [
            { label: 'DESCRIPCIÓN', value: amonestacion.descripcion || 'N/A' },
            { label: 'REGISTRO', value: amonestacion.id || 'N/A' }
        ];
        
        // Generar HTML para las tablas
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
        `;
        
        // Insertar todo el contenido
        document.getElementById('detalleAmonestacion').innerHTML = html;
        document.getElementById('verAmonestacionConductorNombre').textContent = amonestacion.conductor_nombre;
        
        // Cargar historial de pagos (simulado)
        let historialHtml = '<p>No hay pagos registrados</p>';
        if (amonestacion.id) {
            // En una aplicación real, harías una petición AJAX para obtener los pagos
            historialHtml = `
                <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--color-borde);">
                    <div style="display: flex; justify-content: space-between;">
                        <span><strong>Fecha:</strong> 15/06/2023</span>
                        <span><strong>Monto:</strong> 10,000 XAF</span>
                    </div>
                    <div style="margin-top: 5px;"><strong>Descripción:</strong> Pago parcial</div>
                </div>
            `;
        }
        
        document.getElementById('historialPagosAmonestacion').innerHTML = historialHtml;
        
        // Mostrar modal
        abrirModal('modalVerAmonestacion');
    }
    
    // Ver pago
    function verPago(tipo, pagoId) {
        // En una aplicación real, harías una petición AJAX para obtener los detalles del pago
        currentPago = {
            id: pagoId,
            tipo: tipo,
            fecha: '2023-06-15',
            monto: tipo === 'prestamo' ? 50000 : 10000,
            descripcion: tipo === 'prestamo' ? 'Pago parcial de préstamo' : 'Pago de amonestación',
            conductor_nombre: 'Conductor Ejemplo'
        };
        
        // Datos para las columnas
        const columna1 = [
            { label: 'FECHA', value: currentPago.fecha || 'N/A' },
            { label: 'CONDUCTOR', value: currentPago.conductor_nombre || 'N/A' },
            { label: 'TIPO', value: ucfirst(currentPago.tipo) || 'N/A' }
        ];
        
        const columna2 = [
            { label: 'MONTO', value: (currentPago.monto ? numberFormat(currentPago.monto) + ' XAF' : 'N/A') },
            { label: 'DESCRIPCIÓN', value: currentPago.descripcion || 'N/A' },
            { label: 'REGISTRO', value: currentPago.id || 'N/A' }
        ];
        
        // Generar HTML para las tablas
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
        `;
        
        // Insertar todo el contenido
        document.getElementById('detallePago').innerHTML = html;
        
        // Mostrar modal
        abrirModal('modalVerPago');
    }
    
    // Editar préstamo
    function editarPrestamo(prestamoId = null) {
        if (!prestamoId && currentPrestamo) {
            prestamoId = currentPrestamo.id;
        }
        
        if (prestamoId) {
            // En una aplicación real, harías una petición AJAX para obtener los datos del préstamo
            // y llenarías el formulario de edición
            alert('Funcionalidad de edición en desarrollo');
        }
    }
    
    // Editar amonestación
    function editarAmonestacion(amonestacionId = null) {
        if (!amonestacionId && currentAmonestacion) {
            amonestacionId = currentAmonestacion.id;
        }
        
        if (amonestacionId) {
            // En una aplicación real, harías una petición AJAX para obtener los datos de la amonestación
            // y llenarías el formulario de edición
            alert('Funcionalidad de edición en desarrollo');
        }
    }
    
    // Cambiar estado de amonestación
    function cambiarEstadoAmonestacion(estado) {
        if (!currentAmonestacion) return;
        
        if (confirm(`¿Cambiar estado de la amonestación a "${estado}"?`)) {
            // Crear formulario dinámico para enviar la solicitud
            const form = document.createElement('form');
            form.method = 'post';
            form.style.display = 'none';
            
            const inputAccion = document.createElement('input');
            inputAccion.type = 'hidden';
            inputAccion.name = 'accion';
            inputAccion.value = 'cambiar_estado_amonestacion';
            form.appendChild(inputAccion);
            
            const inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = 'id';
            inputId.value = currentAmonestacion.id;
            form.appendChild(inputId);
            
            const inputEstado = document.createElement('input');
            inputEstado.type = 'hidden';
            inputEstado.name = 'estado';
            inputEstado.value = estado;
            form.appendChild(inputEstado);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    // Ver detalle de conductor
    function verDetalleConductor(conductorId) {
        // En una aplicación real, redirigirías a la página del conductor o abrirías un modal
        alert(`Ver detalles del conductor ID: ${conductorId}`);
    }
    
    // Función para formatear números
    function numberFormat(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }
    
    // Función para capitalizar primera letra
    function ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    // Cerrar modal al hacer clic fuera del contenido
    window.onclick = function(event) {
        if (event.target.className === 'modal__overlay') {
            const modalId = event.target.parentElement.id;
            cerrarModal(modalId);
        }
    };
    </script>
    <?php include '../../layout/footer.php'; ?>
</body>
</html>