<?php
session_start();
require_once '../../config/conexion.php';
require_once '../../fpdf/fpdf.php';

// Verificar seguridad
if (!isset($_SESSION['user_id'])) {
    die("Acceso denegado.");
}

$id_alumno = $_GET['id_alumno'] ?? null;

if (!$id_alumno) {
    die("Error: Faltan datos.");
}

// 1. Obtener todos los datos (Alumno + Tutor + Curso Actual)
$sql = "SELECT 
            a.*, 
            t.apellido as t_apellido, t.nombre as t_nombre, t.dni as t_dni, t.celular as t_celular, t.direccion as t_direccion,
            c.anio_curso, c.division, c.turno,
            cl.anio as anio_lectivo,
            i.fecha_inscripcion, i.estado, i.observaciones
        FROM alumnos a
        LEFT JOIN tutores t ON a.id_tutor = t.id
        LEFT JOIN inscripciones i ON a.id = i.id_alumno 
            AND i.id_ciclo_lectivo = (SELECT id FROM ciclos_lectivos WHERE activo = 1 LIMIT 1)
        LEFT JOIN cursos c ON i.id_curso = c.id
        LEFT JOIN ciclos_lectivos cl ON i.id_ciclo_lectivo = cl.id
        WHERE a.id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $id_alumno]);
$datos = $stmt->fetch();

if (!$datos) {
    die("Alumno no encontrado.");
}

// --- CREACIÓN DEL PDF ---
class PDF extends FPDF
{
    // Cabecera de página
    function Header()
    {
        $this->SetFont('Arial','B',16);
        $this->Cell(0,10,utf8_decode('ESCUELA DE COMERCIO N° 1'),0,1,'C');
        $this->SetFont('Arial','',10);
        $this->Cell(0,5,utf8_decode('Gral. Manuel Belgrano'),0,1,'C');
        $this->Ln(10);
        
        $this->SetFont('Arial','B',14);
        $this->Cell(0,10,utf8_decode('FICHA DE MATRÍCULA'),1,1,'C');
        $this->Ln(5);
    }

    // Pie de página
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,utf8_decode('Página ').$this->PageNo().' - Sistema de Gestión Escolar',0,0,'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial','',11);

// --- SECCIÓN 1: DATOS DEL ALUMNO ---
$pdf->SetFillColor(240,240,240); // Gris muy suave
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,8,utf8_decode('1. DATOS DEL ALUMNO'),1,1,'L',true);
$pdf->SetFont('Arial','',11);
$pdf->Ln(2);

$pdf->Cell(30,7,'DNI:',0,0);
$pdf->Cell(60,7,$datos['dni'],0,0);
$pdf->Cell(40,7,'Fecha Nac:',0,0);
$pdf->Cell(60,7,date("d/m/Y", strtotime($datos['fecha_nacimiento'])),0,1);

$pdf->Cell(30,7,'Apellido:',0,0);
$pdf->Cell(60,7,utf8_decode($datos['apellido']),0,0);
$pdf->Cell(40,7,'Nombre:',0,0);
$pdf->Cell(60,7,utf8_decode($datos['nombre']),0,1);

$pdf->Cell(30,7,utf8_decode('Dirección:'),0,0);
$pdf->Cell(160,7,utf8_decode($datos['direccion'] . ' - ' . $datos['localidad']),0,1);

$pdf->Cell(30,7,utf8_decode('Celular:'),0,0);
$pdf->Cell(160,7,utf8_decode($datos['celular']),0,1);

$pdf->Ln(5);

// --- SECCIÓN 2: DATOS DEL TUTOR ---
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,8,utf8_decode('2. DATOS DEL TUTOR / RESPONSABLE'),1,1,'L',true);
$pdf->SetFont('Arial','',11);
$pdf->Ln(2);

if ($datos['t_apellido']) {
    $pdf->Cell(30,7,'Parentesco:',0,0);
    $pdf->Cell(160,7,utf8_decode($datos['parentesco_tutor']),0,1);

    $pdf->Cell(30,7,'Apellido:',0,0);
    $pdf->Cell(60,7,utf8_decode($datos['t_apellido']),0,0);
    $pdf->Cell(40,7,'Nombre:',0,0);
    $pdf->Cell(60,7,utf8_decode($datos['t_nombre']),0,1);

    $pdf->Cell(30,7,'DNI:',0,0);
    $pdf->Cell(60,7,$datos['t_dni'],0,0);
    $pdf->Cell(40,7,'Celular:',0,0);
    $pdf->Cell(60,7,$datos['t_celular'],0,1);
    
    $pdf->Cell(30,7,utf8_decode('Dirección:'),0,0);
    $pdf->Cell(160,7,utf8_decode($datos['t_direccion']),0,1);
} else {
    $pdf->Cell(0,10,'No hay datos de tutor registrados.',0,1);
}

$pdf->Ln(5);

// --- SECCIÓN 3: INSCRIPCIÓN ---
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,8,utf8_decode('3. DETALLE DE INSCRIPCIÓN'),1,1,'L',true);
$pdf->SetFont('Arial','',11);
$pdf->Ln(2);

if ($datos['anio_curso']) {
    $pdf->Cell(40,8,'Ciclo Lectivo:',0,0);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(50,8,$datos['anio_lectivo'],0,0);
    $pdf->SetFont('Arial','',11);
    
    $pdf->Cell(40,8,utf8_decode('Condición:'),0,0);
    $pdf->Cell(50,8,$datos['estado'],0,1);

    $pdf->Cell(40,8,'Curso Asignado:',0,0);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(150,8,utf8_decode($datos['anio_curso'] . ' "' . $datos['division'] . '" - Turno ' . $datos['turno']),0,1);
    $pdf->SetFont('Arial','',11);
    
    if(!empty($datos['observaciones'])){
        $pdf->Ln(2);
        $pdf->Cell(40,8,'Observaciones:',0,0);
        $pdf->MultiCell(0,8,utf8_decode($datos['observaciones']));
    }
    
    $pdf->Ln(2);
    $pdf->SetFont('Arial','I',10);
    $pdf->Cell(0,8,'Fecha de registro: ' . date("d/m/Y H:i", strtotime($datos['fecha_inscripcion'])),0,1);
} else {
    $pdf->SetTextColor(200,0,0);
    $pdf->Cell(0,10,utf8_decode('EL ALUMNO NO REGISTRA INSCRIPCIÓN EN EL CICLO ACTUAL.'),0,1,'C');
    $pdf->SetTextColor(0,0,0);
}

// --- FIRMAS ---
$pdf->Ln(35);

$pdf->Cell(90,0,'',0,0);
$pdf->Cell(90,0,'',0,1);

$pdf->Cell(90,5,'__________________________',0,0,'C');
$pdf->Cell(90,5,'__________________________',0,1,'C');

$pdf->Cell(90,5,'Firma del Responsable',0,0,'C');
$pdf->Cell(90,5,'Firma Autoridad Escolar',0,1,'C');

$nombre_archivo = 'Ficha_' . $datos['dni'] . '.pdf';
$pdf->Output('I', $nombre_archivo); // 'I' muestra en navegador, 'D' descarga
?>