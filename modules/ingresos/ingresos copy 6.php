<?php
include '../../layout/header.php';
require '../../config/database.php';

// ==============================
// LÓGICA DE ACCIONES: Agregar / Editar / Eliminar
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
        $caja_id = 1; // Caja predeterminada

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

        // Calcular monto esperado
        $monto_base = ($tipo_ingreso == 'obligatorio') ? 
            ($conductor['ingreso_obligatorio'] ?? 0) : 
            ($conductor['ingreso_libre'] ?? 0);
        $saldo_pendiente = $ultimo_ingreso ? ($ultimo_ingreso['monto_esperado'] - $ultimo_ingreso['monto_ingresado']) : 0;
        $monto_esperado = $monto_base + $saldo_pendiente;

        // Calcular ciclo - Versión corregida
        if ($tipo_ingreso == 'obligatorio') {
            if (!$ultimo_ingreso || $ultimo_ingreso['ciclo_completado']) {
                $numero_ciclo = 1; // Valor por defecto si no hay último ingreso
                
                if ($ultimo_ingreso && isset($ultimo_ingreso['ciclo'])) {
                    $partes_ciclo = explode("-", $ultimo_ingreso['ciclo']);
                    if (count($partes_ciclo) > 1) {
                        $numero_ciclo = intval($partes_ciclo[1]) + 1;
                    }
                }
                
                $nuevo_ciclo = "Mes-" . $numero_ciclo;
                $contador_ciclo = 1;
                $ciclo_completado = 0;
            } else {
                $nuevo_ciclo = $ultimo_ingreso['ciclo'];
                $contador_ciclo = $ultimo_ingreso['contador_ciclo'] + 1;
                $ciclo_completado = ($contador_ciclo >= ($conductor['dias_por_ciclo'] ?? 30)) ? 1 : 0;
            }
        } else {
            $nuevo_ciclo = $ultimo_ingreso ? $ultimo_ingreso['ciclo'] : "Mes-1";
            $contador_ciclo = $ultimo_ingreso ? $ultimo_ingreso['contador_ciclo'] : 0;
            $ciclo_completado = $ultimo_ingreso ? $ultimo_ingreso['ciclo_completado'] : 0;
        }

        // Insertar ingreso
        $stmt = $pdo->prepare("
            INSERT INTO ingresos (
                fecha, conductor_id, tipo_ingreso, monto_ingresado, monto_esperado, 
                monto_pendiente, kilometros, caja_id, ciclo, contador_ciclo, ciclo_completado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $fecha, $conductor_id, $tipo_ingreso, $monto_ingresado, $monto_esperado,
            ($monto_esperado - $monto_ingresado), $kilometros, $caja_id, $nuevo_ciclo, $contador_ciclo, $ciclo_completado
        ]);

        // Actualizar kilómetros del vehículo
        if (isset($conductor['vehiculo_id'])) {
            $stmt = $pdo->prepare("UPDATE vehiculos SET km_actual = ? WHERE id = ?");
            $stmt->execute([$kilometros, $conductor['vehiculo_id']]);
        }

        header("Location: ingresos.php");
        exit;
    }

    // Eliminar ingreso
    elseif ($accion == 'eliminar') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM ingresos WHERE id = ?");
        $stmt->execute([$id]);
        echo "<script>alert('Ingreso eliminado correctamente.'); window.location.href='ingresos.php';</script>";
    }
}

