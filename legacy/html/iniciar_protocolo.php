<?php
// iniciar_protocolo.php
// Paso 1: Inicia la consulta y devuelve los IDs (threadId, runId) para verificar después.

// === CONFIGURACIÓN DE SEGURIDAD Y CORS ===
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// === VERIFICACIONES DE SESIÓN Y USOS (Tu código original) ===
session_start();
require_once 'conexion.php'; 

// 1. Verificación de que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401); // No autorizado
    echo json_encode(['status' => 'error', 'error' => 'No has iniciado sesión.']);
    exit;
}
$usuario_id = $_SESSION['usuario_id'];

// 2. Verificación de plan y usos (Leemos de la BD para estar seguros)
$stmt_usos = $conexion->prepare("SELECT plan_activo, usos_protocolos FROM usuarios WHERE id = ?");
$stmt_usos->bind_param("i", $usuario_id);
$stmt_usos->execute();
$user_data = $stmt_usos->get_result()->fetch_assoc();
$stmt_usos->close();

if (!$user_data) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Usuario no encontrado.']);
    exit;
}

// Sincronizamos sesión para el chequeo
$_SESSION['plan_activo'] = (int)$user_data['plan_activo'];
$_SESSION['usos_protocolos'] = (int)$user_data['usos_protocolos'];

$nivel_requerido = 2;
if ($_SESSION['plan_activo'] < $nivel_requerido) {
    http_response_code(403); // Prohibido
    echo json_encode(['status' => 'error', 'error' => 'No tienes el plan requerido.']);
    exit;
}

if ($_SESSION['usos_protocolos'] <= 0) {
    http_response_code(403); // Prohibido
    echo json_encode(['status' => 'error', 'error' => 'Has agotado tus consultas de protocolos.']);
    exit;
}
// === FIN VERIFICACIONES ===


// === CONFIGURACIÓN DE API (Tus claves originales) ===
$apiKey = 'REDACTED_OPENAI_API_KEY'; 
$assistantId = 'asst_BFz9EIi5blO5SUrYXFLgkGME'; 

// === FUNCIÓN DE CURL (Tu función original) ===
function callOpenAI_API($method, $url, $apiKey, $data = null) {
    $ch = curl_init();
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'OpenAI-Beta: assistants=v2'
    ];
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        throw new Exception("Error de cURL: " . $error_msg);
    }
    curl_close($ch);
    $decodedResponse = json_decode($response, true);
    if ($httpCode >= 400) {
        $errorMessage = $decodedResponse['error']['message'] ?? 'Error desconocido en la API de OpenAI.';
        throw new Exception("Error de API (HTTP $httpCode): " . $errorMessage);
    }
    return $decodedResponse;
}

// === LÓGICA PRINCIPAL: INICIAR LA TAREA ===
try {
    $input = json_decode(file_get_contents('php://input'));
    if (json_last_error() !== JSON_ERROR_NONE || !isset($input->consulta) || !isset($input->nivel)) {
        throw new Exception('Datos de entrada inválidos. Se requiere "consulta" y "nivel".', 400);
    }
    $userQuery = $input->consulta;
    $userLevel = $input->nivel;

    // === ¡PROMPT MEJORADO! ===
    // Le pedimos JSON a la IA (para que no falle), pero le ordenamos
    // que ponga todo el contenido "bonito" (Markdown) DENTRO de la clave 'protocolo'.
    // Usamos el prompt simple de tu archivo original pero adaptado.
    $fullPrompt = "Responde como un experto en la Nueva Escuela Mexicana. La situación es: \"{$userQuery}\" en el nivel \"{$userLevel}\". 

    Tu respuesta DEBE ser un objeto JSON válido con la estructura:
    {
      \"protocolo\": \"...\",
      \"fuentes\": [\"...\"],
      \"sugerencias\": [\"...\"]
    }
    
    REGLAS CRÍTICAS:
    1.  El valor de la clave 'protocolo' DEBE ser un texto MUY extenso y detallado en formato MARKDOWN.
    2.  Este texto MARKDOWN debe incluir toda la información:
        * ## Fundamentación Legal
        * ## Procedimiento de Actuación
        * ## Sugerencias y Consideraciones
    3.  El contenido de los arrays 'fuentes' y 'sugerencias' NO es importante. Puedes dejar los arrays vacíos.
    4.  TODA la información valiosa debe estar dentro del string 'protocolo' en formato MARKDOWN.
    
    NO RESPONDAS NADA FUERA DEL JSON. Tu respuesta debe empezar con { y terminar con }.";

    // 1. Crear el Thread
    $thread = callOpenAI_API('POST', 'https://api.openai.com/v1/threads', $apiKey);
    $threadId = $thread['id'];

    // 2. Añadir el Mensaje al Thread
    $messageData = ['role' => 'user', 'content' => $fullPrompt];
    callOpenAI_API('POST', "https://api.openai.com/v1/threads/{$threadId}/messages", $apiKey, $messageData);

    // 3. Ejecutar el Asistente (Run)
    $runData = ['assistant_id' => $assistantId];
    $run = callOpenAI_API('POST', "https://api.openai.com/v1/threads/{$threadId}/runs", $apiKey, $runData);
    $runId = $run['id'];
    
    // 4. Devolver los IDs (el "ticket") al frontend para que pueda verificar
    echo json_encode([
        'status' => 'iniciado', 
        'threadId' => $threadId, 
        'runId' => $runId
    ]);

} catch (Exception $e) {
    $errorCode = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($errorCode);
    echo json_encode(['status' => 'error', 'error'   => $e->getMessage()]);
} finally {
    if (isset($conexion)) {
        $conexion->close();
    }
}
?>