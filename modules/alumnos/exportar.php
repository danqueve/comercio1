<?php
session_start();
require_once '../../config/conexion.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

// Parámetros — igual que alumnos/index.php
$busqueda = trim($_GET['q'] ?? '');
$filtro_curso = trim($_GET['curso'] ?? '');
$filtro_turno = trim($_GET['turno'] ?? '');
$termino = "%$busqueda%";

if ($filtro_curso !== '') {
    // Modo: filtrar por curso y turno
    $partes = explode(' ', $filtro_curso, 2);
    $anio_b = $partes[0] ?? '';
    $divis_b = $partes[1] ?? '';
    $extra_turno = $filtro_turno !== '' ? ' AND c.turno = :turno' : '';

    $sql = "SELECT
                a.dni, a.apellido, a.nombre, a.celular,
                c.anio_curso, c.division, c.turno,
                i.estado as condicion
            FROM alumnos a
            INNER JOIN inscripciones i ON a.id = i.id_alumno
                AND i.id_ciclo_lectivo = (SELECT id FROM ciclos_lectivos WHERE activo = 1 LIMIT 1)
            INNER JOIN cursos c ON i.id_curso = c.id
            LEFT JOIN tutores t ON a.id_tutor = t.id
            WHERE c.anio_curso = :anio AND c.division = :divis$extra_turno
            ORDER BY a.apellido ASC, a.nombre ASC";

    $stmt = $pdo->prepare($sql);
    $params = ['anio' => $anio_b, 'divis' => $divis_b];
    if ($filtro_turno !== '')
        $params['turno'] = $filtro_turno;
    $stmt->execute($params);

    $suffix = '_' . $anio_b . $divis_b . ($filtro_turno ? '_' . $filtro_turno : '');
} else {
    // Modo: búsqueda libre
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

    $suffix = !empty($busqueda) ? '_' . preg_replace('/[^a-z0-9]/i', '_', $busqueda) : '';
}

$alumnos = $stmt->fetchAll();

// Generar CSV
$filename = 'alumnos_' . date('Y-m-d') . $suffix . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
echo "\xEF\xBB\xBF"; // BOM para Excel

$output = fopen('php://output', 'w');
fputcsv($output, ['DNI', 'Apellido', 'Nombre', 'Curso', 'División', 'Turno', 'Estado', 'Celular'], ';');

foreach ($alumnos as $a) {
    fputcsv($output, [
        $a['dni'],
        $a['apellido'],
        $a['nombre'],
        $a['anio_curso'] ?? '',
        $a['division'] ?? '',
        $a['turno'] ?? '',
        $a['condicion'] ?? 'No inscrito',
        $a['celular'] ?? '',
    ], ';');
}

fclose($output);
exit;
