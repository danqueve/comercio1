<?php
session_start();
require_once '../../config/conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Obtener todos los productos para el select
$stmt = $pdo->query("SELECT id, descripcion, talle, precio_venta, stock_deposito, stock_administracion FROM vestimenta_productos ORDER BY descripcion ASC");
$productos = $stmt->fetchAll();

$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container">
    <div class="card shadow border-0">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-cart-plus me-2"></i> Registrar Nueva Venta de Vestimenta</h5>
            <a href="index.php" class="btn btn-sm btn-light">Volver</a>
        </div>
        <div class="card-body p-4">
            <form action="procesar_venta.php" method="POST" id="formVenta">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="tablaDetalles">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40%;">Producto</th>
                                <th style="width: 20%;">Origen Stock</th>
                                <th style="width: 15%;">Cantidad</th>
                                <th style="width: 15%;">Precio Unit.</th>
                                <th style="width: 10%;">Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="fila-producto">
                                <td>
                                    <select name="producto_id[]" class="form-select select-producto" required>
                                        <option value="">Seleccione un producto...</option>
                                        <?php foreach ($productos as $p): ?>
                                            <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio_venta'] ?>"
                                                data-sd="<?= $p['stock_deposito'] ?>"
                                                data-sa="<?= $p['stock_administracion'] ?>">
                                                <?= htmlspecialchars($p['descripcion']) ?> (
                                                <?= htmlspecialchars($p['talle']) ?>) - Stock: [D:
                                                <?= $p['stock_deposito'] ?>, A:
                                                <?= $p['stock_administracion'] ?>]
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="origen[]" class="form-select" required>
                                        <option value="deposito">Depósito</option>
                                        <option value="administracion">Administración</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="cantidad[]" class="form-control input-cantidad" value="1"
                                        min="1" required>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" name="precio_unitario[]"
                                            class="form-control input-precio" readonly>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-end">$ <span class="subtotal">0,00</span></div>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-outline-danger btn-sm eliminar-fila"
                                        disabled><i class="bi bi-x-lg"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <button type="button" class="btn btn-outline-primary" id="btnAgregarFila">
                        <i class="bi bi-plus-lg"></i> Agregar otro item
                    </button>
                    <div class="h4 mb-0">Total Venta: <span id="totalVenta" class="fw-bold text-success">$ 0,00</span>
                    </div>
                </div>

                <hr class="my-4">

                <div class="text-end">
                    <button type="submit" class="btn btn-success btn-lg px-5 shadow">
                        <i class="bi bi-check-lg"></i> Confirmar Venta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tabla = document.getElementById('tablaDetalles').getElementsByTagName('tbody')[0];
        const btnAgregar = document.getElementById('btnAgregarFila');
        const spanTotal = document.getElementById('totalVenta');

        function actualizarCalculos() {
            let total = 0;
            document.querySelectorAll('.fila-producto').forEach(fila => {
                const select = fila.querySelector('.select-producto');
                const cantInput = fila.querySelector('.input-cantidad');
                const precioInput = fila.querySelector('.input-precio');
                const subtotalSpan = fila.querySelector('.subtotal');

                const option = select.options[select.selectedIndex];
                if (option && option.value) {
                    const precio = parseFloat(option.dataset.precio);
                    precioInput.value = precio.toFixed(2);
                    const subtotal = precio * parseInt(cantInput.value || 0);
                    subtotalSpan.textContent = subtotal.toLocaleString('es-AR', { minimumFractionDigits: 2 });
                    total += subtotal;
                } else {
                    precioInput.value = '';
                    subtotalSpan.textContent = '0,00';
                }
            });
            spanTotal.textContent = '$ ' + total.toLocaleString('es-AR', { minimumFractionDigits: 2 });
        }

        btnAgregar.addEventListener('click', function () {
            const primeraFila = document.querySelector('.fila-producto');
            const nuevaFila = primeraFila.cloneNode(true);

            // Limpiar valores
            nuevaFila.querySelector('.select-producto').selectedIndex = 0;
            nuevaFila.querySelector('.input-cantidad').value = 1;
            nuevaFila.querySelector('.input-precio').value = '';
            nuevaFila.querySelector('.subtotal').textContent = '0,00';

            const btnEliminar = nuevaFila.querySelector('.eliminar-fila');
            btnEliminar.disabled = false;
            btnEliminar.addEventListener('click', function () {
                nuevaFila.remove();
                actualizarCalculos();
            });

            tabla.appendChild(nuevaFila);
            vincularEventos(nuevaFila);
        });

        function vincularEventos(fila) {
            fila.querySelector('.select-producto').addEventListener('change', actualizarCalculos);
            fila.querySelector('.input-cantidad').addEventListener('input', actualizarCalculos);
            fila.querySelector('.eliminar-fila').addEventListener('click', function () {
                if (document.querySelectorAll('.fila-producto').length > 1) {
                    fila.remove();
                    actualizarCalculos();
                }
            });
        }

        vincularEventos(document.querySelector('.fila-producto'));
    });
</script>

<?php include $base_path . 'includes/footer.php'; ?>