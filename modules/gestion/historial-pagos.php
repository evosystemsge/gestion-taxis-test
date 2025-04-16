<?php
// Obtener historial de pagos
$historialPagos = $pdo->query("
    (SELECT 'prestamo' AS tipo, pp.id, pp.fecha, pp.monto, pp.descripcion, c.nombre AS conductor_nombre, p.id AS referencia_id, p.conductor_id
     FROM pagos_prestamos pp
     JOIN prestamos p ON pp.prestamo_id = p.id
     JOIN conductores c ON p.conductor_id = c.id)
    
    UNION ALL
    
    (SELECT 'amonestacion' AS tipo, pa.id, pa.fecha, pa.monto, pa.descripcion, c.nombre AS conductor_nombre, a.id AS referencia_id, a.conductor_id
     FROM pagos_amonestaciones pa
     JOIN amonestaciones a ON pa.amonestacion_id = a.id
     JOIN conductores c ON a.conductor_id = c.id)
    
    UNION ALL
    
    (SELECT 'ingreso' AS tipo, pip.id, pip.fecha, pip.monto, pip.descripcion, c.nombre AS conductor_nombre, NULL AS referencia_id, pip.conductor_id
     FROM pagos_ingresos_pendientes pip
     JOIN conductores c ON pip.conductor_id = c.id)
    
    UNION ALL
    
    (SELECT 'salario' AS tipo, ps.id, ps.fecha, ps.monto, ps.descripcion, c.nombre AS conductor_nombre, NULL AS referencia_id, ps.conductor_id
     FROM pagos_salarios ps
     JOIN conductores c ON ps.conductor_id = c.id)
    
    ORDER BY fecha DESC
")->fetchAll();
?>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Conductor</th>
                <th>Monto</th>
                <th>Descripción</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tableBodyHistorial">
            <?php foreach ($historialPagos as $pago): ?>
                <tr data-conductor="<?= $pago['conductor_id'] ?>">
                    <td><?= $pago['id'] ?></td>
                    <td><?= $pago['fecha'] ?></td>
                    <td><?= ucfirst($pago['tipo']) ?></td>
                    <td><?= htmlspecialchars($pago['conductor_nombre']) ?></td>
                    <td><?= number_format($pago['monto'], 0, ',', '.') ?> XAF</td>
                    <td><?= htmlspecialchars($pago['descripcion']) ?></td>
                    <td>
                        <button class="btn btn-ver" onclick="verDetallePago('<?= $pago['tipo'] ?>', <?= $pago['id'] ?>)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                        <button class="btn btn-eliminar" onclick="eliminarPago('<?= $pago['tipo'] ?>', <?= $pago['id'] ?>)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>