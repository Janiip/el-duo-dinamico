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
    $cajaAbiertaVenta = false;
    $resultCajaVenta = $conexion->query('SELECT estado FROM caja WHERE fecha = CURDATE() LIMIT 1');
    if ($resultCajaVenta) {
        $filaCajaVenta = $resultCajaVenta->fetch_assoc();
        $cajaAbiertaVenta = $filaCajaVenta && $filaCajaVenta['estado'] === 'ABIERTA';
    }

    if (!$cajaAbiertaVenta) {
        $error = 'La caja está cerrada. Abrí la caja antes de registrar una venta.';
    } else {
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
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_caja_abrir'])) {
    $existeCaja = null;
    $stmtCheck = $conexion->prepare('SELECT id_caja FROM caja WHERE fecha = CURDATE() LIMIT 1');
    if ($stmtCheck) {
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        $existeCaja = $resCheck ? $resCheck->fetch_assoc() : null;
        $stmtCheck->close();
    }

    if ($existeCaja) {
        $error = 'La caja de hoy ya fue abierta.';
    } else {
        // No se pasa monto_inicial: la columna ya tiene DEFAULT 10000.00 en la tabla.
        $stmt = $conexion->prepare('INSERT INTO caja (fecha, fecha_apertura, id_empleado_apertura, estado) VALUES (CURDATE(), NOW(), ?, \'ABIERTA\')');
        if ($stmt) {
            $stmt->bind_param('i', $empleadoId);
            if ($stmt->execute()) {
                $stmt->close();
                header('Location: empleado.php?mensaje=' . urlencode('Caja abierta con $10.000 de inicio.') . '#s-caja');
                exit;
            } else {
                $error = 'Error al abrir la caja: ' . $conexion->error;
                $stmt->close();
            }
        } else {
            $error = 'Error interno al preparar la apertura de caja.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_caja_cerrar'])) {
    $idCaja = intval($_POST['id_caja'] ?? 0);
    $efectivoContado = ($_POST['efectivo_contado'] ?? '') === '' ? -1 : floatval($_POST['efectivo_contado']);
    $observacionesCaja = trim($_POST['observaciones_caja'] ?? '');

    if ($idCaja <= 0) {
        $error = 'No se encontró la caja del día para cerrar.';
    } elseif ($efectivoContado < 0) {
        $error = 'Indicá el efectivo contado en caja.';
    } else {
        $totalEfectivo = 0.0;
        $totalTransferencia = 0.0;
        $cantidadVentasCierre = 0;
        $rowsPagosCierre = fetchRows($conexion, 'SELECT metodo_pago, COUNT(*) AS cantidad, SUM(total_venta) AS total FROM ventas WHERE DATE(fecha) = CURDATE() GROUP BY metodo_pago');
        foreach ($rowsPagosCierre as $r) {
            $cantidadVentasCierre += intval($r['cantidad']);
            if ($r['metodo_pago'] === 'EFECTIVO') {
                $totalEfectivo = floatval($r['total']);
            } elseif ($r['metodo_pago'] === 'TRANSFERENCIA') {
                $totalTransferencia = floatval($r['total']);
            }
        }

        $montoInicialCaja = 10000.00;
        $stmtMonto = $conexion->prepare('SELECT monto_inicial FROM caja WHERE id_caja = ? LIMIT 1');
        if ($stmtMonto) {
            $stmtMonto->bind_param('i', $idCaja);
            $stmtMonto->execute();
            $resMonto = $stmtMonto->get_result();
            $rowMonto = $resMonto ? $resMonto->fetch_assoc() : null;
            $stmtMonto->close();
            if ($rowMonto) {
                $montoInicialCaja = floatval($rowMonto['monto_inicial']);
            }
        }

        $efectivoEsperado = $montoInicialCaja + $totalEfectivo;
        $diferenciaCaja = $efectivoContado - $efectivoEsperado;

        $stmt = $conexion->prepare('UPDATE caja SET fecha_cierre = NOW(), id_empleado_cierre = ?, total_efectivo_ventas = ?, total_transferencia_ventas = ?, cantidad_ventas = ?, efectivo_esperado = ?, efectivo_contado = ?, diferencia = ?, observaciones = ?, estado = \'CERRADA\' WHERE id_caja = ? AND estado = \'ABIERTA\'');
        if ($stmt) {
            $stmt->bind_param('iddidddsi', $empleadoId, $totalEfectivo, $totalTransferencia, $cantidadVentasCierre, $efectivoEsperado, $efectivoContado, $diferenciaCaja, $observacionesCaja, $idCaja);
            if ($stmt->execute()) {
                $stmt->close();
                header('Location: empleado.php?mensaje=' . urlencode('Caja cerrada correctamente.') . '#s-caja');
                exit;
            } else {
                $error = 'Error al cerrar la caja: ' . $conexion->error;
                $stmt->close();
            }
        } else {
            $error = 'Error interno al preparar el cierre de caja.';
        }
    }
}

