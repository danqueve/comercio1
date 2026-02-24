<?php
require_once '../../config/conexion.php';

header('Content-Type: application/json');

if (isset($_GET['dni'])) {
    $dni = trim($_GET['dni']);
    
    try {
        $sql = "SELECT * FROM tutores WHERE dni = :dni LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['dni' => $dni]);
        $tutor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tutor) {
            echo json_encode(['found' => true, 'data' => $tutor]);
        } else {
            echo json_encode(['found' => false]);
        }
    } catch (PDOException $e) {
        echo json_encode(['found' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['found' => false]);
}
?>