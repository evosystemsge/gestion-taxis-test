<?php
include '../../layout/header.php';
require '../../config/database.php';

// Obtener información de la caja predeterminada
$stmt = $pdo->prepare("SELECT id, nombre, saldo_actual FROM cajas WHERE id = 1");
$stmt->execute();
$caja = $stmt->fetch();
$caja_id = $caja['id'] ?? 1;
$nombre_caja = $caja['nombre'] ?? 'Caja Principal';
$saldo_caja = $caja['saldo_actual'] ?? 0;

// ==============================
// LÓGICA DE ACCIONES: Agregar / Editar / Eliminar / Pagar
// ==============================
if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    // Agregar ingreso
    if ($accion == 'agregar') {
        $fecha = $_POST['fecha'];
        $conductor_id = $_POST['conductor_id'];
        $tipo_ingreso = $_POST['tipo_ingreso'];
        $monto_ingresado = $_POST['monto_ingresado'];
        $kilometros = $_POST['kilometros'];

        // Validar fecha única para el conductor
        $stmt = $pdo->prepare("SELECT id FROM ingresos WHERE conductor_id = ? AND fecha = ?");
        $stmt->execute([$conductor_id, $fecha]);
        if ($stmt->fetch()) {
            die("<script>alert('Ya existe un ingreso para este conductor en la fecha seleccionada.'); window.location.href='ingresos.php';</script>");
        }

        // Obtener datos del conductor
        $stmt = $pdo->prepare("SELECT * FROM conductores WHERE id = ?");
        $stmt->execute([$conductor_id]);
        $conductor = $stmt->fetch();

        // Obtener último ingreso del conductor
        $stmt = $pdo->prepare("SELECT * FROM ingresos WHERE conductor_id = ? ORDER BY fecha DESC, id DESC LIMIT 1");
        $stmt->execute([$conductor_id]);
        $ultimo_ingreso = $stmt->fetch();

        // Calcular monto esperado (versión mejorada)
        $monto_base = ($tipo_ingreso == 'obligatorio') ? 
            ($conductor['ingreso_obligatorio'] ?? 0) : 
            ($conductor['ingreso_libre'] ?? 0);
        
        // Saldo pendiente del último ingreso (si existe)
        $saldo_pendiente = $ultimo_ingreso ? ($ultimo_ingreso['monto_pendiente'] ?? 0) : 0;
        $monto_esperado = $monto_base + $saldo_pendiente;

        // Calcular recorrido (kilometros actuales - kilometros del último registro)
        $recorrido = 0;
        if ($ultimo_ingreso && $ultimo_ingreso['kilometros'] > 0) {
            $recorrido = $kilometros - $ultimo_ingreso['kilometros'];
        }

        // Calcular ciclo
        if ($tipo_ingreso == 'obligatorio') {
            if (!$ultimo_ingreso || ($ultimo_ingreso['ciclo_completado'] ?? 0)) {
                $numero_ciclo = 1;
                
                if ($ultimo_ingreso && isset($ultimo_ingreso['ciclo'])) {
                    $partes_ciclo = explode("-", $ultimo_ingreso['ciclo']);
                    if (count($partes_ciclo) > 1) {
                        $numero_ciclo = intval($partes_cilco[1]) + 1;
                    }
                }
                
                $nuevo_ciclo = "Mes-" . $numero_ciclo;
                $contador_ciclo = 1;
                $ciclo_completado = 0;
            } else {
                $nuevo_ciclo = $ultimo_ingreso['ciclo'] ?? 'Mes-1';
                $contador_ciclo = ($ultimo_ingreso['contador_ciclo'] ?? 0) + 1;
                $ciclo_completado = ($contador_ciclo >= ($conductor['dias_por_ciclo'] ?? 30)) ? 1 : 0;
            }
        } else {
            $nuevo_ciclo = $ultimo_ingreso ? ($ultimo_ingreso['ciclo'] ?? 'Mes-1') : 'Mes-1';
            $contador_ciclo = $ultimo_ingreso ? ($ultimo_ingreso['contador_ciclo'] ?? 0) : 0;
            $ciclo_completado = $ultimo_ingreso ? ($ultimo_ingreso['ciclo_completado'] ?? 0) : 0;
        }

        // Iniciar transacción
        $pdo->beginTransaction();
        
        try {
            // 1. Insertar ingreso
            $stmt = $pdo->prepare("
                INSERT INTO ingresos (
                    fecha, conductor_id, tipo_ingreso, monto_ingresado, monto_esperado, 
                    monto_pendiente, kilometros, recorrido, caja_id, ciclo, contador_ciclo, ciclo_completado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $fecha, $conductor_id, $tipo_ingreso, $monto_ingresado, $monto_esperado,
                ($monto_esperado - $monto_ingresado), $kilometros, $recorrido, $caja_id, $nuevo_ciclo, $contador_ciclo, $ciclo_completado
            ]);

            // Obtener el ID del ingreso recién insertado
            $ingreso_id = $pdo->lastInsertId();

            // 2. Actualizar kilómetros del vehículo
            if (isset($conductor['vehiculo_id'])) {
                $stmt = $pdo->prepare("UPDATE vehiculos SET km_actual = ? WHERE id = ?");
                $stmt->execute([$kilometros, $conductor['vehiculo_id']]);
            }

            // 3. Actualizar saldo en la caja
            $stmt = $pdo->prepare("UPDATE cajas SET saldo_actual = saldo_actual + ? WHERE id = ?");
            $stmt->execute([$monto_ingresado, $caja_id]);

            // 4. Registrar movimiento en caja
            $stmt = $pdo->prepare("
                INSERT INTO movimientos_caja (
                    caja_id, ingreso_id, tipo, monto, descripcion
                ) VALUES (?, ?, 'ingreso', ?, ?)
            ");
            $descripcion = "Ingreso de " . ($conductor['nombre'] ?? 'conductor');
            $stmt->execute([
                $caja_id, 
                $ingreso_id,
                $monto_ingresado,
                $descripcion
            ]);

            $pdo->commit();
            
            header("Location: ingresos.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            die("<script>alert('Error al registrar el ingreso: " . addslashes($e->getMessage()) . "'); window.location.href='ingresos.php';</script>");
        }
    }

    // Eliminar ingreso
    elseif ($accion == 'eliminar') {
        $id = $_POST['id'];
        
        $pdo->beginTransaction();
        try {
            // Obtener el ingreso para saber el monto a revertir
            $stmt = $pdo->prepare("SELECT monto_ingresado, caja_id FROM ingresos WHERE id = ?");
            $stmt->execute([$id]);
            $ingreso = $stmt->fetch();
            
            if ($ingreso) {
                // Revertir el saldo en la caja
                $stmt = $pdo->prepare("UPDATE cajas SET saldo_actual = saldo_actual - ? WHERE id = ?");
                $stmt->execute([$ingreso['monto_ingresado'], $ingreso['caja_id']]);
                
                // Eliminar el movimiento de caja asociado
                $stmt = $pdo->prepare("DELETE FROM movimientos_caja WHERE ingreso_id = ?");
                $stmt->execute([$id]);
                
                // Eliminar el ingreso
                $stmt = $pdo->prepare("DELETE FROM ingresos WHERE id = ?");
                $stmt->execute([$id]);
            }
            
            $pdo->commit();
            echo "<script>alert('Ingreso eliminado correctamente.'); window.location.href='ingresos.php';</script>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<script>alert('Error al eliminar el ingreso: " . addslashes($e->getMessage()) . "'); window.location.href='ingresos.php';</script>";
        }
    }
    
    // Pagar ingreso (NUEVA SECCIÓN)
    elseif ($accion == 'pagar') {
        $ingreso_id = $_POST['ingreso_id'];
        $monto_pagado = $_POST['monto'];
        
        $pdo->beginTransaction();
        try {
            // 1. Obtener el ingreso actual
            $stmt = $pdo->prepare("SELECT * FROM ingresos WHERE id = ?");
            $stmt->execute([$ingreso_id]);
            $ingreso = $stmt->fetch();
            
            if (!$ingreso) {
                throw new Exception("Ingreso no encontrado");
            }
            
            // Validar que el monto a pagar no exceda el pendiente
            if ($monto_pagado > $ingreso['monto_pendiente']) {
                throw new Exception("El monto a pagar no puede ser mayor al pendiente");
            }
            
            // 2. Actualizar el ingreso (sumar al monto existente)
            $nuevo_ingresado = $ingreso['monto_ingresado'] + $monto_pagado;
            $nuevo_pendiente = $ingreso['monto_esperado'] - $nuevo_ingresado;
            
            $stmt = $pdo->prepare("
                UPDATE ingresos SET 
                    monto_ingresado = ?,
                    monto_pendiente = ?
                WHERE id = ?
            ");
            $stmt->execute([$nuevo_ingresado, $nuevo_pendiente, $ingreso_id]);
            
            // 3. Actualizar saldo en caja
            $stmt = $pdo->prepare("UPDATE cajas SET saldo_actual = saldo_actual + ? WHERE id = ?");
            $stmt->execute([$monto_pagado, $ingreso['caja_id']]);
            
            // 4. Registrar movimiento en caja
            $stmt = $pdo->prepare("
                INSERT INTO movimientos_caja (
                    caja_id, ingreso_id, tipo, monto, descripcion
                ) VALUES (?, ?, 'pago', ?, ?)
            ");
            $descripcion = "Pago de saldo pendiente - Ingreso ID: ".$ingreso_id;
            $stmt->execute([
                $ingreso['caja_id'], 
                $ingreso_id,
                $monto_pagado,
                $descripcion
            ]);
            
            $pdo->commit();
            echo "<script>alert('Pago registrado correctamente.'); window.location.href='ingresos.php';</script>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<script>alert('Error al registrar pago: " . addslashes($e->getMessage()) . "'); window.location.href='ingresos.php';</script>";
        }
    }
}

// Obtener todos los ingresos con datos de conductor y vehículo
$ingresos = $pdo->query("
    SELECT i.*, c.nombre AS conductor_nombre, v.marca, v.modelo, v.matricula, v.km_actual, c.dias_por_ciclo
    FROM ingresos i
    LEFT JOIN conductores c ON i.conductor_id = c.id
    LEFT JOIN vehiculos v ON c.vehiculo_id = v.id
    ORDER BY i.fecha DESC
")->fetchAll();

// Obtener conductores activos con vehículo asignado para el formulario
$conductores = $pdo->query("
    SELECT c.id, c.nombre, c.ingreso_obligatorio, c.ingreso_libre 
    FROM conductores c
    WHERE c.estado = 'activo' AND c.vehiculo_id IS NOT NULL
    ORDER BY c.nombre
")->fetchAll();

// Actualizar saldo de caja después de posibles operaciones
$stmt = $pdo->prepare("SELECT saldo_actual FROM cajas WHERE id = ?");
$stmt->execute([$caja_id]);
$saldo_caja = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ingresos</title>
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
        --color-pagar: #20c997; /* Nuevo color para pagar */
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
        min-width: 36px;
        height: 36px;
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
    }
    </style>
</head>
<body>
    <div class="container">
        <h2>Lista de Ingresos</h2>
        
        <!-- Controles de tabla -->
        <div class="table-controls">
            <input type="text" id="searchInput" placeholder="Buscar ingreso..." class="modal__form-input">
            <select id="filterConductor" class="modal__form-input">
                <option value="">Todos los conductores</option>
                <?php foreach ($conductores as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filterTipo" class="modal__form-input">
                <option value="">Todos los tipos</option>
                <option value="obligatorio">Obligatorio</option>
                <option value="libre">Libre</option>
            </select>
            <button id="openModalAgregar" class="btn btn-nuevo">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Nuevo Ingreso
            </button>
        </div>
        
        <!-- Tabla de ingresos -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Conductor</th>
                        <th>Vehículo</th>
                        <th>Ingresado</th>
                        <th>Pendiente</th>
                        <th>Recorrido</th>
                        <th>Ciclo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($ingresos as $ing): 
                        // Clase para resaltar filas con pendientes
                        $clasePendiente = $ing['monto_pendiente'] > 0 ? 'alerta-pendiente' : '';
                    ?>
                        <tr class="<?= $clasePendiente ?>" 
                            data-conductor="<?= $ing['conductor_id'] ?>" 
                            data-tipo="<?= $ing['tipo_ingreso'] ?>" 
                            data-ciclo="<?= $ing['ciclo'] ?>" 
                            data-fecha="<?= $ing['fecha'] ?>">
                            <td><?= $ing['id'] ?></td>
                            <td><?= $ing['fecha'] ?></td>
                            <td><?= htmlspecialchars($ing['conductor_nombre']) ?></td>
                            <td><?= htmlspecialchars($ing['marca']) ?> <?= htmlspecialchars($ing['modelo']) ?> (<?= htmlspecialchars($ing['matricula']) ?>)</td>
                            <td><?= number_format($ing['monto_ingresado'], 0, ',', '.') ?> XAF</td>
                            <td><?= number_format($ing['monto_pendiente'], 0, ',', '.') ?> XAF</td>
                            <td><?= $ing['recorrido'] > 0 ? number_format($ing['recorrido'], 0, ',', '.') . ' km' : '-' ?></td>
                            <td>
                                Mes-<?= htmlspecialchars($ing['ciclo']) ?>
                                <?= $ing['tipo_ingreso'] == 'obligatorio' ? " ({$ing['contador_ciclo']}/{$ing['dias_por_ciclo']})" : "" ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: nowrap;">
                                    <!-- 1. Botón VER -->
                                    <button class="btn btn-ver" onclick="verIngreso(<?= htmlspecialchars(json_encode($ing), ENT_QUOTES, 'UTF-8') ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                    
                                    <!-- 2. Botón PAGAR (solo visible si hay pendiente) -->
                                    <?php if($ing['monto_pendiente'] > 0): ?>
                                        <button class="btn btn-pagar" onclick="pagarIngreso(<?= $ing['id'] ?>, <?= $ing['monto_pendiente'] ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="12" y1="19" x2="12" y2="5"></line>
                                                <polyline points="5 12 12 5 19 12"></polyline>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <!-- 3. Botón ELIMINAR -->
                                    <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar este ingreso? Se revertirá el saldo en caja.')">
                                        <input type="hidden" name="id" value="<?= $ing['id'] ?>">
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
        <button class="action-button" id="btnAddNew" title="Nuevo ingreso">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
        </button>
    </div>
    
    <!-- ============ MODALES ============ -->
    
    <!-- Modal Agregar Ingreso -->
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
                <h3 class="modal__title">Agregar Nuevo Ingreso</h3>
            </div>
            
            <div class="modal__body">
                <form id="formIngreso" method="post" class="modal__form">
                    <input type="hidden" name="accion" id="formAccion" value="agregar">
                    <input type="hidden" name="id" id="ingresoId">
                    
                    <div class="modal__form-group">
                        <label for="fecha" class="modal__form-label">Fecha</label>
                        <input type="date" name="fecha" id="fecha" class="modal__form-input" required>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="conductor_id" class="modal__form-label">Conductor</label>
                        <select name="conductor_id" id="conductor_id" class="modal__form-input" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($conductores as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="tipo_ingreso" class="modal__form-label">Tipo de Ingreso</label>
                        <select name="tipo_ingreso" id="tipo_ingreso" class="modal__form-input" required>
                            <option value="obligatorio">Obligatorio</option>
                            <option value="libre">Libre</option>
                        </select>
                    </div>
                    
                    <div class="modal__form-row">
                        <div class="modal__form-group">
                            <label for="monto_esperado" class="modal__form-label">Monto Esperado</label>
                            <input type="number" name="monto_esperado" id="monto_esperado" class="modal__form-input" readonly>
                        </div>
                        
                        <div class="modal__form-group">
                            <label for="monto_ingresado" class="modal__form-label">Monto Ingresado</label>
                            <input type="number" name="monto_ingresado" id="monto_ingresado" class="modal__form-input" required>
                        </div>
                    </div>
                    
                    <div class="modal__form-row">
                        <div class="modal__form-group">
                            <label for="kilometros" class="modal__form-label">Kilómetros</label>
                            <input type="number" name="kilometros" id="kilometros" class="modal__form-input" required>
                        </div>
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
                <button type="submit" class="modal__action-btn modal__action-btn--primary" form="formIngreso">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Guardar Ingreso
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Ver Ingreso -->
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
                <h3 class="modal__title">Detalles del Ingreso</h3>
            </div>
            
            <div class="modal__body">
                <div id="detalleIngreso" style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Contenido dinámico aquí -->
                </div>
            </div>
            
            <div class="modal__footer">
                <button class="modal__action-btn modal__action-btn--primary" onclick="editarIngreso()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Editar
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
                <h3 class="modal__title">Pagar Ingreso Pendiente</h3>
            </div>
            
            <div class="modal__body">
                <form id="formPagar" method="post" class="modal__form">
                    <input type="hidden" name="accion" value="pagar">
                    <input type="hidden" name="ingreso_id" id="pagarIngresoId">
                    
                    <div class="modal__form-group">
                        <label class="modal__form-label">Monto Pendiente</label>
                        <input type="text" id="pagarMontoPendiente" class="modal__form-input" readonly>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="pagarMonto" class="modal__form-label">Monto a Pagar</label>
                        <input type="number" name="monto" id="pagarMonto" class="modal__form-input" required min="1">
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
                <button type="submit" class="modal__action-btn modal__action-btn--primary" form="formPagar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    Confirmar Pago
                </button>
            </div>
        </div>
    </div>
    
    <script>
    // Variables globales
    let currentIngreso = null;
    let currentPage = 1;
    const rowsPerPage = 10;
    let filteredData = [];
    let conductoresData = <?= json_encode($conductores) ?>;
    
    // Inicialización al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        initEventListeners();
        setupPagination();
        updateTable();
        document.getElementById('fecha').valueAsDate = new Date();
    });
    
    // Configurar eventos
    function initEventListeners() {
        // Botón Agregar
        document.getElementById('openModalAgregar').addEventListener('click', () => abrirModal('modalAgregar', 'agregar'));
        document.getElementById('btnAddNew').addEventListener('click', () => abrirModal('modalAgregar', 'agregar'));
        
        // Botón Ir Arriba
        document.getElementById('btnScrollTop').addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    
        // Eventos de formulario
        document.getElementById('conductor_id').addEventListener('change', calcularMontoEsperado);
        document.getElementById('tipo_ingreso').addEventListener('change', calcularMontoEsperado);
    
        // Filtros
        document.getElementById('filterConductor').addEventListener('change', () => {
            currentPage = 1;
            updateTable();
        });
        
        document.getElementById('filterTipo').addEventListener('change', () => {
            currentPage = 1;
            updateTable();
        });
    
        // Buscador
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentPage = 1;
                updateTable();
            }, 300);
        });
        
        // Paginación
        document.getElementById('prevPage').addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                updateTable();
            }
        });
        
        document.getElementById('nextPage').addEventListener('click', () => {
            const totalPages = Math.ceil(filteredData.length / rowsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                updateTable();
            }
        });
    }
    
    // Función para abrir el modal de pago
    function pagarIngreso(ingresoId, montoPendiente) {
        document.getElementById('pagarIngresoId').value = ingresoId;
        document.getElementById('pagarMontoPendiente').value = numberFormat(montoPendiente) + ' XAF';
        document.getElementById('pagarMonto').value = montoPendiente;
        document.getElementById('pagarMonto').max = montoPendiente;
        abrirModal('modalPagar');
    }
    
    // Abrir modal (agregar/editar)
    function abrirModal(modalId, accion = null, ingresoId = null) {
        const modal = document.getElementById(modalId);
        
        // Configuración específica para modalAgregar
        if (modalId === 'modalAgregar') {
            const form = document.getElementById('formIngreso');
            document.querySelector(`#${modalId} .modal__title`).textContent = 
                (accion === 'agregar') ? 'Agregar Ingreso' : 'Editar Ingreso';
            document.getElementById('formAccion').value = accion;
        
            if (accion === 'agregar') {
                form.reset();
                document.getElementById('fecha').valueAsDate = new Date();
                document.getElementById('fecha').readOnly = false;
                document.getElementById('kilometros').readOnly = false;
            }
        
            if (accion === 'editar' && ingresoId) {
                const ingreso = obtenerIngresoPorId(ingresoId);
                if (ingreso) {
                    currentIngreso = ingreso;
                    document.getElementById('ingresoId').value = ingreso.id;
                    document.getElementById('fecha').value = ingreso.fecha;
                    document.getElementById('conductor_id').value = ingreso.conductor_id;
                    document.getElementById('tipo_ingreso').value = ingreso.tipo_ingreso;
                    document.getElementById('monto_esperado').value = ingreso.monto_esperado;
                    document.getElementById('monto_ingresado').value = ingreso.monto_ingresado;
                    document.getElementById('kilometros').value = ingreso.kilometros;
                    
                    if (hayRegistrosPosteriores(ingreso.conductor_id, ingreso.fecha)) {
                        document.getElementById('fecha').readOnly = true;
                        document.getElementById('kilometros').readOnly = true;
                    }
                }
            }
        }
        
        modal.style.display = 'block';
    }
    
    // Cerrar modal
    function cerrarModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    // Actualizar tabla con filtros y paginación
    function updateTable() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const conductorFilter = document.getElementById('filterConductor').value;
        const tipoFilter = document.getElementById('filterTipo').value;
        
        const rows = document.querySelectorAll('#tableBody tr');
        filteredData = [];
        
        rows.forEach(row => {
            const conductor = row.getAttribute('data-conductor');
            const tipo = row.getAttribute('data-tipo');
            const texto = row.textContent.toLowerCase();
            
            const matchesSearch = texto.includes(searchTerm);
            const matchesConductor = !conductorFilter || conductor === conductorFilter;
            const matchesTipo = !tipoFilter || tipo === tipoFilter;
            
            if (matchesSearch && matchesConductor && matchesTipo) {
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
        
        rows.forEach(row => {
            if (paginatedData.includes(row)) {
                row.style.display = '';
            } else if (filteredData.includes(row)) {
                row.style.display = 'none';
            }
        });
        
        // Actualizar controles
        const totalPages = Math.ceil(filteredData.length / rowsPerPage);
        document.getElementById('prevPage').disabled = currentPage === 1;
        document.getElementById('nextPage').disabled = currentPage === totalPages || totalPages === 0;
        document.getElementById('pageInfo').textContent = `Página ${currentPage} de ${totalPages}`;
    }
    
    // Configurar paginación
    function setupPagination() {
        const totalRows = document.querySelectorAll('#tableBody tr').length;
        const totalPages = Math.ceil(totalRows / rowsPerPage);
        
        document.getElementById('prevPage').disabled = currentPage === 1;
        document.getElementById('nextPage').disabled = currentPage === totalPages;
        document.getElementById('pageInfo').textContent = `Página ${currentPage} de ${totalPages}`;
    }
    
    // Calcular monto esperado automáticamente
    function calcularMontoEsperado() {
        const conductorId = document.getElementById('conductor_id').value;
        const tipoIngreso = document.getElementById('tipo_ingreso').value;
        
        if (!conductorId) return;
    
        const conductor = conductoresData.find(c => c.id == conductorId);
        if (!conductor) return;
    
        const montoEsperado = (tipoIngreso === 'obligatorio') ? 
            (conductor.ingreso_obligatorio || 0) : 
            (conductor.ingreso_libre || 0);
        
        document.getElementById('monto_esperado').value = montoEsperado;
    }
    
    // Función para obtener ingreso por ID
    function obtenerIngresoPorId(id) {
        const table = document.getElementById('tableBody');
        const rows = table.getElementsByTagName('tr');
        
        for (let row of rows) {
            const rowId = row.cells[0].textContent;
            if (rowId == id) {
                return {
                    id: id,
                    fecha: row.cells[1].textContent,
                    conductor_id: row.getAttribute('data-conductor'),
                    tipo_ingreso: row.getAttribute('data-tipo') === 'obligatorio' ? 'obligatorio' : 'libre',
                    monto_esperado: parseFloat(row.cells[5].textContent.replace(/\./g, '').replace(' XAF', '')),
                    monto_ingresado: parseFloat(row.cells[4].textContent.replace(/\./g, '').replace(' XAF', '')),
                    kilometros: parseFloat(row.cells[6].textContent.split(' ')[0])
                };
            }
        }
        return null;
    }
    
    // Verificar si hay registros posteriores (para bloquear edición)
    function hayRegistrosPosteriores(conductorId, fecha) {
        const table = document.getElementById('tableBody');
        const rows = table.getElementsByTagName('tr');
        
        for (let row of rows) {
            const rowConductor = row.getAttribute('data-conductor');
            const rowFecha = row.cells[1].textContent;
            
            if (rowConductor == conductorId && rowFecha > fecha) {
                return true;
            }
        }
        return false;
    }
    
    // Funciones para los botones de acciones
    function verIngreso(ingreso) {
        currentIngreso = ingreso;
        
        const columna1 = [
            { label: 'FECHA', value: ingreso.fecha || 'N/A' },
            { label: 'CONDUCTOR', value: ingreso.conductor_nombre || 'N/A' },
            { label: 'VEHÍCULO', value: (ingreso.marca ? `${ingreso.marca} ${ingreso.modelo} (${ingreso.matricula})` : 'N/A') },
            { label: 'TIPO DE INGRESO', value: ingreso.tipo_ingreso === 'obligatorio' ? 'Obligatorio' : 'Libre' },
            { label: 'KILÓMETROS', value: (ingreso.kilometros ? numberFormat(ingreso.kilometros) + ' km' : 'N/A') }
        ];
        
        const columna2 = [
            { label: 'MONTO ESPERADO', value: (ingreso.monto_esperado ? numberFormat(ingreso.monto_esperado) + ' XAF' : 'N/A') },
            { label: 'MONTO INGRESADO', value: (ingreso.monto_ingresado ? numberFormat(ingreso.monto_ingresado) + ' XAF' : 'N/A') },
            { label: 'PENDIENTE', value: (ingreso.monto_pendiente ? numberFormat(ingreso.monto_pendiente) + ' XAF' : 'N/A') },
            { label: 'RECORRIDO', value: (ingreso.recorrido > 0 ? numberFormat(ingreso.recorrido) + ' km' : 'N/A') },
            { label: 'CICLO', value: ingreso.ciclo ? `Mes-${ingreso.ciclo} (${ingreso.contador_ciclo}/${ingreso.dias_por_ciclo})` : 'N/A' }
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
        `;
        
        document.getElementById('detalleIngreso').innerHTML = html;
        abrirModal('modalVer');
    }
    
    function editarIngreso(id = null) {
        if (!id && currentIngreso) id = currentIngreso.id;
        if (id) {
            abrirModal('modalAgregar', 'editar', id);
            cerrarModal('modalVer');
        }
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
    </script>
    <?php include '../../layout/footer.php'; ?>
</body>
</html>