<?php
require_once '../../config/database.php';

// Determinar la pestaña activa para mantenerla después de las acciones
$tab_activa = $_GET['tab'] ?? 'prestamos';

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'agregar_prestamo') {
        try {
            $pdo->beginTransaction();
            
            // Validar datos
            $fecha = $_POST['fecha'] ?? '';
            $conductor_id = $_POST['conductor_id'] ?? '';
            $monto = floatval($_POST['monto'] ?? 0);
            $descripcion = $_POST['descripcion'] ?? '';
            $caja_id = $_POST['caja_id'] ?? '';
            
            if (empty($fecha)) throw new Exception("Fecha es requerida");
            if (empty($conductor_id)) throw new Exception("Conductor es requerido");
            if ($monto <= 0) throw new Exception("Monto debe ser mayor a 0");
            if (empty($caja_id)) throw new Exception("Caja no especificada");
            
            // Insertar préstamo
            $stmt = $pdo->prepare("
                INSERT INTO prestamos (fecha, conductor_id, monto, descripcion, saldo_pendiente, estado)
                VALUES (?, ?, ?, ?, ?, 'pendiente')
            ");
            $stmt->execute([$fecha, $conductor_id, $monto, $descripcion, $monto]);
            $prestamo_id = $pdo->lastInsertId();
            
            // Actualizar saldo de caja
            $stmt = $pdo->prepare("
                UPDATE cajas 
                SET saldo_actual = saldo_actual - ?
                WHERE id = ?
            ");
            $stmt->execute([$monto, $caja_id]);
            
            // Registrar movimiento de caja
            $stmt = $pdo->prepare("
                INSERT INTO movimientos_caja (
                    caja_id, prestamo_id, tipo, monto, descripcion, fecha
                ) VALUES (?, ?, 'egreso', ?, ?, ?)
            ");
            $stmt->execute([
                $caja_id,
                $prestamo_id,
                $monto,
                'Préstamo a conductor: ' . $descripcion,
                $fecha
            ]);
            
            $pdo->commit();
            $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Préstamo agregado correctamente'];
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error: ' . $e->getMessage()];
        }
        header("Location: gestion.php?tab=$tab_activa");
        exit();
    }
    elseif ($accion === 'pagar_prestamo') {
        try {
            $pdo->beginTransaction();
            
            // Validar datos
            $prestamo_id = $_POST['id'] ?? '';
            $fecha = $_POST['fecha'] ?? '';
            $monto = floatval($_POST['monto'] ?? 0);
            $descripcion = $_POST['descripcion'] ?? '';
            $caja_id = $_POST['caja_id'] ?? '';
            
            if (empty($prestamo_id)) throw new Exception("Préstamo no especificado");
            if (empty($fecha)) throw new Exception("Fecha es requerida");
            if ($monto <= 0) throw new Exception("Monto debe ser mayor a 0");
            if (empty($caja_id)) throw new Exception("Caja no especificada");
            
            // Verificar saldo pendiente
            $stmt = $pdo->prepare("SELECT saldo_pendiente FROM prestamos WHERE id = ?");
            $stmt->execute([$prestamo_id]);
            $saldo_pendiente = $stmt->fetchColumn();
            
            if ($monto > $saldo_pendiente) {
                throw new Exception("El monto a pagar excede el saldo pendiente");
            }
            
            // Registrar pago
            $stmt = $pdo->prepare("
                INSERT INTO pagos_prestamos (prestamo_id, fecha, monto, descripcion)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$prestamo_id, $fecha, $monto, $descripcion]);
            
            // Actualizar saldo pendiente del préstamo
            $stmt = $pdo->prepare("
                UPDATE prestamos 
                SET saldo_pendiente = saldo_pendiente - ?,
                    estado = CASE WHEN (saldo_pendiente - ?) <= 0 THEN 'pagado' ELSE estado END
                WHERE id = ?
            ");
            $stmt->execute([$monto, $monto, $prestamo_id]);
            
            // Actualizar saldo de caja
            $stmt = $pdo->prepare("
                UPDATE cajas 
                SET saldo_actual = saldo_actual + ?
                WHERE id = ?
            ");
            $stmt->execute([$monto, $caja_id]);
            
            // Registrar movimiento de caja
            $stmt = $pdo->prepare("
                INSERT INTO movimientos_caja (
                    caja_id, pago_prestamo_id, tipo, monto, descripcion, fecha
                ) VALUES (?, ?, 'ingreso', ?, ?, ?)
            ");
            $stmt->execute([
                $caja_id,
                $pdo->lastInsertId(),
                $monto,
                'Pago de préstamo: ' . $descripcion,
                $fecha
            ]);
            
            $pdo->commit();
            $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Pago registrado correctamente'];
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error: ' . $e->getMessage()];
        }
        header("Location: gestion.php?tab=$tab_activa");
        exit();
    }
    elseif ($accion === 'editar_prestamo') {
        try {
            $pdo->beginTransaction();
            
            // Validar datos
            $prestamo_id = $_POST['id'] ?? '';
            $fecha = $_POST['fecha'] ?? '';
            $conductor_id = $_POST['conductor_id'] ?? '';
            $monto = floatval($_POST['monto'] ?? 0);
            $descripcion = $_POST['descripcion'] ?? '';
            
            if (empty($prestamo_id)) throw new Exception("Préstamo no especificado");
            if (empty($fecha)) throw new Exception("Fecha es requerida");
            if (empty($conductor_id)) throw new Exception("Conductor es requerido");
            if ($monto <= 0) throw new Exception("Monto debe ser mayor a 0");
            
            // Obtener préstamo actual
            $stmt = $pdo->prepare("SELECT * FROM prestamos WHERE id = ?");
            $stmt->execute([$prestamo_id]);
            $prestamo_actual = $stmt->fetch();
            
            if (!$prestamo_actual) {
                throw new Exception("Préstamo no encontrado");
            }
            
            // Actualizar préstamo
            $stmt = $pdo->prepare("
                UPDATE prestamos 
                SET fecha = ?, conductor_id = ?, monto = ?, descripcion = ?
                WHERE id = ?
            ");
            $stmt->execute([$fecha, $conductor_id, $monto, $descripcion, $prestamo_id]);
            
            $pdo->commit();
            $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Préstamo actualizado correctamente'];
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error: ' . $e->getMessage()];
        }
        header("Location: gestion.php?tab=$tab_activa");
        exit();
    }
    elseif ($accion === 'eliminar_prestamo') {
        try {
            $pdo->beginTransaction();
            
            $prestamo_id = $_POST['id'] ?? '';
            
            if (empty($prestamo_id)) {
                throw new Exception("Préstamo no especificado");
            }
            
            // Verificar si el préstamo tiene pagos
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM pagos_prestamos WHERE prestamo_id = ?");
            $stmt->execute([$prestamo_id]);
            $tiene_pagos = $stmt->fetchColumn() > 0;
            
            if ($tiene_pagos) {
                throw new Exception("No se puede eliminar un préstamo con pagos registrados");
            }
            
            // Eliminar movimientos relacionados
            $stmt = $pdo->prepare("DELETE FROM movimientos_caja WHERE prestamo_id = ?");
            $stmt->execute([$prestamo_id]);
            
            // Eliminar el préstamo
            $stmt = $pdo->prepare("DELETE FROM prestamos WHERE id = ?");
            $stmt->execute([$prestamo_id]);
            
            $pdo->commit();
            $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Préstamo eliminado correctamente'];
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['mensaje'] = ['tipo' => 'danger', 'texto' => 'Error: ' . $e->getMessage()];
        }
        header("Location: gestion.php?tab=$tab_activa");
        exit();
    }
}

// Obtener parámetros de filtrado
$filtro_conductor = $_GET['conductor_id'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_desde = $_GET['fecha_desde'] ?? '';
$filtro_hasta = $_GET['fecha_hasta'] ?? '';
$filtro_monto_desde = $_GET['monto_desde'] ?? '';
$filtro_monto_hasta = $_GET['monto_hasta'] ?? '';
$filtro_busqueda = $_GET['busqueda'] ?? '';

// Construir consulta SQL con filtros
$sql = "
    SELECT p.*, c.nombre AS conductor_nombre
    FROM prestamos p
    JOIN conductores c ON p.conductor_id = c.id
    WHERE 1=1
";

$params = [];

if ($filtro_conductor) {
    $sql .= " AND p.conductor_id = ?";
    $params[] = $filtro_conductor;
}

if ($filtro_estado) {
    $sql .= " AND p.estado = ?";
    $params[] = $filtro_estado;
}

if ($filtro_desde) {
    $sql .= " AND p.fecha >= ?";
    $params[] = $filtro_desde;
}

if ($filtro_hasta) {
    $sql .= " AND p.fecha <= ?";
    $params[] = $filtro_hasta;
}

if ($filtro_monto_desde) {
    $sql .= " AND p.monto >= ?";
    $params[] = $filtro_monto_desde;
}

if ($filtro_monto_hasta) {
    $sql .= " AND p.monto <= ?";
    $params[] = $filtro_monto_hasta;
}

if ($filtro_busqueda) {
    $sql .= " AND (c.nombre LIKE ? OR p.descripcion LIKE ?)";
    $searchTerm = "%$filtro_busqueda%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY p.fecha DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prestamos = $stmt->fetchAll();

// Obtener conductores para el filtro
$conductores = $pdo->query("SELECT id, nombre FROM conductores ORDER BY nombre")->fetchAll();

// Obtener la caja predeterminada
$caja_predeterminada = $pdo->query("SELECT id, saldo_actual FROM cajas WHERE predeterminada = 1 LIMIT 1")->fetch();

// Mostrar mensajes
$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);
?>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Conductor</th>
                <th>Monto</th>
                <th>Saldo</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($prestamos as $prestamo): ?>
                <tr>
                    <td><?= $prestamo['id'] ?></td>
                    <td><?= date('d/m/Y', strtotime($prestamo['fecha'])) ?></td>
                    <td><?= htmlspecialchars($prestamo['conductor_nombre']) ?></td>
                    <td><?= number_format($prestamo['monto'], 0, ',', '.') ?> XAF</td>
                    <td><?= number_format($prestamo['saldo_pendiente'], 0, ',', '.') ?> XAF</td>
                    <td>
                        <span class="badge badge-<?= $prestamo['estado'] ?>">
                            <?= ucfirst($prestamo['estado']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 8px;">
                            <!-- Botón Ver -->
                            <button class="btn btn-ver" onclick="verDetallesPrestamo(<?= $prestamo['id'] ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                            
                            <!-- Botón Pagar (solo si tiene saldo pendiente) -->
                            <?php if ($prestamo['saldo_pendiente'] > 0): ?>
                                <button class="btn btn-nuevo" onclick="abrirModalPagoPrestamo(<?= $prestamo['id'] ?>, <?= $prestamo['saldo_pendiente'] ?>, '<?= htmlspecialchars(addslashes($prestamo['conductor_nombre'])) ?>')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 2v20M2 12h20"></path>
                                    </svg>
                                </button>
                            <?php endif; ?>
                            
                            <!-- Botón Editar -->
                            <button class="btn btn-editar" onclick="abrirModalEditarPrestamo(<?= $prestamo['id'] ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>
                            
                            <!-- Botón Eliminar -->
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="accion" value="eliminar_prestamo">
                                <input type="hidden" name="id" value="<?= $prestamo['id'] ?>">
                                <input type="hidden" name="tab" value="<?= $tab_activa ?>">
                                <button type="submit" class="btn btn-eliminar" onclick="return confirm('¿Estás seguro de eliminar este préstamo?')">
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

<!-- Modal para pagar préstamo -->
<div class="modal" id="modalPagarPrestamo">
    <div class="modal__overlay" onclick="cerrarModal('modalPagarPrestamo')"></div>
    <div class="modal__container">
        <div class="modal__close" onclick="cerrarModal('modalPagarPrestamo')">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </div>
        <div class="modal__header">
            <h3 class="modal__title">Registrar Pago</h3>
        </div>
        <form id="formPagarPrestamo" method="post">
            <div class="modal__body">
                <input type="hidden" name="accion" value="pagar_prestamo">
                <input type="hidden" name="id" id="prestamo_id_pago">
                <input type="hidden" name="caja_id" value="<?= $caja_predeterminada['id'] ?>">
                <input type="hidden" name="tab" value="<?= $tab_activa ?>">
                
                <div class="modal__form-group">
                    <label class="modal__form-label">Conductor</label>
                    <input type="text" id="conductor_nombre_pago" class="modal__form-input" readonly>
                </div>
                
                <div class="modal__form-group">
                    <label class="modal__form-label">Fecha *</label>
                    <input type="date" name="fecha" class="modal__form-input" required value="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="modal__form-group">
                    <label class="modal__form-label">Monto a pagar (XAF) *</label>
                    <input type="number" name="monto" id="monto_pago" class="modal__form-input" min="1" required>
                </div>
                
                <div class="modal__form-group">
                    <label class="modal__form-label">Descripción</label>
                    <textarea name="descripcion" class="modal__form-input" rows="3">Pago de préstamo</textarea>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="modal__action-btn" onclick="cerrarModal('modalPagarPrestamo')">
                    Cancelar
                </button>
                <button type="submit" class="modal__action-btn modal__action-btn--primary">
                    Registrar Pago
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para ver detalles -->
<div class="modal" id="modalDetallesPrestamo">
    <div class="modal__overlay" onclick="cerrarModal('modalDetallesPrestamo')"></div>
    <div class="modal__container" style="max-width: 600px;">
        <div class="modal__close" onclick="cerrarModal('modalDetallesPrestamo')">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </div>
        <div class="modal__header">
            <h3 class="modal__title">Detalles del Préstamo</h3>
        </div>
        <div class="modal__body">
            <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
                <tr>
                    <td style="width:40%; padding:10px; font-weight:bold; background:#f8f9fa;">ID</td>
                    <td style="padding:10px;" id="detalle_id"></td>
                </tr>
                <tr>
                    <td style="padding:10px; font-weight:bold; background:#f8f9fa;">Fecha</td>
                    <td style="padding:10px;" id="detalle_fecha"></td>
                </tr>
                <tr>
                    <td style="padding:10px; font-weight:bold; background:#f8f9fa;">Conductor</td>
                    <td style="padding:10px;" id="detalle_conductor"></td>
                </tr>
                <tr>
                    <td style="padding:10px; font-weight:bold; background:#f8f9fa;">Monto</td>
                    <td style="padding:10px;" id="detalle_monto"></td>
                </tr>
                <tr>
                    <td style="padding:10px; font-weight:bold; background:#f8f9fa;">Saldo Pendiente</td>
                    <td style="padding:10px;" id="detalle_saldo"></td>
                </tr>
                <tr>
                    <td style="padding:10px; font-weight:bold; background:#f8f9fa;">Estado</td>
                    <td style="padding:10px;" id="detalle_estado"></td>
                </tr>
                <tr>
                    <td style="padding:10px; font-weight:bold; background:#f8f9fa;">Descripción</td>
                    <td style="padding:10px;" id="detalle_descripcion"></td>
                </tr>
            </table>
            
            <div class="historial-container">
                <h4 class="historial-title">Historial de Pagos</h4>
                <div class="historial-content" id="historial_pagos">
                    <!-- Contenido cargado dinámicamente -->
                </div>
            </div>
        </div>
        <div class="modal__footer">
            <button type="button" class="modal__action-btn" onclick="cerrarModal('modalDetallesPrestamo')">
                Cerrar
            </button>
        </div>
    </div>
</div>

<!-- Modal para editar préstamo -->
<div class="modal" id="modalEditarPrestamo">
    <div class="modal__overlay" onclick="cerrarModal('modalEditarPrestamo')"></div>
    <div class="modal__container">
        <div class="modal__close" onclick="cerrarModal('modalEditarPrestamo')">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </div>
        <div class="modal__header">
            <h3 class="modal__title">Editar Préstamo</h3>
        </div>
        <form id="formEditarPrestamo" method="post">
            <input type="hidden" name="accion" value="editar_prestamo">
            <input type="hidden" name="id" id="editar_id">
            <input type="hidden" name="tab" value="<?= $tab_activa ?>">
            <div class="modal__body" id="editar_contenido">
                <!-- Contenido cargado dinámicamente -->
            </div>
            <div class="modal__footer">
                <button type="button" class="modal__action-btn" onclick="cerrarModal('modalEditarPrestamo')">
                    Cancelar
                </button>
                <button type="submit" class="modal__action-btn modal__action-btn--primary">
                    Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Variables globales
let prestamosData = <?= json_encode($prestamos) ?>;
let conductoresData = <?= json_encode($conductores) ?>;

// Funciones para modales
function abrirModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Pagar préstamo
function abrirModalPagoPrestamo(id, saldo, conductor) {
    document.getElementById('prestamo_id_pago').value = id;
    document.getElementById('conductor_nombre_pago').value = conductor;
    document.getElementById('monto_pago').value = saldo;
    document.getElementById('monto_pago').max = saldo;
    
    abrirModal('modalPagarPrestamo');
}

// Ver detalles
function verDetallesPrestamo(id) {
    const prestamo = prestamosData.find(p => p.id == id);
    if (prestamo) {
        document.getElementById('detalle_id').textContent = prestamo.id;
        document.getElementById('detalle_fecha').textContent = new Date(prestamo.fecha).toLocaleDateString();
        document.getElementById('detalle_conductor').textContent = prestamo.conductor_nombre;
        document.getElementById('detalle_monto').textContent = prestamo.monto.toLocaleString() + ' XAF';
        document.getElementById('detalle_saldo').textContent = prestamo.saldo_pendiente.toLocaleString() + ' XAF';
        document.getElementById('detalle_estado').textContent = prestamo.estado.charAt(0).toUpperCase() + prestamo.estado.slice(1);
        document.getElementById('detalle_descripcion').textContent = prestamo.descripcion || 'Sin descripción';
        
        // Cargar historial de pagos
        fetch(`get_pagos_prestamo.php?id=${id}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('historial_pagos').innerHTML = data || '<p>No hay pagos registrados para este préstamo.</p>';
            })
            .catch(() => {
                document.getElementById('historial_pagos').innerHTML = '<p>Error al cargar el historial de pagos.</p>';
            });
        
        abrirModal('modalDetallesPrestamo');
    }
}

// Editar préstamo
function abrirModalEditarPrestamo(id) {
    const prestamo = prestamosData.find(p => p.id == id);
    if (prestamo) {
        document.getElementById('editar_id').value = id;
        
        // Crear formulario de edición
        let html = `
            <div class="modal__form-group">
                <label class="modal__form-label">Conductor *</label>
                <select name="conductor_id" class="modal__form-input" required>
                    <option value="">Seleccionar conductor</option>
        `;
        
        conductoresData.forEach(conductor => {
            html += `
                <option value="${conductor.id}" ${prestamo.conductor_id == conductor.id ? 'selected' : ''}>
                    ${conductor.nombre}
                </option>
            `;
        });
        
        html += `
                </select>
            </div>
            
            <div class="modal__form-group">
                <label class="modal__form-label">Fecha *</label>
                <input type="date" name="fecha" class="modal__form-input" required 
                       value="${prestamo.fecha.split(' ')[0]}">
            </div>
            
            <div class="modal__form-group">
                <label class="modal__form-label">Monto (XAF) *</label>
                <input type="number" name="monto" class="modal__form-input" min="1" required 
                       value="${prestamo.monto}">
            </div>
            
            <div class="modal__form-group">
                <label class="modal__form-label">Descripción</label>
                <textarea name="descripcion" class="modal__form-input" rows="3">${prestamo.descripcion || ''}</textarea>
            </div>
        `;
        
        document.getElementById('editar_contenido').innerHTML = html;
        abrirModal('modalEditarPrestamo');
    }
}

// Validar formulario de pago
document.getElementById('formPagarPrestamo').addEventListener('submit', function(e) {
    const monto = parseFloat(this.monto.value);
    const saldoPendiente = parseFloat(this.monto.max);
    
    if (monto > saldoPendiente) {
        e.preventDefault();
        alert('Error: El monto a pagar no puede ser mayor al saldo pendiente.');
    }
});
</script>