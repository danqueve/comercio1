<?php
session_start();
require_once '../../config/conexion.php';
// Asegúrate de que la librería FPDF esté en la carpeta correcta
require_once '../../fpdf/fpdf.php';

// Verificamos sesión
if (!isset($_SESSION['user_id'])) {
    die("Error: Acceso denegado. Debes iniciar sesión.");
}

$id_curso = $_GET['id'] ?? null;

if (!$id_curso) {
    die("Error: No se especificó el curso.");
}

// 1. Obtener Datos del Curso
$stmt_curso = $pdo->prepare("SELECT * FROM cursos WHERE id = :id");
$stmt_curso->execute(['id' => $id_curso]);
$curso = $stmt_curso->fetch();

if (!$curso) {
    die("Error: Curso no encontrado.");
}

// 2. Obtener Alumnos
// Traemos todos los alumnos inscritos en el ciclo activo para este curso
$sql_alumnos = "SELECT a.dni, a.apellido, a.nombre, i.estado
                FROM inscripciones i
                JOIN alumnos a ON i.id_alumno = a.id
                JOIN ciclos_lectivos cl ON i.id_ciclo_lectivo = cl.id
                WHERE i.id_curso = :curso AND cl.activo = 1
                ORDER BY a.apellido ASC, a.nombre ASC";

$stmt = $pdo->prepare($sql_alumnos);
$stmt->execute(['curso' => $id_curso]);
$alumnos = $stmt->fetchAll();

// --- CLASE PDF PERSONALIZADA ---
class PDF extends FPDF
{
    // Variable para guardar el título del curso y usarlo en el header de cada página
    public $info_curso;

    function Header()
    {
        // Encabezado Institucional
        $this->SetFont('Arial','B',14);
        $this->Cell(0,10,utf8_decode('ESCUELA DE COMERCIO N° 1'),0,1,'C');
        $this->SetFont('Arial','',10);
        $this->Cell(0,5,utf8_decode('Gral. Manuel Belgrano'),0,1,'C');
        $this->Ln(5);

        // Título del Reporte
        $this->SetFont('Arial','B',12);
        $this->Cell(0,10,utf8_decode('LISTADO DE ALUMNOS'),0,1,'C');
        
        // Datos del Curso (Recibidos desde la instancia)
        $this->SetFont('Arial','',11);
        $this->Cell(0,10,utf8_decode($this->info_curso),1,1,'C');
        $this->Ln(5);

        // Encabezados de la Tabla
        $this->SetFont('Arial','B',10);
        $this->SetFillColor(240,240,240); // Gris muy suave
        
        // Definición de anchos y títulos
        // Ancho total A4 vertical margen estandar aprox 190mm
        $this->Cell(10,8,'#',1,0,'C',true);
        $this->Cell(60,8,'Apellido',1,0,'L',true);
        $this->Cell(60,8,'Nombre',1,0,'L',true);
        $this->Cell(30,8,'DNI',1,0,'C',true);
        $this->Cell(30,8,utf8_decode('Condición'),1,1,'C',true);
    }

    function Footer()
    {
        // Posición a 1.5 cm del final
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,utf8_decode('Página ').$this->PageNo().' - Sistema de Gestión Escolar',0,0,'C');
    }
}

// --- GENERACIÓN DEL PDF ---
$pdf = new PDF();
// Configuramos la info del curso antes de crear la página
$pdf->info_curso = "Curso: " . $curso['anio_curso'] . " '" . $curso['division'] . "'   -   Turno: " . $curso['turno'];

$pdf->AddPage();
$pdf->SetFont('Arial','',10);

$contador = 1;

if (count($alumnos) > 0) {
    foreach ($alumnos as $alu) {
        $pdf->Cell(10,8,$contador++,1,0,'C');
        $pdf->Cell(60,8,utf8_decode($alu['apellido']),1,0,'L');
        $pdf->Cell(60,8,utf8_decode($alu['nombre']),1,0,'L');
        $pdf->Cell(30,8,$alu['dni'],1,0,'C');
        $pdf->Cell(30,8,$alu['estado'],1,1,'C');
    }
} else {
    $pdf->SetFont('Arial','I',10);
    $pdf->Cell(0,10,utf8_decode('No hay alumnos inscritos en este curso actualmente.'),1,1,'C');
}

// Nombre del archivo de descarga
$nombre_archivo = 'Lista_' . $curso['anio_curso'] . $curso['division'] . '.pdf';

// Salida al navegador ('I' = Inline/Visualizar, 'D' = Download/Descargar)
$pdf->Output('I', $nombre_archivo);
?>