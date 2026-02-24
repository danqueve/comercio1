<?php
session_start();
require_once '../../config/conexion.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

// Mismos parámetros de filtro que pagos/index.php
$fecha_inicio  = $_GET['desde'] ?? date('Y-m-01');
$fecha_fin     = $_GET['hasta'] ?? date('Y-m-d');
$busqueda_pago = trim($_GET['q'] ?? '');

$inicio_sql = $fecha_inicio . ' 00:00:00';
$fin_sql    = $fecha_fin    . ' 23:59:59';
$termino    = "%$busqueda_pago%";

$where_busqueda = '';
if (!empty($busqueda_pago)) {
    $where_busqueda = " AND (a.apellido LIKE :q OR a.nombre LIKE :q2 OR a.dni LIKE :q3)";
}

// Sin LIMIT para exportar todo el período
$sql = "SELECT p.id, p.fecha, a.dni, a.apellido, a.nombre, p.concepto, p.monto, p.usuario_responsable
        FROM pagos p
        JOIN alumnos a ON p.id_alumno = a.id
        WHERE p.fecha BETWEEN :inicio AND :fin" . $where_busqueda . "
        ORDER BY p.fecha DESC";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':inicio', $inicio_sql);
$stmt->bindValue(':fin', $fin_sql);
if (!empty($busqueda_pago)) {
    $stmt->bindValue(':q',  $termino);
    $stmt->bindValue(':q2', $termino);
    $stmt->bindValue(':q3', $termino);
}
$stmt->execute();
$pagos = $stmt->fetchAll();

// Generar nombre de archivo
$filename = 'pagos_' . str_replace('-', '', $fecha_inicio) . '_' . str_replace('-', '', $fecha_fin) . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM UTF-8 para Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Cabecera
fputcsv($output, ['ID', 'Fecha', 'DNI', 'Apellido', 'Nombre', 'Concepto', 'Monto', 'Responsable'], ';');

foreach ($pagos as $p) {
    fputcsv($output, [
        '#' . str_pad($p['id'], 6, '0', STR_PAD_LEFT),
        $p['fecha'],
        $p['dni'],
        $p['apellido'],
        $p['nombre'],
        $p['concepto'],
        number_format($p['monto'], 2, ',', '.'),
        $p['usuario_responsable'],
    ], ';');
}

// Fila de total
$total = array_sum(array_column($pagos, 'monto'));
fputcsv($output, ['', '', '', '', '', 'TOTAL', number_format($total, 2, ',', '.'), ''], ';');

fclose($output);
exit;
