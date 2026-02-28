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

// Parámetros — mismos que pagos/index.php y exportar.php
$fecha_inicio = $_GET['desde'] ?? date('Y-m-01');
$fecha_fin = $_GET['hasta'] ?? date('Y-m-d');
$busqueda_pago = trim($_GET['q'] ?? '');

$inicio_sql = $fecha_inicio . ' 00:00:00';
$fin_sql = $fecha_fin . ' 23:59:59';
$termino = "%$busqueda_pago%";

$where_busqueda = '';
if (!empty($busqueda_pago)) {
    $where_busqueda = " AND (a.apellido LIKE :q OR a.nombre LIKE :q2 OR a.dni LIKE :q3)";
}

// Pagos
$sql = "SELECT p.id, p.fecha, a.dni, a.apellido, a.nombre, p.concepto, p.monto, p.usuario_responsable
        FROM pagos p
        JOIN alumnos a ON p.id_alumno = a.id
        WHERE p.fecha BETWEEN :inicio AND :fin" . $where_busqueda . "
        ORDER BY p.fecha DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':inicio', $inicio_sql);
$stmt->bindValue(':fin', $fin_sql);
if (!empty($busqueda_pago)) {
    $stmt->bindValue(':q', $termino);
    $stmt->bindValue(':q2', $termino);
    $stmt->bindValue(':q3', $termino);
}
$stmt->execute();
$pagos = $stmt->fetchAll();
$total_pagos = array_sum(array_column($pagos, 'monto'));

// Ventas vestimenta
$sql_vest = "SELECT v.id, v.fecha, v.total, u.nombre_completo as usuario
             FROM vestimenta_ventas v
             LEFT JOIN usuarios u ON v.id_usuario = u.id
             WHERE v.fecha BETWEEN :inicio AND :fin
             ORDER BY v.fecha DESC";
$stmt_v = $pdo->prepare($sql_vest);
$stmt_v->execute(['inicio' => $inicio_sql, 'fin' => $fin_sql]);
$ventas_v = $stmt_v->fetchAll();
$total_vest = array_sum(array_column($ventas_v, 'total'));

// --- PDF ---
class PDF extends FPDF
{
    public $periodo = '';

    function Header()
    {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 9, enc('ESCUELA DE COMERCIO N° 1'), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, enc('Gral. Manuel Belgrano'), 0, 1, 'C');
        $this->Ln(3);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 9, enc('REPORTE DE CAJA'), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 7, enc($this->periodo), 1, 1, 'C');
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, enc('Emitido: ' . date('d/m/Y H:i')), 0, 1, 'R');
        $this->Ln(2);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, enc('Página ' . $this->PageNo() . ' — Sistema de Gestión Escolar'), 0, 0, 'C');
    }

    function SectionHeader(string $title)
    {
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(220, 230, 241);
        $this->Cell(0, 8, enc($title), 1, 1, 'L', true);
    }

    function TableHeader(array $cols, array $widths, array $aligns)
    {
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(240, 243, 248);
        for ($i = 0; $i < count($cols); $i++) {
            $this->Cell($widths[$i], 7, enc($cols[$i]), 1, 0, $aligns[$i], true);
        }
        $this->Ln();
    }
}

$pdf = new PDF('L'); // Landscape para más columnas
$pdf->periodo = 'Período: ' . date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin));
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

// ---- SECCIÓN PAGOS ----
$pdf->SectionHeader('PAGOS / CUOTAS');
$pdf->TableHeader(
    ['Fecha', 'Recibo', 'Alumno', 'DNI', 'Concepto', 'Responsable', 'Monto'],
    [22, 20, 60, 22, 60, 40, 32],
    ['C', 'C', 'L', 'C', 'L', 'L', 'R']
);

$fill = false;
foreach ($pagos as $p) {
    $pdf->SetFillColor($fill ? 245 : 255, $fill ? 248 : 255, $fill ? 252 : 255);
    $pdf->Cell(22, 7, date('d/m/Y', strtotime($p['fecha'])), 1, 0, 'C', true);
    $pdf->Cell(20, 7, '#' . str_pad($p['id'], 5, '0', STR_PAD_LEFT), 1, 0, 'C', true);
    $pdf->Cell(60, 7, enc($p['apellido'] . ', ' . $p['nombre']), 1, 0, 'L', true);
    $pdf->Cell(22, 7, $p['dni'], 1, 0, 'C', true);
    $pdf->Cell(60, 7, enc($p['concepto']), 1, 0, 'L', true);
    $pdf->Cell(40, 7, enc($p['usuario_responsable']), 1, 0, 'L', true);
    $pdf->Cell(32, 7, '$ ' . number_format($p['monto'], 0, ',', '.'), 1, 1, 'R', true);
    $fill = !$fill;
}
// Subtotal pagos
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(220, 230, 241);
$pdf->Cell(224, 7, enc('SUBTOTAL CUOTAS'), 1, 0, 'R', true);
$pdf->Cell(32, 7, '$ ' . number_format($total_pagos, 0, ',', '.'), 1, 1, 'R', true);

$pdf->Ln(4);

// ---- SECCIÓN VESTIMENTA ----
$pdf->SetFont('Arial', '', 9);
$pdf->SectionHeader('VENTAS VESTIMENTA');
$pdf->TableHeader(
    ['Fecha', 'Venta #', 'Usuario', 'Total'],
    [30, 25, 100, 40],
    ['C', 'C', 'L', 'R']
);

$fill = false;
foreach ($ventas_v as $vv) {
    $pdf->SetFillColor($fill ? 245 : 255, $fill ? 248 : 255, $fill ? 252 : 255);
    $pdf->Cell(30, 7, date('d/m/Y', strtotime($vv['fecha'])), 1, 0, 'C', true);
    $pdf->Cell(25, 7, '#' . str_pad($vv['id'], 5, '0', STR_PAD_LEFT), 1, 0, 'C', true);
    $pdf->Cell(100, 7, enc($vv['usuario'] ?? 'Desconocido'), 1, 0, 'L', true);
    $pdf->Cell(40, 7, '$ ' . number_format($vv['total'], 0, ',', '.'), 1, 1, 'R', true);
    $fill = !$fill;
}
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(220, 230, 241);
$pdf->Cell(155, 7, enc('SUBTOTAL VESTIMENTA'), 1, 0, 'R', true);
$pdf->Cell(40, 7, '$ ' . number_format($total_vest, 0, ',', '.'), 1, 1, 'R', true);

$pdf->Ln(4);

// ---- TOTAL GENERAL ----
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(200, 220, 255);
$pdf->Cell(155, 9, enc('TOTAL GENERAL NETO'), 1, 0, 'R', true);
$pdf->Cell(40, 9, '$ ' . number_format($total_pagos + $total_vest, 0, ',', '.'), 1, 1, 'R', true);

$nombre = 'Reporte_Caja_' . str_replace('-', '', $fecha_inicio) . '_' . str_replace('-', '', $fecha_fin) . '.pdf';
$pdf->Output('I', $nombre);
