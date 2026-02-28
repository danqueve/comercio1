<?php
session_start();
require_once '../../config/conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descripcion = $_POST['descripcion'];
    $talle = $_POST['talle'];
    $stock_deposito = (int) $_POST['stock_deposito'];
    $stock_administracion = (int) $_POST['stock_administracion'];
    $costo = (float) $_POST['costo'];
    $precio_venta = (float) $_POST['precio_venta'];

    $sql = "INSERT INTO vestimenta_productos (descripcion, talle, stock_deposito, stock_administracion, costo, precio_venta) 
            VALUES (:desc, :talle, :sd, :sa, :costo, :precio)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'desc' => $descripcion,
        'talle' => $talle,
        'sd' => $stock_deposito,
        'sa' => $stock_administracion,
        'costo' => $costo,
        'precio' => $precio_venta
    ]);

    header('Location: index.php?msg=creado');
    exit;
}

$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow border-0">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i> Nuevo Producto de Vestimenta</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Descripción</label>
                            <input type="text" name="descripcion" class="form-control" required
                                placeholder="Ej: Chomba Colegio">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Talle</label>
                                <input type="text" name="talle" class="form-control" placeholder="Ej: M, 10, 38">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Stock Depósito</label>
                                <input type="number" name="stock_deposito" class="form-control" value="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Stock Admin.</label>
                                <input type="number" name="stock_administracion" class="form-control" value="0">
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Costo ($)</label>
                                <input type="number" step="0.01" name="costo" class="form-control" value="0.00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Precio Venta ($)</label>
                                <input type="number" step="0.01" name="precio_venta" class="form-control" value="0.00">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-success px-4">Guardar Producto</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>