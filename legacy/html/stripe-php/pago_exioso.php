<?php
// Inicia la sesión para poder acceder y modificar las variables de sesión del usuario.
session_start();

// Carga la librería de Stripe y establece tu clave secreta.
require_once('stripe-php/init.php');
\Stripe\Stripe::setApiKey('pk_test_51PmpIaI8fBGkCwpD7J5JkkQohOu4CP7els9FBjYg4FD5JO1SGpYQCyfVpvfRGbaMixeaOhIqZXhmMNmwnW5WpNQq00KfGCgXhj'); // <-- Pega tu clave secreta de prueba

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pago Exitoso</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 40px; }
        .container { max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px; }
        .success-message { color: #2ecc71; }
        .error-message { color: #e74c3c; }
        .cta-button { display: inline-block; background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>

<div class="container">
<?php
try {
    // 1. RECUPERAR LA SESIÓN DE CHECKOUT DESDE LA URL
    $session_id = $_GET['session_id'];
    $session = \Stripe\Checkout\Session::retrieve($session_id);

    // 2. EXTRAER EL ID DE TU USUARIO QUE GUARDAMOS ANTES
    $usuario_id = $session->client_reference_id;
    $estado_pago_stripe = $session->payment_status;

    // 3. VERIFICAR QUE EL PAGO FUE EXITOSO Y QUE TENEMOS UN USUARIO
    if ($usuario_id && $estado_pago_stripe === 'paid') {

        // =================================================================
        // AQUÍ VA TU CÓDIGO PARA ACTUALIZAR LA BASE DE DATOS
        // Ejemplo usando MySQLi con sentencias preparadas (muy recomendado)
        
        $servername = "localhost";
        $username = "root"; // Tu usuario de BD
        $password = ""; // Tu contraseña de BD
        $dbname = "tu_base_de_datos"; // El nombre de tu BD

        $conn = new mysqli($servername, $username, $password, $dbname);
        if ($conn->connect_error) {
            die("Error de conexión: " . $conn->connect_error);
        }

        // Prepara la consulta para actualizar el estado del usuario.
        $sql = "UPDATE usuarios SET estado_pago = ?, plan_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        $estado_pagado = "pagado"; // El nuevo estado
        $plan_comprado = $session->display_items[0]->custom->name; // Obtiene el nombre del plan (ej: "Mentor con Causa")
        
        $stmt->bind_param("ssi", $estado_pagado, $plan_comprado, $usuario_id);
        
        if ($stmt->execute()) {
            // La base de datos se actualizó correctamente.
            // Ahora, actualiza también la sesión de PHP para que los cambios se reflejen de inmediato.
            $_SESSION['estado_pago'] = 'pagado';

            // Muestra un mensaje de éxito al usuario.
            echo '<h1 class="success-message">¡Gracias por tu suscripción!</h1>';
            echo '<p>Tu pago ha sido procesado exitosamente. Ya tienes acceso a todas las herramientas de tu plan.</p>';

        } else {
            // Si la base de datos no se pudo actualizar.
            echo '<h1 class="error-message">Error Crítico</h1>';
            echo '<p>Tu pago fue exitoso, pero no pudimos activar tu cuenta. Por favor, contacta a soporte con el ID de sesión: ' . htmlspecialchars($session_id) . '</p>';
        }
        
        $stmt->close();
        $conn->close();
        // =================================================================

    } else {
        // Si el pago no fue exitoso o no hay un ID de usuario.
        echo '<h1 class="error-message">Pago no completado</h1>';
        echo '<p>Parece que el proceso de pago no se completó correctamente.</p>';
    }

} catch (Exception $e) {
    http_response_code(500);
    echo '<h1 class="error-message">Ocurrió un error</h1>';
    echo '<p>No pudimos verificar el estado de tu pago. Por favor, intenta de nuevo o contacta a soporte.</p>';
}
?>
    <a href="index.php" class="cta-button">Ir a la Página Principal</a>
</div>

</body>
</html>