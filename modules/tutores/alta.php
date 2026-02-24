<?php
require_once '../../config/conexion.php';

header('Content-Type: application/json');

$dni = $_GET['dni'] ?? '';

if (!empty($dni)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM tutores WHERE dni = :dni LIMIT 1");
        $stmt->execute(['dni' => $dni]);
        $tutor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tutor) {
            echo json_encode(['success' => true, 'tutor' => $tutor]);
        } else {
            echo json_encode(['success' => false]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false]);
}
?>