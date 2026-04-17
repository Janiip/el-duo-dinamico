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
    $saboresPorProducto = $_POST['sabores'] ?? [];
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
            'index' => $index,
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
                $stmtDetalle = $conexion->prepare('INSERT INTO detalle_ventas (id_venta, id_sabor, id_accesorio, cantidad, precio_unitario, subtotal, sabores) VALUES (?, NULL, ?, ?, ?, ?, ?)');
                $stmtUpdate = $conexion->prepare('UPDATE accesorios SET stock_actual = stock_actual - ? WHERE id_accesorio = ?');

                if (!$stmtDetalle || !$stmtUpdate) {
                    $conexion->rollback();
                    $error = 'Error interno al preparar los detalles de la venta.';
                } else {
                    foreach ($items as $item) {
                        $flavorsKey = strval((intval($item['index'] ?? 0)) + 1);
                        $saboresElegidos = $saboresPorProducto[$flavorsKey] ?? [];
                        if (!is_array($saboresElegidos)) {
                            $saboresElegidos = [];
                        }
                        $saboresElegidos = array_map('trim', $saboresElegidos);
                        $saboresElegidos = array_values(array_filter($saboresElegidos, function ($v) {
                            return $v !== '';
                        }));
                        $saboresTexto = count($saboresElegidos) ? implode(' / ', $saboresElegidos) : null;
                        if ($saboresTexto !== null && strlen($saboresTexto) > 500) {
                            $saboresTexto = substr($saboresTexto, 0, 500);
                        }
                        $stmtDetalle->bind_param('iiidds', $ventaId, $item['id'], $item['cantidad'], $item['precio'], $item['subtotal'], $saboresTexto);
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

$ventaDetalle = null;
$ventaDetalleItems = [];
$ventaDetalleId = intval($_GET['venta'] ?? 0);
if ($ventaDetalleId > 0) {
    $stmt = $conexion->prepare('SELECT v.id_venta AS id, v.fecha, v.total_venta AS total, v.metodo_pago AS pago, IFNULL(u.nombre_usuario, "Sin empleado") AS empleado FROM ventas v LEFT JOIN usuarios u ON v.id_empleado = u.id_usuario WHERE v.id_venta = ? AND v.id_empleado = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('ii', $ventaDetalleId, $empleadoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ventaDetalle = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }

    $stmtItems = $conexion->prepare('SELECT dv.cantidad, dv.precio_unitario, dv.subtotal, dv.sabores, a.nombre AS accesorio_nombre, a.descripcion AS accesorio_descripcion FROM detalle_ventas dv LEFT JOIN accesorios a ON dv.id_accesorio = a.id_accesorio WHERE dv.id_venta = ?');
    if ($stmtItems) {
        $stmtItems->bind_param('i', $ventaDetalleId);
        $stmtItems->execute();
        $result = $stmtItems->get_result();
        $ventaDetalleItems = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmtItems->close();
    }
}
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
                                    <td><a class="boton-en-linea" href="empleado.php?venta=<?= sanitize($venta['id']) ?>#s-venta-detalle">👁️</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="s-venta-detalle" class="pantalla">
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
            <?php if (!$ventaDetalleId): ?>
                <div style="padding:12px 14px; background:#fff; border-radius:14px; border:1px solid #ddd; font-weight:700;">
                    Seleccioná una venta desde el historial para ver su ticket.
                </div>
            <?php elseif (!$ventaDetalle): ?>
                <div style="padding:12px 14px; background:#fff7f7; border-radius:14px; border:1px solid #f0c7c7; color:#8a1c1c; font-weight:800;">
                    No se encontró la venta #<?= sanitize($ventaDetalleId) ?> (o no te pertenece).
                </div>
            <?php else: ?>
                <div class="tarjeta-detalle">
                    <div class="informacion-detalle">Venta del día <span>#<?= sanitize($ventaDetalle['id']) ?></span></div>
                    <div class="informacion-detalle">Fecha: <span><?= sanitize(date('d/m/Y H:i', strtotime($ventaDetalle['fecha']))) ?></span></div>
                    <div class="informacion-detalle">Empleado: <span><?= sanitize($ventaDetalle['empleado']) ?></span></div>
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
                                <?php if (count($ventaDetalleItems) === 0): ?>
                                    <tr>
                                        <td colspan="4" style="text-align:center; padding:18px 0;">
                                            No hay items registrados para esta venta.<br>
                                            <small style="color:#8a1c1c; font-weight:800;">
                                                Se registró el total, pero no se guardó el detalle de productos en la base de datos.
                                            </small>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ventaDetalleItems as $item): ?>
                                        <?php
                                            $nombreProducto = $item['accesorio_nombre'] ?: 'Producto';
                                            $detalleProducto = $item['sabores'] ? $item['sabores'] : ($item['accesorio_descripcion'] ?: '');
                                        ?>
                                        <tr>
                                            <td style="text-align:left;padding:8px 9px;">
                                                <?= sanitize($nombreProducto) ?>
                                                <?php if (trim($detalleProducto) !== ''): ?>
                                                    <br><small style="color:#888;font-style:italic;"><?= sanitize($detalleProducto) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= sanitize($item['cantidad']) ?></td>
                                            <td><?= formatMoney($item['precio_unitario']) ?></td>
                                            <td><?= formatMoney($item['subtotal']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="informacion-detalle" style="font-size:1rem;">Total: <span><?= formatMoney($ventaDetalle['total']) ?></span></div>
                    <div class="informacion-detalle">Pago: <span><?= sanitize($ventaDetalle['pago']) ?></span></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        window.EMPLEADO_BOOT = {
            accesoriosData: <?= json_encode($accesorios) ?>,
            LOW_STOCK_THRESHOLD: 3,
            ventaConfirmada: <?= $ventaConfirmada ? 'true' : 'false' ?>
        };
    </script>
    <script src="empleado.js"></script>
</body>

</html>
