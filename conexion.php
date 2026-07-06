<?php
// conexion.php
// Ajusta estos valores según la configuración de XAMPP
$servername = 'localhost';
$username = 'root';
$password = '';
$base_de_datos = 'dajana_helados';

// Crear conexión
$conexion = new mysqli($servername, $username, $password, $base_de_datos);

// Verificar conexión
if ($conexion->connect_errno) {
    die('Error de conexión a la base de datos: (' . $conexion->connect_errno . ') ' . $conexion->connect_error);
}

// Opcional: establecer codificación UTF-8
$conexion->set_charset('utf8');

// Asegura compatibilidad para guardar sabores elegidos en el ticket.
// Si la columna no existe aún, la crea (se ejecuta una sola vez).
if ($result = $conexion->query("SHOW COLUMNS FROM detalle_ventas LIKE 'sabores'")) {
    if ($result->num_rows === 0) {
        // NULLable para no romper ventas viejas.
        $conexion->query("ALTER TABLE detalle_ventas ADD COLUMN sabores TEXT NULL AFTER id_accesorio");
    }
    $result->free();
}

// Ejemplo de uso:
// include 'conexion.php';
// $resultado = $conexion->query("SELECT * FROM usuarios");
// while ($fila = $resultado->fetch_assoc()) {
//     echo $fila['nombre_usuario'] . '<br>';
// }

?>