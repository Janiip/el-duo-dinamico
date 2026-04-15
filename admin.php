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
    }
}

function fetchRows($conexion, $sql) {
    $result = $conexion->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

$sabores = fetchRows($conexion, 'SELECT id_sabor AS id, nombre, tipo, precio, stock_actual FROM sabores WHERE estado = \'ACTIVO\' ORDER BY tipo, nombre');
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
                <a class="boton boton-secundario boton-pequeno" >⚙️ EDITAR SECCIONES</a>
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

            <div class="pestanas-categoria">
                <span class="pestana-categoria activo">Chocolates</span>
                <span class="pestana-categoria">Dulces de leche</span>
                <span class="pestana-categoria">Frutas al agua</span>
                <span class="pestana-categoria">Frutas a la crema</span>
                <span class="pestana-categoria">Cremas</span>
                <span class="pestana-categoria">Cremas especiales</span>
            </div>

            <div class="envoltorio-tabla">
                <table>
                    <thead>
                        <tr>
                            <th>NOMBRE</th>
                            <th>PRECIO/LITRO</th>
                            <th>STOCK (L)</th>
                            <th>EDITAR</th>
                            <th>ELIMINAR</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($sabores) === 0): ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding:18px 0;">No hay sabores registrados aun.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sabores as $sabor): ?>
                                <tr>
                                    <td><?= sanitize($sabor['nombre']) ?></td>
                                    <td><?= formatMoney($sabor['precio']) ?></td>
                                    <td><?= sanitize($sabor['stock_actual']) ?> L</td>
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
                <input class="buscador" type="text" placeholder="🔍 Buscar empleado...">
                <input class="buscador maxw-175" type="date">
                <button class="boton boton-secundario boton-limpiar">✕ LIMPIAR</button>
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

    <script>
        function updateStockTabs() {
            const sabRadio = document.getElementById('stk-tab-sab');
            const accRadio = document.getElementById('stk-tab-acc');
            const sabSection = document.getElementById('stk-sab');
            const accSection = document.getElementById('stk-acc');
            const sabLabel = document.querySelector('label[for="stk-tab-sab"]');
            const accLabel = document.querySelector('label[for="stk-tab-acc"]');

            if (sabRadio.checked) {
                sabSection.style.display = 'block';
                accSection.style.display = 'none';
                sabLabel.classList.add('activo');
                accLabel.classList.remove('activo');
            } else {
                sabSection.style.display = 'none';
                accSection.style.display = 'block';
                sabLabel.classList.remove('activo');
                accLabel.classList.add('activo');
            }
        }

        function filterSabores() {
            const select = document.getElementById('stock-category-filter');
            const search = document.getElementById('search-sabores');
            const category = select.value.toLowerCase();
            const query = search.value.trim().toLowerCase();
            const rows = document.querySelectorAll('#stk-sab tbody tr');

            rows.forEach(row => {
                const name = row.cells[0]?.textContent.toLowerCase() || '';
                const type = row.dataset.categoria?.toLowerCase() || '';
                const matchesCategory = !category || type === category;
                const matchesSearch = !query || name.includes(query) || type.includes(query);
                row.style.display = matchesCategory && matchesSearch ? '' : 'none';
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const sabRadio = document.getElementById('stk-tab-sab');
            const accRadio = document.getElementById('stk-tab-acc');
            const select = document.getElementById('stock-category-filter');
            const search = document.getElementById('search-sabores');

            sabRadio.addEventListener('change', updateStockTabs);
            accRadio.addEventListener('change', updateStockTabs);
            select.addEventListener('change', filterSabores);
            search.addEventListener('input', filterSabores);
            updateStockTabs();
            filterSabores();
        });
    </script>

</body>

</html>