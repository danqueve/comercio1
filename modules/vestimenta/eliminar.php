<?php
session_start();
require_once '../../config/conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if ($id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM vestimenta_productos WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: index.php?msg=eliminado');
    } catch (PDOException $e) {
        header('Location: index.php?msg=error_relacion');
    }
} else {
    header('Location: index.php');
}
exit;
?>