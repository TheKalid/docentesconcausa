<?php
// procesar_login.php (VERSIÓN BLINDADA CON RATE LIMITER)

// 1. Inicia la sesión. Obligatorio para rastrear los intentos.
session_start();

// 2. Incluye la conexión a la base de datos.
require_once 'conexion.php';

// 3. Verifica que se hayan enviado datos por POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

// =========================================================================
// INICIO DEL ESCUDO DEFENSIVO (RATE LIMITER BASADO EN SESIÓN)
// =========================================================================

// A. Parámetros de seguridad
$limite_intentos = 5; // Máximo de intentos fallidos permitidos
$tiempo_bloqueo = 300; // Tiempo de castigo en segundos (300s = 5 minutos)

// B. Verificamos si el usuario actual ya se encuentra bajo penalización
if (isset($_SESSION['intentos_fallidos']) && isset($_SESSION['ultimo_intento'])) {
    
    // Calculamos los segundos desde el último error
    $tiempo_transcurrido = time() - $_SESSION['ultimo_intento'];

    // Si ya alcanzó el límite Y aún no termina el tiempo de castigo...
    if ($_SESSION['intentos_fallidos'] >= $limite_intentos && $tiempo_transcurrido < $tiempo_bloqueo) {
        
        // Calculamos cuánto tiempo le falta para ser desbloqueado
        $tiempo_restante = $tiempo_bloqueo - $tiempo_transcurrido;
        
        // Enviamos el mensaje de error personalizado a login.php
        $_SESSION['login_error'] = "Acceso bloqueado por seguridad. Intente de nuevo en " . $tiempo_restante . " segundos.";
        header("Location: login.php");
        
        // ¡CRÍTICO! Usamos exit() para matar el script aquí mismo. 
        // Así evitamos que la base de datos trabaje procesando peticiones masivas.
        exit(); 
        
    } 
    // Si ya alcanzó el límite PERO ya cumplió su tiempo de castigo...
    elseif ($_SESSION['intentos_fallidos'] >= $limite_intentos && $tiempo_transcurrido >= $tiempo_bloqueo) {
        // Le damos una nueva oportunidad reiniciando su contador a cero
        $_SESSION['intentos_fallidos'] = 0;
    }
}
// =========================================================================
// FIN DEL ESCUDO DEFENSIVO
// =========================================================================

// 4. Obtiene los datos del formulario.
$correo = $_POST['correo'];
$password = $_POST['password'];

// 5. Prepara la consulta para buscar al usuario.
$stmt = $conexion->prepare("SELECT id, nombre, password, plan_activo, usos_plan_intermedio FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $correo);
$stmt->execute();
$resultado = $stmt->get_result();

// 6. Verifica si se encontró un usuario.
if ($resultado->num_rows === 1) {
    $usuario = $resultado->fetch_assoc();

    // Compara la contraseña.
    if (password_verify($password, $usuario['password'])) {
        // ¡La contraseña es correcta!
        
        // ✅ ACCIÓN DEFENSIVA: Limpiamos el historial de fallos porque logró entrar.
        $_SESSION['intentos_fallidos'] = 0;
        unset($_SESSION['ultimo_intento']);

        // 7. Guardamos los datos clave del usuario en la sesión.
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['plan_activo'] = (int)$usuario['plan_activo'];
        $_SESSION['usos_plan_intermedio'] = (int)$usuario['usos_plan_intermedio'];

        // 8. Redirigimos al usuario a la página principal.
        header("Location: index.php");
        exit();
    }
}

// --- LÓGICA DE FALLO (Usuario no existe o contraseña incorrecta) ---

// ❌ ACCIÓN DEFENSIVA: Registramos el error en el historial
if (!isset($_SESSION['intentos_fallidos'])) {
    $_SESSION['intentos_fallidos'] = 0; // Inicializamos si es su primer error
}

$_SESSION['intentos_fallidos']++; // Sumamos un error al contador
$_SESSION['ultimo_intento'] = time(); // Guardamos la hora exacta del fallo

// Calculamos cuántos intentos le quedan para avisarle
$intentos_restantes = $limite_intentos - $_SESSION['intentos_fallidos'];

if ($intentos_restantes > 0) {
    // Aún tiene intentos, le avisamos cuántos le quedan
    $_SESSION['login_error'] = "El correo o la contraseña son incorrectos. Te quedan " . $intentos_restantes . " intento(s).";
} else {
    // Fue su último intento fallido, activamos el mensaje de bloqueo inicial
    $_SESSION['login_error'] = "Has superado el límite de intentos. Cuenta bloqueada temporalmente.";
}

// Regresamos a la vista
header("Location: login.php");
exit();
?>