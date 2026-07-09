<?php
// Archivo de Configuración Segura

// --- Credenciales de la Base de Datos ---
$db_host = 'localhost';
$db_name = 'causa_db';
$db_user = 'docente_user';
$db_pass = 'REDACTED_DB_PASSWORD'; // Tu contraseña va aquí

// --- Conexión PDO ---
// Creamos la conexión aquí para no repetirla en otros archivos.
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Si la conexión falla, detenemos todo y mostramos un error genérico.
    error_log("Error de conexión a la base de datos: " . $e->getMessage()); // Esto guarda el error real en los logs del servidor para que tú lo veas.
    die("Error: No se pudo conectar con el servidor. Por favor, inténtalo más tarde.");
}
?>