$sabores = fetchRows($conexion, "SELECT id_sabor AS id, nombre, tipo, precio, stock_actual FROM sabores WHERE estado = 'ACTIVO' ORDER BY tipo, nombre");
$sabores_inactivos = fetchRows($conexion, "SELECT id_sabor AS id, nombre, tipo FROM sabores WHERE estado = 'INACTIVO' ORDER BY tipo, nombre");
$accesorios = fetchRows($conexion, "SELECT id_accesorio AS id, nombre, descripcion, precio, stock_actual FROM accesorios WHERE estado = 'ACTIVO' ORDER BY nombre");
$ventas = fetchRows($conexion, 'SELECT v.id_venta AS id, v.fecha, v.total_venta AS total, v.metodo_pago AS pago, IFNULL(u.nombre_usuario, "Sin empleado") AS empleado FROM ventas v LEFT JOIN usuarios u ON v.id_empleado = u.id_usuario WHERE v.id_empleado = ' . intval($empleadoId) . ' ORDER BY v.fecha DESC LIMIT 20');

$cajaHoy = null;
$resultCajaHoy = $conexion->query('SELECT * FROM caja WHERE fecha = CURDATE() LIMIT 1');
if ($resultCajaHoy) {
    $cajaHoy = $resultCajaHoy->fetch_assoc();
}
$cajaAbierta = $cajaHoy && $cajaHoy['estado'] === 'ABIERTA';
$cajaCerradaHoy = $cajaHoy && $cajaHoy['estado'] === 'CERRADA';

$resumenPagosHoy = ['EFECTIVO' => 0.0, 'TRANSFERENCIA' => 0.0];
$cantidadVentasHoyPago = 0;
$rowsPagosHoy = fetchRows($conexion, "SELECT metodo_pago, COUNT(*) AS cantidad, SUM(total_venta) AS total FROM ventas WHERE DATE(fecha) = CURDATE() GROUP BY metodo_pago");
foreach ($rowsPagosHoy as $r) {
    $resumenPagosHoy[$r['metodo_pago']] = floatval($r['total']);
    $cantidadVentasHoyPago += intval($r['cantidad']);
}
$montoInicialCajaHoy = $cajaHoy ? floatval($cajaHoy['monto_inicial']) : 10000.00;
$efectivoEsperadoHoy = $montoInicialCajaHoy + $resumenPagosHoy['EFECTIVO'];

