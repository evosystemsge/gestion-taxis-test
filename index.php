<?php
    include 'layout/header.php';

    // Valores de ejemplo (deberás obtenerlos de la base de datos)
    $totalIngresos = 5000;
    $totalGastos = 2500;
    $totalDeudas = 1200;
    $saldoCaja = 1300;

    // Ejemplo de transacciones recientes (deberían obtenerse de la base de datos)
    $transacciones = [
        ['fecha' => '2025-04-01', 'descripcion' => 'Venta de servicio', 'monto' => 1500],
        ['fecha' => '2025-03-31', 'descripcion' => 'Pago de proveedor', 'monto' => -700],
        ['fecha' => '2025-03-30', 'descripcion' => 'Ingreso en caja', 'monto' => 2000],
        ['fecha' => '2025-03-29', 'descripcion' => 'Compra de insumos', 'monto' => -500],
    ];
?>

<div class="dashboard-container">
    <div class="dashboard">
        <div class="summary-box ingresos">
            <h2>Total Ingresos</h2>
            <p>$<?php echo number_format($totalIngresos, 2); ?></p>
        </div>
        <div class="summary-box gastos">
            <h2>Total Gastos</h2>
            <p>$<?php echo number_format($totalGastos, 2); ?></p>
        </div>
        <div class="summary-box deudas">
            <h2>Total Deudas</h2>
            <p>$<?php echo number_format($totalDeudas, 2); ?></p>
        </div>
        <div class="summary-box saldo">
            <h2>Saldo Caja</h2>
            <p>$<?php echo number_format($saldoCaja, 2); ?></p>
        </div>
    </div>
</div>

<div class="transactions-container">
    <h2>Últimas Transacciones</h2>
    <div class="table-responsive">
        <table class="transactions-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Descripción</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transacciones as $transaccion): ?>
                    <tr>
                        <td><?php echo $transaccion['fecha']; ?></td>
                        <td><?php echo $transaccion['descripcion']; ?></td>
                        <td class="<?php echo $transaccion['monto'] >= 0 ? 'positivo' : 'negativo'; ?>">
                            $<?php echo number_format($transaccion['monto'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'layout/footer.php'; ?>
