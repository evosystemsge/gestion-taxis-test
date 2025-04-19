<?php
require '../../config/database.php';

// Obtener parámetros de filtro
$search = isset($_GET['search']) ? $_GET['search'] : '';
$conductor_id = isset($_GET['conductor_id']) ? intval($_GET['conductor_id']) : null;
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : null;
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : null;
$tipo_ingreso = isset($_GET['tipo_ingreso']) ? $_GET['tipo_ingreso'] : null;

// Construir consulta base
$sql = "
    SELECT i.*, c.nombre AS conductor_nombre, v.marca, v.modelo, v.matricula, v.km_actual, c.dias_por_ciclo
    FROM ingresos i
    LEFT JOIN conductores c ON i.conductor_id = c.id
    LEFT JOIN vehiculos v ON c.vehiculo_id = v.id
    WHERE i.monto_pendiente > 0
";

// Aplicar filtros
$params = [];
if ($search) {
    $sql .= " AND (c.nombre LIKE ? OR v.matricula LIKE ? OR i.id LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($conductor_id) {
    $sql .= " AND i.conductor_id = ?";
    $params[] = $conductor_id;
}

if ($fecha_desde) {
    $sql .= " AND i.fecha >= ?";
    $params[] = $fecha_desde;
}

if ($fecha_hasta) {
    $sql .= " AND i.fecha <= ?";
    $params[] = $fecha_hasta;
}

if ($tipo_ingreso) {
    $sql .= " AND i.tipo_ingreso = ?";
    $params[] = $tipo_ingreso;
}

$sql .= " ORDER BY i.fecha DESC, i.conductor_id";

// Preparar y ejecutar consulta
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ingresos = $stmt->fetchAll();

