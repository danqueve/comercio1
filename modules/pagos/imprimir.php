<?php
session_start();
require_once '../../config/conexion.php';
require_once '../../fpdf/fpdf.php';

// Verificar seguridad básica
if (!isset($_SESSION['user_id'])) {
    die("Acceso denegado. Debes iniciar sesión.");
}

$id_pago = $_GET['id'] ?? null;
$tipo    = $_GET['tipo'] ?? 'a4'; // 'matricial' o 'a4'

if (!$id_pago) {
    die("Error: No se especificó el pago.");
}

// Obtener datos del pago, alumno y ciclo lectivo
$sql = "SELECT p.*, a.nombre, a.apellido, a.dni, c.anio as anio_lectivo 
        FROM pagos p
        JOIN alumnos a ON p.id_alumno = a.id
        LEFT JOIN ciclos_lectivos c ON p.id_ciclo_lectivo = c.id
        WHERE p.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $id_pago]);
$pago = $stmt->fetch();

if (!$pago) {
    die("Error: El pago no existe.");
}

class ReciboPDF extends FPDF {
    public $esMatricial = false;

    // En modo matricial desactivamos encabezados automáticos para tener control total
    function Header() {
        if (!$this->esMatricial) {
            // Solo para A4 dejamos un margen superior limpio
        }
    }
}

// Configuración inicial del PDF
$pdf = new ReciboPDF('P', 'mm', 'A4');
$pdf->esMatricial = ($tipo == 'matricial');
$pdf->AddPage();

