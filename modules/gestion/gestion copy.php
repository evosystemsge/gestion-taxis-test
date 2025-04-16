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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Deudas y Pagos</title>
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
        flex-wrap: wrap;
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
    
    .resumen-item-details {
        font-size: 0.85rem;
        color: #64748b;
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
    
    /* ============ RESPONSIVE ============ */
    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }
        
        .tabs {
            flex-wrap: wrap;
        }
        
        .tab {
            flex: 1;
            min-width: 120px;
            text-align: center;
        }
        
        .resumen-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <h2>Gestión de Deudas y Pagos</h2>
        
        <!-- Resumen de deudas por conductor -->
        <div class="resumen-container">
            <h3 class="resumen-title">Conductores con Deudas Pendientes</h3>
            <div class="resumen-grid">
                <?php if (count($resumenDeudas) > 0): ?>
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
                <?php else: ?>
                    <div class="resumen-item">
                        <div class="resumen-item-details">
                            No hay conductores con deudas pendientes
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pestañas -->
        <div class="tabs">
            <div class="tab active" data-tab="ingresos-pendientes">Ingresos Pendientes</div>
            <div class="tab" data-tab="prestamos">Préstamos</div>
            <div class="tab" data-tab="amonestaciones">Amonestaciones</div>
            <div class="tab" data-tab="historial-pagos">Historial de Pagos</div>
            <div class="tab" data-tab="salarios">Salarios</div>
            <div class="tab" data-tab="historial-salarios">Historial Salarios</div>
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
            <input type="date" id="filterFechaDesde" class="modal__form-input" placeholder="Fecha desde">
            <input type="date" id="filterFechaHasta" class="modal__form-input" placeholder="Fecha hasta">
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
            <?php include 'ingresos-pendientes.php'; ?>
        </div>
        
        <!-- Pestaña Préstamos -->
        <div class="tab-content" id="prestamos-content">
            <?php include 'prestamos.php'; ?>
        </div>
        
        <!-- Pestaña Amonestaciones -->
        <div class="tab-content" id="amonestaciones-content">
            <?php include 'amonestaciones.php'; ?>
        </div>
        
        <!-- Pestaña Historial de Pagos -->
        <div class="tab-content" id="historial-pagos-content">
            <?php include 'historial-pagos.php'; ?>
        </div>
        
        <!-- Pestaña Salarios -->
        <div class="tab-content" id="salarios-content">
            <?php include 'salarios.php'; ?>
        </div>
        
        <!-- Pestaña Historial Salarios -->
        <div class="tab-content" id="historial-salarios-content">
            <?php include 'historial-salarios.php'; ?>
        </div>
    </div>

    <script>
    // Función para cambiar entre pestañas
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
            // Desactivar todas las pestañas y contenidos
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            // Activar la pestaña y contenido seleccionados
            this.classList.add('active');
            const tabId = this.getAttribute('data-tab');
            document.getElementById(tabId + '-content').classList.add('active');
            
            // Mostrar u ocultar controles según la pestaña
            const filterEstado = document.getElementById('filterEstado');
            const openModalNuevo = document.getElementById('openModalNuevo');
            
            if (tabId === 'prestamos' || tabId === 'amonestaciones') {
                filterEstado.style.display = 'block';
                openModalNuevo.style.display = 'block';
            } else {
                filterEstado.style.display = 'none';
                openModalNuevo.style.display = 'none';
            }
        });
    });
    
    // Función para ver detalles del conductor
    function verDetalleConductor(conductorId) {
        // Aquí puedes implementar la lógica para mostrar detalles del conductor
        // Por ejemplo, podrías abrir un modal o redirigir a otra página
        console.log('Mostrar detalles del conductor ID:', conductorId);
        // Implementación temporal:
        alert('Detalles del conductor ID: ' + conductorId + '\nEsta función se implementará completamente más adelante.');
    }
    
    // Función para aplicar filtros
    function aplicarFiltros() {
        const activeTab = document.querySelector('.tab-content.active').id;
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const conductorId = document.getElementById('filterConductor').value;
        const estado = document.getElementById('filterEstado').value;
        const fechaDesde = document.getElementById('filterFechaDesde').value;
        const fechaHasta = document.getElementById('filterFechaHasta').value;
        
        // Aquí implementarías la lógica para filtrar los datos en la tabla activa
        console.log('Aplicando filtros:', {
            activeTab,
            searchTerm,
            conductorId,
            estado,
            fechaDesde,
            fechaHasta
        });
        
        // Nota: La implementación completa de filtrado se hará cuando desarrolles los archivos individuales
    }
    
    // Event listeners para los filtros
    document.getElementById('searchInput').addEventListener('input', aplicarFiltros);
    document.getElementById('filterConductor').addEventListener('change', aplicarFiltros);
    document.getElementById('filterEstado').addEventListener('change', aplicarFiltros);
    document.getElementById('filterFechaDesde').addEventListener('change', aplicarFiltros);
    document.getElementById('filterFechaHasta').addEventListener('change', aplicarFiltros);
    </script>
    
    <?php include '../../layout/footer.php'; ?>
</body>
</html>