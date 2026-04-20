<?php 
include ('conexion.php');

$mensaje = '';
$error = '';

if (isset($_GET['mensaje']) && $_GET['mensaje'] !== '') {
    $mensaje = $_GET['mensaje'];
}

function sanitize($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatMoney($value) {
    return '$' . number_format((float) $value, 0, ',', '.');
}

function badgeStock($stock) {
    if ($stock <= 0) {
        return '<span class="badge insignia-sin-stock">SIN STOCK</span>';
    }
    if ($stock < 3) {
        return '<span class="badge insignia-bajo">BAJO ⚠️</span>';
    }
    return '<span class="badge insignia-normal">NORMAL ✓</span>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_sabor'])) {
        $nombre = trim($_POST['nombre_sabor'] ?? '');
        $tipo = trim($_POST['tipo_sabor'] ?? '');
        $precio = floatval($_POST['precio_sabor'] ?? 0);
        $stock = intval($_POST['stock_sabor'] ?? 0);

        if ($nombre === '' || $tipo === '' || $precio <= 0) {
            $error = 'Complete todos los campos del sabor y use un precio válido.';
        } else {
            $stmt = $conexion->prepare('INSERT INTO sabores (nombre, tipo, precio, stock_actual, estado) VALUES (?, ?, ?, ?, \'ACTIVO\')');
            if ($stmt) {
                $stmt->bind_param('ssdi', $nombre, $tipo, $precio, $stock);
                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: admin.php?mensaje=' . urlencode('Sabor agregado correctamente.'));
                    exit;
                } else {
                    $error = 'Error al guardar el sabor: ' . $conexion->error;
                    $stmt->close();
                }
            } else {
                $error = 'Error interno al preparar la consulta de sabores.';
            }
        }
    } elseif (isset($_POST['submit_accesorio'])) {
        $nombre = trim($_POST['nombre_accesorio'] ?? '');
        $descripcion = trim($_POST['descripcion_accesorio'] ?? '');
        $precio = floatval($_POST['precio_accesorio'] ?? 0);
        $stock = intval($_POST['stock_accesorio'] ?? 0);

        if ($nombre === '' || $descripcion === '' || $precio <= 0) {
            $error = 'Complete todos los campos del accesorio y use un precio válido.';
        } else {
            $stmt = $conexion->prepare('INSERT INTO accesorios (nombre, descripcion, precio, stock_actual, estado) VALUES (?, ?, ?, ?, \'ACTIVO\')');
            if ($stmt) {
                $stmt->bind_param('ssdi', $nombre, $descripcion, $precio, $stock);
                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: admin.php?mensaje=' . urlencode('Accesorio agregado correctamente.'));
                    exit;
                } else {
                    $error = 'Error al guardar el accesorio: ' . $conexion->error;
                    $stmt->close();
                }
            } else {
                $error = 'Error interno al preparar la consulta de accesorios.';
            }
        }
    } elseif (isset($_POST['submit_accesorio_edit'])) {
        $id = intval($_POST['accesorio_id'] ?? 0);
        $nombre = trim($_POST['nombre_accesorio_edit'] ?? '');
        $descripcion = trim($_POST['descripcion_accesorio_edit'] ?? '');
        $precio = floatval($_POST['precio_accesorio_edit'] ?? 0);
        $stock = intval($_POST['stock_accesorio_edit'] ?? 0);

        if ($id <= 0 || $nombre === '' || $descripcion === '' || $precio <= 0 || $stock < 0) {
            $error = 'Complete correctamente el nombre, descripción, precio y stock del accesorio.';
        } else {
            $stmt = $conexion->prepare('UPDATE accesorios SET nombre = ?, descripcion = ?, precio = ?, stock_actual = ? WHERE id_accesorio = ? AND estado = \'ACTIVO\'');
            if ($stmt) {
                $stmt->bind_param('ssdii', $nombre, $descripcion, $precio, $stock, $id);
                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: admin.php?mensaje=' . urlencode('Accesorio actualizado correctamente.'));
                    exit;
                } else {
                    $error = 'Error al actualizar el accesorio: ' . $conexion->error;
                    $stmt->close();
                }
            } else {
                $error = 'Error interno al preparar la actualización del accesorio.';
            }
        }
    } elseif (isset($_POST['submit_accesorio_delete'])) {
        $id = intval($_POST['accesorio_id_delete'] ?? 0);

        if ($id <= 0) {
            $error = 'No se indicó el accesorio a eliminar.';
        } else {
            $stmt = $conexion->prepare('UPDATE accesorios SET estado = \'INACTIVO\' WHERE id_accesorio = ?');
            if ($stmt) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: admin.php?mensaje=' . urlencode('Accesorio eliminado correctamente.'));
                    exit;
                } else {
                    $error = 'Error al eliminar el accesorio: ' . $conexion->error;
                    $stmt->close();
                }
            } else {
                $error = 'Error interno al preparar la eliminación del accesorio.';
            }
        }
    } elseif (isset($_POST['submit_accesorio_activate'])) {
        $id = intval($_POST['accesorio_id_activate'] ?? 0);

        if ($id <= 0) {
            $error = 'No se indicó el accesorio a activar.';
        } else {
            $stmt = $conexion->prepare('UPDATE accesorios SET estado = \'ACTIVO\' WHERE id_accesorio = ?');
            if ($stmt) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: admin.php?mensaje=' . urlencode('Accesorio activado correctamente.'));
                    exit;
                } else {
                    $error = 'Error al activar el accesorio: ' . $conexion->error;
                    $stmt->close();
                }
            } else {
                $error = 'Error interno al preparar la activación del accesorio.';
            }
        }
    } elseif (isset($_POST['submit_section_rename'])) {
        $oldSection = trim($_POST['old_section'] ?? '');
        $newSection = trim($_POST['new_section'] ?? '');

        if ($oldSection === '' || $newSection === '') {
            $error = 'Complete el nombre antiguo y el nuevo de la sección.';
        } else {
            $stmt = $conexion->prepare('UPDATE sabores SET tipo = ? WHERE tipo = ? AND estado = \'ACTIVO\'');
            if ($stmt) {
                $stmt->bind_param('ss', $newSection, $oldSection);
                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: admin.php?mensaje=' . urlencode('Sección renombrada correctamente.'));
                    exit;
                } else {
                    $error = 'Error al renombrar la sección: ' . $conexion->error;
                    $stmt->close();
                }
            } else {
                $error = 'Error interno al preparar el renombrado de sección.';
            }
        }
    } elseif (isset($_POST['submit_section_delete'])) {
        $oldSection = trim($_POST['section_delete'] ?? '');

        if ($oldSection === '') {
            $error = 'Seleccione una sección para eliminar.';
        } else {
            $stmt = $conexion->prepare('UPDATE sabores SET estado = \'INACTIVO\' WHERE tipo = ? AND estado = \'ACTIVO\'');
            if ($stmt) {
                $stmt->bind_param('s', $oldSection);
                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: admin.php?mensaje=' . urlencode('Sección eliminada correctamente.'));
                    exit;
                } else {
                    $error = 'Error al eliminar la sección: ' . $conexion->error;
                    $stmt->close();
                }
            } else {
                $error = 'Error interno al preparar la eliminación de sección.';
            }
        }
    } elseif (isset($_POST['submit_sabor_edit'])) {
        $id = intval($_POST['sabor_id'] ?? 0);
        $nombre = trim($_POST['nombre_sabor_edit'] ?? '');
        $tipo = trim($_POST['tipo_sabor_edit'] ?? '');
        $precio = floatval($_POST['precio_sabor_edit'] ?? 0);
        $stock = floatval($_POST['stock_sabor_edit'] ?? 0);

        if ($id <= 0 || $nombre === '' || $tipo === '' || $precio <= 0 || $stock < 0) {
            $error = 'Complete correctamente el nombre, tipo, precio y stock del sabor.';
        } else {
            $stmt = $conexion->prepare('UPDATE sabores SET nombre = ?, tipo = ?, precio = ?, stock_actual = ? WHERE id_sabor = ? AND estado = \'ACTIVO\'');
            if ($stmt) {
                $stmt->bind_param('ssdii', $nombre, $tipo, $precio, $stock, $id);
                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: admin.php?mensaje=' . urlencode('Sabor actualizado correctamente.'));
                    exit;
                } else {
                    $error = 'Error al actualizar el sabor: ' . $conexion->error;
                    $stmt->close();
                }
            } else {
                $error = 'Error interno al preparar la actualización del sabor.';
            }
        }
    } elseif (isset($_POST['submit_sabor_delete'])) {
        $id = intval($_POST['sabor_id_delete'] ?? 0);

        if ($id <= 0) {
            $error = 'No se indicó el sabor a eliminar.';
        } else {
            $stmt = $conexion->prepare('UPDATE sabores SET estado = \'INACTIVO\' WHERE id_sabor = ?');
            if ($stmt) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: admin.php?mensaje=' . urlencode('Sabor eliminado correctamente.'));
                    exit;
                } else {
                    $error = 'Error al eliminar el sabor: ' . $conexion->error;
                    $stmt->close();
                }
            } else {
                $error = 'Error interno al preparar la eliminación del sabor.';
            }
        }
    } elseif (isset($_POST['submit_sabor_activate'])) {
        $id = intval($_POST['sabor_id_activate'] ?? 0);

        if ($id <= 0) {
            $error = 'No se indicó el sabor a activar.';
        } else {
            $stmt = $conexion->prepare('UPDATE sabores SET estado = \'ACTIVO\' WHERE id_sabor = ?');
            if ($stmt) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: admin.php?mensaje=' . urlencode('Sabor activado correctamente.'));
                    exit;
                } else {
                    $error = 'Error al activar el sabor: ' . $conexion->error;
                    $stmt->close();
                }
            } else {
                $error = 'Error interno al preparar la activación del sabor.';
            }
        }
    } elseif (isset($_POST['submit_stock_quick'])) {
        $kind = $_POST['stock_kind'] ?? '';
        $id = intval($_POST['stock_item_id'] ?? 0);

        if ($kind === 'sabor') {
            $stock = floatval($_POST['stock_cantidad'] ?? -1);
            if ($id <= 0 || $stock < 0) {
                $error = 'Indicá un stock válido para el sabor.';
            } else {
                $stmt = $conexion->prepare('UPDATE sabores SET stock_actual = ? WHERE id_sabor = ? AND estado = \'ACTIVO\'');
                if ($stmt) {
                    $stmt->bind_param('di', $stock, $id);
                    if ($stmt->execute()) {
                        $stmt->close();
                        header('Location: admin.php?mensaje=' . urlencode('Stock del sabor actualizado.') . '&focus=stock');
                        exit;
                    } else {
                        $error = 'Error al actualizar el stock del sabor: ' . $conexion->error;
                        $stmt->close();
                    }
                } else {
                    $error = 'Error interno al preparar la actualización de stock (sabor).';
                }
            }
        } elseif ($kind === 'accesorio') {
            $stock = intval($_POST['stock_cantidad'] ?? -1);
            if ($id <= 0 || $stock < 0) {
                $error = 'Indicá un stock válido para el accesorio.';
            } else {
                $stmt = $conexion->prepare('UPDATE accesorios SET stock_actual = ? WHERE id_accesorio = ? AND estado = \'ACTIVO\'');
                if ($stmt) {
                    $stmt->bind_param('ii', $stock, $id);
                    if ($stmt->execute()) {
                        $stmt->close();
                        header('Location: admin.php?mensaje=' . urlencode('Stock del accesorio actualizado.') . '&focus=stock');
                        exit;
                    } else {
                        $error = 'Error al actualizar el stock del accesorio: ' . $conexion->error;
                        $stmt->close();
                    }
                } else {
                    $error = 'Error interno al preparar la actualización de stock (accesorio).';
                }
            }
        } else {
            $error = 'Tipo de producto no válido para actualizar stock.';
        }
    }
}

