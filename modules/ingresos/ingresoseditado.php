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

    // Agregar ingreso (mantén tu código actual)
    if ($accion == 'agregar') {
        $fecha = $_POST['fecha'];
        $conductor_id = $_POST['conductor_id'];
        $tipo_ingreso = $_POST['tipo_ingreso'];
        $monto_ingresado = $_POST['monto_ingresado'];
        $kilometros = $_POST['kilometros'];
    }
    // Eliminar ingreso (mantén tu código actual)
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

// Obtener datos para mostrar
$ingresos = $pdo->query("
    SELECT i.*, c.nombre AS conductor_nombre, v.marca, v.modelo, v.matricula, v.km_actual, c.dias_por_ciclo
    FROM ingresos i
    LEFT JOIN conductores c ON i.conductor_id = c.id
    LEFT JOIN vehiculos v ON c.vehiculo_id = v.id
    ORDER BY i.fecha DESC
")->fetchAll();

$conductores = $pdo->query("
    SELECT c.id, c.nombre, c.ingreso_obligatorio, c.ingreso_libre 
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
    <title>Gestión de Ingresos</title>
    <style>
/* ============ ESTILOS PRINCIPALES (MANTENIENDO TU ESTILO ORIGINAL) ============ */
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

/* ============ BOTONES (ESTILO COHERENTE CON TU DISEÑO ORIGINAL) ============ */
.btn {
    padding: 8px 8px;
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
    min-width: 36px;
    height: 36px;
}

/* Botón VER - Manteniendo tu estilo original */
.btn-ver {
    background-color: var(--color-info);
    color: white;
}

.btn-ver:hover {
    background-color: #138496;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Botón PAGAR - Nuevo estilo coherente */
.btn-pagar {
    background-color: var(--color-pagar);
    color: white;
}

.btn-pagar:hover {
    background-color: #1aa179;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Botón ELIMINAR - Manteniendo tu estilo original */
.btn-eliminar {
    background-color: var(--color-peligro);
    color: white;
}

.btn-eliminar:hover {
    background-color: #c82333;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* ============ TABLA (ESTILO IDÉNTICO AL ORIGINAL) ============ */
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
    vertical-align: middle; /* Añadido para alinear botones */
}

.table tr:hover td {
    background-color: rgba(0, 75, 135, 0.05);
}

/* Estilo para filas con pendientes (original) */
.table tr.alerta-pendiente td {
    background-color: #ffebee !important;
    color: #dc3545 !important;
    font-weight: bold;
}

/* Contenedor de botones - Ajuste para mantener el estilo */
.acciones-container {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

/* ============ MODALES (ESTILO IDÉNTICO AL ORIGINAL) ============ */
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

/* ... (resto de tus estilos de modal originales) ... */

/* ============ RESPONSIVE (IGUAL AL ORIGINAL) ============ */
@media (max-width: 768px) {
    .container {
        padding: 15px;
    }
    
    .modal__container {
        max-width: 98%;
        max-height: 98vh;
    }
    
    .table-controls input, 
    .table-controls select {
        max-width: 100%;
    }
    
    /* Ajustes específicos para botones en móvil */
    .acciones-container {
        gap: 4px;
    }
    
    .btn {
        padding: 6px 6px;
        min-width: 32px;
        height: 32px;
    }
    
    .btn svg {
        width: 12px;
        height: 12px;
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
        <!-- Contenido de la tabla -->
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
        <?php foreach ($ingresos as $ing): ?>
            <tr class="<?= $ing['monto_pendiente'] > 0 ? 'alerta-pendiente' : '' ?>">
                <!-- Columnas de datos -->
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
                
                <!-- Botones de Acción -->
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

<!-- Modal Pagar (actualizado) -->
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

// ======================================
// FUNCIONES PRINCIPALES
// ======================================

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

// ======================================
// FUNCIONES DE PAGOS
// ======================================

function pagarIngreso(ingresoId, montoPendiente) {
    document.getElementById('pagarIngresoId').value = ingresoId;
    document.getElementById('pagarMontoPendiente').value = numberFormat(montoPendiente) + ' XAF';
    document.getElementById('pagarMonto').value = montoPendiente;
    document.getElementById('pagarMonto').max = montoPendiente;
    abrirModal('modalPagar');
}

// ======================================
// FUNCIONES DE MODALES
// ======================================

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

function cerrarModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// ======================================
// FUNCIONES DE TABLA
// ======================================

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

function setupPagination() {
    const totalRows = document.querySelectorAll('#tableBody tr').length;
    const totalPages = Math.ceil(totalRows / rowsPerPage);
    
    document.getElementById('prevPage').disabled = currentPage === 1;
    document.getElementById('nextPage').disabled = currentPage === totalPages;
    document.getElementById('pageInfo').textContent = `Página ${currentPage} de ${totalPages}`;
}

// ======================================
// FUNCIONES AUXILIARES
// ======================================

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
</body>
</html>