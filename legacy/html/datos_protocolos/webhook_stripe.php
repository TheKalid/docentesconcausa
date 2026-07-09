<?php
// webhook_stripe.php (VERSIÓN FINAL CON ASIGNACIÓN DE USOS)

// --- 1. CONFIGURACIÓN INICIAL ---
require_once('stripe-php/init.php');
require_once('conexion.php');

// --- 2. CLAVES SECRETAS Y CONFIGURACIÓN ---
// Claves actualizadas por el usuario.
$stripeSecretKey = 'REDACTED_STRIPE_SECRET_KEY';
$webhookSecret = 'REDACTED_STRIPE_WEBHOOK_SECRET';

\Stripe\Stripe::setApiKey($stripeSecretKey);

// Mapeo de los ID de Precios de Stripe a los números de plan en tu sistema.
$precios_a_planes = [
    'price_1RwGaII8fBGkCwpDIj2Vzpyb' => 1, // Plan Básico (Docente)
    'price_1RwGc2I8fBGkCwpDtGlbySYI' => 2, // Plan Intermedio (Mentor)
    'price_1RmXLAI8fBGkCwpDUxMuQTrW' => 3  // Plan Avanzado (Líder)
];

// --- 3. RECIBIR Y VERIFICAR EL EVENTO DE STRIPE ---
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $webhookSecret
    );
} catch(\UnexpectedValueException $e) {
    http_response_code(400); exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400); exit();
}

// --- 4. MANEJAR EL TIPO DE EVENTO ESPECÍFICO ---
switch ($event->type) {
    case 'checkout.session.completed':
        $session = $event->data->object;
        
        $userId = $session->client_reference_id;
        $subscriptionId = $session->subscription;
        $customerId = $session->customer;
        
        $checkout_items = \Stripe\Checkout\Session::allLineItems($session->id, []);
        $priceId = $checkout_items->data[0]->price->id;
        
        $subscription = \Stripe\Subscription::retrieve($subscriptionId);
        $fecha_proximo_pago_timestamp = $subscription->current_period_end;
        
        if ($userId && isset($precios_a_planes[$priceId])) {
            $plan_a_activar = $precios_a_planes[$priceId];
            // Llamamos a la función que actualiza la base de datos con el plan y los usos.
            activarSuscripcion($conexion, $userId, $plan_a_activar, $subscriptionId, $customerId, $fecha_proximo_pago_timestamp);
        }
        break;

    case 'customer.subscription.deleted':
        $subscription = $event->data->object;
        $subscriptionId = $subscription->id;
        cancelarSuscripcion($conexion, $subscriptionId);
        break;

    default:
        echo 'Evento recibido: ' . $event->type;
}

// --- 5. FUNCIONES DE LÓGICA DE NEGOCIO ---

/**
 * Activa o actualiza el plan de un usuario y asigna los usos correspondientes.
 */
function activarSuscripcion($conexion, $userId, $plan_activo, $subscriptionId, $customerId, $fecha_proximo_pago) {
    
    // ========= INICIO DE LA LÓGICA DE ASIGNACIÓN DE USOS =========
    $usos_plan_basico = 0;
    $usos_evaluacion = 0;
    $usos_fisica = 0;
    $usos_plan_intermedio = 0;
    $usos_protocolos = 0;
    $usos_bitacora = 0;

    if ($plan_activo == 1) { // Nivel 1 (Básico)
        $usos_plan_basico = 5;
        $usos_evaluacion = 5;
        $usos_fisica = 5;
    } elseif ($plan_activo == 2) { // Nivel 2 (Intermedio)
        $usos_plan_intermedio = 7;
        $usos_evaluacion = 7;
        $usos_protocolos = 7;
        $usos_bitacora = 7;
    }
    // Puedes añadir una sección para el plan 3 (Líder) aquí si lo necesitas.

    // Preparamos la consulta SQL para actualizar el plan y AÑADIR los nuevos usos.
    // Usamos `columna = columna + ?` para sumar los nuevos usos a los que ya tuviera.
    $sql = "UPDATE usuarios SET 
                plan_activo = ?, 
                stripe_subscription_id = ?, 
                stripe_customer_id = ?,
                fecha_proximo_pago = FROM_UNIXTIME(?),
                fecha_inicio_ciclo = CURDATE(),
                usos_plan_basico = usos_plan_basico + ?,
                usos_evaluacion_diagnostica = usos_evaluacion_diagnostica + ?,
                usos_fisica = usos_fisica + ?,
                usos_plan_intermedio = usos_plan_intermedio + ?,
                usos_protocolos = usos_protocolos + ?,
                usos_bitacora = usos_bitacora + ?
            WHERE id = ?";
            
    $stmt = $conexion->prepare($sql);
    // El orden y tipo de las variables debe coincidir con los '?' de la consulta.
    // i: integer, s: string
    $stmt->bind_param("issiiiiiiii", 
        $plan_activo, 
        $subscriptionId, 
        $customerId,
        $fecha_proximo_pago,
        $usos_plan_basico,
        $usos_evaluacion,
        $usos_fisica,
        $usos_plan_intermedio,
        $usos_protocolos,
        $usos_bitacora,
        $userId
    );
    $stmt->execute();
    $stmt->close();
    // ========= FIN DE LA LÓGICA DE ASIGNACIÓN DE USOS =========
}

/**
 * Cancela el plan de un usuario (lo pone a 0).
 */
function cancelarSuscripcion($conexion, $subscriptionId) {
    $sql = "UPDATE usuarios SET plan_activo = 0, fecha_proximo_pago = NULL WHERE stripe_subscription_id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $subscriptionId);
    $stmt->execute();
    $stmt->close();
}

// --- 6. RESPONDER A STRIPE ---
http_response_code(200);
?>
