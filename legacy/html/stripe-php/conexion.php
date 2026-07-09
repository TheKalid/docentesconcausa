<?php
// --- Archivo de Conexión ÚNICO y DEFINITIVO ---

// 1. Credenciales para un entorno local XAMPP (la forma más segura de que funcione)
$servidor = "localhost"; 
$usuario_db = "docente_user"; // Usuario por defecto de XAMPP, tiene todos los permisos.
$password_db = "REDACTED_DB_PASSWORD";    // La contraseña de root en XAMPP casi siempre está vacía.
$nombre_db = "causa_db"; // El nombre de tu base de datos.

// 2. Intento de Conexión
$conexion = new mysqli($servidor, $usuario_db, $password_db, $nombre_db);

// 3. Verificación de la Conexión
if ($conexion->connect_error) {
    // Si hay un error, el script se detiene y muestra el problema exacto.
    die("Conexión fallida: " . $conexion->connect_error);
}

// 4. Configuración del set de caracteres (muy importante para acentos y ñ)
$conexion->set_charset("utf8");

// Nota: Se corrigió un error que tenías, una llave '}' extra al final del archivo.