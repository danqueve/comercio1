<?php
session_start();
require_once '../../config/conexion.php';
require_once '../../fpdf/fpdf.php';

if (!isset($_SESSION['user_id'])) {
    die("Error: Acceso denegado.");
}

function enc(string $s): string
{
    return iconv('UTF-8', 'windows-1252//TRANSLIT', $s) ?: $s;
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
$total_general = array_sum(array_column($ventas, 'total'));

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

// --- PDF ---
class PDF extends FPDF
{
    public $periodo = '';

    function Header()
    {
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 8, enc('ESCUELA DE COMERCIO N° 1'), 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 4, enc('Gral. Manuel Belgrano'), 0, 1, 'C');
        $this->Ln(2);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 8, enc('HISTORIAL DE VENTAS — VESTIMENTA'), 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 6, enc($this->periodo), 1, 1, 'C');
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(0, 4, enc('Emitido: ' . date('d/m/Y H:i')), 0, 1, 'R');
        $this->Ln(1);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(0, 8, enc('Página ' . $this->PageNo() . ' — Sistema de Gestión Escolar'), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->periodo = 'Período: ' . date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin));
$pdf->AddPage();

foreach ($ventas as $v) {
    // --- Cabecera de la venta ---
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(210, 225, 245);
    $pdf->Cell(35, 7, enc('Venta #' . str_pad($v['id'], 5, '0', STR_PAD_LEFT)), 1, 0, 'L', true);
    $pdf->Cell(40, 7, date('d/m/Y H:i', strtotime($v['fecha'])), 1, 0, 'C', true);
    $pdf->Cell(0, 7, enc('Usuario: ' . ($v['usuario'] ?? 'Desconocido')), 1, 1, 'L', true);

    // --- Items de la venta ---
    $detalles = $detalles_por_venta[$v['id']] ?? [];
    if (!empty($detalles)) {
        // Sub-cabecera de columnas
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(235, 240, 248);
        $pdf->Cell(5, 6, '', 1, 0, 'C', true); // indentación
        $pdf->Cell(75, 6, 'Producto', 1, 0, 'L', true);
        $pdf->Cell(20, 6, 'Talle', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Origen', 1, 0, 'C', true);
        $pdf->Cell(15, 6, 'Cant.', 1, 0, 'C', true);
        $pdf->Cell(25, 6, 'P. Unit.', 1, 0, 'R', true);
        $pdf->Cell(0, 6, 'Subtotal', 1, 1, 'R', true);

        $pdf->SetFont('Arial', '', 8);
        $fill = false;
        foreach ($detalles as $det) {
            $subtotal = $det['cantidad'] * $det['precio_unitario'];
            $pdf->SetFillColor($fill ? 248 : 255, $fill ? 250 : 255, $fill ? 255 : 255);
            $pdf->Cell(5, 6, '', 1, 0, 'C', true);
            $pdf->Cell(75, 6, enc($det['descripcion']), 1, 0, 'L', true);
            $pdf->Cell(20, 6, enc($det['talle']), 1, 0, 'C', true);
            $pdf->Cell(20, 6, enc(ucfirst($det['origen_stock'])), 1, 0, 'C', true);
            $pdf->Cell(15, 6, (string) $det['cantidad'], 1, 0, 'C', true);
            $pdf->Cell(25, 6, '$ ' . number_format($det['precio_unitario'], 0, ',', '.'), 1, 0, 'R', true);
            $pdf->Cell(0, 6, '$ ' . number_format($subtotal, 0, ',', '.'), 1, 1, 'R', true);
            $fill = !$fill;
        }
    } else {
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(0, 6, enc('   Sin detalle de productos registrado.'), 1, 1, 'L');
    }

    // --- Total de la venta ---
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(220, 230, 245);
    $pdf->Cell(135, 6, enc('TOTAL VENTA #' . str_pad($v['id'], 5, '0', STR_PAD_LEFT)), 1, 0, 'R', true);
    $pdf->Cell(0, 6, '$ ' . number_format($v['total'], 0, ',', '.'), 1, 1, 'R', true);

    $pdf->Ln(3); // Espacio entre ventas

    // Salto de página si queda poco espacio
    if ($pdf->GetY() > 190) {
        $pdf->AddPage();
    }
}

// Total general
$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(190, 215, 255);
$pdf->Cell(135, 8, enc('TOTAL GENERAL DEL PERÍODO'), 1, 0, 'R', true);
$pdf->Cell(0, 8, '$ ' . number_format($total_general, 0, ',', '.'), 1, 1, 'R', true);

$pdf->Output('I', 'Ventas_Vestimenta_' . str_replace('-', '', $fecha_inicio) . '.pdf');
