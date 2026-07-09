<?php
// --- CÓDIGO DE DEPURACIÓN ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
// --- FIN DE DEPURACIÓN ---

session_start();

require_once('stripe-php/init.php');

// --- Configuración de Stripe ---
// ✅ CORRECCIÓN CLAVE: Usamos la Clave Secreta que es consistente con tu webhook.
// Asegúrate de que esta sea tu clave secreta de PRUEBA más reciente de tu Dashboard de Stripe.
\Stripe\Stripe::setApiKey('REDACTED_STRIPE_SECRET_KEY
'); 

header('Content-Type: application/json');

// --- Precios de los planes ---
// Estos IDs deben corresponder a los precios que creaste en tu Dashboard de Stripe.
$precios = [
    'basico' => 'price_1RwGaII8fBGkCwpDIj2Vzpyb', // Reemplaza si es necesario
    'mentor' => 'price_1RwGc2I8fBGkCwpDtGlbySYI', // Reemplaza si es necesario
];

try {
    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(401); 
        echo json_encode(['error' => 'Debes iniciar sesión para realizar un pago.']);
        exit();
    }
    
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data);
    
    if (!isset($data->plan) || !isset($precios[$data->plan])) {
        http_response_code(400);
        echo json_encode(['error' => 'El plan seleccionado no es válido.']);
        exit();
    }
    $planId = $data->plan;

    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price' => $precios[$planId],
            'quantity' => 1,
        ]],
        'mode' => 'subscription',
        'client_reference_id' => $_SESSION['usuario_id'], 
        'success_url' => 'http://localhost/Pagina%20web%202/pago_exitoso.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => 'http://localhost/Pagina%20web%202/catalogo_de_pagos.php',
    ]);

    echo json_encode(['id' => $checkout_session->id]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>