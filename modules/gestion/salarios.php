<?php
// Obtener pagos de salarios
$pagosSalarios = $pdo->query("
    SELECT ps.*, c.nombre AS conductor_nombre
    FROM pagos_salarios ps
    JOIN conductores c ON ps.conductor_id = c.id
    ORDER BY ps.fecha DESC
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
                <th>Descripción</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tableBodySalarios">
            <?php foreach ($pagosSalarios as $pago): ?>
                <tr>
                    <td><?= $pago['id'] ?></td>
                    <td><?= $pago['fecha'] ?></td>
                    <td><?= htmlspecialchars($pago['conductor_nombre']) ?></td>
                    <td><?= number_format($pago['monto'], 0, ',', '.') ?> XAF</td>
                    <td><?= htmlspecialchars($pago['descripcion']) ?></td>
                    <td>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <button class="btn btn-ver" onclick="verDetallePago('salario', <?= $pago['id'] ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                            <button class="btn btn-ver" onclick="imprimirNomina(<?= $pago['id'] ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                    <rect x="6" y="14" width="12" height="8"></rect>
                                </svg>
                                Imprimir
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>