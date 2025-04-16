<?php
// Obtener amonestaciones
$amonestaciones = $pdo->query("
    SELECT a.*, c.nombre AS conductor_nombre
    FROM amonestaciones a
    JOIN conductores c ON a.conductor_id = c.id
    ORDER BY a.fecha DESC
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
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tableBodyAmonestaciones">
            <?php foreach ($amonestaciones as $amonestacion): ?>
                <tr data-conductor="<?= $amonestacion['conductor_id'] ?>" data-estado="<?= $amonestacion['estado'] ?>">
                    <td><?= $amonestacion['id'] ?></td>
                    <td><?= $amonestacion['fecha'] ?></td>
                    <td><?= htmlspecialchars($amonestacion['conductor_nombre']) ?></td>
                    <td><?= number_format($amonestacion['monto'], 0, ',', '.') ?> XAF</td>
                    <td>
                        <span class="badge badge-<?= $amonestacion['estado'] ?>">
                            <?= ucfirst($amonestacion['estado']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <button class="btn btn-ver" onclick="verAmonestacion(<?= $amonestacion['id'] ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                            <button class="btn btn-nuevo" onclick="abrirModalPagoAmonestacion(<?= $amonestacion['id'] ?>, <?= $amonestacion['monto'] ?>, '<?= htmlspecialchars($amonestacion['conductor_nombre']) ?>')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 2v20M2 12h20"></path>
                                </svg>
                                Pagar
                            </button>
                            <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar esta amonestación?')">
                                <input type="hidden" name="id" value="<?= $amonestacion['id'] ?>">
                                <input type="hidden" name="accion" value="eliminar_amonestacion">
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