function fetchRows($conexion, $sql) {
    $result = $conexion->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

$sabores = fetchRows($conexion, 'SELECT id_sabor AS id, nombre, tipo, precio, stock_actual FROM sabores WHERE estado = \'ACTIVO\' ORDER BY tipo, nombre');
$sabores_inactivos = fetchRows($conexion, 'SELECT id_sabor AS id, nombre, tipo, precio, stock_actual FROM sabores WHERE estado = \'INACTIVO\' ORDER BY tipo, nombre');
$sabores_categorias = fetchRows($conexion, 'SELECT DISTINCT tipo FROM sabores WHERE estado = \'ACTIVO\' ORDER BY tipo');
$accesorios = fetchRows($conexion, 'SELECT id_accesorio AS id, nombre, descripcion, precio, stock_actual FROM accesorios WHERE estado = \'ACTIVO\' ORDER BY nombre');
$accesorios_inactivos = fetchRows($conexion, 'SELECT id_accesorio AS id, nombre, descripcion, precio, stock_actual FROM accesorios WHERE estado = \'INACTIVO\' ORDER BY nombre');
$ventas = fetchRows($conexion, 'SELECT v.id_venta AS id, v.fecha, v.total_venta AS total, v.metodo_pago AS pago, IFNULL(u.nombre_usuario, "Sin empleado") AS empleado FROM ventas v LEFT JOIN usuarios u ON v.id_empleado = u.id_usuario ORDER BY v.fecha DESC LIMIT 20');

$ventaDetalle = null;
$ventaDetalleItems = [];
$ventaDetalleId = intval($_GET['venta'] ?? 0);
if ($ventaDetalleId > 0) {
    $stmt = $conexion->prepare('SELECT v.id_venta AS id, v.fecha, v.total_venta AS total, v.metodo_pago AS pago, IFNULL(u.nombre_usuario, "Sin empleado") AS empleado FROM ventas v LEFT JOIN usuarios u ON v.id_empleado = u.id_usuario WHERE v.id_venta = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $ventaDetalleId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ventaDetalle = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }

    $stmtItems = $conexion->prepare('SELECT dv.cantidad, dv.precio_unitario, dv.subtotal, dv.sabores, a.nombre AS accesorio_nombre, a.descripcion AS accesorio_descripcion, s.nombre AS sabor_nombre, s.tipo AS sabor_tipo FROM detalle_ventas dv LEFT JOIN accesorios a ON dv.id_accesorio = a.id_accesorio LEFT JOIN sabores s ON dv.id_sabor = s.id_sabor WHERE dv.id_venta = ?');
    if ($stmtItems) {
        $stmtItems->bind_param('i', $ventaDetalleId);
        $stmtItems->execute();
        $result = $stmtItems->get_result();
        $ventaDetalleItems = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmtItems->close();
    }
}

$stockBajo = 0;
$sinStock = 0;
foreach ($sabores as $s) {
    $stock = floatval($s['stock_actual']);
    if ($stock <= 0) {
        $sinStock++;
    } elseif ($stock < 3) {
        $stockBajo++;
    }
}

$totalVentasRegistradas = count($ventas);
$numeroUltimaVenta = $ventas[0]['id'] ?? 0;
$ventasDelDia = 0;
$transaccionesHoy = 0;
foreach ($ventas as $v) {
    $fechaVenta = substr($v['fecha'], 0, 10);
    if ($fechaVenta === date('Y-m-d')) {
        $ventasDelDia += floatval($v['total']);
        $transaccionesHoy++;
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dajana helados / Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>

<body>

    <div id="s-panel" class="pantalla">
        <div class="encabezado">
            <div class="logo">
                <div class="logo-nombre"><img src="img/dajana-logo.png" alt="dajana"></div>
                <div class="logo-subtitulo">helados</div>
            </div>
            <div class="botones-encabezado">
                <div class="rol-encabezado">ADMIN</div>
                <a class="boton boton-ventas" href="#s-historial">VENTAS</a>
                <a class="boton boton-cerrar" href="index.php">CERRAR SESIÓN</a>
            </div>
        </div>
        <div class="contenido">
            <div class="pestanas-navegacion">
                <a class="pestana-navegacion" href="#s-sabores">SABORES</a>
                <a class="pestana-navegacion" href="#s-accesorios">ACCESORIOS</a>
                <a class="pestana-navegacion" href="#s-stock">STOCK</a>
                <a class="pestana-navegacion" href="#s-historial">HISTORIAL</a>
            </div>
            <div class="tarjeta-panel">
                <div class="titulo-panel">PANEL DE CONTROL</div>
                <?php if ($mensaje): ?>
                    <div style="color: #1b5e20; background:#e8f5e9; padding:10px; border-radius:8px; margin:10px 0;">
                        <?= sanitize($mensaje) ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div style="color: #b71c1c; background:#ffebee; padding:10px; border-radius:8px; margin:10px 0;">
                        <?= sanitize($error) ?>
                    </div>
                <?php endif; ?>
                <div class="estadistica">💰 Ventas del día: <span><?= formatMoney($ventasDelDia) ?></span></div>
                <div class="estadistica">🧾 Transacciones hoy: <span><?= sanitize($transaccionesHoy) ?></span></div>
                <div class="estadistica">🔢 Última venta del día: <span>#<?= sanitize($numeroUltimaVenta) ?></span></div>
                <div class="estadistica">⚠️ Sabores stock bajo (&lt;3L): <span><?= sanitize($stockBajo) ?></span></div>
                <div class="estadistica">❌ Sabores sin stock: <span><?= sanitize($sinStock) ?></span></div>
                <div class="estadistica">🍧❌ Sabores inactivos: <span><?= sanitize(count($sabores_inactivos)) ?></span></div>
                <div class="estadistica">📦 Total ventas registradas: <span><?= sanitize($totalVentasRegistradas) ?></span></div>
            </div>
        </div>
    </div>

    <div id="s-sabores" class="pantalla">
        <div class="encabezado">
            <div class="logo">
                <div class="logo-nombre"><img src="img/dajana-logo.png" alt="dajana"></div>
                <div class="logo-subtitulo">helados</div>
            </div>
            <div class="botones-encabezado">
                <div class="rol-encabezado">ADMIN</div>
                <a class="boton boton-volver" href="#s-panel">VOLVER</a>
            </div>
        </div>
        <div class="contenido">
            <div class="barra">
                <div class="titulo-pagina flex-1 no-mb">SABORES</div>
                <button id="edit-sections-button" type="button" class="boton boton-secundario boton-pequeno">⚙️ EDITAR SECCIONES</button>
                <button type="button" id="open-add-sabor-modal" class="boton boton-agregar boton-pequeno">+ Agregar SABOR</button>
            </div>

            <div id="section-manager" class="modal-overlay" style="display:none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>Administrar secciones de sabores</div>
                        <button type="button" id="close-section-manager" class="boton boton-secundario boton-pequeno">Cerrar</button>
                    </div>
                    <?php if (count($sabores_categorias) === 0): ?>
                        <div style="padding:10px; background:#fff; border:1px solid #ddd; border-radius:8px;">No hay secciones registradas.</div>
                    <?php else: ?>
                        <?php foreach ($sabores_categorias as $categoria): ?>
                            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-bottom:10px;">
                                <div style="min-width:160px; font-weight:600;"><?= sanitize($categoria['tipo']) ?></div>
                                <form method="post" style="display:flex; gap:8px; align-items:center; flex:1; min-width:260px;">
                                    <input type="hidden" name="old_section" value="<?= sanitize($categoria['tipo']) ?>">
                                    <input type="text" name="new_section" placeholder="Nuevo nombre" style="flex:1; padding:8px; border:1px solid #bbb; border-radius:8px;">
                                    <button type="submit" name="submit_section_rename" class="boton boton-pequeno">Renombrar</button>
                                </form>
                                <form method="post" onsubmit="return confirm('¿Eliminar la sección <?= sanitize($categoria['tipo']) ?> y todos sus sabores?');">
                                    <input type="hidden" name="section_delete" value="<?= sanitize($categoria['tipo']) ?>">
                                    <button type="submit" name="submit_section_delete" class="boton boton-pequeno" style="background:#d32f2f;">Eliminar</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div id="add-sabor-modal" class="modal-overlay" style="display:none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>Agregar sabor nuevo</div>
                        <button type="button" id="close-add-sabor-modal" class="boton boton-secundario boton-pequeno">Cerrar</button>
                    </div>
                    <form id="add-sabor-form" method="post" style="display:grid; gap:12px;">
                        <input type="hidden" name="submit_sabor" value="1">
                        <label style="display:block;">
                            Nombre<br>
                            <input type="text" name="nombre_sabor" placeholder="Ej: Chocolate" required style="width:100%; padding:10px; border:1px solid #bbb; border-radius:10px;">
                        </label>
                        <label style="display:block;">
                            Tipo<br>
                            <input type="text" name="tipo_sabor" placeholder="Ej: Chocolates" required style="width:100%; padding:10px; border:1px solid #bbb; border-radius:10px;">
                        </label>
                        <label style="display:block;">
                            Precio/L<br>
                            <input type="number" step="0.01" min="0" name="precio_sabor" placeholder="2800" required style="width:100%; padding:10px; border:1px solid #bbb; border-radius:10px;">
                        </label>
                        <label style="display:block;">
                            Stock (L)<br>
                            <input type="number" step="0.1" min="0" name="stock_sabor" placeholder="12" required style="width:100%; padding:10px; border:1px solid #bbb; border-radius:10px;">
                        </label>
                        <div style="display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap;">
                            <button type="button" id="cancel-add-sabor" class="boton boton-secundario boton-pequeno">Cancelar</button>
                            <button type="submit" class="boton boton-agregar">Agregar sabor</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="pestanas-categoria" id="sabores-tabs">
                <?php if (count($sabores_categorias) > 1): ?>
                    <span class="pestana-categoria activo" data-section="all">Todos</span>
                <?php endif; ?>
                <?php foreach ($sabores_categorias as $categoria): ?>
                    <span class="pestana-categoria" data-section="<?= sanitize($categoria['tipo']) ?>"><?= sanitize($categoria['tipo']) ?></span>
                <?php endforeach; ?>
            </div>

            <div class="envoltorio-tabla">
                <table>
                    <thead>
                        <tr>
                            <th>NOMBRE</th>
                            <th>TIPO</th>
                            <th>PRECIO/LITRO</th>
                            <th>STOCK (L)</th>
                            <th>EDITAR</th>
                            <th>ELIMINAR</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($sabores) === 0): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:18px 0;">No hay sabores registrados aun.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sabores as $sabor): ?>
                                <tr data-section="<?= sanitize($sabor['tipo']) ?>" data-id="<?= sanitize($sabor['id']) ?>" data-nombre="<?= sanitize($sabor['nombre']) ?>" data-tipo="<?= sanitize($sabor['tipo']) ?>" data-precio="<?= sanitize($sabor['precio']) ?>" data-stock="<?= sanitize($sabor['stock_actual']) ?>">
                                    <td><?= sanitize($sabor['nombre']) ?></td>
                                    <td><?= sanitize($sabor['tipo']) ?></td>
                                    <td><?= formatMoney($sabor['precio']) ?></td>
                                    <td><?= sanitize($sabor['stock_actual']) ?> L</td>
                                    <td><button type="button" class="boton-en-linea edit-sabor-btn">✏️</button></td>
                                    <td><button type="button" class="boton-en-linea delete-sabor-btn">🗑️</button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:24px; padding:16px; border:1px solid #ddd; border-radius:14px; background:#f7f7f7;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; gap:10px;">
                    <div style="font-weight:700;">Sabores inactivos (<?= sanitize(count($sabores_inactivos)) ?>)</div>
                    <button type="button" id="toggle-inactive-flavors" class="boton boton-secundario boton-pequeno">Mostrar/Ocultar</button>
                </div>
                <div id="inactive-flavor-section" style="display:none;">
                    <div class="envoltorio-tabla">
                        <table>
                            <thead>
                                <tr>
                                    <th>NOMBRE</th>
                                    <th>TIPO</th>
                                    <th>PRECIO/LITRO</th>
                                    <th>STOCK (L)</th>
                                    <th>ACTIVAR</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($sabores_inactivos) === 0): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding:18px 0;">No hay sabores inactivos.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sabores_inactivos as $sabor): ?>
                                        <tr>
                                            <td><?= sanitize($sabor['nombre']) ?></td>
                                            <td><?= sanitize($sabor['tipo']) ?></td>
                                            <td><?= formatMoney($sabor['precio']) ?></td>
                                            <td><?= sanitize($sabor['stock_actual']) ?> L</td>
                                            <td>
                                                <form method="post" style="margin:0; display:inline;">
                                                    <input type="hidden" name="submit_sabor_activate" value="1">
                                                    <input type="hidden" name="sabor_id_activate" value="<?= sanitize($sabor['id']) ?>">
                                                    <button type="submit" class="boton boton-agregar boton-pequeno">ACTIVAR</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="edit-sabor-modal" class="modal-overlay" style="display:none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>Editar sabor</div>
                        <button type="button" id="close-edit-sabor-modal" class="boton boton-secundario boton-pequeno">Cerrar</button>
                    </div>
                    <form id="flavor-edit-form" method="post" style="display:grid; gap:12px;">
                        <input type="hidden" name="submit_sabor_edit" value="1">
                        <input type="hidden" name="sabor_id" id="edit-sabor-id">
                        <label style="display:block;">
                            Nombre<br>
                            <input type="text" name="nombre_sabor_edit" id="edit-sabor-nombre" required style="width:100%; padding:10px; border:1px solid #bbb; border-radius:10px;">
                        </label>
                        <label style="display:block;">
                            Tipo<br>
                            <input type="text" name="tipo_sabor_edit" id="edit-sabor-tipo" required style="width:100%; padding:10px; border:1px solid #bbb; border-radius:10px;">
                        </label>
                        <label style="display:block;">
                            Precio/L<br>
                            <input type="number" step="0.01" min="0" name="precio_sabor_edit" id="edit-sabor-precio" required style="width:100%; padding:10px; border:1px solid #bbb; border-radius:10px;">
                        </label>
                        <label style="display:block;">
                            Stock (L)<br>
                            <input type="number" step="0.1" min="0" name="stock_sabor_edit" id="edit-sabor-stock" required style="width:100%; padding:10px; border:1px solid #bbb; border-radius:10px;">
                        </label>
                        <div style="display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap;">
                            <button type="button" id="cancel-edit-sabor" class="boton boton-secundario boton-pequeno">Cancelar</button>
                            <button type="submit" class="boton boton-agregar">Guardar cambios</button>
                        </div>
                    </form>
                </div>
            </div>

            <form id="flavor-delete-form" method="post" style="display:none;">
                <input type="hidden" name="submit_sabor_delete" value="1">
                <input type="hidden" name="sabor_id_delete" id="delete-sabor-id">
            </form>

            <div id="delete-sabor-modal" class="modal-overlay" style="display:none;">
                <div class="modal-content" style="width:min(560px, 100%);">
                    <div class="modal-header">
                        <div>Eliminar sabor</div>
                        <button type="button" id="close-delete-sabor-modal" class="boton boton-secundario boton-pequeno">Cerrar</button>
                    </div>
                    <div style="display:grid; gap:12px;">
                        <div style="padding:12px; border:1px solid #eee; border-radius:12px; background:#fff7f7;">
                            ¿Seguro que querés eliminar el sabor <strong id="delete-sabor-nombre">—</strong>?
                            <div style="margin-top:6px; color:#8a1c1c; font-weight:700; font-size:.9rem;">
                                Esta acción lo pasa a INACTIVO (no se puede deshacer desde acá).
                            </div>
                        </div>
                        <div style="display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap;">
                            <button type="button" id="cancel-delete-sabor" class="boton boton-secundario boton-pequeno">Cancelar</button>
                            <button type="button" id="confirm-delete-sabor" class="boton boton-pequeno" style="background:#d32f2f;">Eliminar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="s-accesorios" class="pantalla">
        <div class="encabezado">
            <div class="logo">
                <div class="logo-nombre"><img src="img/dajana-logo.png" alt="dajana"></div>
                <div class="logo-subtitulo">helados</div>
            </div>
            <div class="botones-encabezado">
                <div class="rol-encabezado">ADMIN</div>
                <a class="boton boton-volver" href="#s-panel">VOLVER</a>
            </div>
        </div>
        <div class="contenido">
            <div class="barra">
                <div class="titulo-pagina flex-1 no-mb">ACCESORIOS</div>
                <button type="button" id="open-add-accesorio-modal" class="boton boton-agregar boton-pequeno">+ Agregar ACCESORIO</button>
            </div>

            <div id="add-accesorio-modal" class="modal-overlay" style="display:none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>Agregar accesorio nuevo</div>
                        <button type="button" id="close-add-accesorio-modal" class="boton boton-secundario boton-pequeno">Cerrar</button>
                    </div>
                    <form id="add-accesorio-form" method="post" style="display:grid; gap:12px;">
                        <input type="hidden" name="submit_accesorio" value="1">
                        <label style="display:block;">
                            Nombre<br>
                            <input type="text" name="nombre_accesorio" placeholder="Ej: Cucurucho" required style="width:100%; padding:10px; border:1px solid #bbb; border-radius:10px;">
                        </label>
                        <label style="display:block;">
                            Descripción<br>
                            <input type="text" name="descripcion_accesorio" placeholder="Ej: Cucurucho de chocolate" required style="width:100%; padding:10px; border:1px solid #bbb; border-radius:10px;">
                        </label>
                        <label style="display:block;">
                            Precio unit.<br>
                            <input type="number" step="0.01" min="0" name="precio_accesorio" placeholder="800" required style="width:100%; padding:10px; border:1px solid #bbb; border-radius:10px;">
                        </label>
                        <label style="display:block;">
                            Stock<br>
                            <input type="number" min="0" name="stock_accesorio" placeholder="100" required style="width:100%; padding:10px; border:1px solid #bbb; border-radius:10px;">
                        </label>
                        <div style="display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap;">
                            <button type="button" id="cancel-add-accesorio" class="boton boton-secundario boton-pequeno">Cancelar</button>
                            <button type="submit" class="boton boton-agregar">Agregar accesorio</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="envoltorio-tabla">
                <table>
                    <thead>
                        <tr>
                            <th>NOMBRE</th>
                            <th>DESCRIPCIÓN</th>
                            <th>PRECIO</th>
                            <th>STOCK</th>
                            <th>ESTADO</th>
                            <th>✏️</th>
                            <th>🗑</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($accesorios) === 0): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:18px 0;">No hay accesorios registrados aun.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($accesorios as $accesorio): ?>
                                <tr data-id="<?= sanitize($accesorio['id']) ?>"
                                    data-nombre="<?= sanitize($accesorio['nombre']) ?>"
                                    data-descripcion="<?= sanitize($accesorio['descripcion']) ?>"
                                    data-precio="<?= sanitize($accesorio['precio']) ?>"
                                    data-stock="<?= sanitize($accesorio['stock_actual']) ?>">
                                    <td><?= sanitize($accesorio['nombre']) ?></td>
                                    <td><?= sanitize($accesorio['descripcion']) ?></td>
                                    <td><?= formatMoney($accesorio['precio']) ?></td>
                                    <td><?= sanitize($accesorio['stock_actual']) ?></td>
                                    <td><span class="badge insignia-normal">ACTIVO</span></td>
                                    <td><button type="button" class="boton-en-linea edit-accesorio-btn">✏️</button></td>
                                    <td><button type="button" class="boton-en-linea delete-accesorio-btn">🗑️</button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:24px; padding:16px; border:1px solid #ddd; border-radius:14px; background:#f7f7f7;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; gap:10px;">
                    <div style="font-weight:700;">Accesorios inactivos (<?= sanitize(count($accesorios_inactivos)) ?>)</div>
                    <button type="button" id="toggle-inactive-accesorios" class="boton boton-secundario boton-pequeno">Mostrar/Ocultar</button>
                </div>
                <div id="inactive-accesorio-section" style="display:none;">
                    <div class="envoltorio-tabla">
                        <table>
                            <thead>
                                <tr>
                                    <th>NOMBRE</th>
                                    <th>DESCRIPCIÓN</th>
                                    <th>PRECIO</th>
                                    <th>STOCK</th>
                                    <th>ACTIVAR</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($accesorios_inactivos) === 0): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding:18px 0;">No hay accesorios inactivos.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($accesorios_inactivos as $acc): ?>
                                        <tr>
                                            <td><?= sanitize($acc['nombre']) ?></td>
                                            <td><?= sanitize($acc['descripcion']) ?></td>
                                            <td><?= formatMoney($acc['precio']) ?></td>
                                            <td><?= sanitize($acc['stock_actual']) ?></td>
                                            <td>
                                                <form method="post" style="margin:0; display:inline;">
                                                    <input type="hidden" name="submit_accesorio_activate" value="1">
                                                    <input type="hidden" name="accesorio_id_activate" value="<?= sanitize($acc['id']) ?>">
                                                    <button type="submit" class="boton boton-agregar boton-pequeno">ACTIVAR</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="edit-accesorio-modal" class="modal-overlay" style="display:none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>Editar accesorio</div>
                        <button type="button" id="close-edit-accesorio-modal" class="boton boton-secundario boton-pequeno">Cerrar</button>
                    </div>
                    <form id="accesorio-edit-form" method="post" style="display:grid; gap:12px;">
                        <input type="hidden" name="submit_accesorio_edit" value="1">
                        <input type="hidden" name="accesorio_id" id="edit-accesorio-id">
                        <label style="display:block;">
                            Nombre<br>
                            <input type="text" name="nombre_accesorio_edit" id="edit-accesorio-nombre" required style="width:100%; padding:10px; border:1px solid #bbb; border-radius:10px;">
                        </label>
                        <label style="display:block;">
                            Descripción<br>
                            <input type="text" name="descripcion_accesorio_edit" id="edit-accesorio-descripcion" required style="width:100%; padding:10px; border:1px solid #bbb; border-radius:10px;">
                        </label>
                        <label style="display:block;">
                            Precio unit.<br>
                            <input type="number" step="0.01" min="0" name="precio_accesorio_edit" id="edit-accesorio-precio" required style="width:100%; padding:10px; border:1px solid #bbb; border-radius:10px;">
                        </label>
                        <label style="display:block;">
                            Stock<br>
                            <input type="number" min="0" name="stock_accesorio_edit" id="edit-accesorio-stock" required style="width:100%; padding:10px; border:1px solid #bbb; border-radius:10px;">
                        </label>
                        <div style="display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap;">
                            <button type="button" id="cancel-edit-accesorio" class="boton boton-secundario boton-pequeno">Cancelar</button>
                            <button type="submit" class="boton boton-agregar">Guardar cambios</button>
                        </div>
                    </form>
                </div>
            </div>

            <form id="accesorio-delete-form" method="post" style="display:none;">
                <input type="hidden" name="submit_accesorio_delete" value="1">
                <input type="hidden" name="accesorio_id_delete" id="delete-accesorio-id">
            </form>

            <div id="delete-accesorio-modal" class="modal-overlay" style="display:none;">
                <div class="modal-content" style="width:min(560px, 100%);">
                    <div class="modal-header">
                        <div>Eliminar accesorio</div>
                        <button type="button" id="close-delete-accesorio-modal" class="boton boton-secundario boton-pequeno">Cerrar</button>
                    </div>
                    <div style="display:grid; gap:12px;">
                        <div style="padding:12px; border:1px solid #eee; border-radius:12px; background:#fff7f7;">
                            ¿Seguro que querés eliminar el accesorio <strong id="delete-accesorio-nombre">—</strong>?
                            <div style="margin-top:6px; color:#8a1c1c; font-weight:700; font-size:.9rem;">
                                Esta acción lo pasa a INACTIVO (no se puede deshacer desde acá).
                            </div>
                        </div>
                        <div style="display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap;">
                            <button type="button" id="cancel-delete-accesorio" class="boton boton-secundario boton-pequeno">Cancelar</button>
                            <button type="button" id="confirm-delete-accesorio" class="boton boton-pequeno" style="background:#d32f2f;">Eliminar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="s-stock" class="pantalla">
        <div class="encabezado">
            <div class="logo">
                <div class="logo-nombre"><img src="img/dajana-logo.png" alt="dajana"></div>
                <div class="logo-subtitulo">helados</div>
            </div>
            <div class="botones-encabezado">
                <div class="rol-encabezado">ADMIN</div>
                <a class="boton boton-volver" href="#s-panel">VOLVER</a>
            </div>
        </div>
        <div class="contenido">
            <div class="titulo-pagina">CONTROL DE STOCK</div>

            <input type="radio" name="stk-tab" id="stk-tab-sab" class="radio-stock" checked>
            <input type="radio" name="stk-tab" id="stk-tab-acc" class="radio-stock">
            <div class="contenido-stock">
                <div class="envoltorio-pestanas-stock mb-14">
                    <label class="etiqueta-pestana-stock" for="stk-tab-sab">SABORES</label>
                    <label class="etiqueta-pestana-stock" for="stk-tab-acc">ACCESORIOS</label>
                </div>

                <div id="stk-sab" class="seccion-stock">
                    <div class="filtros-stock">
                        <select id="stock-category-filter" class="selector-stock">
                            <option value="">Ver todos</option>
                            <?php foreach ($sabores_categorias as $categoria): ?>
                                <option value="<?= sanitize($categoria['tipo']) ?>"><?= sanitize($categoria['tipo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input id="search-sabores" class="buscador" type="text" placeholder="🔍 Buscar sabor...">
                    </div>
                    <div class="envoltorio-tabla">
                        <table>
                            <thead>
                                <tr>
                                    <th>SABOR</th>
                                    <th>TIPO</th>
                                    <th>STOCK (litros)</th>
                                    <th>ESTADO</th>
                                    <th>✏️</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($sabores) === 0): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding:18px 0;">No hay sabores cargados en la base de datos.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sabores as $sabor): ?>
                                        <tr data-categoria="<?= sanitize($sabor['tipo']) ?>"
                                            data-id="<?= sanitize($sabor['id']) ?>"
                                            data-nombre="<?= sanitize($sabor['nombre']) ?>"
                                            data-stock="<?= sanitize($sabor['stock_actual']) ?>">
                                            <td><?= sanitize($sabor['nombre']) ?></td>
                                            <td><?= sanitize($sabor['tipo']) ?></td>
                                            <td><?= sanitize($sabor['stock_actual']) ?> L</td>
                                            <td><?= badgeStock($sabor['stock_actual']) ?></td>
                                            <td><button type="button" class="boton-en-linea edit-stock-sabor-btn">✏️</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="stk-acc" class="seccion-stock">
                    <div class="envoltorio-tabla">
                        <table>
                            <thead>
                                <tr>
                                    <th>ACCESORIO</th>
                                    <th>DESCRIPCIÓN</th>
                                    <th>STOCK</th>
                                    <th>ESTADO</th>
                                    <th>✏️</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($accesorios) === 0): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding:18px 0;">No hay accesorios cargados en la base de datos.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($accesorios as $accesorio): ?>
                                        <tr data-id="<?= sanitize($accesorio['id']) ?>"
                                            data-nombre="<?= sanitize($accesorio['nombre']) ?>"
                                            data-stock="<?= sanitize($accesorio['stock_actual']) ?>">
                                            <td><?= sanitize($accesorio['nombre']) ?></td>
                                            <td><?= sanitize($accesorio['descripcion']) ?></td>
                                            <td><?= sanitize($accesorio['stock_actual']) ?></td>
                                            <td><?= badgeStock($accesorio['stock_actual']) ?></td>
                                            <td><button type="button" class="boton-en-linea edit-stock-accesorio-btn">✏️</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="edit-stock-modal" class="modal-overlay" style="display:none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <div id="edit-stock-modal-title">Editar stock</div>
                        <button type="button" id="close-edit-stock-modal" class="boton boton-secundario boton-pequeno">Cerrar</button>
                    </div>
                    <form id="edit-stock-form" method="post" style="display:grid; gap:12px;">
                        <input type="hidden" name="submit_stock_quick" value="1">
                        <input type="hidden" name="stock_kind" id="edit-stock-kind" value="">
                        <input type="hidden" name="stock_item_id" id="edit-stock-item-id" value="">
                        <div id="edit-stock-product-label" style="font-weight:800; color:var(--granate);"></div>
                        <label style="display:block;">
                            <span id="edit-stock-label-text">Cantidad</span><br>
                            <input type="number" name="stock_cantidad" id="edit-stock-cantidad" min="0" required style="width:100%; padding:10px; border:1px solid #bbb; border-radius:10px;">
                        </label>
                        <div style="display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap;">
                            <button type="button" id="cancel-edit-stock" class="boton boton-secundario boton-pequeno">Cancelar</button>
                            <button type="submit" class="boton boton-agregar">Guardar</button>
                        </div>
                    </form>
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
                <div class="rol-encabezado">ADMIN</div>
                <a class="boton boton-volver" href="#s-panel">VOLVER</a>
            </div>
        </div>
        <div class="contenido">
            <div class="titulo-pagina">HISTORIAL DE VENTAS</div>
            <div class="fila-flexible">
                <input id="search-ventas" class="buscador" type="text" placeholder="🔍 Buscar venta, empleado, pago...">
                <input id="filter-fecha" class="buscador maxw-175" type="date">
                <button id="clear-ventas-filters" type="button" class="boton boton-secundario boton-limpiar">✕ LIMPIAR</button>
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
                            <th>ELIM.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ventas) === 0): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:18px 0;">No hay ventas registradas aun.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ventas as $venta): ?>
                                <tr>
                                    <td><strong>#<?= sanitize($venta['id']) ?></strong></td>
                                    <td><?= sanitize(date('d/m/Y', strtotime($venta['fecha']))) ?></td>
                                    <td><?= formatMoney($venta['total']) ?></td>
                                    <td><?= sanitize($venta['pago']) ?></td>
                                    <td><?= sanitize($venta['empleado']) ?></td>
                                    <td><a class="boton-en-linea" href="admin.php?venta=<?= sanitize($venta['id']) ?>#s-venta-detalle">👁️</a></td>
                                    <td><button class="boton-en-linea">🗑️</button></td>
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
                <div class="rol-encabezado">ADMIN</div>
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
                    No se encontró la venta #<?= sanitize($ventaDetalleId) ?>.
                </div>
            <?php else: ?>
                <div class="tarjeta-detalle">
                    <div class="info-detalle">Venta del día <span>#<?= sanitize($ventaDetalle['id']) ?></span></div>
                    <div class="info-detalle">Fecha: <span><?= sanitize(date('d/m/Y H:i', strtotime($ventaDetalle['fecha']))) ?></span></div>
                    <div class="info-detalle">Empleado: <span><?= sanitize($ventaDetalle['empleado']) ?></span></div>
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
                                            $nombreProducto = $item['accesorio_nombre'] ?: ($item['sabor_nombre'] ?: 'Producto');
                                            $detalleProducto = $item['sabores'] ?: ($item['accesorio_descripcion'] ?: ($item['sabor_tipo'] ? ('Tipo: ' . $item['sabor_tipo']) : ''));
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
                    <div class="info-detalle" style="font-size:1rem;">Total: <span><?= formatMoney($ventaDetalle['total']) ?></span></div>
                    <div class="info-detalle">Pago: <span><?= sanitize($ventaDetalle['pago']) ?></span></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="admin.js"></script>

</body>

</html>