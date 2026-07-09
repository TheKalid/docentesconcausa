<?php
// procesar_planeacion_fisica.php (VERSIÓN FINAL ADAPTADA A TU SISTEMA)

// ========= INICIO DE LA LÓGICA DE CRÉDITOS Y SESIÓN =========
session_start();
require_once 'conexion.php';

// CORREGIDO: Buscamos 'usuario_id' para ser consistente con tu login.
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Acceso no autorizado.', 'details' => 'No se encontró un ID de usuario en la sesión.']);
    exit;
}
$usuario_id = $_SESSION['usuario_id']; // CORREGIDO: Usamos la variable correcta.

$json_data_input = file_get_contents('php://input');
$data_input = json_decode($json_data_input, true);
$tipo_uso = $data_input['tipo_uso'] ?? '';

if ($tipo_uso !== 'usos_fisica') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tipo de generador no válido.', 'details' => 'Este procesador solo acepta el tipo "usos_fisica".']);
    exit;
}

$usosRestantes = 0;
$conexion->begin_transaction();
try {
    $stmt = $conexion->prepare("SELECT usos_fisica FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $usos_actuales = $user['usos_fisica'] ?? 0;

    if (!$user || $usos_actuales <= 0) {
        throw new Exception('Lo sentimos, has agotado tus usos para esta herramienta.');
    }

    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_fisica = usos_fisica - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $usuario_id);
    $stmt_update->execute();
    $conexion->commit();
    $usosRestantes = $usos_actuales - 1;

} catch (Exception $e) {
    $conexion->rollback();
    http_response_code(402);
    echo json_encode(['success' => false, 'error' => 'Error con los créditos de usuario.', 'details' => $e->getMessage()]);
    exit;
}
// ========= FIN DE LA LÓGICA DE CRÉDITOS Y SESIÓN =========


//======================================================================
// INICIO DE TU CÓDIGO ORIGINAL (Manejo de la Petición y API)
//======================================================================
$apiKeyGeneral = 'REDACTED_OPENAI_API_KEY';
$apiEndpoint = 'https://api.openai.com/v1/completions';

// Reutilizamos los datos ya leídos al principio.
$data = $data_input;
if (empty($data['prompt'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Petición inválida. No se recibió el prompt.']);
    exit();
}

$promptParaIA = $data['prompt'];

try {
    $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKeyGeneral];
    $postData = json_encode([
        'model' => 'gpt-3.5-turbo-instruct',
        'prompt' => $promptParaIA,
        'max_tokens' => 2048,
        'temperature' => 0.7
    ]);

    $ch = curl_init($apiEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) { throw new Exception('Error de conexión con la API externa: ' . curl_error($ch)); }
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) { throw new Exception("La API de OpenAI devolvió un error HTTP {$httpCode}: " . $response); }
    
    // --- RESPUESTA FINAL ESTRUCTURADA ---
    $openai_data = json_decode($response, true);
    echo json_encode([
        'success' => true,
        'plan' => $openai_data,
        'usos_restantes' => $usosRestantes
    ]);

} catch (Exception $e) {
    // Si la llamada a la IA falla, devolvemos el crédito.
    $conexion_rev = new mysqli($servidor, $usuario_db, $password_db, $nombre_db);
    if (!$conexion_rev->connect_error) {
        $stmt_rev = $conexion_rev->prepare("UPDATE usuarios SET usos_fisica = usos_fisica + 1 WHERE id = ?");
        $stmt_rev->bind_param("i", $usuario_id);
        $stmt_rev->execute();
        $conexion_rev->close();
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ha ocurrido un error en el servidor.', 'details' => $e->getMessage()]);
    exit;
}
?>