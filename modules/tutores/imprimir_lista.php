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

$sql = "SELECT t.id, t.dni, t.apellido, t.nombre, t.celular,
               (SELECT COUNT(*) FROM alumnos a WHERE a.id_tutor = t.id) as cantidad_alumnos
        FROM tutores t
        WHERE t.apellido LIKE :b1 OR t.nombre LIKE :b2 OR t.dni LIKE :b3
        ORDER BY t.apellido ASC, t.nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['b1' => $termino, 'b2' => $termino, 'b3' => $termino]);
$tutores = $stmt->fetchAll();

class PDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 9, enc('ESCUELA DE COMERCIO N° 1'), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, enc('Gral. Manuel Belgrano'), 0, 1, 'C');
        $this->Ln(3);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 9, enc('LISTADO DE TUTORES / RESPONSABLES'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, enc('Emitido: ' . date('d/m/Y H:i')), 0, 1, 'R');
        $this->Ln(2);
        // Cabecera tabla
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(220, 230, 241);
        $this->Cell(10, 8, '#', 1, 0, 'C', true);
        $this->Cell(60, 8, 'Apellido', 1, 0, 'L', true);
        $this->Cell(55, 8, 'Nombre', 1, 0, 'L', true);
        $this->Cell(30, 8, 'DNI', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Celular', 1, 0, 'C', true);
        $this->Cell(0, 8, 'Alumnos', 1, 1, 'C', true);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, enc('Página ' . $this->PageNo() . ' — Sistema de Gestión Escolar'), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

$fill = false;
$i = 1;
foreach ($tutores as $t) {
    $pdf->SetFillColor($fill ? 245 : 255, $fill ? 248 : 255, $fill ? 252 : 255);
    $pdf->Cell(10, 8, $i++, 1, 0, 'C', true);
    $pdf->Cell(60, 8, enc($t['apellido']), 1, 0, 'L', true);
    $pdf->Cell(55, 8, enc($t['nombre']), 1, 0, 'L', true);
    $pdf->Cell(30, 8, $t['dni'], 1, 0, 'C', true);
    $pdf->Cell(35, 8, $t['celular'] ?? '-', 1, 0, 'C', true);
    $pdf->Cell(0, 8, (string) $t['cantidad_alumnos'], 1, 1, 'C', true);
    $fill = !$fill;
}

$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 8, enc('Total de tutores: ' . count($tutores)), 0, 1, 'R');

$pdf->Output('I', 'Tutores_' . date('Y-m-d') . '.pdf');
