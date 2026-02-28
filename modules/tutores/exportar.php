<?php
session_start();
require_once '../../config/conexion.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

$busqueda = trim($_GET['q'] ?? '');
$termino = "%$busqueda%";

$sql = "SELECT t.dni, t.apellido, t.nombre, t.celular, t.direccion,
               (SELECT COUNT(*) FROM alumnos a WHERE a.id_tutor = t.id) as cantidad_alumnos
        FROM tutores t
        WHERE t.apellido LIKE :b1 OR t.nombre LIKE :b2 OR t.dni LIKE :b3
        ORDER BY t.apellido ASC, t.nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['b1' => $termino, 'b2' => $termino, 'b3' => $termino]);
$tutores = $stmt->fetchAll();

$filename = 'tutores_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');
fputcsv($output, ['DNI', 'Apellido', 'Nombre', 'Celular', 'Dirección', 'Alumnos a Cargo'], ';');

foreach ($tutores as $t) {
    fputcsv($output, [
        $t['dni'],
        $t['apellido'],
        $t['nombre'],
        $t['celular'] ?? '',
        $t['direccion'] ?? '',
        $t['cantidad_alumnos'],
    ], ';');
}

fclose($output);
exit;
