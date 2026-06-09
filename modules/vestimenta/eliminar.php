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

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if (!$id) {
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM vestimenta_productos WHERE id = :id");
    $stmt->execute(['id' => $id]);
    audit_log($pdo, 'ELIMINAR_PRODUCTO_VESTIMENTA', "ID producto: $id");
    header('Location: index.php?msg=eliminado');
} catch (PDOException $e) {
    log_error('ELIMINAR_PRODUCTO_VESTIMENTA', $e->getMessage());
    header('Location: index.php?msg=error_relacion');
}
exit;

