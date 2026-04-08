<?php
// conexion.php
// Ajusta estos valores según la configuración de XAMPP
$host = 'localhost';
$usuario = 'root';
$password = '';
$base_de_datos = 'dajana_helados';

// Crear conexión
$conexion = new mysqli($host, $usuario, $password, $base_de_datos);

// Verificar conexión
if ($conexion->connect_errno) {
    die('Error de conexión a la base de datos: (' . $conexion->connect_errno . ') ' . $conexion->connect_error);
}

// Opcional: establecer codificación UTF-8
$conexion->set_charset('utf8');

// Ejemplo de uso:
// include 'conexion.php';
// $resultado = $conexion->query("SELECT * FROM usuarios");
// while ($fila = $resultado->fetch_assoc()) {
//     echo $fila['nombre_usuario'] . '<br>';
// }

?>