if ($tipo == 'matricial') {
    // ==========================================
    // MODO MATRICIAL (EPSON LX-350)
    // ==========================================
    // Fuente Courier: Monoespaciada, rápida, nativa de impresoras de matriz de punto
    $pdf->SetFont('Courier', 'B', 12);
    
    // Encabezado
    $pdf->Cell(0, 5, utf8_decode('ESCUELA DE COMERCIO N° 1'), 0, 1, 'L');
    $pdf->SetFont('Courier', '', 10);
    $pdf->Cell(0, 5, utf8_decode('Gral. Manuel Belgrano'), 0, 1, 'L');
    $pdf->Cell(0, 5, str_repeat('-', 40), 0, 1, 'L'); // Línea separadora
    
    $pdf->Ln(3);
    
    // Número y Fecha
    $pdf->SetFont('Courier', 'B', 12);
    $pdf->Cell(0, 6, utf8_decode('RECIBO N°: ' . str_pad($pago['id'], 6, '0', STR_PAD_LEFT)), 0, 1, 'L');
    $pdf->SetFont('Courier', '', 10);
    $pdf->Cell(0, 6, 'FECHA: ' . date('d/m/Y H:i', strtotime($pago['fecha'])), 0, 1, 'L');
    
    $pdf->Ln(3);
    
    // Datos del Alumno
    $pdf->Cell(20, 5, 'ALUMNO:', 0, 0);
    $pdf->Cell(0, 5, utf8_decode($pago['apellido'] . ', ' . $pago['nombre']), 0, 1);
    
    $pdf->Cell(20, 5, 'DNI:', 0, 0);
    $pdf->Cell(0, 5, $pago['dni'], 0, 1);
    
    $pdf->Ln(3);
    $pdf->Cell(0, 5, str_repeat('-', 40), 0, 1, 'L');
    $pdf->Ln(2);

    // Concepto
    $pdf->Cell(20, 5, 'CONCEPTO:', 0, 1);
    $pdf->MultiCell(0, 5, utf8_decode($pago['concepto']));
    
    $pdf->Ln(5);
    
    // Total
    $pdf->SetFont('Courier', 'B', 14);
    $pdf->Cell(20, 8, 'TOTAL:', 0, 0);
    $pdf->Cell(0, 8, '$ ' . number_format($pago['monto'], 2, ',', '.'), 0, 1);
    
    $pdf->SetFont('Courier', '', 10);
    $pdf->Ln(10);
    
    // Firma
    $pdf->Cell(0, 5, 'Firma y Sello:', 0, 1);
    $pdf->Ln(12);
    $pdf->Cell(0, 5, str_repeat('.', 30), 0, 1);
    

    // Fin del ticket matricial
    $pdf->Ln(5);
    $pdf->Cell(0, 5, str_repeat('-', 80), 0, 1, 'C'); // Línea de corte visual

} else {
    // ==========================================
    // MODO A4 (ORIGINAL Y DUPLICADO)
    // ==========================================
    
    // Función auxiliar para dibujar un recibo en una posición Y específica
    $dibujarRecibo = function($pdf, $y, $titulo) use ($pago) {
        $pdf->SetY($y);
        
        // Marco exterior
        $pdf->Rect(10, $y, 190, 130);
        
        // Encabezado Institucional
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, utf8_decode('ESCUELA DE COMERCIO N° 1'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, utf8_decode('Gral. Manuel Belgrano'), 0, 1, 'C');
        $pdf->Ln(5);
        
        // Etiqueta (Original / Duplicado)
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(0, 6, utf8_decode($titulo), 1, 1, 'C', true);
        
        // Caja de Información Derecha (Número y Fecha)
        $pdf->SetY($y + 10);
        $pdf->SetX(130);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(60, 8, utf8_decode('RECIBO N° ' . str_pad($pago['id'], 8, '0', STR_PAD_LEFT)), 0, 1, 'R');
        $pdf->SetX(130);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(60, 6, 'Fecha: ' . date('d/m/Y', strtotime($pago['fecha'])), 0, 1, 'R');
        
        $pdf->Ln(10);
        
        // Cuerpo del Recibo
        $pdf->SetX(20);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(35, 8, 'Recibimos de:', 0, 0);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(130, 8, utf8_decode($pago['apellido'] . ', ' . $pago['nombre']), 0, 1, 'L');
        $pdf->Line(55, $pdf->GetY(), 190, $pdf->GetY()); // Línea subrayada

        $pdf->SetX(20);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(35, 8, 'DNI:', 0, 0);
        $pdf->Cell(130, 8, $pago['dni'], 0, 1, 'L');
        $pdf->Line(55, $pdf->GetY(), 190, $pdf->GetY());
        
        $pdf->Ln(2);
        
        $pdf->SetX(20);
        $pdf->Cell(35, 8, 'La suma de:', 0, 0);
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(130, 8, '$ ' . number_format($pago['monto'], 2, ',', '.'), 0, 1, 'L');
        $pdf->Line(55, $pdf->GetY(), 190, $pdf->GetY());
        
        $pdf->Ln(2);
        
        $pdf->SetX(20);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(35, 8, 'En concepto de:', 0, 0);
        $pdf->MultiCell(135, 8, utf8_decode($pago['concepto']), 0, 'L');
        
        // Firma
        $pdf->SetY($y + 95);
        $pdf->SetX(120);
        $pdf->Cell(60, 0, '', 1, 1); // Línea firma
        $pdf->SetY($y + 97);
        $pdf->SetX(120);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(60, 4, utf8_decode('Firma y Sello Autorizado'), 0, 1, 'C');
        $pdf->SetX(120);
        
    };
    
    // Dibujar ORIGINAL arriba
    $dibujarRecibo($pdf, 10, 'ORIGINAL');
    
    // Línea de corte (tijeras)
    $pdf->SetY(148);
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(0, 0, '- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  corte aqui  - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -', 0, 1, 'C');
    
    // Dibujar DUPLICADO abajo
    $dibujarRecibo($pdf, 155, 'DUPLICADO (ARCHIVO)');
}

$nombre_archivo = 'Recibo_' . $pago['id'] . '.pdf';
$pdf->Output('I', $nombre_archivo);
?>