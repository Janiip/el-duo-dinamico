<?php
session_start();
include ('conexion.php');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($usuario === '' || $password === '') {
        $error = 'Complete usuario y contraseña.';
    } else {
        $stmt = $conexion->prepare('SELECT nombre_usuario, rol FROM usuarios WHERE nombre_usuario = ? AND password = MD5(?) LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('ss', $usuario, $password);
            $stmt->execute();
            $resultado = $stmt->get_result();

            if ($fila = $resultado->fetch_assoc()) {
                $_SESSION['usuario'] = $fila['nombre_usuario'];
                $_SESSION['rol'] = $fila['rol'];

                $stmt->close();
                $conexion->close();

                header('Location: ' . ($fila['rol'] === 'ADMIN' ? 'admin.php' : 'empleado.php'));
                exit;
            }

            $error = 'Usuario o contraseña incorrectos';
            $stmt->close();
        } else {
            $error = 'Error interno. Intente de nuevo.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dajana helados / login</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="index.css">
</head>

<body>
    <div class="encabezado-login">
        <div class="logo-nombre"><img src="img/dajana-logo.png" alt="logo dajana"></div>
        <div class="logo-subtitulo">h e l a d o s</div>
    </div>

    <div class="cuerpo-login">
        <div class="titulo-login">LOGIN</div>
        <form method="post" class="formulario-login">
            <div class="columna-apilada-7">
                <div class="etiqueta-personalizada">USUARIO</div>
                <input class="entrada-personalizada" id="inp-u" name="usuario" type="text" placeholder="INGRESE NOMBRE DE USUARIO" value="<?= htmlspecialchars($_POST['usuario'] ?? '', ENT_QUOTES) ?>">
            </div>
            <div class="columna-apilada-7">
                <div class="etiqueta-personalizada">CONTRASEÑA</div>
                <input class="entrada-personalizada" id="inp-p" name="password" type="password" placeholder="INGRESE CONTRASEÑA">
            </div>
            <div class="error" id="error-login" style="display: <?= $error ? 'block' : 'none' ?>;"><?= htmlspecialchars($error) ?></div>
            <div class="centrado margen-superior-6">
                <button class="boton-iniciar-sesion" type="submit">INICIAR SESIÓN</button>
            </div>
        </form>
        <div class="pistas-login">
             admin / admin123 &nbsp;|&nbsp;  juan / juan123 &nbsp;|&nbsp;  ana / ana123
        </div>
    </div>
</body>

</html>
