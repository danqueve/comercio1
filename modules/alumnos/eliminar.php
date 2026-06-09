<?php
require_once '../../config/auth.php';
require_once '../../config/conexion.php';
require_once '../../config/csrf.php';
require_once '../../config/logger.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

csrf_verify();

$id_alumno = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if (!$id_alumno) {
    header('Location: index.php?mensaje=error_id');
    exit;
}

try {
    $stmt_alu = $pdo->prepare("SELECT apellido, nombre, dni FROM alumnos WHERE id = :id");
    $stmt_alu->execute(['id' => $id_alumno]);
    $alumno = $stmt_alu->fetch();

    if (!$alumno) {
        header('Location: index.php?mensaje=error_no_encontrado');
        exit;
    }

    $pdo->beginTransaction();

    $pdo->prepare("DELETE FROM inscripciones WHERE id_alumno = :id")->execute(['id' => $id_alumno]);
    $pdo->prepare("DELETE FROM pagos WHERE id_alumno = :id")->execute(['id' => $id_alumno]);
    $pdo->prepare("DELETE FROM alumnos WHERE id = :id")->execute(['id' => $id_alumno]);

    $pdo->commit();

    $detalle = "DNI: {$alumno['dni']} | {$alumno['apellido']}, {$alumno['nombre']}";
    audit_log($pdo, 'ELIMINAR_ALUMNO', $detalle);
    invalidar_cache_dashboard();

    header('Location: index.php?mensaje=eliminado_ok');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_error('ELIMINAR_ALUMNO', $e->getMessage());
    header('Location: index.php?mensaje=error_bd');
}
exit;
