<?php
session_start();
require_once '../../config/conexion.php';
require_once '../../fpdf/fpdf.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    die("Error: Acceso denegado. Debes iniciar sesión.");
}

// Helper: reemplaza utf8_decode (deprecada en PHP 8.2)
function enc(string $str): string
{
    return iconv('UTF-8', 'windows-1252//TRANSLIT', $str) ?: $str;
}

// Parámetros
$filtro_curso = isset($_GET['curso']) ? trim($_GET['curso']) : '';
$filtro_turno = isset($_GET['turno']) ? trim($_GET['turno']) : '';

if ($filtro_curso === '') {
    die("Error: No se especificó el curso.");
}

$partes = explode(' ', $filtro_curso, 2);
$anio_b = $partes[0] ?? '';
$divis_b = $partes[1] ?? '';

// Condición extra por turno
$extra_turno = $filtro_turno !== '' ? ' AND c.turno = :turno' : '';

$sql = "SELECT a.apellido, a.nombre, a.dni, c.anio_curso, c.division, c.turno
        FROM alumnos a
        INNER JOIN inscripciones i ON a.id = i.id_alumno
            AND i.id_ciclo_lectivo = (SELECT id FROM ciclos_lectivos WHERE activo = 1 LIMIT 1)
        INNER JOIN cursos c ON i.id_curso = c.id
        WHERE c.anio_curso = :anio AND c.division = :divis$extra_turno
        ORDER BY a.apellido ASC, a.nombre ASC";

$stmt = $pdo->prepare($sql);
$params = ['anio' => $anio_b, 'divis' => $divis_b];
if ($filtro_turno !== '')
    $params['turno'] = $filtro_turno;
$stmt->execute($params);
$alumnos = $stmt->fetchAll();

// --- CLASE PDF PERSONALIZADA ---
class PDF extends FPDF
{
    public $info_curso = '';

    function Header()
    {
        // Encabezado institucional
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, enc('ESCUELA DE COMERCIO N° 1'), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, enc('Gral. Manuel Belgrano'), 0, 1, 'C');
        $this->Ln(4);

        // Título
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, enc('LISTADO DE ALUMNOS'), 0, 1, 'C');

        // Datos del curso
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 9, enc($this->info_curso), 1, 1, 'C');
        $this->Ln(4);

        // Fecha de emisión
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, enc('Emitido: ' . date('d/m/Y H:i')), 0, 1, 'R');
        $this->Ln(2);

        // Encabezados de tabla
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(220, 230, 241);
        $this->Cell(12, 8, '#', 1, 0, 'C', true);
        $this->Cell(65, 8, 'Apellido', 1, 0, 'L', true);
        $this->Cell(65, 8, 'Nombre', 1, 0, 'L', true);
        $this->Cell(38, 8, 'DNI', 1, 1, 'C', true);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, enc('Página ' . $this->PageNo() . ' — Sistema de Gestión Escolar'), 0, 0, 'C');
    }
}

// --- GENERACIÓN DEL PDF ---
$pdf = new PDF();

$turno_label = $filtro_turno !== '' ? '   Turno: ' . $filtro_turno : '';
$pdf->info_curso = "Curso: {$anio_b}° \"{$divis_b}\"{$turno_label}";

$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

$fill = false;
$contador = 1;

if (count($alumnos) > 0) {
    foreach ($alumnos as $alu) {
        $pdf->SetFillColor($fill ? 245 : 255, $fill ? 248 : 255, $fill ? 252 : 255);
        $pdf->Cell(12, 8, $contador++, 1, 0, 'C', true);
        $pdf->Cell(65, 8, enc($alu['apellido']), 1, 0, 'L', true);
        $pdf->Cell(65, 8, enc($alu['nombre']), 1, 0, 'L', true);
        $pdf->Cell(38, 8, $alu['dni'], 1, 1, 'C', true);
        $fill = !$fill;
    }
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, enc('Total de alumnos: ' . count($alumnos)), 0, 1, 'R');
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, enc('No hay alumnos inscriptos en este curso para el ciclo activo.'), 1, 1, 'C');
}

$nombre_archivo = 'Lista_' . $anio_b . $divis_b . ($filtro_turno ? '_' . $filtro_turno : '') . '.pdf';
$pdf->Output('I', $nombre_archivo);
?>