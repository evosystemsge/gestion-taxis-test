<?php
// Incluimos el encabezado y la conexión a la base de datos
include '../../layout/header.php';
require '../../config/database.php';

// ==============================
// LÓGICA DE ACCIONES: Agregar / Editar / Eliminar
// ==============================
if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion == 'agregar') {
        // Agregar nuevo vehículo
        $stmt = $pdo->prepare("INSERT INTO vehiculos (marca, modelo, matricula, numero, km_inicial, km_actual, km_aceite) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['marca'], $_POST['modelo'], $_POST['matricula'],
            $_POST['numero'], $_POST['km_inicial'], $_POST['km_actual'], $_POST['km_aceite']
        ]);
        header("Location: vehiculos.php");
        exit;

    } elseif ($accion == 'eliminar') {
        // Eliminar vehículo si no está asignado a un conductor
        $id = $_POST['id'];
        $stmt = $pdo->prepare("SELECT * FROM conductores WHERE vehiculo_id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            echo "<script>alert('Este vehículo está asignado a un conductor y no se puede eliminar hasta desvincularlo.'); window.location.href='vehiculos.php';</script>";
        } else {
            $stmt = $pdo->prepare("DELETE FROM vehiculos WHERE id = ?");
            $stmt->execute([$id]);
            echo "<script>alert('Vehículo eliminado correctamente.'); window.location.href='vehiculos.php';</script>";
        }

    } elseif ($accion == 'editar') {
        // Editar vehículo
        $stmt = $pdo->prepare("UPDATE vehiculos SET marca=?, modelo=?, matricula=?, numero=?, km_inicial=?, km_actual=?, km_aceite=? WHERE id=?");
        $stmt->execute([
            $_POST['marca'], $_POST['modelo'], $_POST['matricula'], $_POST['numero'],
            $_POST['km_inicial'], $_POST['km_actual'], $_POST['km_aceite'], $_POST['id']
        ]);
        header("Location: vehiculos.php");
        exit;
    }
}

