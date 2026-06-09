<?php
require_once '../../config/auth.php';
require_once '../../config/conexion.php';
require_once '../../config/csrf.php';
require_once '../../config/logger.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: nueva_venta.php');
    exit;
}

csrf_verify();

// Validar que lleguen arrays coherentes (precio ignorado — se lee de la BD)
$producto_ids = $_POST['producto_id'] ?? [];
$origenes     = $_POST['origen']     ?? [];
$cantidades   = $_POST['cantidad']   ?? [];

if (
    empty($producto_ids) ||
    count($producto_ids) !== count($origenes) ||
    count($producto_ids) !== count($cantidades)
) {
    log_error('PROCESAR_VENTA', 'Arrays de venta inconsistentes — posible manipulación del form.');
    header('Location: nueva_venta.php?msg=error_datos');
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Validar stock suficiente Y obtener precio oficial de la BD para cada ítem
    // F6-FIX: el precio viene de la BD, no del cliente, para evitar manipulación.
    $detalles_validados = [];

    foreach ($producto_ids as $i => $p_id) {
        $p_id = (int) $p_id;
        $cant = (int) $cantidades[$i];
        $orig = in_array($origenes[$i], ['deposito', 'administracion'], true)
                ? $origenes[$i]
                : 'administracion';

        if ($p_id <= 0) {
            throw new \RuntimeException("Ítem #" . ($i + 1) . ": producto no seleccionado.");
        }
        if ($cant <= 0) {
            throw new \RuntimeException("La cantidad debe ser mayor a cero (ítem #" . ($i + 1) . ").");
        }

        $columna_stock = ($orig === 'deposito') ? 'stock_deposito' : 'stock_administracion';
        $stmt_check = $pdo->prepare(
            "SELECT $columna_stock AS stock_disponible, descripcion, precio_venta
             FROM vestimenta_productos WHERE id = :id FOR UPDATE"
        );
        $stmt_check->execute(['id' => $p_id]);
        $prod = $stmt_check->fetch();

        if (!$prod) {
            throw new \RuntimeException("Producto #$p_id no encontrado.");
        }
        if ($prod['stock_disponible'] < $cant) {
            throw new \RuntimeException(
                "Stock insuficiente para \"{$prod['descripcion']}\": disponible {$prod['stock_disponible']}, solicitado $cant."
            );
        }

        $detalles_validados[] = [
            'p_id'        => $p_id,
            'cant'        => $cant,
            'orig'        => $orig,
            'precio_bd'   => (float) $prod['precio_venta'], // precio oficial de la BD
            'col_stock'   => $columna_stock,
        ];
    }

    // 2. Calcular total usando precios de la BD
    $total_venta = 0;
    foreach ($detalles_validados as $det) {
        $total_venta += $det['precio_bd'] * $det['cant'];
    }

    // 3. Insertar cabecera de venta
    $stmt_v = $pdo->prepare("INSERT INTO vestimenta_ventas (id_usuario, total) VALUES (?, ?)");
    $stmt_v->execute([$_SESSION['user_id'], $total_venta]);
    $id_venta = $pdo->lastInsertId();

    // 4. Insertar detalles y descontar stock atómicamente
    $stmt_d = $pdo->prepare(
        "INSERT INTO vestimenta_venta_detalles (id_venta, id_producto, cantidad, precio_unitario, origen_stock)
         VALUES (?, ?, ?, ?, ?)"
    );

    foreach ($detalles_validados as $det) {
        $p_id  = $det['p_id'];
        $cant  = $det['cant'];
        $orig  = $det['orig'];
        $precio_u    = $det['precio_bd'];
        $columna_stock = $det['col_stock'];

        $stmt_d->execute([$id_venta, $p_id, $cant, $precio_u, $orig]);

        $columna_stock = ($orig === 'deposito') ? 'stock_deposito' : 'stock_administracion';
        // El WHERE garantiza que no baje de 0 aunque haya race condition
        $sql_stock = "UPDATE vestimenta_productos SET $columna_stock = $columna_stock - :cant
                      WHERE id = :id AND $columna_stock >= :cant2";
        $stmt_s = $pdo->prepare($sql_stock);
        $stmt_s->execute(['cant' => $cant, 'id' => $p_id, 'cant2' => $cant]);

        if ($stmt_s->rowCount() === 0) {
            throw new \RuntimeException("Error de concurrencia: stock insuficiente al momento del descuento.");
        }
    }

    $pdo->commit();
    audit_log($pdo, 'VENTA_VESTIMENTA', "ID venta: $id_venta — Total: $$total_venta");
    header('Location: ver_ventas.php?msg=venta_ok');

} catch (\RuntimeException $e) {
    $pdo->rollBack();
    // Los RuntimeException de este bloque tienen mensajes de usuario (stock insuficiente, etc.)
    header("Location: nueva_venta.php?msg=" . urlencode($e->getMessage()));
} catch (\Exception $e) {
    $pdo->rollBack();
    log_error('PROCESAR_VENTA', $e->getMessage());
    header('Location: nueva_venta.php?msg=error_interno');
}
exit;

