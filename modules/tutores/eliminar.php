<?php
require_once '../../config/auth.php';
require_once '../../config/conexion.php';
require_once '../../config/csrf.php';
require_once '../../config/logger.php';

require_login();

// Solo acepta POST para operaciones destructivas
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

csrf_verify();

$id_tutor = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if (!$id_tutor) {
    header('Location: index.php?mensaje=error_id');
    exit;
}

try {
    // Validación de integridad: verificar si tiene alumnos asociados
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM alumnos WHERE id_tutor = :id");
    $stmt_check->execute(['id' => $id_tutor]);
    $cantidad_alumnos = $stmt_check->fetchColumn();

    if ($cantidad_alumnos > 0) {
        header('Location: index.php?mensaje=error_dependencia&cant=' . $cantidad_alumnos);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM tutores WHERE id = :id");
    $stmt->execute(['id' => $id_tutor]);

    if ($stmt->rowCount() > 0) {
        audit_log($pdo, 'ELIMINAR_TUTOR', "ID tutor: $id_tutor");
        header('Location: index.php?mensaje=eliminado_ok');
    } else {
        header('Location: index.php?mensaje=error_no_encontrado');
    }

} catch (PDOException $e) {
    log_error('ELIMINAR_TUTOR', $e->getMessage());
    header('Location: index.php?mensaje=error_bd');
}
exit;

