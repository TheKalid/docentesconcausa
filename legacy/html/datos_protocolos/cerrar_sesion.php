<?php
// 1. Inicia la sesión para poder acceder a ella.
// Es el primer paso obligatorio para manipular sesiones.
session_start();

// 2. Elimina todas las variables de la sesión.
// Esto borra $_SESSION['usuario_id'], $_SESSION['usuario_nombre'], etc.
$_SESSION = array();

// 3. Destruye la sesión por completo.
// Este paso es más profundo, ya que elimina la cookie de sesión del
// navegador del usuario, asegurando que no pueda ser reutilizada.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 4. Redirige al usuario a la página de inicio.
// Ahora, cuando el archivo index.php se cargue, revisará la sesión,
// no encontrará ningún dato de usuario y mostrará la página como
// si fuera para un visitante nuevo (con el botón "Iniciar Sesión").
header("Location: index.php");
exit();
?>