<?php
require '../../config/database.php';
header('Content-Type: application/json');

// Validar acceso
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => 'Método no permitido']));
}

// Obtener filtros del POST
$data = json_decode(file_get_contents('php://input'), true);
$filtros = [
    'search' => $data['search'] ?? '',
    'marca' => $data['marca'] ?? '',
    'modelo' => $data['modelo'] ?? '',
    'fecha' => $data['fecha'] ?? ''
];

// Construir consulta con filtros
$sql = "SELECT c.*, v.marca AS vehiculo_marca, v.modelo AS vehiculo_modelo 
        FROM conductores c
        LEFT JOIN vehiculos v ON c.vehiculo_id = v.id
        WHERE 1=1";

$params = [];

if (!empty($filtros['search'])) {
    $sql .= " AND (c.nombre LIKE ? OR c.telefono LIKE ? OR c.dip LIKE ?)";
    $searchTerm = "%{$filtros['search']}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($filtros['marca'])) {
    $sql .= " AND v.marca = ?";
    $params[] = $filtros['marca'];
}

if (!empty($filtros['modelo'])) {
    $sql .= " AND v.modelo = ?";
    $params[] = $filtros['modelo'];
}

if (!empty($filtros['fecha'])) {
    $sql .= " AND c.fecha = ?";
    $params[] = $filtros['fecha'];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$conductores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generar Excel (simple)
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="conductores_export.xls"');

echo "<table border='1'>";
echo "<tr><th>ID</th><th>Nombre</th><th>Teléfono</th><th>Vehículo</th></tr>";

foreach ($conductores as $conductor) {
    echo "<tr>";
    echo "<td>".htmlspecialchars($conductor['id'])."</td>";
    echo "<td>".htmlspecialchars($conductor['nombre'])."</td>";
    echo "<td>".htmlspecialchars($conductor['telefono'])."</td>";
    echo "<td>".htmlspecialchars($conductor['vehiculo_marca'])." ".htmlspecialchars($conductor['vehiculo_modelo'])."</td>";
    echo "</tr>";
}

echo "</table>";
exit;