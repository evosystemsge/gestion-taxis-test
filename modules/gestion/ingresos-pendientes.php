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