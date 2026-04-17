<?php
session_start();
include('conexion.php');

if (!isset($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'EMPLEADO') {
    header('Location: index.php');
    exit;
}

function sanitize($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatMoney($value) {
    return '$' . number_format((float) $value, 0, ',', '.');
}

function fetchRows($conexion, $sql) {
    $result = $conexion->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getEmpleadoId($conexion, $usuario) {
    $stmt = $conexion->prepare('SELECT id_usuario FROM usuarios WHERE nombre_usuario = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['id_usuario'] ?? null;
}

function getAccesorio($conexion, $id) {
    $stmt = $conexion->prepare('SELECT id_accesorio AS id, nombre, descripcion, precio, stock_actual FROM accesorios WHERE id_accesorio = ? AND estado = \'ACTIVO\' LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

$mensaje = '';
$error = '';
$ventaConfirmada = false;
$ventaIdConfirmada = null;
$ventaTotalConfirmada = 0;
$metodoPagoConfirmado = 'EFECTIVO';
$fechaConfirmada = '';

if (isset($_SESSION['flash_venta_confirmada'])) {
    $flash = $_SESSION['flash_venta_confirmada'];
    $ventaConfirmada = true;
    $ventaIdConfirmada = $flash['id'] ?? null;
    $ventaTotalConfirmada = $flash['total'] ?? 0;
    $metodoPagoConfirmado = $flash['pago'] ?? 'EFECTIVO';
    $fechaConfirmada = $flash['fecha'] ?? '';
    unset($_SESSION['flash_venta_confirmada']);
}

$empleadoId = getEmpleadoId($conexion, $_SESSION['usuario']);
if ($empleadoId === null) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_venta'])) {
    $productoIds = $_POST['producto_id'] ?? [];
    $cantidades = $_POST['cantidad'] ?? [];
    $metodoPago = $_POST['metodo_pago'] ?? 'EFECTIVO';
    $items = [];
    $totalVenta = 0;

    if (!in_array($metodoPago, ['EFECTIVO', 'TRANSFERENCIA'], true)) {
        $error = 'Seleccione un método de pago válido.';
    }

    foreach ($productoIds as $index => $productoId) {
        $id = intval($productoId);
        $cantidad = intval($cantidades[$index] ?? 0);

        if ($id <= 0 || $cantidad <= 0) {
            continue;
        }

        $producto = getAccesorio($conexion, $id);
        if (!$producto) {
            $error = 'Uno de los productos seleccionados no está disponible.';
            break;
        }

        if ($producto['stock_actual'] < $cantidad) {
            $error = 'No hay stock suficiente para: ' . sanitize($producto['nombre']) . '.';
            break;
        }

        $subtotal = $cantidad * $producto['precio'];
        $items[] = [
            'id' => $producto['id'],
            'nombre' => $producto['nombre'],
            'precio' => $producto['precio'],
            'cantidad' => $cantidad,
            'subtotal' => $subtotal,
        ];
        $totalVenta += $subtotal;
    }

    if (!$error && count($items) === 0) {
        $error = 'Agregue al menos un producto antes de registrar la venta.';
    }

    if (!$error) {
        $conexion->begin_transaction();
        $stmtVenta = $conexion->prepare('INSERT INTO ventas (fecha, id_empleado, metodo_pago, total_venta) VALUES (NOW(), ?, ?, ?)');
        if (!$stmtVenta) {
            $conexion->rollback();
            $error = 'Error al preparar la venta.';
        } else {
            $stmtVenta->bind_param('isd', $empleadoId, $metodoPago, $totalVenta);
            if (!$stmtVenta->execute()) {
                $conexion->rollback();
                $error = 'Error al guardar la venta: ' . $conexion->error;
            } else {
                $ventaId = $conexion->insert_id;
                $stmtDetalle = $conexion->prepare('INSERT INTO detalle_ventas (id_venta, id_sabor, id_accesorio, cantidad, precio_unitario, subtotal) VALUES (?, NULL, ?, ?, ?, ?)');
                $stmtUpdate = $conexion->prepare('UPDATE accesorios SET stock_actual = stock_actual - ? WHERE id_accesorio = ?');

                if (!$stmtDetalle || !$stmtUpdate) {
                    $conexion->rollback();
                    $error = 'Error interno al preparar los detalles de la venta.';
                } else {
                    foreach ($items as $item) {
                        $stmtDetalle->bind_param('iiidd', $ventaId, $item['id'], $item['cantidad'], $item['precio'], $item['subtotal']);
                        if (!$stmtDetalle->execute()) {
                            $conexion->rollback();
                            $error = 'Error al guardar un detalle de venta: ' . $conexion->error;
                            break;
                        }
                        $stmtUpdate->bind_param('ii', $item['cantidad'], $item['id']);
                        if (!$stmtUpdate->execute()) {
                            $conexion->rollback();
                            $error = 'Error al actualizar stock del producto: ' . $conexion->error;
                            break;
                        }
                    }

                    if (!$error) {
                        $conexion->commit();
                        $_SESSION['flash_venta_confirmada'] = [
                            'id' => $ventaId,
                            'total' => $totalVenta,
                            'pago' => $metodoPago,
                            'fecha' => date('Y-m-d H:i:s'),
                        ];
                        header('Location: empleado.php');
                        exit;
                    }
                }
            }
            if ($stmtVenta) {
                $stmtVenta->close();
            }
        }
    }
}

$sabores = fetchRows($conexion, "SELECT id_sabor AS id, nombre, tipo, precio, stock_actual FROM sabores WHERE estado = 'ACTIVO' ORDER BY tipo, nombre");
$accesorios = fetchRows($conexion, "SELECT id_accesorio AS id, nombre, descripcion, precio, stock_actual FROM accesorios WHERE estado = 'ACTIVO' ORDER BY nombre");
$ventas = fetchRows($conexion, 'SELECT v.id_venta AS id, v.fecha, v.total_venta AS total, v.metodo_pago AS pago, IFNULL(u.nombre_usuario, "Sin empleado") AS empleado FROM ventas v LEFT JOIN usuarios u ON v.id_empleado = u.id_usuario WHERE v.id_empleado = ' . intval($empleadoId) . ' ORDER BY v.fecha DESC LIMIT 20');
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dajana helados / Empleado</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="empleado.css">
</head>

<body>
    <div id="s-emp" class="pantalla">
        <div class="encabezado">
            <div class="logo">
                <div class="logo-nombre"><img src="img/dajana-logo.png" alt="dajana"></div>
                <div class="logo-subtitulo">helados</div>
            </div>
            <div class="botones-encabezado">
                <div class="rol-encabezado">EMPLEADO</div>
                <a class="boton boton-cerrar" href="index.php">CERRAR SESIÓN</a>
            </div>
        </div>
        <div class="inicio-empleado">
            <div class="bienvenida-empleado">¡Bienvenida/o, <?= sanitize($_SESSION['usuario']) ?>!</div>
            <div class="botones-empleado">
                <a class="boton-empleado boton-empleado-principal" href="#s-venta">REGISTRAR VENTA</a>
                <a class="boton-empleado boton-empleado-secundario" href="#s-historial">MIS VENTAS</a>
            </div>
        </div>
    </div>

    <div id="s-venta" class="pantalla">
        <div class="encabezado">
            <div class="logo">
                <div class="logo-nombre"><img src="img/dajana-logo.png" alt="dajana"></div>
                <div class="logo-subtitulo">helados</div>
            </div>
            <div class="botones-encabezado">
                <div class="rol-encabezado">EMPLEADO</div>
                <a class="boton boton-volver" href="#s-emp">VOLVER</a>
            </div>
        </div>
        <div class="contenido">
            <div class="barra">
                <div class="titulo-pagina flex-1 no-mb">REGISTRAR VENTA</div>
            </div>
            <?php if ($mensaje): ?>
                <div class="mensaje-exito" style="margin-bottom:16px; padding:12px 14px; background:#e8f5e9; color:#1b5e20; border-radius:12px;"><?= sanitize($mensaje) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="mensaje-error" style="margin-bottom:16px; padding:12px 14px; background:#ffebee; color:#b71c1c; border-radius:12px;"><?= sanitize($error) ?></div>
            <?php endif; ?>
            <form method="post" id="sale-form">
                <div class="carrito" id="carrito">
                    <div class="item-venta item-producto" data-index="1">
                        <div class="fila-venta">
                            <div class="etiqueta-venta">PRODUCTO</div>
                            <select class="entrada-venta tipo-producto" name="producto_id[]">
                                <option value="">SELECCIONAR</option>
                                <?php foreach ($accesorios as $accesorio): ?>
                                    <option value="<?= sanitize($accesorio['id']) ?>" data-price="<?= sanitize($accesorio['precio']) ?>" data-name="<?= sanitize($accesorio['nombre']) ?>" <?= $accesorio['stock_actual'] <= 0 ? 'disabled' : '' ?>>
                                        <?= sanitize($accesorio['nombre']) ?>  <?= formatMoney($accesorio['precio']) ?><?= $accesorio['stock_actual'] <= 0 ? '  SIN STOCK' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="fila-venta sabores-por-producto">
                            <div class="etiqueta-venta">SABORES</div>
                            <div class="envoltorio-sabores">
                                <div class="titulo-sabores">SABORES DISPONIBLES</div>
                                <div class="pestanas-categoria-sabores" style="margin-bottom:12px; gap:6px;">
                                    <?php foreach ($sabores as $sabor): ?>
                                        <?php $lowStock = $sabor['stock_actual'] < 3; ?>
                                        <button type="button" class="ficha sabor-boton<?= $lowStock ? ' agotado' : '' ?>" data-sabor="<?= sanitize($sabor['nombre']) ?>" <?= $lowStock ? 'disabled' : '' ?> title="Stock: <?= sanitize($sabor['stock_actual']) ?>">
                                            <?= sanitize($sabor['nombre']) ?> (<?= sanitize($sabor['tipo']) ?>)<?= $lowStock ? ' · BAJO STOCK' : '' ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <div class="sabores-seleccionados" style="display:none;"></div>
                            </div>
                        </div>
                        <div class="fila-venta">
                            <div class="etiqueta-venta">CANTIDAD</div>
                            <input class="entrada-venta cantidad-producto" type="number" min="1" name="cantidad[]" value="1">
                        </div>
                        <div class="fila-venta">
                            <div class="etiqueta-venta">PRECIO UNIT.</div>
                            <input class="entrada-venta precio-unitario" type="text" readonly value="">
                        </div>
                        <div class="fila-venta" style="justify-content:flex-end;">
                            <button type="button" class="boton boton-en-linea boton-eliminar-item">Eliminar</button>
                        </div>
                    </div>
                </div>

                <div class="envoltorio-agregar-carrito">
                    <button type="button" class="boton boton-secundario boton-agregar-item">+ AGREGAR PRODUCTO</button>
                </div>

                <div class="caja-total">TOTAL: <span>$0</span></div>

                <div class="caja-pago">
                    <div class="titulo-pago">MÉTODO DE PAGO</div>
                    <input type="hidden" name="metodo_pago" id="metodo_pago" value="EFECTIVO">
                    <div class="opciones-pago">
                        <button type="button" class="opcion-pago pago-activo" data-payment="EFECTIVO">EFECTIVO</button>
                        <button type="button" class="opcion-pago" data-payment="TRANSFERENCIA">TRANSFERENCIA</button>
                    </div>
                </div>

                <input type="hidden" name="submit_venta" value="1">
            </form>

            <div class="botones-venta">
                <button type="button" id="view-ticket" class="boton boton-agregar boton-confirmar">VER TICKET</button>
                <a class="boton boton-secundario" href="#s-emp">CANCELAR</a>
            </div>
        </div>
    </div>

    <div id="s-ticket" class="pantalla">
        <div class="encabezado">
            <div class="logo">
                <div class="logo-nombre"><img src="img/dajana-logo.png" alt="dajana"></div>
                <div class="logo-subtitulo">helados</div>
            </div>
            <div class="botones-encabezado">
                <div class="rol-encabezado">EMPLEADO</div>
                <a class="boton boton-volver" href="#s-venta">EDITAR</a>
            </div>
        </div>
        <div class="contenido">
            <div class="titulo-pagina">REVISAR TICKET</div>
            <div class="envoltorio-tickets" id="ticket-previews">
                <div class="caja-ticket">
                    <div class="etiqueta-ticket etiqueta-empleado">COPIA EMPLEADO</div>
                    <div class="titulo-ticket">DAJANA HELADOS</div>
                    <div class="numero-ticket">Venta del día <span id="ticket-number">#0</span></div>
                    <div class="fila-ticket"><span>Empleado:</span><span><?= sanitize($_SESSION['usuario']) ?></span></div>
                    <div class="fila-ticket"><span>Fecha:</span><span id="ticket-fecha"></span></div>
                    <div id="ticket-items-1"></div>
                    <div class="total-ticket">TOTAL: <span id="ticket-total">$0</span></div>
                    <div class="fila-ticket" style="margin-top:5px;"><span>Pago:</span><span id="ticket-pago">EFECTIVO</span></div>
                </div>
                <div class="caja-ticket">
                    <div class="etiqueta-ticket etiqueta-cliente">TICKET CLIENTE</div>
                    <div class="titulo-ticket">DAJANA HELADOS</div>
                    <div class="numero-ticket">Orden <span id="ticket-number-client">#0</span></div>
                    <div class="fila-ticket"><span>Fecha:</span><span id="ticket-fecha-client"></span></div>
                    <div id="ticket-items-2"></div>
                    <div class="total-ticket">TOTAL: <span id="ticket-total-client">$0</span></div>
                    <div class="fila-ticket" style="margin-top:5px;"><span>Pago:</span><span id="ticket-pago-client">EFECTIVO</span></div>
                </div>
            </div>
            <div class="acciones-ticket">
                <button type="button" class="boton boton-agregar boton-confirmar" id="submit-sale">CONFIRMAR Y REGISTRAR</button>
                <a class="boton boton-rojo" href="#s-emp">CANCELAR VENTA</a>
            </div>
        </div>
    </div>

    <div id="s-conf" class="pantalla">
        <div class="encabezado">
            <div class="logo">
                <div class="logo-nombre"><img src="img/dajana-logo.png" alt="dajana"></div>
                <div class="logo-subtitulo">helados</div>
            </div>
            <div class="botones-encabezado">
                <div class="rol-encabezado" style="color:var(--dorado-light);">EMPLEADO</div>
            </div>
        </div>
        <div class="contenido">
            <div class="tarjeta-confirmacion">
                <div class="icono-confirmacion"></div>
                <div class="titulo-confirmacion">¡VENTA REGISTRADA!</div>
                <div class="detalle-confirmacion">Venta <strong>#<?= sanitize($ventaIdConfirmada ?? 0) ?> del día</strong></div>
                <div class="detalle-confirmacion">Fecha: <strong><?= sanitize($fechaConfirmada ? date('d/m/Y H:i', strtotime($fechaConfirmada)) : '') ?></strong></div>
                <div class="detalle-confirmacion">Total: <strong><?= sanitize(formatMoney($ventaTotalConfirmada)) ?></strong></div>
                <div class="detalle-confirmacion">Pago: <strong><?= sanitize($metodoPagoConfirmado) ?></strong></div>
                <div class="botones-confirmacion">
                    <a class="boton boton-agregar" href="#s-venta">NUEVA VENTA</a>
                    <a class="boton boton-secundario" href="#s-historial">VER MIS VENTAS</a>
                </div>
            </div>
        </div>
    </div>

    <div id="s-historial" class="pantalla">
        <div class="encabezado">
            <div class="logo">
                <div class="logo-nombre"><img src="img/dajana-logo.png" alt="dajana"></div>
                <div class="logo-subtitulo">helados</div>
            </div>
            <div class="botones-encabezado">
                <div class="rol-encabezado">EMPLEADO</div>
                <a class="boton boton-volver" href="#s-emp">VOLVER</a>
            </div>
        </div>
        <div class="contenido">
            <div class="titulo-pagina">MIS VENTAS</div>
            <div class="fila-flexible">
                <input id="search-ventas" class="buscador" type="text" placeholder=" Buscar en mis ventas...">
                <input id="filter-fecha" class="buscador maxw-175" type="date">
                <button id="clear-ventas-filters" type="button" class="boton boton-secundario boton-limpiar"> LIMPIAR</button>
            </div>
            <div class="envoltorio-tabla-oscuro">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>FECHA</th>
                            <th>TOTAL</th>
                            <th>PAGO</th>
                            <th>EMPLEADO</th>
                            <th>VER</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ventas) === 0): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:18px 0;">No hay ventas registradas aun.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ventas as $venta): ?>
                                <tr>
                                    <td><strong>#<?= sanitize($venta['id']) ?></strong></td>
                                    <td><?= sanitize(date('d/m/Y', strtotime($venta['fecha']))) ?></td>
                                    <td><?= formatMoney($venta['total']) ?></td>
                                    <td><?= sanitize($venta['pago']) ?></td>
                                    <td><?= sanitize($venta['empleado']) ?></td>
                                    <td><a class="boton-en-linea" href="#s-detalle1"></a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="s-detalle1" class="pantalla">
        <div class="encabezado">
            <div class="logo">
                <div class="logo-nombre"><img src="img/dajana-logo.png" alt="dajana"></div>
                <div class="logo-subtitulo">helados</div>
            </div>
            <div class="botones-encabezado">
                <div class="rol-encabezado">EMPLEADO</div>
                <a class="boton boton-volver" href="#s-historial">VOLVER</a>
            </div>
        </div>
        <div class="contenido">
            <div class="titulo-pagina">DETALLE DE VENTA</div>
            <div class="tarjeta-detalle">
                <div class="informacion-detalle">Venta del día <span>#1</span></div>
                <div class="informacion-detalle">Fecha: <span>15/04/2026</span></div>
                <div class="informacion-detalle">Empleado: <span><?= sanitize($_SESSION['usuario']) ?></span></div>
                <div class="envoltorio-tabla" style="margin:12px 0;">
                    <table>
                        <thead>
                            <tr>
                                <th style="text-align:left;">PRODUCTO</th>
                                <th>CANT.</th>
                                <th>PRECIO</th>
                                <th>SUBTOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="text-align:left;padding:8px 9px;">
                                    Pote 1/4 kg<br>
                                    <small style="color:#888;font-style:italic;">Chocolate / Vainilla</small>
                                </td>
                                <td>1</td>
                                <td>$2.500</td>
                                <td>$2.500</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="informacion-detalle" style="font-size:1rem;">Total: <span>$2.500</span></div>
                <div class="informacion-detalle">Pago: <span>EFECTIVO</span></div>
            </div>
        </div>
    </div>

    <script>
        const accesoriosData = <?= json_encode($accesorios) ?>;
        const LOW_STOCK_THRESHOLD = 3;

        function formatMoneyJS(value) {
            return '$' + Number(value).toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }

        function updateRowPrice(row) {
            const productSelect = row.querySelector('.tipo-producto');
            const quantityInput = row.querySelector('.cantidad-producto');
            const priceInput = row.querySelector('.precio-unitario');
            const selectedOption = productSelect.selectedOptions[0];

            if (!selectedOption || !selectedOption.value) {
                priceInput.value = '';
                recalculateTotal();
                return;
            }

            const price = parseFloat(selectedOption.dataset.price) || 0;
            const quantity = Math.max(1, parseInt(quantityInput.value, 10) || 1);
            priceInput.value = formatMoneyJS(price);
            recalculateTotal();
        }

        function recalculateTotal() {
            const rows = document.querySelectorAll('.item-producto');
            let total = 0;
            rows.forEach(row => {
                const productSelect = row.querySelector('.tipo-producto');
                const quantityInput = row.querySelector('.cantidad-producto');
                const selectedOption = productSelect.selectedOptions[0];
                if (!selectedOption || !selectedOption.value) return;
                const price = parseFloat(selectedOption.dataset.price) || 0;
                const quantity = Math.max(1, parseInt(quantityInput.value, 10) || 1);
                total += price * quantity;
            });
            document.querySelector('.caja-total span').textContent = formatMoneyJS(total);
        }

        function setupProductRow(row) {
            const productSelect = row.querySelector('.tipo-producto');
            const quantityInput = row.querySelector('.cantidad-producto');
            const removeButton = row.querySelector('.boton-eliminar-item');

            productSelect.addEventListener('change', () => updateRowPrice(row));
            quantityInput.addEventListener('input', () => {
                if (quantityInput.value === '' || parseInt(quantityInput.value, 10) < 1) {
                    quantityInput.value = 1;
                }
                updateRowPrice(row);
            });
            setupFlavorButtons(row);

            removeButton.addEventListener('click', () => {
                const rows = document.querySelectorAll('.item-producto');
                if (rows.length === 1) return;
                row.remove();
                updateProductNumbers();
                recalculateTotal();
            });
        }

        function setupFlavorButtons(row) {
            const flavorButtons = row.querySelectorAll('.sabor-boton');
            flavorButtons.forEach(button => {
                button.addEventListener('click', () => {
                    if (button.disabled) return;
                    const selected = row.querySelectorAll('.sabor-boton.seleccionado');
                    if (!button.classList.contains('seleccionado') && selected.length >= 3) {
                        return;
                    }
                    button.classList.toggle('seleccionado');
                    updateFlavorInputs(row);
                });
            });
            updateFlavorInputs(row);
        }

        function updateFlavorInputs(row) {
            let hiddenContainer = row.querySelector('.hidden-flavors');
            if (!hiddenContainer) {
                hiddenContainer = document.createElement('div');
                hiddenContainer.className = 'hidden-flavors';
                hiddenContainer.style.display = 'none';
                row.appendChild(hiddenContainer);
            }
            hiddenContainer.innerHTML = '';
            const selected = row.querySelectorAll('.sabor-boton.seleccionado');
            selected.forEach((button, index) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'sabores[' + (row.dataset.index || 0) + '][]';
                input.value = button.dataset.sabor;
                hiddenContainer.appendChild(input);
            });
        }

        function updateProductNumbers() {
            document.querySelectorAll('.item-producto').forEach((row, index) => {
                row.dataset.index = index + 1;
            });
        }

        function addProductRow() {
            const container = document.getElementById('carrito');
            const template = document.querySelector('.item-producto');
            const newRow = template.cloneNode(true);
            newRow.dataset.index = container.querySelectorAll('.item-producto').length + 1;
            newRow.querySelector('.tipo-producto').selectedIndex = 0;
            newRow.querySelector('.precio-unitario').value = '';
            newRow.querySelector('.cantidad-producto').value = 1;
            newRow.querySelectorAll('.sabor-boton').forEach(button => button.classList.remove('seleccionado'));
            newRow.querySelectorAll('.sabor-boton').forEach(button => button.disabled = button.classList.contains('agotado'));
            const hiddenContainer = newRow.querySelector('.hidden-flavors');
            if (hiddenContainer) hiddenContainer.innerHTML = '';
            container.appendChild(newRow);
            setupProductRow(newRow);
            updateProductNumbers();
        }

        function renderTicket() {
            const rows = document.querySelectorAll('.item-producto');
            const items1 = document.getElementById('ticket-items-1');
            const items2 = document.getElementById('ticket-items-2');
            items1.innerHTML = '';
            items2.innerHTML = '';
            let total = 0;

            rows.forEach(row => {
                const productSelect = row.querySelector('.tipo-producto');
                const quantityInput = row.querySelector('.cantidad-producto');
                const selectedOption = productSelect.selectedOptions[0];
                if (!selectedOption || !selectedOption.value) return;

                const quantity = Math.max(1, parseInt(quantityInput.value, 10) || 1);
                const price = parseFloat(selectedOption.dataset.price) || 0;
                const name = selectedOption.dataset.name || '';
                const subtotal = price * quantity;
                total += subtotal;

                const selectedFlavors = Array.from(row.querySelectorAll('.sabor-boton.seleccionado')).map(btn => btn.dataset.sabor);
                const flavorText = selectedFlavors.length ? ' — ' + sanitizeJS(selectedFlavors.join(' / ')) : '';
                const itemHtml = `
                    <div class="item-ticket">
                        <div class="nombre-item-ticket">${name} x${quantity} ${formatMoneyJS(price)}</div>
                        <div class="sabores-item-ticket">${sanitizeJS(selectedOption.textContent)}${flavorText}</div>
                    </div>`;

                items1.insertAdjacentHTML('beforeend', itemHtml);
                items2.insertAdjacentHTML('beforeend', itemHtml);
            });

            document.getElementById('ticket-total').textContent = formatMoneyJS(total);
            document.getElementById('ticket-total-client').textContent = formatMoneyJS(total);
            const now = new Date();
            document.getElementById('ticket-fecha').textContent = now.toLocaleDateString('es-AR');
            document.getElementById('ticket-fecha-client').textContent = now.toLocaleDateString('es-AR');
            const ticketNumber = Math.floor(Math.random() * 9000) + 1000;
            document.getElementById('ticket-number').textContent = '#' + ticketNumber;
            document.getElementById('ticket-number-client').textContent = '#' + ticketNumber;
            document.getElementById('ticket-pago').textContent = document.getElementById('metodo_pago').value;
            document.getElementById('ticket-pago-client').textContent = document.getElementById('metodo_pago').value;
        }

        function sanitizeJS(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function setPaymentMethod(method) {
            document.getElementById('metodo_pago').value = method;
            document.querySelectorAll('.opcion-pago').forEach(button => {
                button.classList.toggle('pago-activo', button.dataset.payment === method);
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.item-producto').forEach(setupProductRow);
            document.querySelector('.boton-agregar-item').addEventListener('click', addProductRow);
            document.querySelectorAll('.opcion-pago').forEach(button => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    setPaymentMethod(button.dataset.payment);
                });
            });
            document.getElementById('view-ticket').addEventListener('click', (event) => {
                event.preventDefault();
                renderTicket();
                location.hash = '#s-ticket';
            });
            document.getElementById('submit-sale').addEventListener('click', (event) => {
                event.preventDefault();
                const rows = document.querySelectorAll('.item-producto');
                const valid = Array.from(rows).some(row => row.querySelector('.tipo-producto').value);
                if (!valid) {
                    alert('Seleccione al menos un producto antes de registrar la venta.');
                    location.hash = '#s-venta';
                    return;
                }
                document.getElementById('sale-form').submit();
            });
            recalculateTotal();
            setPaymentMethod('EFECTIVO');
            <?php if ($ventaConfirmada): ?>
                location.hash = '#s-conf';
            <?php endif; ?>
        });
    </script>
</body>

</html>