// Obtener todos los ingresos con datos de conductor y vehículo
$ingresos = $pdo->query("
    SELECT i.*, c.nombre AS conductor_nombre, v.marca, v.modelo, v.matricula, c.dias_por_ciclo
    FROM ingresos i
    LEFT JOIN conductores c ON i.conductor_id = c.id
    LEFT JOIN vehiculos v ON c.vehiculo_id = v.id
    ORDER BY i.fecha DESC
")->fetchAll();

// Obtener conductores para filtros
$conductores = $pdo->query("SELECT id, nombre FROM conductores ORDER BY nombre")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ingresos</title>
    <style>
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
    
    .alerta-stock {
        background-color: #ffebee !important;
        font-weight: bold;
        color:rgb(235, 13, 46)
    }
    
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
    
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.4);
    }
    
    .modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
        max-width: 600px;
        border-radius: 8px;
    }
    
    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .close:hover {
        color: black;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
    }
    
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .btn-primary {
        background-color: var(--color-primario);
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }
    
    .btn-primary:hover {
        background-color: var(--color-secundario);
    }
    
    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }
        
        .table-controls input, 
        .table-controls select {
            max-width: 100%;
        }
        
        .modal-content {
            width: 95%;
            margin: 10% auto;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <h2>Lista de Ingresos</h2>
        
        <!-- Filtros -->
        <div class="table-controls">
            <input type="text" id="searchInput" placeholder="Buscar...">
            <select id="filterConductor">
                <option value="">Todos los conductores</option>
                <?php foreach ($conductores as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filterTipo">
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
        
        <!-- Tabla -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Conductor</th>
                        <th>Vehículo</th>
                        <th>Tipo</th>
                        <!--<th>Monto Esperado</th>-->
                        <th>Ingresado</th>
                        <th>Pendiente</th>
                        <th>Ciclo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($ingresos as $ing): ?>
                        <tr data-conductor="<?= $ing['conductor_id'] ?>" 
                            data-tipo="<?= $ing['tipo_ingreso'] ?>" 
                            data-ciclo="<?= $ing['ciclo'] ?>" 
                            data-fecha="<?= $ing['fecha'] ?>">
                            <td><?= $ing['id'] ?></td>
                            <td><?= $ing['fecha'] ?></td>
                            <td><?= $ing['conductor_nombre'] ?></td>
                            <td><?= $ing['marca'] ?> <?= $ing['modelo'] ?> (<?= $ing['matricula'] ?>)</td>
                            <td><?= $ing['tipo_ingreso'] == 'obligatorio' ? 'Obligatorio' : 'Libre' ?></td>
                            <!--<td><?= number_format($ing['monto_esperado'], 0, ',', '.') ?> XAF</td>-->
                            <td><?= number_format($ing['monto_ingresado'], 0, ',', '.') ?> XAF</td>
                            <td><?= number_format($ing['monto_pendiente'], 0, ',', '.') ?> XAF</td>
                            <td>
                                <?= $ing['ciclo'] ?>
                                <?= $ing['tipo_ingreso'] == 'obligatorio' ? " ({$ing['contador_ciclo']}/{$ing['dias_por_ciclo']})" : " (-)" ?>
                            </td>
                            <td>
                                <button class="btn btn-ver" onclick="verIngreso(<?= $ing['id'] ?>)">Ver</button>
                                <button class="btn btn-editar" onclick="editarIngreso(<?= $ing['id'] ?>)">Editar</button>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $ing['id'] ?>">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <button type="submit" class="btn btn-eliminar" onclick="return confirm('¿Eliminar este ingreso?')">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Agregar/Editar -->
    <div id="modalIngreso" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitle">Agregar Ingreso</h2>
            <form id="formIngreso" method="post">
                <input type="hidden" name="accion" id="formAccion" value="agregar">
                <input type="hidden" name="id" id="ingresoId">
                
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" name="fecha" id="fecha" required>
                </div>
                
                <div class="form-group">
                    <label>Conductor</label>
                    <select name="conductor_id" id="conductor_id" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($conductores as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Tipo de Ingreso</label>
                    <select name="tipo_ingreso" id="tipo_ingreso" required>
                        <option value="obligatorio">Obligatorio</option>
                        <option value="libre">Libre</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Monto Esperado</label>
                    <input type="number" name="monto_esperado" id="monto_esperado" readonly>
                </div>
                
                <div class="form-group">
                    <label>Monto Ingresado</label>
                    <input type="number" name="monto_ingresado" id="monto_ingresado" required>
                </div>
                
                <div class="form-group">
                    <label>Kilómetros</label>
                    <input type="number" name="kilometros" id="kilometros" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Guardar</button>
            </form>
        </div>
    </div>

    <script>
    // Variables globales
    let currentIngreso = null;
    let conductoresData = <?= json_encode($conductores) ?>;
    
    // Inicialización al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        // Configurar eventos
        initEventListeners();
        updateTable();
    });
    
    // Configurar eventos
    function initEventListeners() {
        // Botón "Agregar Ingreso"
        document.getElementById('openModalAgregar').addEventListener('click', function() {
            abrirModal('modalIngreso', 'agregar');
        });
    
        // Cerrar modal al hacer clic en la "X"
        document.querySelector('.modal .close').addEventListener('click', function() {
            cerrarModal('modalIngreso');
        });
    
        // Cambio de conductor (calcula monto esperado y km actual)
        document.getElementById('conductor_id').addEventListener('change', function() {
            calcularMontoEsperado();
            cargarKilometrosActuales();
        });
    
        // Cambio de tipo de ingreso (obligatorio/libre)
        document.getElementById('tipo_ingreso').addEventListener('change', function() {
            calcularMontoEsperado();
        });
    
        // Eventos de filtros
        document.getElementById('filterConductor').addEventListener('change', aplicarFiltros);
        document.getElementById('filterTipo').addEventListener('change', aplicarFiltros);
        document.getElementById('searchInput').addEventListener('keyup', aplicarFiltros);
    }
    
    // Abrir modal (agregar/editar)
    function abrirModal(modalId, accion = 'agregar', ingresoId = null) {
        const modal = document.getElementById(modalId);
        const form = document.getElementById('formIngreso');
        
        // Configurar título y acción
        document.getElementById('modalTitle').textContent = 
            (accion === 'agregar') ? 'Agregar Ingreso' : 'Editar Ingreso';
        document.getElementById('formAccion').value = accion;
    
        // Limpiar formulario si es agregar
        if (accion === 'agregar') {
            form.reset();
            document.getElementById('fecha').valueAsDate = new Date();
            document.getElementById('fecha').readOnly = false;
            document.getElementById('kilometros').readOnly = false;
        }
    
        // Cargar datos si es editar
        if (accion === 'editar' && ingresoId) {
            // Simulación de datos - en producción usarías una llamada AJAX real
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
                
                // Bloquear campos si hay registros posteriores
                if (hayRegistrosPosteriores(ingreso.conductor_id, ingreso.fecha)) {
                    document.getElementById('fecha').readOnly = true;
                    document.getElementById('kilometros').readOnly = true;
                }
            }
        }
    
        modal.style.display = 'block';
    }
    
    // Función simulada para obtener ingreso por ID (en producción usarías AJAX)
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
                    monto_ingresado: parseFloat(row.cells[6].textContent.replace(/\./g, '').replace(' XAF', '')),
                    kilometros: parseFloat(row.cells[8].textContent.split(' ')[0])
                };
            }
        }
        return null;
    }
    
    // Cerrar modal
    function cerrarModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    // Calcular monto esperado automáticamente
    function calcularMontoEsperado() {
        const conductorId = document.getElementById('conductor_id').value;
        const tipoIngreso = document.getElementById('tipo_ingreso').value;
        
        if (!conductorId) return;
    
        // Simulación de datos del conductor - en producción usarías AJAX
        const conductor = conductoresData.find(c => c.id == conductorId);
        if (!conductor) return;
    
        // Simulación de último ingreso - en producción usarías AJAX
        const ultimoIngreso = obtenerUltimoIngresoConductor(conductorId);
        
        const saldoPendiente = ultimoIngreso ? 
            (ultimoIngreso.monto_esperado - ultimoIngreso.monto_ingresado) : 0;
        
        // Monto base según tipo de ingreso (valores simulados)
        const montoBase = (tipoIngreso === 'obligatorio') ? 
            (conductor.ingreso_obligatorio || 0) : 
            (conductor.ingreso_libre || 0);
        
        document.getElementById('monto_esperado').value = montoBase + saldoPendiente;
    }
    
    // Función simulada para obtener último ingreso del conductor
    function obtenerUltimoIngresoConductor(conductorId) {
        const table = document.getElementById('tableBody');
        const rows = Array.from(table.getElementsByTagName('tr'));
        
        // Filtrar por conductor y ordenar por fecha descendente
        const ingresosConductor = rows
            .filter(row => row.getAttribute('data-conductor') == conductorId)
            .map(row => ({
                fecha: row.cells[1].textContent,
                monto_esperado: parseFloat(row.cells[5].textContent.replace(/\./g, '').replace(' XAF', '')),
                monto_ingresado: parseFloat(row.cells[6].textContent.replace(/\./g, '').replace(' XAF', ''))
            }))
            .sort((a, b) => new Date(b.fecha) - new Date(a.fecha));
        
        return ingresosConductor.length > 0 ? ingresosConductor[0] : null;
    }
    
    // Cargar kilómetros actuales del vehículo
    function cargarKilometrosActuales() {
        const conductorId = document.getElementById('conductor_id').value;
        if (!conductorId) return;
        
        // Simulación - en producción usarías AJAX para obtener los km actuales
        document.getElementById('kilometros').min = 0;
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
    
    // Aplicar filtros
    function aplicarFiltros() {
        const searchText = document.getElementById('searchInput').value.toLowerCase();
        const conductorId = document.getElementById('filterConductor').value;
        const tipoIngreso = document.getElementById('filterTipo').value;
        
        const table = document.getElementById('tableBody');
        const rows = table.getElementsByTagName('tr');
        
        for (let row of rows) {
            const rowConductor = row.getAttribute('data-conductor');
            const rowTipo = row.getAttribute('data-tipo');
            const rowText = row.textContent.toLowerCase();
            
            const coincideBusqueda = !searchText || rowText.includes(searchText);
            const coincideConductor = !conductorId || rowConductor === conductorId;
            const coincideTipo = !tipoIngreso || rowTipo === tipoIngreso;
            
            row.style.display = (coincideBusqueda && coincideConductor && coincideTipo) ? '' : 'none';
        }
    }
    
    // Funciones para los botones de acciones
    function verIngreso(id) {
        alert('Funcionalidad de ver ingreso: ' + id);
        // Implementar lógica para ver detalles
    }
    
    function editarIngreso(id) {
        abrirModal('modalIngreso', 'editar', id);
    }
    
    // Actualizar tabla (para búsqueda/filtros)
    function updateTable() {
        aplicarFiltros();
    }
    </script>
    <?php include '../../layout/footer.php'; ?>
</body>
</html>