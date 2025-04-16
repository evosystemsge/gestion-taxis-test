<?php
// Obtener conductores completos con información adicional
$conductoresCompletos = [];
$stmt = $pdo->query("
    SELECT c.id, c.nombre, c.salario_mensual, c.dias_por_ciclo,
           (SELECT COUNT(*) FROM ingresos WHERE conductor_id = c.id AND tipo_ingreso = 'obligatorio' AND ciclo_completado = 1) AS ciclos_completados,
           (SELECT COUNT(*) FROM ingresos WHERE conductor_id = c.id AND tipo_ingreso = 'obligatorio' AND ciclo = (SELECT MAX(ciclo) FROM ingresos WHERE conductor_id = c.id)) AS dias_trabajados_ultimo_ciclo,
           (SELECT COALESCE(SUM(monto_pendiente), 0) FROM ingresos WHERE conductor_id = c.id AND monto_pendiente > 0) AS total_pendiente,
           (SELECT COALESCE(SUM(saldo_pendiente), 0) FROM prestamos WHERE conductor_id = c.id AND estado = 'pendiente') AS total_prestamos,
           (SELECT COALESCE(SUM(monto), 0) FROM amonestaciones WHERE conductor_id = c.id AND estado = 'activa') AS total_amonestaciones
    FROM conductores c
    ORDER BY c.nombre
");
$conductoresCompletos = $stmt->fetchAll();
?>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Salario Mensual</th>
                <th>Días por Ciclo</th>
                <th>Ciclos Completados</th>
                <th>Días Trabajados</th>
                <th>Total Pendiente</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tableBodyConductores">
            <?php foreach ($conductoresCompletos as $conductor): ?>
                <tr>
                    <td><?= $conductor['id'] ?></td>
                    <td><?= htmlspecialchars($conductor['nombre']) ?></td>
                    <td><?= number_format($conductor['salario_mensual'], 0, ',', '.') ?> XAF</td>
                    <td><?= $conductor['dias_por_ciclo'] ?></td>
                    <td><?= $conductor['ciclos_completados'] ?></td>
                    <td><?= $conductor['dias_trabajados_ultimo_ciclo'] ?> / <?= $conductor['dias_por_ciclo'] ?></td>
                    <td><?= number_format($conductor['total_pendiente'], 0, ',', '.') ?> XAF</td>
                    <td>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <button class="btn btn-ver" onclick="verDetalleConductor(<?= $conductor['id'] ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                            <button class="btn btn-nuevo" onclick="abrirModalPagoSalario(<?= $conductor['id'] ?>, '<?= htmlspecialchars($conductor['nombre']) ?>', <?= $conductor['salario_mensual'] ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 2v20M2 12h20"></path>
                                </svg>
                                Pagar
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>