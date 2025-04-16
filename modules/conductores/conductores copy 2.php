<?php 
include '../../layout/header.php';
require '../../config/database.php';

// Configuración de imágenes para conductores
$uploadDir = '../../imagenes/conductores/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// ==============================
// LÓGICA DE ACCIONES: Agregar / Editar / Eliminar
// ==============================
if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion == 'agregar') {
        // Procesar imagen del conductor
        $imagen = null;
        if (!empty($_FILES['imagen']['name'])) {
            $fileName = uniqid() . '_' . basename($_FILES['imagen']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $targetPath)) {
                $imagen = $fileName;
            }
        }
        
        // Insertar conductor
        $stmt = $pdo->prepare("INSERT INTO conductores 
            (nombre, telefono, dip, vehiculo_id, ingreso_obligatorio, ingreso_libre, imagen) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['nombre'], $_POST['telefono'], $_POST['dip'], 
            $_POST['vehiculo_id'] ?: null,
            $_POST['ingreso_obligatorio'], $_POST['ingreso_libre'], $imagen
        ]);
        
        header("Location: conductores.php");
        exit;

    } elseif ($accion == 'eliminar') {
        $id = $_POST['id'];
        
        // Verificar si tiene vehículo asignado
        $stmt = $pdo->prepare("SELECT vehiculo_id, imagen FROM conductores WHERE id = ?");
        $stmt->execute([$id]);
        $conductor = $stmt->fetch();
        
        if ($conductor && $conductor['vehiculo_id']) {
            echo "<script>alert('No se puede eliminar este conductor. Primero desvincúlelo de un vehículo.'); window.location.href='conductores.php';</script>";
        } else {
            // Eliminar imagen si existe
            if ($conductor['imagen']) {
                $filePath = $uploadDir . $conductor['imagen'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            $stmt = $pdo->prepare("DELETE FROM conductores WHERE id = ?");
            $stmt->execute([$id]);
            echo "<script>alert('Conductor eliminado correctamente.'); window.location.href='conductores.php';</script>";
        }

    } elseif ($accion == 'editar') {
        $id = $_POST['id'];
        $imagen = null;
        
        // Procesar imagen
        if (!empty($_FILES['editar_imagen']['name'])) {
            // Eliminar imagen anterior si existe
            $stmt = $pdo->prepare("SELECT imagen FROM conductores WHERE id = ?");
            $stmt->execute([$id]);
            $conductor = $stmt->fetch();
            
            if ($conductor['imagen']) {
                $oldImagePath = $uploadDir . $conductor['imagen'];
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            
            // Subir nueva imagen
            $fileName = uniqid() . '_' . basename($_FILES['editar_imagen']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['editar_imagen']['tmp_name'], $targetPath)) {
                $imagen = $fileName;
            }
        } elseif (!empty($_POST['imagen_actual'])) {
            $imagen = $_POST['imagen_actual'];
        }
        
        // Actualizar conductor
        $sql = "UPDATE conductores SET 
                nombre = ?, telefono = ?, dip = ?, 
                vehiculo_id = ?, ingreso_obligatorio = ?, ingreso_libre = ?" . 
                ($imagen !== null ? ", imagen = ?" : "") . 
                " WHERE id = ?";
                
        $params = [
            $_POST['nombre'], $_POST['telefono'], $_POST['dip'],
            $_POST['vehiculo_id'] ?: null,
            $_POST['ingreso_obligatorio'], $_POST['ingreso_libre']
        ];
        
        if ($imagen !== null) {
            $params[] = $imagen;
        }
        
        $params[] = $id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        header("Location: conductores.php");
        exit;
    }
}

// Obtener todos los conductores con información de vehículo asignado
$conductores = $pdo->query("
    SELECT c.*, v.marca AS vehiculo_marca, v.modelo AS vehiculo_modelo, 
           v.matricula AS vehiculo_matricula, v.numero AS vehiculo_numero
    FROM conductores c
    LEFT JOIN vehiculos v ON c.vehiculo_id = v.id
")->fetchAll();

// Obtener vehículos no asignados para los selects
$vehiculosDisponibles = $pdo->query("
    SELECT * FROM vehiculos 
    WHERE id NOT IN (SELECT vehiculo_id FROM conductores WHERE vehiculo_id IS NOT NULL)
")->fetchAll();
?>

<style>
/* ============ ESTILOS PRINCIPALES ============ */
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
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
}

.table td {
    background-color: #f9f9f9;
    font-size: 0.95rem;
}

.table tr:hover td {
    background-color: #f1f1f1;
}

/* ============ BOTONES ============ */
.btn {
    padding: 8px 15px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-nuevo {
    background-color: #28a745;
    color: white;
}

.btn-nuevo:hover {
    background-color: #218838;
}

.btn-editar {
    background-color: #ffc107;
    color: #212529;
}

.btn-editar:hover {
    background-color: #e0a800;
}

.btn-ver {
    background-color: #17a2b8;
    color: white;
}

.btn-ver:hover {
    background-color: #138496;
}

.btn-eliminar {
    background-color: #dc3545;
    color: white;
}

.btn-eliminar:hover {
    background-color: #c82333;
}

/* ============ MODALES ============ */
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
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.modal-vehicle__close {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: white;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    z-index: 10;
}

.modal-vehicle__close:hover {
    background: #f1f5f9;
    transform: rotate(90deg);
}

.modal-vehicle__close svg {
    width: 18px;
    height: 18px;
    color: #64748b;
}

.modal-vehicle__header {
    padding: 24px 24px 16px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.modal-vehicle__header-content {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.modal-vehicle__badge {
    margin-left: auto;
}

.modal-vehicle__badge span {
    display: inline-block;
    padding: 4px 20px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    background: #10b981;
    color: white;
}

.modal-vehicle__title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.modal-vehicle__title--highlight {
    color: #004b87;
    font-weight: 600;
}

.modal-vehicle__body {
    padding: 0 24px;
    overflow-y: auto;
    flex-grow: 1;
}

.modal-vehicle__form {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 16px 0;
}

.modal-vehicle__form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.modal-vehicle__form-row {
    display: flex;
    gap: 16px;
}

.modal-vehicle__form-row .modal-vehicle__form-group {
    flex: 1;
}

.modal-vehicle__form-label {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 600;
}

.modal-vehicle__form-input {
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: border-color 0.2s ease;
    width: 100%;
}

.modal-vehicle__form-input:focus {
    outline: none;
    border-color: #004b87;
}

.modal-vehicle__file-input {
    padding: 8px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 0.9rem;
    width: 100%;
}

.modal-vehicle__image-preview {
    margin-top: 8px;
    min-height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 5px;
}

.modal-vehicle__preview-image {
    max-width: 100%;
    max-height: 100px;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}

.modal-vehicle__footer {
    padding: 16px 24px;
    border-top: 1px solid #e2e8f0;
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
    border: 1px solid #e2e8f0;
    color: #1e293b;
    cursor: pointer;
    transition: all 0.2s ease;
}

.modal-vehicle__action-btn:hover {
    background: #f1f5f9;
}

.modal-vehicle__action-btn svg {
    width: 14px;
    height: 14px;
}

.modal-vehicle__action-btn--primary {
    background: #004b87;
    border-color: #004b87;
    color: white;
}

.modal-vehicle__action-btn--primary:hover {
    background: #003d6e;
}

/* ============ TABLA DE DETALLES ============ */
.modal-vehicle__data-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}

.modal-vehicle__data-table tr:not(:last-child) {
    border-bottom: 1px solid #e2e8f0;
}

.modal-vehicle__data-label {
    padding: 12px 16px;
    font-size: 0.85rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
    background-color: #f8fafc;
    text-align: left;
    border-right: 1px solid #e2e8f0;
    width: 40%;
}

.modal-vehicle__data-value {
    padding: 12px 16px;
    font-size: 1rem;
    color: #1e293b;
    font-weight: 500;
    background-color: white;
}

/* ============ FOTO DEL CONDUCTOR ============ */
.conductor-photo-container {
    width: 100%;
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
}

.conductor-photo {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

/* ============ RESPONSIVE ============ */
@media (max-width: 768px) {
    .modal-vehicle__container {
        max-width: 95%;
    }
    
    .modal-vehicle__form-row {
        flex-direction: column;
    }
    
    .table {
        display: block;
        overflow-x: auto;
    }
    
    .modal-vehicle__header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .modal-vehicle__footer {
        flex-wrap: wrap;
    }
    
    .modal-vehicle__action-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<!-- ============ INTERFAZ PRINCIPAL ============ -->
<div class="container">
    <h2>Lista de Conductores</h2>
    <div class="header-table">
        <button id="openModalAgregar" class="btn btn-nuevo">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Nuevo Conductor
        </button>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Teléfono</th>
                <th>DIP</th>
                <th>Ingreso Oblig.</th>
                <th>Ingreso Libre</th>
                <th>Vehículo Asignado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($conductores as $conductor): ?>
                <tr>
                    <td><?= $conductor['id'] ?></td>
                    <td><?= htmlspecialchars($conductor['nombre']) ?></td>
                    <td><?= htmlspecialchars($conductor['telefono']) ?></td>
                    <td><?= htmlspecialchars($conductor['dip']) ?></td>
                    <td><?= number_format($conductor['ingreso_obligatorio'], 0, ',', '.') ?> FCFA</td>
                    <td><?= number_format($conductor['ingreso_libre'], 0, ',', '.') ?> FCFA</td>
                    <td>
                        <?php if ($conductor['vehiculo_id']): ?>
                            <?= htmlspecialchars($conductor['vehiculo_marca']) ?> <?= htmlspecialchars($conductor['vehiculo_modelo']) ?> - 
                            <?= htmlspecialchars($conductor['vehiculo_matricula']) ?> (<?= htmlspecialchars($conductor['vehiculo_numero']) ?>)
                        <?php else: ?>
                            No asignado
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 5px;">
                            <button class="btn btn-ver" onclick="verConductor(<?= htmlspecialchars(json_encode($conductor), ENT_QUOTES, 'UTF-8') ?>)">
                                Ver
                            </button>
                            <button class="btn btn-editar" onclick="editarConductor(<?= htmlspecialchars(json_encode($conductor), ENT_QUOTES, 'UTF-8') ?>)">
                                Editar
                            </button>
                            <form method="post" style="display:inline;" onsubmit="return confirmarEliminacion();">
                                <input type="hidden" name="id" value="<?= $conductor['id'] ?>">
                                <input type="hidden" name="accion" value="eliminar">
                                <button type="submit" class="btn btn-eliminar">
                                    Eliminar
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ============ MODAL AGREGAR CONDUCTOR ============ -->
<div id="modalAgregar" class="modal-vehicle">
    <div class="modal-vehicle__overlay" onclick="cerrarModal('modalAgregar')"></div>
    <div class="modal-vehicle__container">
        <button class="modal-vehicle__close" onclick="cerrarModal('modalAgregar')">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
        
        <div class="modal-vehicle__header">
            <h3 class="modal-vehicle__title">Agregar Nuevo Conductor</h3>
        </div>
        
        <div class="modal-vehicle__body">
            <form method="post" enctype="multipart/form-data" class="modal-vehicle__form" id="modalAgregarForm">
                <div class="modal-vehicle__form-group">
                    <label for="nombre" class="modal-vehicle__form-label">Nombre</label>
                    <input type="text" name="nombre" id="nombre" class="modal-vehicle__form-input" placeholder="Nombre completo" required>
                </div>
                
                <div class="modal-vehicle__form-row">
                    <div class="modal-vehicle__form-group">
                        <label for="telefono" class="modal-vehicle__form-label">Teléfono</label>
                        <input type="text" name="telefono" id="telefono" class="modal-vehicle__form-input" placeholder="Teléfono" required>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label for="dip" class="modal-vehicle__form-label">DIP</label>
                        <input type="text" name="dip" id="dip" class="modal-vehicle__form-input" placeholder="Número DIP" required>
                    </div>
                </div>
                
                <div class="modal-vehicle__form-row">
                    <div class="modal-vehicle__form-group">
                        <label for="ingreso_obligatorio" class="modal-vehicle__form-label">Ingreso Obligatorio</label>
                        <input type="number" name="ingreso_obligatorio" id="ingreso_obligatorio" class="modal-vehicle__form-input" placeholder="FCFA" required>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label for="ingreso_libre" class="modal-vehicle__form-label">Ingreso Libre</label>
                        <input type="number" name="ingreso_libre" id="ingreso_libre" class="modal-vehicle__form-input" placeholder="FCFA" required>
                    </div>
                </div>
                
                <div class="modal-vehicle__form-group">
                    <label for="vehiculo_id" class="modal-vehicle__form-label">Vehículo Asignado</label>
                    <select name="vehiculo_id" id="vehiculo_id" class="modal-vehicle__form-input">
                        <option value="">Seleccione un vehículo</option>
                        <?php foreach ($vehiculosDisponibles as $vehiculo): ?>
                            <option value="<?= $vehiculo['id'] ?>">
                                <?= htmlspecialchars($vehiculo['marca']) ?> <?= htmlspecialchars($vehiculo['modelo']) ?> - <?= htmlspecialchars($vehiculo['matricula']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal-vehicle__form-group">
                    <label for="imagen" class="modal-vehicle__form-label">Foto del Conductor</label>
                    <input type="file" name="imagen" id="imagen" class="modal-vehicle__file-input" accept="image/*">
                    <div class="modal-vehicle__image-preview" id="preview" style="display: none;">
                        <img id="previewImg" src="#" alt="Vista previa" class="modal-vehicle__preview-image">
                    </div>
                </div>
            </form>
        </div>
        
        <div class="modal-vehicle__footer">
            <button type="button" class="modal-vehicle__action-btn" onclick="cerrarModal('modalAgregar')">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
                Cancelar
            </button>
            <button type="submit" name="accion" value="agregar" class="modal-vehicle__action-btn modal-vehicle__action-btn--primary" form="modalAgregarForm">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                Guardar Conductor
            </button>
        </div>
    </div>
</div>

<!-- ============ MODAL VER CONDUCTOR ============ -->
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
            <div class="conductor-photo-container" id="conductorFotoContainer">
                <!-- Imagen del conductor se insertará aquí -->
            </div>
            
            <div class="modal-vehicle__header-content">
                <h3 class="modal-vehicle__title">VEHÍCULO ASIGNADO: <span id="vehiculoAsignado" class="modal-vehicle__title--highlight">No asignado</span></h3>
                <div class="modal-vehicle__badge">
                    <span id="conductorStatusBadge">Activo</span>
                </div>
            </div>
        </div>
        
        <div class="modal-vehicle__body">
            <div id="detalleConductor" style="display: flex; gap: 20px; flex-wrap: wrap;">
                <!-- Contenido dinámico aquí -->
            </div>
        </div>
        
        <div class="modal-vehicle__footer">
            <button class="modal-vehicle__action-btn" onclick="mostrarHistorial('ingresos')">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                Historial de Ingresos
            </button>
            <button class="modal-vehicle__action-btn" onclick="mostrarHistorial('salarios')">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v4"></path>
                    <path d="M12 18v4"></path>
                    <path d="M19 8l-7 7-7-7"></path>
                </svg>
                Historial de Salarios
            </button>
            <button class="modal-vehicle__action-btn" onclick="mostrarHistorial('deudas')">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v4"></path>
                    <path d="M12 18v4"></path>
                    <path d="M5 12h14"></path>
                </svg>
                Historial de Deudas
            </button>
            <button class="modal-vehicle__action-btn modal-vehicle__action-btn--primary" onclick="editarConductor()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                Editar
            </button>
        </div>
    </div>
</div>

<!-- ============ MODAL EDITAR CONDUCTOR ============ -->
<div id="modalEditar" class="modal-vehicle">
    <div class="modal-vehicle__overlay" onclick="cerrarModal('modalEditar')"></div>
    <div class="modal-vehicle__container">
        <button class="modal-vehicle__close" onclick="cerrarModal('modalEditar')">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
        
        <div class="modal-vehicle__header">
            <h3 class="modal-vehicle__title">Editar Conductor</h3>
        </div>
        
        <div class="modal-vehicle__body">
            <form method="post" enctype="multipart/form-data" class="modal-vehicle__form" id="modalEditarForm">
                <input type="hidden" id="editar_id" name="id">
                <input type="hidden" id="imagen_actual" name="imagen_actual">
                
                <div class="modal-vehicle__form-group">
                    <label for="editar_nombre" class="modal-vehicle__form-label">Nombre</label>
                    <input type="text" id="editar_nombre" name="nombre" class="modal-vehicle__form-input" required>
                </div>
                
                <div class="modal-vehicle__form-row">
                    <div class="modal-vehicle__form-group">
                        <label for="editar_telefono" class="modal-vehicle__form-label">Teléfono</label>
                        <input type="text" id="editar_telefono" name="telefono" class="modal-vehicle__form-input" required>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label for="editar_dip" class="modal-vehicle__form-label">DIP</label>
                        <input type="text" id="editar_dip" name="dip" class="modal-vehicle__form-input" required>
                    </div>
                </div>
                
                <div class="modal-vehicle__form-row">
                    <div class="modal-vehicle__form-group">
                        <label for="editar_ingreso_obligatorio" class="modal-vehicle__form-label">Ingreso Obligatorio</label>
                        <input type="number" id="editar_ingreso_obligatorio" name="ingreso_obligatorio" class="modal-vehicle__form-input" required>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label for="editar_ingreso_libre" class="modal-vehicle__form-label">Ingreso Libre</label>
                        <input type="number" id="editar_ingreso_libre" name="ingreso_libre" class="modal-vehicle__form-input" required>
                    </div>
                </div>
                
                <div class="modal-vehicle__form-group">
                    <label for="editar_vehiculo_id" class="modal-vehicle__form-label">Vehículo Asignado</label>
                    <select name="vehiculo_id" id="editar_vehiculo_id" class="modal-vehicle__form-input">
                        <option value="">Seleccione un vehículo</option>
                        <?php foreach ($vehiculosDisponibles as $vehiculo): ?>
                            <option value="<?= $vehiculo['id'] ?>">
                                <?= htmlspecialchars($vehiculo['marca']) ?> <?= htmlspecialchars($vehiculo['modelo']) ?> - <?= htmlspecialchars($vehiculo['matricula']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal-vehicle__form-group">
                    <label for="editar_imagen" class="modal-vehicle__form-label">Foto del Conductor</label>
                    <input type="file" name="editar_imagen" id="editar_imagen" class="modal-vehicle__file-input" accept="image/*">
                    <div class="modal-vehicle__image-preview" id="editar_preview">
                        <img id="editar_previewImg" src="#" alt="Vista previa" class="modal-vehicle__preview-image" style="display: none;">
                        <div id="current_image"></div>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="modal-vehicle__footer">
            <button type="button" class="modal-vehicle__action-btn" onclick="cerrarModal('modalEditar')">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
                Cancelar
            </button>
            <button type="submit" name="accion" value="editar" class="modal-vehicle__action-btn modal-vehicle__action-btn--primary" form="modalEditarForm">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                Actualizar Conductor
            </button>
        </div>
    </div>
</div>

<!-- ============ SCRIPTS JS ============ -->
<script>
// Variables globales
let currentConductor = null;

// Función para abrir modal de agregar
document.getElementById('openModalAgregar').addEventListener('click', function() {
    document.getElementById('modalAgregar').style.display = 'block';
});

// Función para cerrar modales
function cerrarModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Función para ver conductor
function verConductor(conductor) {
    currentConductor = conductor;
    
    // Configurar foto del conductor
    const fotoContainer = document.getElementById('conductorFotoContainer');
    fotoContainer.innerHTML = '';
    
    if (conductor.imagen) {
        fotoContainer.innerHTML = `
            <img src="../../imagenes/conductores/${conductor.imagen}" 
                 class="conductor-photo" 
                 alt="Foto del conductor">
        `;
    } else {
        fotoContainer.innerHTML = `
            <div style="color: #64748b; text-align: center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5.52 19c.64-2.2 1.84-3 3.22-3h6.52c1.38 0 2.58.8 3.22 3"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                    <circle cx="12" cy="12" r="10"></circle>
                </svg>
                <p>No hay foto disponible</p>
            </div>
        `;
    }
    
    // Configurar vehículo asignado
    document.getElementById('vehiculoAsignado').textContent = 
        conductor.vehiculo_id ? 
        `${conductor.vehiculo_marca} ${conductor.vehiculo_modelo} - ${conductor.vehiculo_matricula}` : 
        'No asignado';
    
    // Datos para las columnas
    const columna1 = [
        { label: 'NOMBRE', value: conductor.nombre || 'N/A' },
        { label: 'TELÉFONO', value: conductor.telefono || 'N/A' },
        { label: 'DIP', value: conductor.dip || 'N/A' },
        { label: 'FECHA REGISTRO', value: conductor.fecha_registro || 'N/A' }
    ];
    
    const columna2 = [
        { label: 'INGRESO OBLIGATORIO', value: (conductor.ingreso_obligatorio || '0') + ' FCFA' },
        { label: 'INGRESO LIBRE', value: (conductor.ingreso_libre || '0') + ' FCFA' },
        { label: 'ESTADO', value: conductor.estado || 'Activo' },
        { label: 'OBSERVACIONES', value: conductor.observaciones || 'Ninguna' }
    ];
    
    // Generar HTML para las tablas
    const html = `
        <div style="flex: 1; min-width: 300px;">
            <table class="modal-vehicle__data-table">
                ${columna1.map(item => `
                    <tr>
                        <td class="modal-vehicle__data-label">${item.label}</td>
                        <td class="modal-vehicle__data-value">${item.value}</td>
                    </tr>
                `).join('')}
            </table>
        </div>
        <div style="flex: 1; min-width: 300px;">
            <table class="modal-vehicle__data-table">
                ${columna2.map(item => `
                    <tr>
                        <td class="modal-vehicle__data-label">${item.label}</td>
                        <td class="modal-vehicle__data-value">${item.value}</td>
                    </tr>
                `).join('')}
            </table>
        </div>
    `;
    
    // Insertar todo el contenido
    document.getElementById('detalleConductor').innerHTML = html;
    document.getElementById('modalVer').style.display = 'block';
}

// Función para editar conductor
function editarConductor(conductor = null) {
    if (!conductor && currentConductor) {
        conductor = currentConductor;
    }
    
    if (conductor) {
        document.getElementById('editar_id').value = conductor.id;
        document.getElementById('editar_nombre').value = conductor.nombre;
        document.getElementById('editar_telefono').value = conductor.telefono;
        document.getElementById('editar_dip').value = conductor.dip;
        document.getElementById('editar_ingreso_obligatorio').value = conductor.ingreso_obligatorio;
        document.getElementById('editar_ingreso_libre').value = conductor.ingreso_libre;
        document.getElementById('editar_vehiculo_id').value = conductor.vehiculo_id || '';
        document.getElementById('imagen_actual').value = conductor.imagen || '';
        
        // Mostrar imagen actual
        const previewDiv = document.getElementById('current_image');
        if (conductor.imagen) {
            previewDiv.innerHTML = `
                <img src="../../imagenes/conductores/${conductor.imagen}" 
                     class="modal-vehicle__preview-image"
                     style="max-width: 100px; max-height: 80px;">
                <div style="font-size: 12px; color: #666; text-align: center; margin-top: 5px;">Foto actual</div>
            `;
        } else {
            previewDiv.innerHTML = '<div style="color: #999; font-size: 12px; text-align: center;">No hay foto</div>';
        }
        
        cerrarModal('modalVer');
        document.getElementById('modalEditar').style.display = 'block';
    } else {
        // Si no hay conductor, abrir modal de agregar
        cerrarModal('modalVer');
        document.getElementById('modalAgregar').style.display = 'block';
    }
}

// Función para mostrar historial
function mostrarHistorial(tipo) {
    if (!currentConductor) return;
    
    alert(`Mostrando historial de ${tipo} para ${currentConductor.nombre}`);
    // Aquí iría la lógica para cargar el historial específico
}

// Previsualización de imágenes al seleccionarlas (para agregar)
document.getElementById('imagen')?.addEventListener('change', function(e) {
    const preview = document.getElementById('preview');
    const previewImg = document.getElementById('previewImg');
    
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        }
        
        reader.readAsDataURL(this.files[0]);
    }
});

// Previsualización de imágenes al seleccionarlas (para editar)
document.getElementById('editar_imagen')?.addEventListener('change', function(e) {
    const preview = document.getElementById('editar_previewImg');
    const currentImage = document.getElementById('current_image');
    
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            currentImage.innerHTML = '';
        }
        
        reader.readAsDataURL(this.files[0]);
    }
});

// Cerrar modal al hacer clic fuera del contenido
window.onclick = function(event) {
    if (event.target.className === 'modal-vehicle__overlay') {
        const modalId = event.target.parentElement.id;
        cerrarModal(modalId);
    }
};

// Confirmar eliminación
function confirmarEliminacion() {
    return confirm('¿Estás seguro de que deseas eliminar este conductor?');
}
</script>

<?php include '../../layout/footer.php'; ?>