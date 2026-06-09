<?php
session_start();
require_once '../../config/conexion.php';
require_once '../../config/logger.php';

header('Content-Type: application/json');

// Seguridad: Solo usuarios autenticados pueden buscar datos de tutores
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['found' => false]);
    exit;
}

if (isset($_GET['dni'])) {
    $dni = trim($_GET['dni']);

    // Validar que el DNI sea numérico
    if (!ctype_digit($dni)) {
        echo json_encode(['found' => false]);
        exit;
    }

    try {
        // Solo exponer los campos necesarios para el autocompletado
        $sql = "SELECT id, apellido, nombre, direccion, celular FROM tutores WHERE dni = :dni LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['dni' => $dni]);
        $tutor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tutor) {
            echo json_encode(['found' => true, 'data' => $tutor]);
        } else {
            echo json_encode(['found' => false]);
        }
    } catch (PDOException $e) {
        log_error('BUSCAR_TUTOR', $e->getMessage());
        echo json_encode(['found' => false]);
    }
} else {
    echo json_encode(['found' => false]);
}
?>