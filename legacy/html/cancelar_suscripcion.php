<?php
/**
 * cancelar_suscripcion.php
 * Procesa la solicitud de cancelación de un usuario, se comunica con Stripe y redirige.
 */

// 1. Iniciar sesión y verificar que el usuario está logueado
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Incluir los archivos necesarios
require_once 'stripe-php/init.php'; // Ajusta la ruta a tu librería de Stripe
require_once 'conexion.php';       // Ajusta la ruta a tu conexión de BD

// 3. Configurar tu clave secreta de Stripe (la que empieza con sk_live_)
\Stripe\Stripe::setApiKey('sk_live_...'); // <-- PEGA TU CLAVE SECRETA DE PRODUCCIÓN AQUÍ

$usuario_id = $_SESSION['usuario_id'];

try {
    // 4. Obtener el ID de la suscripción del usuario desde tu base de datos
    $stmt = $conexion->prepare("SELECT stripe_subscription_id FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();

    if (!$usuario || empty($usuario['stripe_subscription_id'])) {
        throw new Exception("ID de suscripción no encontrado para este usuario.");
    }
    
    $subscription_id = $usuario['stripe_subscription_id'];

    // 5. Enviar la petición a Stripe para cancelar la suscripción al final del periodo
    \Stripe\Subscription::update($subscription_id, [
      'cancel_at_period_end' => true,
    ]);

    // 6. Si todo sale bien, redirigir de vuelta al perfil con un mensaje de éxito
    header("Location: perfil.php?status=cancel_success");
    exit();

} catch (Exception $e) {
    // 7. Para cualquier error, redirigir al perfil con un mensaje de error
    // En un entorno real, también guardarías el error en un archivo de logs.
    error_log("Error al cancelar suscripción para usuario $usuario_id: " . $e->getMessage());
    header("Location: perfil.php?status=cancel_error");
    exit();
}
?>