// Obtener conductores activos para el filtro
$conductores = $pdo->query("
    SELECT c.id, c.nombre 
    FROM conductores c
    WHERE c.estado = 'activo' AND c.vehiculo_id IS NOT NULL
    ORDER BY c.nombre
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresos Pendientes</title>
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
    /*
    .container {
        max-width: 1250px;
        margin: 20px auto;
        background: #fff;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }*/
    /*.container {
        max-width: 1400px;
        margin: 0 auto; /* Cambiado para eliminar espacio superior 
        background: #fff;
        padding: 0 25px 25px 25px; /* Eliminado padding superior 
        border-radius: 0 0 10px 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }*/
    
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
<!--<div class="container">
<h1>Ingresos Pendientes</h1>-->
    <!-- Filtros -->
    <div class="filters-container" style="background: #f8f9fa; padding: 3px 15px; border-radius: 8px; margin: 1px 0;">
        <form id="filtersForm" method="get" class="modal__form" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 50px;">
            <div class="modal__form-group">
                <label for="search" class="modal__form-label">Buscar</label>
                <input type="text" name="search" id="search" class="modal__form-input" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Conductor, matrícula o ID">
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
                <label for="tipo_ingreso" class="modal__form-label">Tipo de Ingreso</label>
                <select name="tipo_ingreso" id="tipo_ingreso" class="modal__form-input">
                    <option value="">Todos</option>
                    <option value="obligatorio" <?= $tipo_ingreso == 'obligatorio' ? 'selected' : '' ?>>Obligatorio</option>
                    <option value="libre" <?= $tipo_ingreso == 'libre' ? 'selected' : '' ?>>Libre</option>
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
        </form>
    </div>
    
    <!-- Tabla de ingresos pendientes -->
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
                    <th>Tipo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ingresos)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px;">No hay ingresos pendientes con los filtros aplicados</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ingresos as $ing): ?>
                        <tr>
                            <td><?= $ing['id'] ?></td>
                            <td><?= $ing['fecha'] ?></td>
                            <td><?= htmlspecialchars($ing['conductor_nombre']) ?></td>
                            <td><?= htmlspecialchars($ing['marca']) ?> <?= htmlspecialchars($ing['modelo']) ?> (<?= htmlspecialchars($ing['matricula']) ?>)</td>
                            <td><?= number_format($ing['monto_ingresado'], 0, ',', '.') ?> XAF</td>
                            <td><?= number_format($ing['monto_pendiente'], 0, ',', '.') ?> XAF</td>
                            <td><?= $ing['tipo_ingreso'] == 'obligatorio' ? 'Obligatorio' : 'Libre' ?></td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <!-- Botón VER -->
                                    <button class="btn btn-ver" onclick="verIngreso(<?= htmlspecialchars(json_encode($ing), ENT_QUOTES, 'UTF-8') ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                    
                                    <!-- Botón COMPLETAR -->
                                    <button class="btn btn-pagar" onclick="pagarIngreso(<?= $ing['id'] ?>, <?= $ing['monto_pendiente'] ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<!--</div>-->

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
            <h3 class="modal__title">Detalles del Ingreso Pendiente</h3>
        </div>
        
        <div class="modal__body">
            <div id="detalleIngreso" style="display: flex; gap: 20px; flex-wrap: wrap;">
                <!-- Contenido dinámico aquí -->
            </div>
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
            <h3 class="modal__title">Completar Pago Pendiente</h3>
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
            <button type="button" class="modal__action-btn modal__action-btn--primary" onclick="confirmarPago()">
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
// Función para ver detalles del ingreso
function verIngreso(ingreso) {
    // Formatear el ciclo
    let cicloValue = 'N/A';
    if (ingreso.ciclo !== undefined && ingreso.ciclo !== null) {
        cicloValue = 'Mes-' + ingreso.ciclo;
        if (ingreso.tipo_ingreso === 'obligatorio') {
            const contador = ingreso.contador_ciclo || 0;
            const diasCiclo = ingreso.dias_por_ciclo || 30;
            cicloValue += ` (${contador}/${diasCiclo})`;
        }
    }

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
        { label: 'CICLO', value: cicloValue }
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

// Función para abrir modal de pago
function pagarIngreso(ingresoId, montoPendiente) {
    document.getElementById('pagarIngresoId').value = ingresoId;
    document.getElementById('pagarMontoPendiente').value = numberFormat(montoPendiente) + ' XAF';
    document.getElementById('pagarMonto').value = montoPendiente;
    document.getElementById('pagarMonto').max = montoPendiente;
    abrirModal('modalPagar');
}

// Función para confirmar el pago
function confirmarPago() {
    const form = document.getElementById('formPagar');
    const formData = new FormData(form);
    
    // Validar monto
    const montoPagar = parseFloat(document.getElementById('pagarMonto').value);
    const montoPendiente = parseFloat(document.getElementById('pagarMontoPendiente').value.replace(/\./g, '').replace(' XAF', ''));
    
    if (montoPagar <= 0) {
        alert('El monto a pagar debe ser mayor que cero');
        return;
    }
    
    if (montoPagar > montoPendiente) {
        alert('El monto a pagar no puede ser mayor que el pendiente');
        return;
    }
    
    // Enviar formulario
    fetch('/taxis/modules/ingresos/ingresos.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.ok) {
            return response.text();
        }
        throw new Error('Error en la respuesta del servidor');
    })
    .then(data => {
        // Recargar la página para ver los cambios
        window.location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al procesar el pago: ' + error.message);
    });
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

document.getElementById('fecha_desde').addEventListener('change', function() {
    document.getElementById('filtersForm').submit();
});

document.getElementById('fecha_hasta').addEventListener('change', function() {
    document.getElementById('filtersForm').submit();
});

document.getElementById('tipo_ingreso').addEventListener('change', function() {
    document.getElementById('filtersForm').submit();
});

document.getElementById('conductor_id').addEventListener('change', function() {
    document.getElementById('filtersForm').submit();
});
</script>
</body>
</html>