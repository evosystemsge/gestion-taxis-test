<?php
include '../../layout/header.php';
require '../../config/database.php';

// Obtener la caja predeterminada al inicio del script
$caja_predeterminada = $pdo->query("SELECT id FROM cajas WHERE predeterminada = 1 LIMIT 1")->fetch();
if (!$caja_predeterminada) {
    die("No se ha configurado una caja predeterminada");
}
$caja_id = $caja_predeterminada['id'];

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

// Determinar la pestaña activa
$tab_activa = $_GET['tab'] ?? 'ingresos-pendientes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Deudas</title>
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
        min-width: 100px;
        max-width: 200px;
        padding: 5px 5px;
        border: 1px solid var(--color-borde);
        border-radius: 6px;
        font-size: 0.95rem;
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
    
    /* Estilos para los modales y botones */
    .modal__action-btn--danger {
        background-color: var(--color-peligro);
        border-color: var(--color-peligro);
        color: white;
    }
    
    .modal__action-btn--danger:hover {
        background-color: #c82333;
    }
    
    .historial-container {
        margin-top: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid var(--color-borde);
    }
    
    .historial-title {
        color: var(--color-primario);
        margin-bottom: 15px;
        font-size: 1.1rem;
        font-weight: 600;
    }
    
    .historial-content {
        max-height: 300px;
        overflow-y: auto;
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
            <div class="tab <?= $tab_activa === 'ingresos-pendientes' ? 'active' : '' ?>" data-tab="ingresos-pendientes">Ingresos Pendientes</div>
            <div class="tab <?= $tab_activa === 'prestamos' ? 'active' : '' ?>" data-tab="prestamos">Préstamos</div>
            <div class="tab <?= $tab_activa === 'amonestaciones' ? 'active' : '' ?>" data-tab="amonestaciones">Amonestaciones</div>
            <div class="tab <?= $tab_activa === 'historial' ? 'active' : '' ?>" data-tab="historial">Historial de Pagos</div>
            <div class="tab <?= $tab_activa === 'conductores' ? 'active' : '' ?>" data-tab="conductores">Lista de Conductores</div>
            <div class="tab <?= $tab_activa === 'salarios' ? 'active' : '' ?>" data-tab="salarios">Salarios</div>
        </div>
        
        <!-- Contenido de pestañas -->
        
        <!-- Pestaña Ingresos Pendientes -->
        <div class="tab-content <?= $tab_activa === 'ingresos-pendientes' ? 'active' : '' ?>" id="ingresos-pendientes-content">
            <?php include 'ingresos-pendientes.php'; ?>
        </div>
        
        <!-- Pestaña Préstamos -->
        <div class="tab-content <?= $tab_activa === 'prestamos' ? 'active' : '' ?>" id="prestamos-content">
            <?php include 'prestamos.php'; ?>
        </div>
        
        <!-- Pestaña Amonestaciones -->
        <div class="tab-content <?= $tab_activa === 'amonestaciones' ? 'active' : '' ?>" id="amonestaciones-content">
            <?php include 'amonestaciones.php'; ?>
        </div>
        
        <!-- Pestaña Historial de Pagos -->
        <div class="tab-content <?= $tab_activa === 'historial' ? 'active' : '' ?>" id="historial-content">
            <?php include 'historial-pagos.php'; ?>
        </div>
        
        <!-- Pestaña Lista de Conductores -->
        <div class="tab-content <?= $tab_activa === 'conductores' ? 'active' : '' ?>" id="conductores-content">
            <?php include 'lista-conductores.php'; ?>
        </div>
        
        <!-- Pestaña Salarios -->
        <div class="tab-content <?= $tab_activa === 'salarios' ? 'active' : '' ?>" id="salarios-content">
            <?php include 'salarios.php'; ?>
        </div>
        
        <!-- Botones flotantes -->
        <div class="action-buttons">
            <button class="action-button" onclick="abrirModalNuevoPrestamo()">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
            </button>
            <button class="action-button" onclick="abrirModalNuevaAmonestacion()">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="16"></line>
                    <line x1="8" y1="12" x2="16" y2="12"></line>
                </svg>
            </button>
        </div>
        
        <!-- Modal para nuevo préstamo -->
        <div class="modal" id="modalNuevoPrestamo">
            <div class="modal__overlay" onclick="cerrarModal('modalNuevoPrestamo')"></div>
            <div class="modal__container">
                <div class="modal__close" onclick="cerrarModal('modalNuevoPrestamo')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </div>
                <div class="modal__header">
                    <h3 class="modal__title">Nuevo Préstamo</h3>
                </div>
                <form id="formNuevoPrestamo" method="post" action="deudas.php?tab=prestamos">
                    <div class="modal__body">
                        <div class="modal__form-group">
                            <label class="modal__form-label">Conductor</label>
                            <select name="conductor_id" class="modal__form-input" required>
                                <option value="">Seleccionar conductor</option>
                                <?php foreach ($conductores as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal__form-row">
                            <div class="modal__form-group">
                                <label class="modal__form-label">Fecha</label>
                                <input type="date" name="fecha" class="modal__form-input" required>
                            </div>
                            <div class="modal__form-group">
                                <label class="modal__form-label">Monto</label>
                                <input type="number" name="monto" class="modal__form-input" min="1" required>
                            </div>
                        </div>
                        <div class="modal__form-group">
                            <label class="modal__form-label">Descripción</label>
                            <textarea name="descripcion" class="modal__form-input" rows="3"></textarea>
                        </div>
                        <input type="hidden" name="accion" value="agregar_prestamo">
                        <input type="hidden" name="tab" value="<?= $tab_activa ?>">
                    </div>
                    <div class="modal__footer">
                        <button type="button" class="modal__action-btn" onclick="cerrarModal('modalNuevoPrestamo')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Cancelar
                        </button>
                        <button type="submit" class="modal__action-btn modal__action-btn--primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2v20M2 12h20"></path>
                            </svg>
                            Registrar Préstamo
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal para nueva amonestación -->
        <div class="modal" id="modalNuevaAmonestacion">
            <div class="modal__overlay" onclick="cerrarModal('modalNuevaAmonestacion')"></div>
            <div class="modal__container">
                <div class="modal__close" onclick="cerrarModal('modalNuevaAmonestacion')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </div>
                <div class="modal__header">
                    <h3 class="modal__title">Nueva Amonestación</h3>
                </div>
                <form id="formNuevaAmonestacion" method="post" action="deudas.php?tab=amonestaciones">
                    <div class="modal__body">
                        <div class="modal__form-group">
                            <label class="modal__form-label">Conductor</label>
                            <select name="conductor_id" class="modal__form-input" required>
                                <option value="">Seleccionar conductor</option>
                                <?php foreach ($conductores as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal__form-row">
                            <div class="modal__form-group">
                                <label class="modal__form-label">Fecha</label>
                                <input type="date" name="fecha" class="modal__form-input" required>
                            </div>
                            <div class="modal__form-group">
                                <label class="modal__form-label">Monto</label>
                                <input type="number" name="monto" class="modal__form-input" min="1" required>
                            </div>
                        </div>
                        <div class="modal__form-group">
                            <label class="modal__form-label">Descripción</label>
                            <textarea name="descripcion" class="modal__form-input" rows="3"></textarea>
                        </div>
                        <input type="hidden" name="accion" value="agregar_amonestacion">
                        <input type="hidden" name="tab" value="<?= $tab_activa ?>">
                    </div>
                    <div class="modal__footer">
                        <button type="button" class="modal__action-btn" onclick="cerrarModal('modalNuevaAmonestacion')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Cancelar
                        </button>
                        <button type="submit" class="modal__action-btn modal__action-btn--primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2v20M2 12h20"></path>
                            </svg>
                            Registrar Amonestación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // ============ FUNCIONES GENERALES ============
    function abrirModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    function cerrarModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    
    function imprimirContenido(elementId) {
        const contenido = document.getElementById(elementId).innerHTML;
        const ventana = window.open('', '', 'width=800,height=600');
        ventana.document.write('<html><head><title>Nómina de Pago</title>');
        ventana.document.write('<style>');
        ventana.document.write('body { font-family: Arial, sans-serif; margin: 20px; }');
        ventana.document.write('h1 { color: #004b87; text-align: center; }');
        ventana.document.write('table { width: 100%; border-collapse: collapse; margin: 20px 0; }');
        ventana.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
        ventana.document.write('th { background-color: #f2f2f2; }');
        ventana.document.write('.firma { margin-top: 50px; border-top: 1px solid #000; width: 300px; text-align: center; }');
        ventana.document.write('</style>');
        ventana.document.write('</head><body>');
        ventana.document.write(contenido);
        ventana.document.write('</body></html>');
        ventana.document.close();
        ventana.focus();
        setTimeout(() => {
            ventana.print();
        }, 500);
    }
    
    // ============ FUNCIONES DE PESTAÑAS ============
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            // Actualizar URL sin recargar la página
            history.pushState(null, null, `?tab=${tabId}`);
            
            // Desactivar todas las pestañas y contenidos
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            // Activar la pestaña y contenido seleccionados
            this.classList.add('active');
            document.getElementById(tabId + '-content').classList.add('active');
        });
    });
    
    // Manejar el evento popstate (cuando el usuario navega hacia atrás/adelante)
    window.addEventListener('popstate', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const tabId = urlParams.get('tab') || 'ingresos-pendientes';
        
        // Desactivar todas las pestañas y contenidos
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        // Activar la pestaña correspondiente
        const tabToActivate = document.querySelector(`.tab[data-tab="${tabId}"]`);
        if (tabToActivate) {
            tabToActivate.classList.add('active');
            document.getElementById(tabId + '-content').classList.add('active');
        }
    });
    
    // ============ FUNCIONES PARA MODALES ============
    function abrirModalNuevoPrestamo() {
        // Establecer la fecha actual por defecto
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('#formNuevoPrestamo input[name="fecha"]').value = today;
        abrirModal('modalNuevoPrestamo');
    }
    
    function abrirModalNuevaAmonestacion() {
        // Establecer la fecha actual por defecto
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('#formNuevaAmonestacion input[name="fecha"]').value = today;
        abrirModal('modalNuevaAmonestacion');
    }
    </script>
    <?php include '../../layout/footer.php'; ?>
</body>
</html>