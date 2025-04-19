<?php
require '../../config/database.php';

$conductorId = $_GET['conductor_id'] ?? null;

if (!$conductorId) {
    die('ID de conductor no proporcionado');
}

// Obtener datos del conductor y su vehículo
$query = "SELECT c.*, v.marca, v.modelo, v.matricula, v.numero 
          FROM conductores c 
          LEFT JOIN vehiculos v ON c.vehiculo_id = v.id 
          WHERE c.id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$conductorId]);
$conductor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conductor) {
    die('Conductor no encontrado');
}

// Generar PDF
require '../../config/libs/tcpdf/tcpdf.php';

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Flota Taxis');
$pdf->SetAuthor('Flota Taxis');
$pdf->SetTitle('Ingresos Diarios - ' . $conductor['nombre']);
$pdf->SetMargins(8, 5, 10);
$pdf->SetAutoPageBreak(true, 5);
$pdf->AddPage();

// Encabezado
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 0, 'INGRESOS DIARIOS', 0, 1, 'C');
$pdf->Ln(5);

// Datos del conductor
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(60, 7, 'Nombre: ' . $conductor['nombre'], 0, 0);
$pdf->Cell(60, 7, 'DIP: ' . $conductor['dip'], 0, 0);
$pdf->Cell(60, 7, 'Tel: ' . $conductor['telefono'], 0, 1);

$pdf->Cell(60, 7, 'Vehículo: ' . $conductor['marca'] . ' ' . $conductor['modelo'], 0, 0);
$pdf->Cell(60, 7, 'Matrícula: ' . $conductor['matricula'], 0, 0);
$pdf->Cell(60, 7, 'Número: ' . $conductor['numero'], 0, 1);
$pdf->Ln(10);

// Tabla de ingresos
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(30, 7, 'Fecha', 1, 0, 'C');
$pdf->Cell(15, 7, 'Nº', 1, 0, 'C');
$pdf->Cell(30, 7, 'Ingreso', 1, 0, 'C');
$pdf->Cell(30, 7, 'Pendiente', 1, 0, 'C');
$pdf->Cell(30, 7, 'Completado', 1, 0, 'C');
$pdf->Cell(30, 7, 'Kilómetros', 1, 0, 'C');
$pdf->Cell(30, 7, 'Firma', 1, 1, 'C');

$pdf->SetFont('helvetica', '', 10);
for ($i = 1; $i <= 35; $i++) {
    $pdf->Cell(30, 7, '', 1, 0);
    $pdf->Cell(15, 7, '', 1, 0, 'C');
    $pdf->Cell(30, 7, '', 1, 0);
    $pdf->Cell(30, 7, '', 1, 0);
    $pdf->Cell(30, 7, '', 1, 0);
    $pdf->Cell(30, 7, '', 1, 0);
    $pdf->Cell(30, 7, '', 1, 1);
}

$pdf->Output('ingresos_' . $conductor['nombre'] . '.pdf', 'I');
?>