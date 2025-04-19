<?php
// Obtener préstamos
$prestamos = $pdo->query("
    SELECT p.*, c.nombre AS conductor_nombre
    FROM prestamos p
    JOIN conductores c ON p.conductor_id = c.id
    ORDER BY p.fecha DESC
")->fetchAll();
?>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Conductor</th>
                <th>Monto</th>
                <th>Saldo Pendiente</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tableBodyPrestamos">
            <?php foreach ($prestamos as $prestamo): ?>
                <tr data-conductor="<?= $prestamo['conductor_id'] ?>" data-estado="<?= $prestamo['estado'] ?>">
                    <td><?= $prestamo['id'] ?></td>
                    <td><?= $prestamo['fecha'] ?></td>
                    <td><?= htmlspecialchars($prestamo['conductor_nombre']) ?></td>
                    <td><?= number_format($prestamo['monto'], 0, ',', '.') ?> XAF</td>
                    <td><?= number_format($prestamo['saldo_pendiente'], 0, ',', '.') ?> XAF</td>
                    <td>
                        <span class="badge badge-<?= $prestamo['estado'] ?>">
                            <?= ucfirst($prestamo['estado']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <button class="btn btn-ver" onclick="verPrestamo(<?= $prestamo['id'] ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                            <button class="btn btn-nuevo" onclick="abrirModalPagoPrestamo(<?= $prestamo['id'] ?>, <?= $prestamo['saldo_pendiente'] ?>, '<?= htmlspecialchars($prestamo['conductor_nombre']) ?>')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 2v20M2 12h20"></path>
                                </svg>
                                Pagar
                            </button>
                            <button class="btn btn-editar" onclick="editarPrestamo(<?= $prestamo['id'] ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>
                            <form method="post" style="display:inline;" onsubmit="return confirmarEliminarPrestamo(this, <?= $prestamo['id'] ?>)">
                                <input type="hidden" name="id" value="<?= $prestamo['id'] ?>">
                                <input type="hidden" name="accion" value="eliminar_prestamo">
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
</div>
quiero agregar un filtro a esta hoja, buscar, rango de fechas, listar conductor 
y al lado el boton agregar prestamo: quiero que ese boton agregue el prestamo 
abriendo un modal de agregar, esa accion agregara un prestamo al conductor en 
la tabla prestamos y generara un movimiento de caja 
restando saldo de la caja predeterminada de la tabla cajas y  en la tabla 
movimientos_caja se registrara las columnas que interactuaran en la tabla movimientos_caja: 
son caja_id, prestamo_id, tipo(ingreso o egreso), monto, descripcion, fecha.

Tablas que van a interactuar

Prestamos
id
fecha
conductor_id
monto
descripcion
saldo_pendiente
estado

Cajas
id
nombre
tipo
predeterminada(actualmente la predeterminada es 1)
saldo_actual

Movimientos caja
id
caja_id
ingreso_id
pago_prestamo_id
pago_amonestacion_id
prestamo_id
tipo(ingreso o egreso)
monto
descripcion
fecha
