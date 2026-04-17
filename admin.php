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
$ventas = fetchRows($conexion, 'SELECT v.id_venta AS id, v.fecha, v.total_venta AS total, v.metodo_pago AS pago, IFNULL(u.nombre_usuario, "Sin empleado") AS empleado FROM ventas v LEFT JOIN usuarios u ON v.id_empleado = u.id_usuario ORDER BY v.fecha DESC LIMIT 20');

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
                <div class="estadistica">� Sabores inactivos: <span><?= sanitize(count($sabores_inactivos)) ?></span></div>
                <div class="estadistica">�📦 Total ventas registradas: <span><?= sanitize($totalVentasRegistradas) ?></span></div>
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
            </div>

            <div id="section-manager" style="display:none; margin: 16px 0; padding: 14px; border: 1px solid #cacaca; border-radius: 12px; background: #f7f7f7;">
                <div style="font-weight:700; margin-bottom:10px;">Administrar secciones de sabores</div>
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

            <div style="margin: 16px 0; padding: 14px; border: 1px solid #ddd; border-radius: 12px; background: #fbfbfb;">
                <div style="font-weight:700; margin-bottom:8px;">Agregar sabor nuevo</div>
                <form method="post" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
                    <label style="flex:1; min-width:150px;">
                        Nombre<br>
                        <input type="text" name="nombre_sabor" placeholder="Ej: Chocolate" required style="width:100%; padding:8px;">
                    </label>
                    <label style="flex:1; min-width:150px;">
                        Tipo<br>
                        <input type="text" name="tipo_sabor" placeholder="Ej: Chocolates" required style="width:100%; padding:8px;">
                    </label>
                    <label style="width:120px;">
                        Precio/L<br>
                        <input type="number" step="0.01" min="0" name="precio_sabor" placeholder="2800" required style="width:100%; padding:8px;">
                    </label>
                    <label style="width:120px;">
                        Stock (L)
                        <input type="number" step="0.1" min="0" name="stock_sabor" placeholder="12" required style="width:100%; padding:8px;">
                    </label>
                    <button type="submit" name="submit_sabor" class="boton boton-agregar" style="margin-top:4px;">+ Agregar SABOR</button>
                </form>
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

            <div id="edit-sabor-panel" style="display:none; margin-top:20px; padding:16px; border:1px solid #ccc; border-radius:14px; background:#fff; box-shadow:0 4px 14px rgba(0,0,0,0.04);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                    <div style="font-weight:700; font-size:1rem;">Editar sabor</div>
                    <button type="button" id="cancel-edit-sabor" class="boton boton-secundario boton-pequeno">CERRAR</button>
                </div>
                <form id="flavor-edit-form" method="post" style="display:grid; gap:12px; grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
                    <input type="hidden" name="submit_sabor_edit" value="1">
                    <input type="hidden" name="sabor_id" id="edit-sabor-id">
                    <label style="display:block;">
                        Nombre<br>
                        <input type="text" name="nombre_sabor_edit" id="edit-sabor-nombre" required style="width:100%; padding:9px; border:1px solid #bbb; border-radius:10px;">
                    </label>
                    <label style="display:block;">
                        Tipo<br>
                        <input type="text" name="tipo_sabor_edit" id="edit-sabor-tipo" required style="width:100%; padding:9px; border:1px solid #bbb; border-radius:10px;">
                    </label>
                    <label style="display:block;">
                        Precio/L<br>
                        <input type="number" step="0.01" min="0" name="precio_sabor_edit" id="edit-sabor-precio" required style="width:100%; padding:9px; border:1px solid #bbb; border-radius:10px;">
                    </label>
                    <label style="display:block;">
                        Stock (L)<br>
                        <input type="number" step="0.1" min="0" name="stock_sabor_edit" id="edit-sabor-stock" required style="width:100%; padding:9px; border:1px solid #bbb; border-radius:10px;">
                    </label>
                    <div style="grid-column:1 / -1; display:flex; gap:10px; justify-content:flex-end;">
                        <button type="submit" class="boton boton-agregar">Guardar cambios</button>
                    </div>
                </form>
            </div>

            <form id="flavor-delete-form" method="post" style="display:none;">
                <input type="hidden" name="submit_sabor_delete" value="1">
                <input type="hidden" name="sabor_id_delete" id="delete-sabor-id">
            </form>
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
                <a class="boton boton-secundario boton-pequeno" >⚙️ EDITAR TIPOS</a>
            </div>
            <div style="margin: 16px 0; padding: 14px; border: 1px solid #ddd; border-radius: 12px; background: #fbfbfb;">
                <div style="font-weight:700; margin-bottom:8px;">Agregar accesorio nuevo</div>
                <form method="post" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
                    <label style="flex:1; min-width:150px;">
                        Nombre<br>
                        <input type="text" name="nombre_accesorio" placeholder="Ej: Cucurucho" required style="width:100%; padding:8px;">
                    </label>
                    <label style="flex:1; min-width:150px;">
                        Descripción<br>
                        <input type="text" name="descripcion_accesorio" placeholder="Ej: Cucurucho" required style="width:100%; padding:8px;">
                    </label>
                    <label style="width:120px;">
                        Precio unit.
                        <input type="number" step="0.01" min="0" name="precio_accesorio" placeholder="800" required style="width:100%; padding:8px;">
                    </label>
                    <label style="width:120px;">
                        Stock
                        <input type="number" min="0" name="stock_accesorio" placeholder="100" required style="width:100%; padding:8px;">
                    </label>
                    <button type="submit" name="submit_accesorio" class="boton boton-agregar" style="margin-top:4px;">+ Agregar ACCESORIO</button>
                </form>
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
                                <tr>
                                    <td><?= sanitize($accesorio['nombre']) ?></td>
                                    <td><?= sanitize($accesorio['descripcion']) ?></td>
                                    <td><?= formatMoney($accesorio['precio']) ?></td>
                                    <td><?= sanitize($accesorio['stock_actual']) ?></td>
                                    <td><span class="badge insignia-normal">ACTIVO</span></td>
                                    <td><button class="boton-en-linea">✏️</button></td>
                                    <td><button class="boton-en-linea">🗑️</button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
                                        <tr data-categoria="<?= sanitize($sabor['tipo']) ?>">
                                            <td><?= sanitize($sabor['nombre']) ?></td>
                                            <td><?= sanitize($sabor['tipo']) ?></td>
                                            <td><?= sanitize($sabor['stock_actual']) ?> L</td>
                                            <td><?= badgeStock($sabor['stock_actual']) ?></td>
                                            <td><button class="boton-en-linea">✏️</button></td>
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
                                        <tr>
                                            <td><?= sanitize($accesorio['nombre']) ?></td>
                                            <td><?= sanitize($accesorio['descripcion']) ?></td>
                                            <td><?= sanitize($accesorio['stock_actual']) ?></td>
                                            <td><?= badgeStock($accesorio['stock_actual']) ?></td>
                                            <td><button class="boton-en-linea">✏️</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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
                                    <td><a class="boton-en-linea" href="#">👁️</a></td>
                                    <td><button class="boton-en-linea">🗑️</button></td>
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
                <div class="rol-encabezado">ADMIN</div>
                <a class="boton boton-volver" href="#s-historial">VOLVER</a>
            </div>
        </div>
        <div class="contenido">
            <div class="titulo-pagina">DETALLE DE VENTA</div>
            <div class="tarjeta-detalle">
                <div class="info-detalle">Venta del día <span>#1</span></div>
                <div class="info-detalle">Fecha: <span>25/03/2026</span></div>
                <div class="info-detalle">Empleado: <span>Juan</span></div>
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
                                    <small style="color:#888;font-style:italic;">Chocolate · Vainilla</small>
                                </td>
                                <td>1</td>
                                <td>$2.500</td>
                                <td>$2.500</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;padding:8px 9px;">
                                    Cucurucho<br>
                                    <small style="color:#888;font-style:italic;">Dulce de leche</small>
                                </td>
                                <td>2</td>
                                <td>$800</td>
                                <td>$1.600</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="info-detalle" style="font-size:1rem;">Total: <span>$5.800</span></div>
                <div class="info-detalle">Pago: <span>Efectivo</span></div>
            </div>
        </div>
    </div>

    <div id="s-detalle2" class="pantalla">
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
            <div class="tarjeta-detalle">
                <div class="info-detalle">Venta del día <span>#2</span></div>
                <div class="info-detalle">Fecha: <span>25/03/2026</span></div>
                <div class="info-detalle">Empleado: <span>Ana</span></div>
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
                                    <small style="color:#888;font-style:italic;">Frutilla · Limón</small>
                                </td>
                                <td>1</td>
                                <td>$2.500</td>
                                <td>$2.500</td>
                            </tr>
                            <tr>
                                <td style="text-align:left;padding:8px 9px;">Caja servilletas</td>
                                <td>1</td>
                                <td>$900</td>
                                <td>$900</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="info-detalle" style="font-size:1rem;">Total: <span>$4.500</span></div>
                <div class="info-detalle">Pago: <span>Transferencia</span></div>
            </div>
        </div>
    </div>

    <script src="admin.js"></script>

</body>

</html>