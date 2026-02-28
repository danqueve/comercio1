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

$sql = "SELECT descripcion, talle, precio_venta
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
        $this->Ln(3);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, enc('LISTA DE PRECIOS — VESTIMENTA'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 4, enc('Emitido: ' . date('d/m/Y H:i')), 0, 1, 'R');
        $this->Ln(3);

        // Cabecera tabla
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(220, 230, 241);
        $this->Cell(10, 8, '#', 1, 0, 'C', true);
        $this->Cell(100, 8, 'Descripcion', 1, 0, 'L', true);
        $this->Cell(35, 8, 'Talle', 1, 0, 'C', true);
        $this->Cell(0, 8, 'Precio Venta', 1, 1, 'R', true);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(0, 8, enc('Página ' . $this->PageNo() . ' — Sistema de Gestión Escolar'), 0, 0, 'C');
    }
}

$pdf = new PDF(); // Portrait A4
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

$fill = false;
$i = 1;
foreach ($productos as $p) {
    $pdf->SetFillColor($fill ? 245 : 255, $fill ? 248 : 255, $fill ? 252 : 255);
    $pdf->Cell(10, 8, $i++, 1, 0, 'C', true);
    $pdf->Cell(100, 8, enc($p['descripcion']), 1, 0, 'L', true);
    $pdf->Cell(35, 8, enc($p['talle']), 1, 0, 'C', true);
    $pdf->Cell(0, 8, '$ ' . number_format($p['precio_venta'], 0, ',', '.'), 1, 1, 'R', true);
    $fill = !$fill;
}

$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 6, enc('Total de artículos: ' . count($productos)), 0, 1, 'R');

$pdf->Output('I', 'Lista_Precios_Vestimenta_' . date('Y-m-d') . '.pdf');
?>