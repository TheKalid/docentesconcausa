<?php
// =====================================================================
// ARCHIVO: validar_acceso.php
// OBJETIVO: Validar contraseña de Admin con protección Anti-Fuerza Bruta
// =====================================================================
session_start();

// [SEGURIDAD] Cabeceras
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header('Content-Type: application/json; charset=utf-8');

require_once 'credenciales.php';

// [SEGURIDAD] Sistema Anti-Fuerza Bruta
// Si alguien se equivoca, anotamos el intento en su sesión.
if (!isset($_SESSION['intentos_admin'])) {
    $_SESSION['intentos_admin'] = 0;
}

// Si se equivoca 5 veces, lo bloqueamos por completo en esa sesión
if ($_SESSION['intentos_admin'] >= 5) {
    // Le ponemos un retraso de 3 segundos para desesperar a los bots
    sleep(3); 
    echo json_encode(['success' => false, 'error' => 'Demasiados intentos. Acceso bloqueado.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit();
}

$datos = json_decode(file_get_contents('php://input'), true);

// [SEGURIDAD] Verificación estricta
if (isset($datos['password']) && $datos['password'] === ADMIN_PASSWORD) {
    // Éxito: Le damos la llave de admin y reseteamos sus errores
    $_SESSION['admin_logeado'] = true;
    $_SESSION['intentos_admin'] = 0; // Borramos su historial de errores
    
    // Liberamos la sesión para no trabar el navegador
    session_write_close();
    
    echo json_encode(['success' => true]);
} else {
    // Falla: Le sumamos 1 error y hacemos que el servidor tarde 1 segundo en responder (ralentiza a los bots)
    $_SESSION['intentos_admin'] += 1;
    sleep(1); 
    
    echo json_encode(['success' => false]);
}
?>