// ==============================
// CONSULTA: Obtener lista de vehículos y conductor asignado
// ==============================
$vehiculos = $pdo->query("
    SELECT v.*, c.nombre AS conductor_nombre 
    FROM vehiculos v 
    LEFT JOIN conductores c ON v.id = c.vehiculo_id
")->fetchAll();
?>

<!-- ==============================
    ESTILOS CSS
============================== -->
<style>
/* Estilos generales */
.container {
    max-width: 1250px;
    margin: 20px auto;
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
}

h2 {
    color: #004b87;
    font-size: 1.8rem;
    margin-bottom: 20px;
    text-align: center;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.table th, .table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.table th {
    background-color: #004b87;
    color: white;
}

.table td {
    background-color: #f9f9f9;
}

.table tr:hover {
    background-color: #f1f1f1;
}

.btn, .btn-nuevo, .btn-editar, .btn-ver {
    padding: 8px 15px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    font-size: 0.9rem;
}

.btn {
    background-color: #e74c3c;
    color: white;
}

.btn-nuevo {
    background-color: #2ecc71;
    color: white;
}

.btn-editar {
    background-color: #f39c12;
    color: white;
}

.btn-ver {
    background-color: #3498db;
    color: white;
}

.modal {
    display: none;
    position: fixed;
    z-index: 999;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.modal-content {
    background-color: #fff;
    border-radius: 8px;
    width: 450px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    position: relative;
}

.modal-content h3 {
    margin-bottom: 20px;
    color: #004b87;
}

.modal-content input, .modal-content select {
    width: 100%;
    padding: 10px;
    margin: 8px 0;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 1rem;
}

.modal-content button {
    width: 100%;
    padding: 12px;
    background-color: #2ecc71;
    border: none;
    color: white;
    border-radius: 6px;
    font-size: 1rem;
    cursor: pointer;
    margin-top: 10px;
}

.modal-content button:hover {
    background-color: #27ae60;
}

.close {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 20px;
    color: #e74c3c;
    cursor: pointer;
}

.alerta-mantenimiento {
    background-color: #ffebee;
    font-weight: bold;
}

.header-table {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.label {
    font-weight: bold;
    margin-bottom: 5px;
}
</style>

<!-- ==============================
    INTERFAZ PRINCIPAL
============================== -->
<div class="container">
    <h2>Lista de Vehículos</h2>
    <div class="header-table">
        <button id="openModalAgregar" class="btn btn-nuevo">+ Nuevo Vehículo</button>
    </div>

    <table class="table">
        <tr>
            <th>ID</th><th>Marca</th><th>Modelo</th><th>Matrícula</th><th>Número</th>
            <th>Km Inicial</th><th>Km Actual</th><th>Próx. Mantenimiento</th>
            <th>Conductor</th><th>Acciones</th>
        </tr>
        <?php foreach ($vehiculos as $vehiculo): ?>
            <?php
                $diferencia_km = $vehiculo['km_aceite'] - $vehiculo['km_actual'];
                $clase_alerta = ($diferencia_km <= 500) ? 'alerta-mantenimiento' : '';
            ?>
            <tr class="<?= $clase_alerta ?>">
                <td><?= $vehiculo['id'] ?></td>
                <td><?= $vehiculo['marca'] ?></td>
                <td><?= $vehiculo['modelo'] ?></td>
                <td><?= $vehiculo['matricula'] ?></td>
                <td><?= $vehiculo['numero'] ?></td>
                <td><?= number_format($vehiculo['km_inicial'], 0, ',', '.') ?> km</td>
                <td><?= number_format($vehiculo['km_actual'], 0, ',', '.') ?> km</td>
                <td><?= number_format($vehiculo['km_aceite'], 0, ',', '.') ?> km</td>
                <td><?= $vehiculo['conductor_nombre'] ?? 'No asignado' ?></td>
                <td>
                    <button class="btn-ver" onclick='verVehiculo(<?= json_encode($vehiculo) ?>)'>Ver</button>
                    <button class="btn-editar" onclick='editarVehiculo(<?= json_encode($vehiculo) ?>)'>Editar</button>
                    <form method="post" style="display:inline;" onsubmit="return confirmarEliminacion();">
                        <input type="hidden" name="id" value="<?= $vehiculo['id'] ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <button type="submit" class="btn">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<!-- ==============================
    MODAL: AGREGAR VEHÍCULO
============================== -->
<div id="modalAgregar" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal('modalAgregar')">&times;</span>
        <h3>Agregar Vehículo</h3>
        <form method="post">
            <div class="form-group">
                <label for="marca" class="label">Marca</label>
                <input type="text" name="marca" placeholder="Marca" required>
            </div>
            <div class="form-group">
                <label for="modelo" class="label">Modelo</label>
                <input type="text" name="modelo" placeholder="Modelo" required>
            </div>
            <div class="form-group">
                <label for="matricula" class="label">Matrícula</label>
                <input type="text" name="matricula" placeholder="Matrícula" required>
            </div>
            <div class="form-group">
                <label for="numero" class="label">Número</label>
                <input type="text" name="numero" placeholder="Número" required>
            </div>
            <div class="form-group">
                <label for="km_inicial" class="label">Km Inicial</label>
                <input type="number" name="km_inicial" placeholder="Km Inicial" required>
            </div>
            <div class="form-group">
                <label for="km_actual" class="label">Km Actual</label>
                <input type="number" name="km_actual" placeholder="Km Actual" required>
            </div>
            <div class="form-group">
                <label for="km_aceite" class="label">Km Próximo Mantenimiento</label>
                <input type="number" name="km_aceite" placeholder="Km Mantenimiento" required>
            </div>
            <button type="submit" name="accion" value="agregar">Guardar Vehículo</button>
        </form>
    </div>
</div>

<!-- ==============================
    MODAL: VER VEHÍCULO
============================== -->
<!-- ==============================
    MODAL: VER VEHÍCULO - ELEGANTE
============================== -->
<div id="modalVer" class="modal-vehicle">
    <div class="modal-vehicle__overlay" onclick="cerrarModal('modalVer')"></div>
    <div class="modal-vehicle__container">
        <button class="modal-vehicle__close" onclick="cerrarModal('modalVer')">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        <div class="modal-vehicle__header">
            <h3 class="modal-vehicle__title">Detalles del Vehículo</h3>
            <div class="modal-vehicle__badge">
                <span id="vehicleStatusBadge">Activo</span>
            </div>
        </div>

        <div class="modal-vehicle__image-container">
            <div class="modal-vehicle__image-placeholder" id="vehicleImagePlaceholder">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 18H3a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h18a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <path d="M5 18v-5h1.13a2 2 0 0 1 1.95 1.55L8.5 18"></path>
                    <path d="M18 18v-5h-1.13a2 2 0 0 0-1.95 1.55L14.5 18"></path>
                    <line x1="8" y1="8" x2="16" y2="8"></line>
                    <line x1="6" y1="12" x2="18" y2="12"></line>
                </svg>
            </div>
        </div>

        <div id="detalleVehiculo" class="modal-vehicle__details">
            <!-- Contenido dinámico aquí -->
        </div>

        <div class="modal-vehicle__footer">
            <button class="modal-vehicle__action-btn" onclick="mostrarHistorial()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                Historial
            </button>
            <button class="modal-vehicle__action-btn modal-vehicle__action-btn--primary" onclick="editarVehiculo()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                Editar
            </button>
        </div>
    </div>
</div>

<style>
/* -------------------------------
   MODAL VEHICLE - ELEGANT DESIGN
--------------------------------- */
:root {
    --modal-primary: #4f46e5;
    --modal-primary-light: #6366f1;
    --modal-dark: #1e293b;
    --modal-gray: #64748b;
    --modal-light-gray: #f1f5f9;
    --modal-border: #e2e8f0;
    --modal-success: #10b981;
    --modal-warning: #f59e0b;
    --modal-error: #ef4444;
}

.modal-vehicle {
    display: none;
    position: fixed;
    z-index: 9999;
    inset: 0;
    font-family: 'Inter', sans-serif;
}

.modal-vehicle__overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
}

.modal-vehicle__container {
    position: relative;
    background: white;
    width: 800px;
    max-width: 90%;
    max-height: 90vh;
    margin: auto;
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    animation: modalFadeIn 0.3s ease-out;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-vehicle__close {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: white;
    border: 1px solid var(--modal-border);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    z-index: 10;
}
.modal-vehicle__close:hover {
    background: var(--modal-light-gray);
    transform: rotate(90deg);
}
.modal-vehicle__close svg {
    width: 18px;
    height: 18px;
    color: var(--modal-gray);
}

.modal-vehicle__header {
    padding: 24px 24px 16px;
    border-bottom: 1px solid var(--modal-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-vehicle__title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--modal-dark);
}
.modal-vehicle__badge span {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    background: var(--modal-success);
    color: white;
}

.modal-vehicle__image-container {
    height: 180px;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-vehicle__image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--modal-gray);
}
.modal-vehicle__image-placeholder svg {
    opacity: 0.5;
}

.modal-vehicle__details {
    padding: 24px;
    overflow-y: auto;
    flex-grow: 1;
}

/* === TABLA DE DETALLES === */
.modal-vehicle__detail-grid {
    width: 100%;
    border: 1px solid var(--modal-border);
    border-radius: 6px;
    overflow: hidden;
    border-collapse: collapse;
    box-shadow: 0 0 0 1px var(--modal-border);
}

.modal-vehicle__detail-item {
    display: flex;
    border-bottom: 1px solid var(--modal-border);
}
.modal-vehicle__detail-item:last-child {
    border-bottom: none;
}

.modal-vehicle__detail-label,
.modal-vehicle__detail-value {
    padding: 12px 16px;
    flex: 1;
    border-right: 1px solid var(--modal-border);
}
.modal-vehicle__detail-label {
    background: var(--modal-light-gray);
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--modal-gray);
    text-transform: uppercase;
}
.modal-vehicle__detail-value {
    background: white;
    font-size: 1rem;
    color: var(--modal-dark);
}
.modal-vehicle__detail-value--highlight {
    background: #e0e7ff;
    color: var(--modal-primary);
    font-weight: 600;
}

.modal-vehicle__footer {
    padding: 16px 24px;
    border-top: 1px solid var(--modal-border);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}
.modal-vehicle__action-btn {
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    background: white;
    border: 1px solid var(--modal-border);
    color: var(--modal-dark);
    cursor: pointer;
    transition: all 0.2s ease;
}
.modal-vehicle__action-btn:hover {
    background: var(--modal-light-gray);
}
.modal-vehicle__action-btn svg {
    width: 14px;
    height: 14px;
}
.modal-vehicle__action-btn--primary {
    background: var(--modal-primary);
    border-color: var(--modal-primary);
    color: white;
}
.modal-vehicle__action-btn--primary:hover {
    background: var(--modal-primary-light);
}

@media (max-width: 640px) {
    .modal-vehicle__container {
        max-width: 95%;
    }
    .modal-vehicle__footer {
        flex-direction: column;
    }
    .modal-vehicle__action-btn {
        justify-content: center;
    }
}
</style>

<script>
function verVehiculo(vehiculo) {
    const detalles = [
        { label: 'Marca', value: vehiculo.marca },
        { label: 'Modelo', value: vehiculo.modelo },
        { label: 'Año', value: vehiculo.ano || 'N/A' },
        { label: 'Matrícula', value: vehiculo.matricula },
        { label: 'Número de Vehículo', value: vehiculo.numero },
        { label: 'Kilometraje Inicial', value: vehiculo.km_inicial + ' km' },
        { label: 'Kilometraje Actual', value: vehiculo.km_actual + ' km' },
        { label: 'Próximo Mantenimiento', value: vehiculo.km_aceite + ' km' },
        { label: 'Última Revisión', value: vehiculo.ultima_revision || 'N/A' },
        { label: 'Conductor Asignado', value: vehiculo.conductor_nombre || 'No asignado', highlight: true },
        { label: 'Tipo de Combustible', value: vehiculo.combustible || 'N/A' },
        { label: 'Estado', value: vehiculo.estado || 'Activo' }
    ];

    let html = '<div class="modal-vehicle__detail-grid">';
    detalles.forEach(detalle => {
        html += `
            <div class="modal-vehicle__detail-item">
                <span class="modal-vehicle__detail-label">${detalle.label}</span>
                <span class="modal-vehicle__detail-value ${detalle.highlight ? 'modal-vehicle__detail-value--highlight' : ''}">
                    ${detalle.value}
                </span>
            </div>
        `;
    });
    html += '</div>';

    // Actualizar el estado del badge
    const estado = vehiculo.estado || 'Activo';
    let badgeClass = '';
    if (estado.toLowerCase() === 'activo') {
        badgeClass = 'background: var(--modal-success)';
    } else if (estado.toLowerCase() === 'mantenimiento') {
        badgeClass = 'background: var(--modal-warning)';
    } else if (estado.toLowerCase() === 'inactivo') {
        badgeClass = 'background: var(--modal-error)';
    }

    document.getElementById('vehicleStatusBadge').textContent = estado;
    document.getElementById('vehicleStatusBadge').style = badgeClass;

    document.getElementById('detalleVehiculo').innerHTML = html;
    document.getElementById('modalVer').style.display = 'block';
}

function cerrarModal(id) {
    document.getElementById(id).style.display = 'none';
}

function mostrarHistorial() {
    alert('Mostrar historial del vehículo');
}

function editarVehiculo() {
    alert('Editar vehículo');
}
</script>

<!-- ==============================
    MODAL: EDITAR VEHÍCULO
============================== -->
<div id="modalEditar" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal('modalEditar')">&times;</span>
        <h3>Editar Vehículo</h3>
        <form method="post">
            <input type="hidden" id="editar_id" name="id">
            <div class="form-group">
                <label for="editar_marca" class="label">Marca</label>
                <input type="text" id="editar_marca" name="marca" required>
            </div>
            <div class="form-group">
                <label for="editar_modelo" class="label">Modelo</label>
                <input type="text" id="editar_modelo" name="modelo" required>
            </div>
            <div class="form-group">
                <label for="editar_matricula" class="label">Matrícula</label>
                <input type="text" id="editar_matricula" name="matricula" required>
            </div>
            <div class="form-group">
                <label for="editar_numero" class="label">Número</label>
                <input type="text" id="editar_numero" name="numero" required>
            </div>
            <div class="form-group">
                <label for="editar_km_inicial" class="label">Km Inicial</label>
                <input type="number" id="editar_km_inicial" name="km_inicial" required>
            </div>
            <div class="form-group">
                <label for="editar_km_actual" class="label">Km Actual</label>
                <input type="number" id="editar_km_actual" name="km_actual" required>
            </div>
            <div class="form-group">
                <label for="editar_km_aceite" class="label">Km Próximo Mantenimiento</label>
                <input type="number" id="editar_km_aceite" name="km_aceite" required>
            </div>
            <button type="submit" name="accion" value="editar">Actualizar Vehículo</button>
        </form>
    </div>
</div>

<!-- ==============================
    SCRIPTS JS
============================== -->
<script>
// Función para abrir el modal de agregar vehículo
document.getElementById('openModalAgregar').onclick = function() {
    document.getElementById('modalAgregar').style.display = 'flex';
};

// Función para cerrar el modal
function cerrarModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Función para editar vehículo
function editarVehiculo(vehiculo) {
    document.getElementById('editar_id').value = vehiculo.id;
    document.getElementById('editar_marca').value = vehiculo.marca;
    document.getElementById('editar_modelo').value = vehiculo.modelo;
    document.getElementById('editar_matricula').value = vehiculo.matricula;
    document.getElementById('editar_numero').value = vehiculo.numero;
    document.getElementById('editar_km_inicial').value = vehiculo.km_inicial;
    document.getElementById('editar_km_actual').value = vehiculo.km_actual;
    document.getElementById('editar_km_aceite').value = vehiculo.km_aceite;
    document.getElementById('modalEditar').style.display = 'flex';
}

// Función para mostrar detalles del vehículo
function verVehiculo(vehiculo) {
    const detalles = `
        <p><strong>Marca:</strong> ${vehiculo.marca}</p>
        <p><strong>Modelo:</strong> ${vehiculo.modelo}</p>
        <p><strong>Matrícula:</strong> ${vehiculo.matricula}</p>
        <p><strong>Número:</strong> ${vehiculo.numero}</p>
        <p><strong>Km Inicial:</strong> ${vehiculo.km_inicial}</p>
        <p><strong>Km Actual:</strong> ${vehiculo.km_actual}</p>
        <p><strong>Km Próx. Mantenimiento:</strong> ${vehiculo.km_aceite}</p>
        <p><strong>Conductor:</strong> ${vehiculo.conductor_nombre || 'No asignado'}</p>
    `;
    document.getElementById('detalleVehiculo').innerHTML = detalles;
    document.getElementById('modalVer').style.display = 'flex';
}

// Cerrar modal al hacer clic fuera del contenido
window.onclick = function(event) {
    if (event.target.className === 'modal') {
        cerrarModal(event.target.id);
    }
};
</script>

<?php include '../../layout/footer.php'; ?>
