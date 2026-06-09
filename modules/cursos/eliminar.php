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

$id_curso = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if (!$id_curso) {
    header('Location: index.php?mensaje=error_id');
    exit;
}

try {
    // Validación de integridad: verificar si hay alumnos inscriptos
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE id_curso = :id");
    $stmt_check->execute(['id' => $id_curso]);
    $cantidad_inscriptos = $stmt_check->fetchColumn();

    if ($cantidad_inscriptos > 0) {
        header('Location: index.php?mensaje=error_dependencia&cant=' . $cantidad_inscriptos);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM cursos WHERE id = :id");
    $stmt->execute(['id' => $id_curso]);

    if ($stmt->rowCount() > 0) {
        audit_log($pdo, 'ELIMINAR_CURSO', "ID curso: $id_curso");
        header('Location: index.php?mensaje=eliminado_ok');
    } else {
        header('Location: index.php?mensaje=error_no_encontrado');
    }

} catch (PDOException $e) {
    log_error('ELIMINAR_CURSO', $e->getMessage());
    header('Location: index.php?mensaje=error_bd');
}
exit;
