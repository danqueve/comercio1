<?php
ob_start(); // Prevenir cualquier salida accidental
session_start();
require_once '../../config/conexion.php';
require_once '../../fpdf/fpdf.php';

if (!isset($_SESSION['user_id'])) {
    die("Acceso denegado.");
}

$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

$inicio_sql = $desde . ' 00:00:00';
$fin_sql = $hasta . ' 23:59:59';

// Función auxiliar para manejar codificación sin warnings de deprecación
if (!function_exists('fpdf_utf8')) {
    function fpdf_utf8($str)
    {
        return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
    }
}

// Obtener ventas con usuario
$sql = "SELECT v.*, u.nombre_completo 
        FROM vestimenta_ventas v
        LEFT JOIN usuarios u ON v.id_usuario = u.id
        WHERE v.fecha BETWEEN :inicio AND :fin
        ORDER BY v.fecha ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['inicio' => $inicio_sql, 'fin' => $fin_sql]);
$ventas = $stmt->fetchAll();

class BalancePDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, fpdf_utf8('ESCUELA DE COMERCIO N° 1 - BALANCE VESTIMENTA'), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, fpdf_utf8('Reporte de Ventas de Uniformes'), 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, fpdf_utf8('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new BalancePDF();
$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 7, "Periodo: " . date('d/m/Y', strtotime($desde)) . " al " . date('d/m/Y', strtotime($hasta)), 0, 1);
$pdf->Ln(3);

// Cabecera de Tabla
$pdf->SetFillColor(230, 230, 230);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(20, 8, 'ID', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'Fecha', 1, 0, 'C', true);
$pdf->Cell(80, 8, 'Usuario / Vendedor', 1, 0, 'L', true);
$pdf->Cell(55, 8, 'Total', 1, 1, 'R', true);

$pdf->SetFont('Arial', '', 10);
$total_general = 0;

foreach ($ventas as $v) {
    $pdf->Cell(20, 7, "#" . $v['id'], 1, 0, 'C');
    $pdf->Cell(35, 7, date('d/m/Y H:i', strtotime($v['fecha'])), 1, 0, 'C');
    $pdf->Cell(80, 7, fpdf_utf8($v['nombre_completo'] ?? 'Desconocido'), 1, 0, 'L');
    $pdf->Cell(55, 7, "$ " . number_format($v['total'], 2, ',', '.'), 1, 1, 'R');
    $total_general += $v['total'];

    // Opcional: Detalles en pequeño abajo si hay espacio? 
    // Por ahora solo el balance general solicitado.
}

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(135, 10, 'TOTAL RECAUDADO VESTIMENTA:', 1, 0, 'R', true);
$pdf->Cell(55, 10, "$ " . number_format($total_general, 2, ',', '.'), 1, 1, 'R', true);

$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(0, 5, "Fin del reporte.", 0, 1, 'C');

$pdf->Output('I', 'Balance_Vestimenta_' . $desde . '.pdf');
