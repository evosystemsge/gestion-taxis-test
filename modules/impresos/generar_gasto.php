<?php
require '../../config/libs/tcpdf/tcpdf.php';

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Flota Taxis');
$pdf->SetAuthor('Flota Taxis');
$pdf->SetTitle('Registro de Gastos');
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// Encabezado
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'REGISTRO DE GASTOS', 0, 1, 'C');
$pdf->Ln(10);

// Tabla de gastos
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(30, 7, 'Fecha', 1, 0, 'C');
$pdf->Cell(40, 7, 'Concepto', 1, 0, 'C');
$pdf->Cell(30, 7, 'Monto', 1, 0, 'C');
$pdf->Cell(40, 7, 'Proveedor', 1, 0, 'C');
$pdf->Cell(40, 7, 'Responsable', 1, 1, 'C');

$pdf->SetFont('helvetica', '', 10);
for ($i = 1; $i <= 20; $i++) {
    $pdf->Cell(30, 7, '', 1, 0);
    $pdf->Cell(40, 7, '', 1, 0);
    $pdf->Cell(30, 7, '', 1, 0);
    $pdf->Cell(40, 7, '', 1, 0);
    $pdf->Cell(40, 7, '', 1, 1);
}

$pdf->Output('registro_gastos.pdf', 'I');
?>