$empleadoAperturaNombre = '';
$empleadoCierreNombre = '';
if ($cajaHoy) {
    $stmtNombres = $conexion->prepare('SELECT id_usuario, nombre_usuario FROM usuarios WHERE id_usuario IN (?, ?)');
    if ($stmtNombres) {
        $idApertura = intval($cajaHoy['id_empleado_apertura'] ?? 0);
        $idCierre = intval($cajaHoy['id_empleado_cierre'] ?? 0);
        $stmtNombres->bind_param('ii', $idApertura, $idCierre);
        $stmtNombres->execute();
        $resNombres = $stmtNombres->get_result();
        $filasNombres = $resNombres ? $resNombres->fetch_all(MYSQLI_ASSOC) : [];
        $stmtNombres->close();
        foreach ($filasNombres as $u) {
            if (intval($u['id_usuario']) === $idApertura) {
                $empleadoAperturaNombre = $u['nombre_usuario'];
            }
            if (intval($u['id_usuario']) === $idCierre) {
                $empleadoCierreNombre = $u['nombre_usuario'];
            }
        }
    }
}

$cajasHistorial = fetchRows($conexion, 'SELECT c.*, ua.nombre_usuario AS empleado_apertura_nombre, uc.nombre_usuario AS empleado_cierre_nombre FROM caja c LEFT JOIN usuarios ua ON c.id_empleado_apertura = ua.id_usuario LEFT JOIN usuarios uc ON c.id_empleado_cierre = uc.id_usuario ORDER BY c.fecha DESC LIMIT 10');

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
                <a class="boton-empleado boton-empleado-secundario" href="#s-caja">CAJA</a>
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
            <?php if ($cajaAbierta): ?>
            <div class="layout-venta">
                <aside class="panel-sabores-inactivos">
                    <div class="titulo-sabores">SABORES INACTIVOS</div>
                    <?php if (count($sabores_inactivos) > 0): ?>
                        <button type="button" class="boton-ver-inactivos" data-label="Ver sabores inactivos (<?= count($sabores_inactivos) ?>)">Ver sabores inactivos (<?= count($sabores_inactivos) ?>)</button>
                        <div class="sabores-inactivos-lista" style="display:none;">
                            <div class="pestanas-categoria-sabores" style="gap:6px;">
                                <?php foreach ($sabores_inactivos as $s): ?>
                                    <span class="ficha ficha-inactivo" title="INACTIVO">
                                        <?= sanitize($s['nombre']) ?> (<?= sanitize($s['tipo']) ?>)
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="texto-inactivos">No hay sabores inactivos.</div>
                    <?php endif; ?>
                </aside>
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
            </div>

            <div class="botones-venta">
                <button type="button" id="view-ticket" class="boton boton-agregar boton-confirmar">VER TICKET</button>
                <a class="boton boton-secundario" href="#s-emp">CANCELAR</a>
            </div>
            <?php else: ?>
            <div class="tarjeta-panel" style="max-width:520px; margin:0 auto; text-align:center;">
                <div class="titulo-panel">🔒 CAJA CERRADA</div>
                <p style="font-weight:700; margin-bottom:16px;">No podés registrar ventas hasta que se abra la caja del día.</p>
                <a class="boton boton-agregar" href="#s-caja">IR A CAJA</a>
            </div>
            <?php endif; ?>
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
                <a class="boton boton-secundario" href="#s-emp">CANCELAR VENTA</a>
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
                    <button type="button" class="boton boton-imprimir" id="reimprimir-conf" title="Reimprimir ticket del cliente">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                        REIMPRIMIR TICKET
                    </button>
                    <a class="boton boton-secundario" href="#s-historial">VER MIS VENTAS</a>
                </div>
            </div>
        </div>
    </div>

    <div id="s-caja" class="pantalla">
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
            <div class="titulo-pagina">CAJA DIARIA</div>

            <?php if ($mensaje): ?>
                <div class="mensaje-exito" style="margin-bottom:16px; padding:12px 14px; background:#e8f5e9; color:#1b5e20; border-radius:12px;"><?= sanitize($mensaje) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="mensaje-error" style="margin-bottom:16px; padding:12px 14px; background:#ffebee; color:#b71c1c; border-radius:12px;"><?= sanitize($error) ?></div>
            <?php endif; ?>

            <div class="tarjeta-panel" style="margin-bottom:16px;">
                <div class="titulo-panel">RESUMEN DE HOY (<?= sanitize(date('d/m/Y')) ?>)</div>
                <div class="estadistica">💵 Ventas en efectivo: <span><?= formatMoney($resumenPagosHoy['EFECTIVO']) ?></span></div>
                <div class="estadistica">💳 Ventas en transferencia: <span><?= formatMoney($resumenPagosHoy['TRANSFERENCIA']) ?></span></div>
                <div class="estadistica">🧾 Cantidad de ventas hoy: <span><?= sanitize($cantidadVentasHoyPago) ?></span></div>
                <div class="estadistica">📊 Total ventas hoy: <span><?= formatMoney($resumenPagosHoy['EFECTIVO'] + $resumenPagosHoy['TRANSFERENCIA']) ?></span></div>
            </div>

            <?php if (!$cajaHoy): ?>
                <div class="tarjeta-panel">
                    <div class="titulo-panel">ABRIR CAJA</div>
                    <p style="font-weight:700; color:#555;">La caja de hoy todavía no fue abierta. Siempre se inicia con <?= formatMoney(10000) ?> en efectivo.</p>
                    <form method="post" style="display:grid; gap:12px; max-width:420px;">
                        <input type="hidden" name="submit_caja_abrir" value="1">
                        <div>
                            <button type="submit" class="boton boton-agregar">🔓 ABRIR CAJA CON <?= formatMoney(10000) ?></button>
                        </div>
                    </form>
                </div>
            <?php elseif ($cajaAbierta): ?>
                <div class="tarjeta-panel" style="margin-bottom:16px;">
                    <div class="titulo-panel">CAJA ABIERTA 🟢</div>
                    <div class="estadistica">🕐 Apertura: <span><?= sanitize(date('d/m/Y H:i', strtotime($cajaHoy['fecha_apertura']))) ?></span></div>
                    <div class="estadistica">👤 Abrió: <span><?= sanitize($empleadoAperturaNombre ?: 'Sin datos') ?></span></div>
                    <div class="estadistica">💰 Monto inicial: <span><?= formatMoney($cajaHoy['monto_inicial']) ?></span></div>
                    <div class="estadistica">🧮 Efectivo esperado ahora: <span><?= formatMoney($efectivoEsperadoHoy) ?></span></div>
                </div>

                <div class="tarjeta-panel">
                    <div class="titulo-panel">CERRAR CAJA</div>
                    <form method="post" id="form-cerrar-caja" style="display:grid; gap:12px; max-width:420px;">
                        <input type="hidden" name="submit_caja_cerrar" value="1">
                        <input type="hidden" name="id_caja" value="<?= sanitize($cajaHoy['id_caja']) ?>">
                        <label style="display:block;">
                            <span>Efectivo contado en caja</span><br>
                            <input type="number" step="0.01" min="0" name="efectivo_contado" required class="campo-caja">
                        </label>
                        <label style="display:block;">
                            <span>Observaciones (opcional)</span><br>
                            <textarea name="observaciones_caja" rows="2" class="campo-caja"></textarea>
                        </label>
                        <div>
                            <button type="submit" class="boton boton-agregar">🔒 CERRAR CAJA</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="tarjeta-panel">
                    <div class="titulo-panel">CAJA CERRADA 🔴</div>
                    <div class="estadistica">🕐 Apertura: <span><?= sanitize(date('d/m/Y H:i', strtotime($cajaHoy['fecha_apertura']))) ?></span> por <span><?= sanitize($empleadoAperturaNombre ?: 'Sin datos') ?></span></div>
                    <div class="estadistica">🕐 Cierre: <span><?= sanitize(date('d/m/Y H:i', strtotime($cajaHoy['fecha_cierre']))) ?></span> por <span><?= sanitize($empleadoCierreNombre ?: 'Sin datos') ?></span></div>
                    <div class="estadistica">💰 Monto inicial: <span><?= formatMoney($cajaHoy['monto_inicial']) ?></span></div>
                    <div class="estadistica">💵 Total efectivo vendido: <span><?= formatMoney($cajaHoy['total_efectivo_ventas']) ?></span></div>
                    <div class="estadistica">💳 Total transferencia vendido: <span><?= formatMoney($cajaHoy['total_transferencia_ventas']) ?></span></div>
                    <div class="estadistica">🧮 Efectivo esperado: <span><?= formatMoney($cajaHoy['efectivo_esperado']) ?></span></div>
                    <div class="estadistica">🧾 Efectivo contado: <span><?= formatMoney($cajaHoy['efectivo_contado']) ?></span></div>
                    <div class="estadistica">⚖️ Diferencia: <span><?= formatMoney($cajaHoy['diferencia']) ?></span></div>
                    <?php if (trim((string) $cajaHoy['observaciones']) !== ''): ?>
                        <div class="estadistica">📝 Observaciones: <span><?= sanitize($cajaHoy['observaciones']) ?></span></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="titulo-pagina" style="margin-top:24px;">ÚLTIMAS CAJAS</div>
            <div class="envoltorio-tabla-oscuro">
                <table>
                    <thead>
                        <tr>
                            <th>FECHA</th>
                            <th>APERTURA</th>
                            <th>CIERRE</th>
                            <th>INICIAL</th>
                            <th>EFECTIVO</th>
                            <th>TRANSF.</th>
                            <th>ESPERADO</th>
                            <th>CONTADO</th>
                            <th>DIF.</th>
                            <th>ESTADO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($cajasHistorial) === 0): ?>
                            <tr>
                                <td colspan="10" style="text-align:center; padding:18px 0;">No hay cajas registradas aun.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cajasHistorial as $c): ?>
                                <tr>
                                    <td><?= sanitize(date('d/m/Y', strtotime($c['fecha']))) ?></td>
                                    <td><?= sanitize($c['empleado_apertura_nombre'] ?: '-') ?></td>
                                    <td><?= sanitize($c['empleado_cierre_nombre'] ?: '-') ?></td>
                                    <td><?= formatMoney($c['monto_inicial']) ?></td>
                                    <td><?= formatMoney($c['total_efectivo_ventas']) ?></td>
                                    <td><?= formatMoney($c['total_transferencia_ventas']) ?></td>
                                    <td><?= $c['efectivo_esperado'] !== null ? formatMoney($c['efectivo_esperado']) : '-' ?></td>
                                    <td><?= $c['efectivo_contado'] !== null ? formatMoney($c['efectivo_contado']) : '-' ?></td>
                                    <td><?= $c['diferencia'] !== null ? formatMoney($c['diferencia']) : '-' ?></td>
                                    <td><?= sanitize($c['estado']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
                <div class="envoltorio-tickets">
                    <div class="caja-ticket">
                        <div class="etiqueta-ticket etiqueta-empleado">COPIA EMPLEADO</div>
                        <div class="titulo-ticket">DAJANA HELADOS</div>
                        <div class="numero-ticket">Venta del día <span>#<?= sanitize($ventaDetalle['id']) ?></span></div>
                        <div class="fila-ticket"><span>Empleado:</span><span><?= sanitize($ventaDetalle['empleado']) ?></span></div>
                        <div class="fila-ticket"><span>Fecha:</span><span><?= sanitize(date('d/m/Y H:i', strtotime($ventaDetalle['fecha']))) ?></span></div>
                        <div id="detalle-items-1">
                            <?php if (count($ventaDetalleItems) === 0): ?>
                                <div class="item-ticket">
                                    <div class="nombre-item-ticket">No hay items registrados para esta venta.</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($ventaDetalleItems as $item): ?>
                                    <?php
                                        $nombreProducto = $item['accesorio_nombre'] ?: 'Producto';
                                        $detalleProducto = $item['sabores'] ? $item['sabores'] : ($item['accesorio_descripcion'] ?: '');
                                    ?>
                                    <div class="item-ticket">
                                        <div class="nombre-item-ticket"><?= sanitize($nombreProducto) ?> x<?= sanitize($item['cantidad']) ?> <?= formatMoney($item['precio_unitario']) ?></div>
                                        <?php if (trim($detalleProducto) !== ''): ?>
                                            <div class="sabores-item-ticket"><?= sanitize($detalleProducto) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="total-ticket">TOTAL: <span><?= formatMoney($ventaDetalle['total']) ?></span></div>
                        <div class="fila-ticket" style="margin-top:5px;"><span>Pago:</span><span><?= sanitize($ventaDetalle['pago']) ?></span></div>
                    </div>
                    <div class="caja-ticket">
                        <div class="etiqueta-ticket etiqueta-cliente">TICKET CLIENTE</div>
                        <div class="titulo-ticket">DAJANA HELADOS</div>
                        <div class="numero-ticket">Orden <span>#<?= sanitize($ventaDetalle['id']) ?></span></div>
                        <div class="fila-ticket"><span>Fecha:</span><span><?= sanitize(date('d/m/Y H:i', strtotime($ventaDetalle['fecha']))) ?></span></div>
                        <div id="detalle-items-2">
                            <?php if (count($ventaDetalleItems) === 0): ?>
                                <div class="item-ticket">
                                    <div class="nombre-item-ticket">No hay items registrados para esta venta.</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($ventaDetalleItems as $item): ?>
                                    <?php
                                        $nombreProducto = $item['accesorio_nombre'] ?: 'Producto';
                                        $detalleProducto = $item['sabores'] ? $item['sabores'] : ($item['accesorio_descripcion'] ?: '');
                                    ?>
                                    <div class="item-ticket">
                                        <div class="nombre-item-ticket"><?= sanitize($nombreProducto) ?> x<?= sanitize($item['cantidad']) ?> <?= formatMoney($item['precio_unitario']) ?></div>
                                        <?php if (trim($detalleProducto) !== ''): ?>
                                            <div class="sabores-item-ticket"><?= sanitize($detalleProducto) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="total-ticket">TOTAL: <span><?= formatMoney($ventaDetalle['total']) ?></span></div>
                        <div class="fila-ticket" style="margin-top:5px;"><span>Pago:</span><span><?= sanitize($ventaDetalle['pago']) ?></span></div>
                    </div>
                </div>
                <div class="acciones-ticket" style="margin-top:18px;">
                    <button type="button" class="boton boton-imprimir" id="reimprimir-detalle"
                        data-orden="<?= sanitize($ventaDetalle['id']) ?>"
                        data-fecha="<?= sanitize(date('d/m/Y H:i', strtotime($ventaDetalle['fecha']))) ?>"
                        data-total="<?= sanitize(formatMoney($ventaDetalle['total'])) ?>"
                        data-pago="<?= sanitize($ventaDetalle['pago']) ?>"
                        title="Reimprimir ticket del cliente">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                        REIMPRIMIR TICKET CLIENTE
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── OVERLAY DE IMPRESIÓN (solo visible al imprimir) ── -->
    <div id="print-ticket-overlay" style="display:none;">
        <div class="ticket-print-wrap">
            <div class="tp-title">DAJANA HELADOS</div>
            <div class="tp-sub" id="pt-orden">Orden #—</div>
            <div class="tp-sub" id="pt-fecha">—</div>
            <hr class="tp-sep">
            <div id="pt-items"><!-- items inyectados por JS --></div>
            <hr class="tp-sep">
            <div class="tp-total">TOTAL: <span id="pt-total">$0</span></div>
            <div class="tp-row" style="margin-top:2mm;"><span>Pago:</span><span id="pt-pago">EFECTIVO</span></div>
            <hr class="tp-sep">
            <div class="tp-footer">¡Gracias por su compra!<br>Dajana Helados</div>
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