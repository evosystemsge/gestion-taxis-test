<?php
require '../../config/database.php';

$conductorId = $_GET['conductor_id'] ?? null;

if (!$conductorId) {
    die('ID de conductor no proporcionado');
}

// Obtener datos del conductor
$query = "SELECT * FROM conductores WHERE id = ?";
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
$pdf->SetTitle('Contrato - ' . $conductor['nombre']);
$pdf->SetMargins(20, 20, 20);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// Contenido del contrato
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'CONTRATO DE TRABAJO', 0, 1, 'C');
$pdf->Ln(10);

$pdf->SetFont('helvetica', '', 12);
$html = <<<EOD
<p style="text-align: justify;">
<b>CONTRATO DE TRABAJO</b> que celebran por una parte <b>FLOTA TAXIS</b>, representada en este acto por su gerente, 
y por la otra parte el Sr. <b>{$conductor['nombre']}</b>, identificado con DIP N° {$conductor['dip']}, 
a quien en lo sucesivo se le denominará "EL CONDUCTOR", al tenor de las siguientes declaraciones y cláusulas:
</p>

<h4>DECLARACIONES</h4>
<p style="text-align: justify;">
I. Declara FLOTA TAXIS que es una empresa dedicada al servicio de transporte de pasajeros en vehículos taxi.
</p>
<p style="text-align: justify;">
II. Declara EL CONDUCTOR que tiene la experiencia y capacidad necesaria para desempeñar las funciones que se le encomienden.
</p>

<h4>CLÁUSULAS</h4>
<p style="text-align: justify;">
<b>PRIMERA.</b> FLOTA TAXIS contrata los servicios de EL CONDUCTOR para que preste servicios de conducción de vehículos taxi.
</p>
<p style="text-align: justify;">
<b>SEGUNDA.</b> EL CONDUCTOR se obliga a cumplir con los horarios y turnos que le sean asignados, así como a mantener el vehículo en buen estado.
</p>
<p style="text-align: justify;">
<b>TERCERA.</b> EL CONDUCTOR recibirá un salario mensual de {$conductor['salario_mensual']} XAF, más los ingresos diarios acordados.
</p>
<p style="text-align: justify;">
<b>CUARTA.</b> El presente contrato tendrá una duración de un año, prorrogable automáticamente salvo renuncia de alguna de las partes.
</p>

<p style="text-align: center; margin-top: 40px;">
__________________________<br>
<b>FLOTA TAXIS</b><br>
Firma y sello
</p>

<p style="text-align: center; margin-top: 40px;">
__________________________<br>
<b>{$conductor['nombre']}</b><br>
EL CONDUCTOR
</p>
EOD;

$pdf->writeHTML($html, true, false, true, false, '');

$pdf->Output('contrato_' . $conductor['nombre'] . '.pdf', 'I');
?>