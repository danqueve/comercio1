<?php
session_start();
require_once '../../config/conexion.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

$fecha_inicio = $_GET['desde'] ?? date('Y-m-01');
$fecha_fin = $_GET['hasta'] ?? date('Y-m-d');
$inicio_sql = $fecha_inicio . ' 00:00:00';
$fin_sql = $fecha_fin . ' 23:59:59';

// Cabeceras de venta
$sql = "SELECT v.id, v.fecha, v.total, u.nombre_completo as usuario
        FROM vestimenta_ventas v
        LEFT JOIN usuarios u ON v.id_usuario = u.id
        WHERE v.fecha BETWEEN :inicio AND :fin
        ORDER BY v.fecha DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['inicio' => $inicio_sql, 'fin' => $fin_sql]);
$ventas = $stmt->fetchAll();

// Detalles de todas las ventas del período
$ids = array_column($ventas, 'id');
$detalles_por_venta = [];
if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql_d = "SELECT vd.id_venta, p.descripcion, p.talle, vd.cantidad, vd.precio_unitario, vd.origen_stock
              FROM vestimenta_venta_detalles vd
              JOIN vestimenta_productos p ON vd.id_producto = p.id
              WHERE vd.id_venta IN ($placeholders)
              ORDER BY vd.id_venta, p.descripcion";
    $stmt_d = $pdo->prepare($sql_d);
    $stmt_d->execute($ids);
    foreach ($stmt_d->fetchAll() as $det) {
        $detalles_por_venta[$det['id_venta']][] = $det;
    }
}

$filename = 'ventas_vestimenta_' . str_replace('-', '', $fecha_inicio) . '_' . str_replace('-', '', $fecha_fin) . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');
fputcsv($output, ['Venta #', 'Fecha', 'Usuario', 'Producto', 'Talle', 'Origen', 'Cantidad', 'Precio Unit.', 'Subtotal', 'Total Venta'], ';');

$total_general = 0;
foreach ($ventas as $v) {
    $detalles = $detalles_por_venta[$v['id']] ?? [];
    $num_venta = '#' . str_pad($v['id'], 5, '0', STR_PAD_LEFT);
    $fecha_fmt = date('d/m/Y H:i', strtotime($v['fecha']));
    $usuario = $v['usuario'] ?? 'Desconocido';

    if (!empty($detalles)) {
        $primera = true;
        foreach ($detalles as $det) {
            $subtotal = $det['cantidad'] * $det['precio_unitario'];
            fputcsv($output, [
                $primera ? $num_venta : '',
                $primera ? $fecha_fmt : '',
                $primera ? $usuario : '',
                $det['descripcion'],
                $det['talle'],
                ucfirst($det['origen_stock']),
                $det['cantidad'],
                number_format($det['precio_unitario'], 2, ',', '.'),
                number_format($subtotal, 2, ',', '.'),
                $primera ? number_format($v['total'], 2, ',', '.') : '',
            ], ';');
            $primera = false;
        }
    } else {
        fputcsv($output, [
            $num_venta,
            $fecha_fmt,
            $usuario,
            'Sin detalle',
            '',
            '',
            '',
            '',
            '',
            number_format($v['total'], 2, ',', '.'),
        ], ';');
    }
    $total_general += $v['total'];
}

// Total general
fputcsv($output, ['', '', '', '', '', '', '', '', 'TOTAL GENERAL', number_format($total_general, 2, ',', '.')], ';');

fclose($output);
exit;
