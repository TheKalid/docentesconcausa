<?php
session_start();
header('Content-Type: application/json');

// Validar seguridad: Solo el admin que pasó por la pantalla previa puede hacer esto
if (!isset($_SESSION['acceso_admin_concedido']) || $_SESSION['acceso_admin_concedido'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Acceso no autorizado.']);
    exit();
}

require_once 'conexion.php';

// Obtener datos enviados desde JavaScript
$datos = json_decode(file_get_contents('php://input'), true);
$email = $datos['email'] ?? '';
$plan_activo = intval($datos['plan'] ?? 0);

if (empty($email) || $plan_activo === 0) {
    echo json_encode(['success' => false, 'error' => 'Faltan datos (correo o plan).']);
    exit();
}

// 1. ASIGNACIÓN DE USOS (Misma lógica de webhook_stripe.php)
$usos_plan_basico = 0;
$usos_evaluacion = 0;
$usos_fisica = 0;
$usos_plan_intermedio = 0;
$usos_protocolos = 0;
$usos_bitacora = 0;
$usos_examenes = 0;

if ($plan_activo == 1) { 
    $usos_plan_basico = 5;
    $usos_evaluacion = 5;
    $usos_fisica = 5;
} elseif ($plan_activo == 2) { 
    $usos_plan_intermedio = 7;
    $usos_evaluacion = 7;
    $usos_protocolos = 7;
    $usos_bitacora = 7;
    $usos_examenes = 5; 
}

// 2. ACTUALIZAR BASE DE DATOS
// Al ser un pago único en efectivo, sumamos los créditos y actualizamos la fecha de inicio, 
// pero dejamos el stripe_subscription_id en NULL o intacto.
$sql = "UPDATE usuarios SET 
            plan_activo = ?, 
            fecha_inicio_ciclo = CURDATE(),
            usos_plan_basico = usos_plan_basico + ?,
            usos_evaluacion_diagnostica = usos_evaluacion_diagnostica + ?,
            usos_fisica = usos_fisica + ?,
            usos_plan_intermedio = usos_plan_intermedio + ?,
            usos_protocolos = usos_protocolos + ?,
            usos_bitacora = usos_bitacora + ?,
            usos_examenes = usos_examenes + ? 
        WHERE email = ?";

$stmt = $conexion->prepare($sql);

// "iiiiiiiis" -> 8 enteros, 1 string (el email)
$stmt->bind_param("iiiiiiiis", 
    $plan_activo, 
    $usos_plan_basico,
    $usos_evaluacion,
    $usos_fisica,
    $usos_plan_intermedio,
    $usos_protocolos,
    $usos_bitacora,
    $usos_examenes,
    $email
);

$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    // Si no se afectó ninguna fila, es probable que el correo no exista en la base de datos
    echo json_encode(['success' => false, 'error' => 'No se encontró ningún usuario con ese correo o los datos son idénticos.']);
}

$stmt->close();
$conexion->close();
?>