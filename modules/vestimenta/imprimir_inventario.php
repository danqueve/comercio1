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

$busqueda = trim($_GET['q'] ?? '');
$termino = "%$busqueda%";

$sql = "SELECT descripcion, talle, stock_deposito, stock_administracion, costo, precio_venta
        FROM vestimenta_productos
        WHERE descripcion LIKE :b1
        ORDER BY descripcion ASC, talle ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['b1' => $termino]);
$productos = $stmt->fetchAll();

class PDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 8, enc('ESCUELA DE COMERCIO N° 1'), 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 4, enc('Gral. Manuel Belgrano'), 0, 1, 'C');
        $this->Ln(2);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 8, enc('INVENTARIO DE VESTIMENTA'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 4, enc('Emitido: ' . date('d/m/Y H:i')), 0, 1, 'R');
        $this->Ln(2);

        // Cabecera tabla
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(220, 230, 241);
        $this->Cell(8, 7, '#', 1, 0, 'C', true);
        $this->Cell(60, 7, 'Descripción', 1, 0, 'L', true);
        $this->Cell(18, 7, 'Talle', 1, 0, 'C', true);
        $this->Cell(20, 7, 'Stk. Dep.', 1, 0, 'C', true);
        $this->Cell(20, 7, 'Stk. Adm.', 1, 0, 'C', true);
        $this->Cell(18, 7, 'Total', 1, 0, 'C', true);
        $this->Cell(23, 7, 'Costo', 1, 0, 'R', true);
        $this->Cell(0, 7, 'P. Venta', 1, 1, 'R', true);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(0, 8, enc('Página ' . $this->PageNo() . ' — Sistema de Gestión Escolar'), 0, 0, 'C');
    }
}

$pdf = new PDF(); // Portrait A4 (default)
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

$fill = false;
$i = 1;
$total_deposito = 0;
$total_admin = 0;

foreach ($productos as $p) {
    $total_stock = $p['stock_deposito'] + $p['stock_administracion'];
    $total_deposito += $p['stock_deposito'];
    $total_admin += $p['stock_administracion'];

    // Color si stock bajo
    if ($total_stock == 0) {
        $pdf->SetTextColor(180, 0, 0);
    } elseif ($total_stock <= 3) {
        $pdf->SetTextColor(180, 100, 0);
    } else {
        $pdf->SetTextColor(0, 0, 0);
    }

    $pdf->SetFillColor($fill ? 245 : 255, $fill ? 248 : 255, $fill ? 252 : 255);
    $pdf->Cell(8, 7, $i++, 1, 0, 'C', true);
    $pdf->Cell(60, 7, enc($p['descripcion']), 1, 0, 'L', true);
    $pdf->Cell(18, 7, enc($p['talle']), 1, 0, 'C', true);
    $pdf->Cell(20, 7, (string) $p['stock_deposito'], 1, 0, 'C', true);
    $pdf->Cell(20, 7, (string) $p['stock_administracion'], 1, 0, 'C', true);
    $pdf->Cell(18, 7, (string) $total_stock, 1, 0, 'C', true);
    $pdf->Cell(23, 7, '$ ' . number_format($p['costo'], 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(0, 7, '$ ' . number_format($p['precio_venta'], 0, ',', '.'), 1, 1, 'R', true);
    $fill = !$fill;
}

$pdf->SetTextColor(0, 0, 0);

// Fila de totales
$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(210, 225, 245);
$pdf->Cell(106, 7, enc('TOTALES'), 1, 0, 'R', true);
$pdf->Cell(20, 7, (string) $total_deposito, 1, 0, 'C', true);
$pdf->Cell(20, 7, (string) $total_admin, 1, 0, 'C', true);
$pdf->Cell(18, 7, (string) ($total_deposito + $total_admin), 1, 0, 'C', true);
$pdf->Cell(0, 7, '', 1, 1, 'C', true);

$pdf->Ln(2);
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(150, 0, 0);
$pdf->Cell(0, 5, enc('* Productos en rojo: sin stock. Naranja: stock crítico (≤ 3 unidades).'), 0, 1, 'L');

$pdf->Output('I', 'Inventario_Vestimenta_' . date('Y-m-d') . '.pdf');
?>