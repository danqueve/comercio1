<?php
session_start();
require_once '../../config/conexion.php';
require_once '../../config/logger.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Solo administradores
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: ver_ventas.php?msg=sin_permiso');
    exit;
}

$id_venta = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id_venta) {
    header('Location: ver_ventas.php');
    exit;
}

// Verificar que la venta existe
$stmt_v = $pdo->prepare("SELECT id, total FROM vestimenta_ventas WHERE id = ?");
$stmt_v->execute([$id_venta]);
$venta = $stmt_v->fetch();

if (!$venta) {
    header('Location: ver_ventas.php?msg=no_encontrada');
    exit;
}

// Obtener detalles para restaurar stock
$stmt_d = $pdo->prepare("SELECT id_producto, cantidad, origen_stock FROM vestimenta_venta_detalles WHERE id_venta = ?");
$stmt_d->execute([$id_venta]);
$detalles = $stmt_d->fetchAll();

try {
    $pdo->beginTransaction();

    // 1. Restaurar stock de cada ítem
    foreach ($detalles as $det) {
        $columna = ($det['origen_stock'] === 'deposito') ? 'stock_deposito' : 'stock_administracion';
        $sql_stock = "UPDATE vestimenta_productos SET $columna = $columna + :cant WHERE id = :id";
        $stmt_s = $pdo->prepare($sql_stock);
        $stmt_s->execute(['cant' => $det['cantidad'], 'id' => $det['id_producto']]);
    }

    // 2. Eliminar detalles
    $pdo->prepare("DELETE FROM vestimenta_venta_detalles WHERE id_venta = ?")->execute([$id_venta]);

    // 3. Eliminar cabecera
    $pdo->prepare("DELETE FROM vestimenta_ventas WHERE id = ?")->execute([$id_venta]);

    // 4. Log de auditoría
    audit_log($pdo, 'ELIMINAR_VENTA_VESTIMENTA', "ID venta: $id_venta — Total: $" . $venta['total']);

    $pdo->commit();
    header('Location: ver_ventas.php?msg=venta_eliminada');
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: ver_ventas.php?msg=error_eliminar');
}
exit;
?>