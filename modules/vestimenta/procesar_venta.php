<?php
session_start();
require_once '../../config/conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $producto_ids = $_POST['producto_id'];
    $origenes = $_POST['origen'];
    $cantidades = $_POST['cantidad'];
    $precios = $_POST['precio_unitario'];

    try {
        $pdo->beginTransaction();

        // 1. Calcular total de la venta
        $total_venta = 0;
        foreach ($precios as $i => $precio) {
            $total_venta += (float) $precio * (int) $cantidades[$i];
        }

        // 2. Insertar cabecera de venta
        $stmt_v = $pdo->prepare("INSERT INTO vestimenta_ventas (id_usuario, total) VALUES (?, ?)");
        $stmt_v->execute([$_SESSION['user_id'], $total_venta]);
        $id_venta = $pdo->lastInsertId();

        // 3. Insertar detalles y descontar stock
        $stmt_d = $pdo->prepare("INSERT INTO vestimenta_venta_detalles (id_venta, id_producto, cantidad, precio_unitario, origen_stock) VALUES (?, ?, ?, ?, ?)");

        foreach ($producto_ids as $i => $p_id) {
            $cant = (int) $cantidades[$i];
            $precio_u = (float) $precios[$i];
            $orig = $origenes[$i];

            // Insertar detalle
            $stmt_d->execute([$id_venta, $p_id, $cant, $precio_u, $orig]);

            // Descontar stock
            $columna_stock = ($orig === 'deposito') ? 'stock_deposito' : 'stock_administracion';
            $sql_stock = "UPDATE vestimenta_productos SET $columna_stock = $columna_stock - :cant WHERE id = :id";
            $stmt_s = $pdo->prepare($sql_stock);
            $stmt_s->execute(['cant' => $cant, 'id' => $p_id]);
        }

        $pdo->commit();
        header('Location: ver_ventas.php?msg=venta_ok');
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error al procesar la venta: " . $e->getMessage());
    }
} else {
    header('Location: nueva_venta.php');
}
exit;
?>