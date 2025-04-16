<?php
// [El código PHP anterior permanece igual hasta la parte de los filtros]
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Préstamos</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .badge-pendiente { background-color: #ffc107; color: #000; }
        .badge-pagado { background-color: #28a745; color: #fff; }
        .badge-vencido { background-color: #dc3545; color: #fff; }
        .table-container { overflow-x: auto; }
        .btn-accion { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
        .filter-form .form-control, .filter-form .form-select {
            background-color: #f8f9fa;
        }
        .filter-form .form-control:focus, .filter-form .form-select:focus {
            background-color: #fff;
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-3">
        <?php if (isset($mensaje)): ?>
            <div class="alert alert-<?= $mensaje['tipo'] ?> alert-dismissible fade show">
                <?= $mensaje['texto'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <h1 class="mb-4">Gestión de Préstamos</h1>

        <!-- Filtros (ahora sin botones) -->
        <div class="card mb-4">
            <div class="card-body filter-form">
                <form id="filtrosForm" method="get" class="row g-3">
                    <div class="col-md-3">
                        <label for="conductor_id" class="form-label">Conductor</label>
                        <select id="conductor_id" name="conductor_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <?php foreach ($conductores as $conductor): ?>
                                <option value="<?= $conductor['id'] ?>" <?= $filtro_conductor == $conductor['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($conductor['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="fecha_desde" class="form-label">Desde</label>
                        <input type="date" id="fecha_desde" name="fecha_desde" class="form-control" value="<?= $filtro_desde ?>" onchange="this.form.submit()">
                    </div>
                    <div class="col-md-2">
                        <label for="fecha_hasta" class="form-label">Hasta</label>
                        <input type="date" id="fecha_hasta" name="fecha_hasta" class="form-control" value="<?= $filtro_hasta ?>" onchange="this.form.submit()">
                    </div>
                    <div class="col-md-2">
                        <label for="estado" class="form-label">Estado</label>
                        <select id="estado" name="estado" class="form-select" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <option value="pendiente" <?= $filtro_estado == 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="pagado" <?= $filtro_estado == 'pagado' ? 'selected' : '' ?>>Pagado</option>
                            <option value="vencido" <?= $filtro_estado == 'vencido' ? 'selected' : '' ?>>Vencido</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="busqueda" class="form-label">Buscar</label>
                        <input type="text" id="busqueda" name="busqueda" class="form-control" placeholder="Buscar..." 
                               value="<?= htmlspecialchars($filtro_busqueda) ?>" 
                               oninput="debounceSubmit()">
                    </div>
                </form>
            </div>
        </div>

        <!-- Botón Agregar y Tabla -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Listado de Préstamos</h5>
                <button class="btn btn-success" onclick="abrirModalAgregarPrestamo()">
                    <i class="fas fa-plus"></i> Agregar Préstamo
                </button>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table table-striped table-hover">
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
                                        <div class="d-flex gap-2">
                                            <!-- Botón Ver -->
                                            <button class="btn btn-sm btn-primary btn-accion" onclick="verPrestamo(<?= $prestamo['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <!-- Botón Pagar (solo si tiene saldo pendiente) -->
                                            <?php if ($prestamo['saldo_pendiente'] > 0): ?>
                                                <button class="btn btn-sm btn-success btn-accion" onclick="abrirModalPagoPrestamo(<?= $prestamo['id'] ?>, <?= $prestamo['saldo_pendiente'] ?>, '<?= htmlspecialchars(addslashes($prestamo['conductor_nombre'])) ?>')">
                                                    <i class="fas fa-money-bill-wave"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Botón Editar -->
                                            <button class="btn btn-sm btn-warning btn-accion" onclick="abrirModalEditarPrestamo(<?= $prestamo['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <!-- Botón Eliminar -->
                                            <form method="post" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar este préstamo?')">
                                                <input type="hidden" name="accion" value="eliminar_prestamo">
                                                <input type="hidden" name="id" value="<?= $prestamo['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger btn-accion">
                                                    <i class="fas fa-trash-alt"></i>
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
        </div>
    </div>

    <!-- Modal Agregar Préstamo -->
    <div class="modal fade" id="modalAgregarPrestamo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Nuevo Préstamo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="formAgregarPrestamo">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="agregar_prestamo">
                        <input type="hidden" name="caja_id" value="<?= $caja_predeterminada['id'] ?>">
                        
                        <div class="mb-3">
                            <label for="conductor_id_modal" class="form-label">Conductor *</label>
                            <select id="conductor_id_modal" name="conductor_id" class="form-select" required>
                                <option value="">Seleccionar conductor</option>
                                <?php foreach ($conductores as $conductor): ?>
                                    <option value="<?= $conductor['id'] ?>"><?= htmlspecialchars($conductor['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="fecha_prestamo" class="form-label">Fecha *</label>
                            <input type="date" id="fecha_prestamo" name="fecha" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="monto_prestamo" class="form-label">Monto (XAF) *</label>
                            <input type="number" id="monto_prestamo" name="monto" class="form-control" min="1" step="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion_prestamo" class="form-label">Descripción</label>
                            <textarea id="descripcion_prestamo" name="descripcion" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Saldo disponible en caja:</strong> <?= number_format($caja_predeterminada['saldo_actual'], 0, ',', '.') ?> XAF
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Préstamo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Pagar Préstamo -->
    <div class="modal fade" id="modalPagarPrestamo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Pago de Préstamo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="formPagarPrestamo">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="pagar_prestamo">
                        <input type="hidden" id="prestamo_id_pago" name="id">
                        <input type="hidden" name="caja_id" value="<?= $caja_predeterminada['id'] ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Conductor</label>
                            <input type="text" id="conductor_nombre_pago" class="form-control" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="fecha_pago" class="form-label">Fecha *</label>
                            <input type="date" id="fecha_pago" name="fecha" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="monto_pago" class="form-label">Monto a pagar (XAF) *</label>
                            <input type="number" id="monto_pago" name="monto" class="form-control" min="1" step="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion_pago" class="form-label">Descripción</label>
                            <textarea id="descripcion_pago" name="descripcion" class="form-control" rows="3">Pago de préstamo</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Registrar Pago</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    

    <!-- Bootstrap JS y dependencias -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Debounce para el campo de búsqueda
        let debounceTimer;
        function debounceSubmit() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                document.getElementById('filtrosForm').submit();
            }, 500);
        }
        
        // Resetear filtros
        function resetFiltros() {
            window.location.href = window.location.pathname;
        }
        
        // Abrir modal agregar préstamo
        function abrirModalAgregarPrestamo() {
            const modal = new bootstrap.Modal(document.getElementById('modalAgregarPrestamo'));
            modal.show();
        }
        
        // Abrir modal pago préstamo
        function abrirModalPagoPrestamo(prestamo_id, saldo_pendiente, conductor_nombre) {
            document.getElementById('prestamo_id_pago').value = prestamo_id;
            document.getElementById('conductor_nombre_pago').value = conductor_nombre;
            document.getElementById('monto_pago').value = saldo_pendiente;
            document.getElementById('monto_pago').max = saldo_pendiente;
            
            const modal = new bootstrap.Modal(document.getElementById('modalPagarPrestamo'));
            modal.show();
        }
        
        // Abrir modal editar préstamo
        function abrirModalEditarPrestamo(prestamo_id) {
            // Aquí iría la lógica para cargar los datos del préstamo
            alert('Editar préstamo ID: ' + prestamo_id);
        }
        
        // Ver detalles del préstamo
        function verPrestamo(prestamo_id) {
            // Aquí iría la lógica para ver los detalles
            alert('Ver préstamo ID: ' + prestamo_id);
        }
        
        // Validar formulario agregar préstamo
        document.getElementById('formAgregarPrestamo').addEventListener('submit', function(e) {
            const monto = parseFloat(document.getElementById('monto_prestamo').value);
            const saldoCaja = parseFloat(<?= $caja_predeterminada['saldo_actual'] ?>);
            
            if (monto > saldoCaja) {
                e.preventDefault();
                alert('Error: El monto del préstamo excede el saldo disponible en la caja.');
                return false;
            }
        });
    </script>
</body>
</html>