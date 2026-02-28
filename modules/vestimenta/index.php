<?php
session_start();
require_once '../../config/conexion.php';

// Verificar seguridad
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Búsqueda
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$termino = "%$busqueda%";

// Consulta de productos
$sql = "SELECT * FROM vestimenta_productos 
        WHERE descripcion LIKE :b1 
        ORDER BY descripcion ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['b1' => $termino]);
$productos = $stmt->fetchAll();

// --- INCLUIR CABECERA MAESTRA ---
$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container">

    <!-- Mensajes de operación -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'eliminado'): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Producto eliminado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($_GET['msg'] === 'error_relacion'): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>No se puede eliminar este producto</strong> porque tiene ventas registradas asociadas.
                Si ya no querés usarlo, podés poner su stock en 0.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Encabezado y Botones -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="bi bi-tag-fill text-primary me-2"></i> Inventario de Vestimenta</h3>
        <div class="d-flex gap-2">
            <a href="imprimir_inventario.php?q=<?= urlencode($busqueda) ?>" target="_blank"
                class="btn btn-outline-danger btn-sm shadow-sm">
                <i class="bi bi-file-earmark-pdf"></i> PDF Inventario
            </a>
            <a href="imprimir_precios.php?q=<?= urlencode($busqueda) ?>" target="_blank"
                class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="bi bi-file-earmark-pdf"></i> PDF Lista de Precios
            </a>
            <a href="ver_ventas.php" class="btn btn-outline-info shadow-sm">
                <i class="bi bi-list-check"></i> Ver Ventas
            </a>
            <a href="nueva_venta.php" class="btn btn-primary shadow-sm">
                <i class="bi bi-cart-plus"></i> Nueva Venta
            </a>
            <a href="crear.php" class="btn btn-success shadow-sm">
                <i class="bi bi-plus-lg"></i> Nuevo Producto
            </a>
        </div>
    </div>

    <!-- Buscador -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <form method="GET" action="" class="row g-2">
                <div class="col-md-10">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i
                                class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control border-start-0 ps-0"
                            placeholder="Buscar por descripción..." value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Productos -->
    <div class="card shadow border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Descripción</th>
                            <th>Talle</th>
                            <th class="text-center">Stock Depósito</th>
                            <th class="text-center">Stock Admin.</th>
                            <th class="text-end">Costo</th>
                            <th class="text-end">Precio Venta</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($productos) > 0): ?>
                            <?php foreach ($productos as $prod): ?>
                                <tr>
                                    <td class="ps-3 fw-bold">
                                        <?= htmlspecialchars($prod['descripcion']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($prod['talle']) ?>
                                    </td>
                                    <td class="text-center">
                                        <span
                                            class="badge <?= $prod['stock_deposito'] > 5 ? 'bg-success' : 'bg-warning' ?> bg-opacity-10 <?= $prod['stock_deposito'] > 5 ? 'text-success' : 'text-warning' ?> border">
                                            <?= $prod['stock_deposito'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span
                                            class="badge <?= $prod['stock_administracion'] > 2 ? 'bg-info' : 'bg-danger' ?> bg-opacity-10 <?= $prod['stock_administracion'] > 2 ? 'text-info' : 'text-danger' ?> border">
                                            <?= $prod['stock_administracion'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end">$
                                        <?= number_format($prod['costo'], 2, ',', '.') ?>
                                    </td>
                                    <td class="text-end fw-bold">$
                                        <?= number_format($prod['precio_venta'], 2, ',', '.') ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group">
                                            <a href="editar.php?id=<?= $prod['id'] ?>" class="btn btn-sm btn-outline-secondary"
                                                title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="eliminar.php?id=<?= $prod['id'] ?>" class="btn btn-sm btn-outline-danger"
                                                title="Eliminar"
                                                onclick="return confirm('¿Eliminar el producto: <?= addslashes(htmlspecialchars($prod['descripcion'])) ?> (Talle: <?= addslashes($prod['talle']) ?>)?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-tag display-6 d-block mb-3 opacity-50"></i>
                                        <p class="mb-0">No se encontraron productos.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include $base_path . 'includes/footer.php'; ?>