<?php
session_start();
require_once '../../config/conexion.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

// Mismos parámetros de búsqueda que alumnos/index.php
$busqueda = trim($_GET['q'] ?? '');
$termino  = "%$busqueda%";

$sql = "SELECT 
            a.dni, a.apellido, a.nombre, a.celular,
            c.anio_curso, c.division, c.turno,
            i.estado as condicion
        FROM alumnos a
        LEFT JOIN inscripciones i ON a.id = i.id_alumno 
            AND i.id_ciclo_lectivo = (SELECT id FROM ciclos_lectivos WHERE activo = 1 LIMIT 1)
        LEFT JOIN cursos c ON i.id_curso = c.id
        WHERE a.apellido LIKE :b1 OR a.nombre LIKE :b2 OR a.dni LIKE :b3
        ORDER BY a.apellido ASC, a.nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['b1' => $termino, 'b2' => $termino, 'b3' => $termino]);
$alumnos = $stmt->fetchAll();

// Generar CSV
$filename = 'alumnos_' . date('Y-m-d') . (!empty($busqueda) ? '_' . preg_replace('/[^a-z0-9]/i', '_', $busqueda) : '') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM para Excel (UTF-8 con acento)
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Cabecera del CSV
fputcsv($output, ['DNI', 'Apellido', 'Nombre', 'Curso', 'División', 'Turno', 'Estado', 'Celular'], ';');

foreach ($alumnos as $a) {
    fputcsv($output, [
        $a['dni'],
        $a['apellido'],
        $a['nombre'],
        $a['anio_curso'] ?? '',
        $a['division']   ?? '',
        $a['turno']      ?? '',
        $a['condicion']  ?? 'No inscrito',
        $a['celular']    ?? '',
    ], ';');
}

fclose($output);
exit;
