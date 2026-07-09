<?php
// =========================================================================
// 1. DETECCIÓN AUTOMÁTICA DE ENTORNO (Mr. Robot Protocol)
// =========================================================================
// Verificamos si la IP es local o el host es localhost
$is_local = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || $_SERVER['HTTP_HOST'] == 'localhost';

if ($is_local) {
    // --- LLAVES PARA SU LABORATORIO LOCAL (XAMPP) ---
    // En XAMPP, el usuario maestro siempre es 'root' sin contraseña.
    $host = "localhost";
    $usuario = "root";     // CAMBIO CLAVE: Usamos root en lugar de docente_user
    $password = "";         // CAMBIO CLAVE: Sin contraseña en local
    $base_datos = "causa_db";
} else {
    // --- LLAVES PARA EL SERVIDOR REAL (PRODUCCIÓN) ---
    // Estos datos solo se activarán cuando suba el archivo al hosting.
    $host = "localhost";
    $usuario = "docente_user"; 
    $password = "REDACTED_DB_PASSWORD"; 
    $base_datos = "causa_db";
}

// =========================================================================
// 2. INICIO DE ENLACE NEURONAL (MySQLi)
// =========================================================================
// Intentamos conectar con las credenciales detectadas arriba
$conexion = new mysqli($host, $usuario, $password, $base_datos);

// =========================================================================
// 3. DIAGNÓSTICO DE ESTATUS
// =========================================================================
if ($conexion->connect_error) {
    // Si falla, mostramos el reporte técnico solo en local para estudio
    if ($is_local) {
        die("Fallo crítico en el enlace, Señor Stark: " . $conexion->connect_error);
    } else {
        die("Error de comunicación con la matriz de datos.");
    }
}

// Conexión exitosa. El sistema está listo para procesar el login.
?>