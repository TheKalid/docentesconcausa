<?php

/**
 * Endpoint para consultar protocolos de la Nueva Escuela Mexicana (NEM)
 * utilizando un Asistente de OpenAI.
 * VERSIÓN FINAL CORREGIDA CON CONTADOR Y SEGURIDAD
 */

// === CONFIGURACIÓN DE SEGURIDAD Y CORS ===
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// === VERIFICACIONES DE SESIÓN Y USOS ===
session_start();
// Se asume que conexion.php está en la misma carpeta. Si no es así, esta es la línea a cambiar.
require_once 'conexion.php'; 

// 1. Verificación de que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401); // No autorizado
    echo json_encode(['success' => false, 'error' => 'No has iniciado sesión para realizar esta acción.']);
    exit;
}

// 2. Verificación de que el usuario tenga el plan activo
$nivel_requerido = 2; 
if (($_SESSION['plan_activo'] ?? 0) < $nivel_requerido) {
    http_response_code(403); // Prohibido
    echo json_encode(['success' => false, 'error' => 'No tienes el plan requerido para acceder a este recurso.']);
    exit;
}

// 3. Verificación de usos restantes para este módulo
if (!isset($_SESSION['usos_protocolos'])) {
    // Si la sesión no tiene el dato, lo leemos de la BD
    $stmt_usos = $conexion->prepare("SELECT usos_protocolos FROM usuarios WHERE id = ?");
    $stmt_usos->bind_param("i", $_SESSION['usuario_id']);
    $stmt_usos->execute();
    $result_usos = $stmt_usos->get_result();
    if ($user_usos = $result_usos->fetch_assoc()) {
        $_SESSION['usos_protocolos'] = (int)$user_usos['usos_protocolos'];
    }
    $stmt_usos->close();
}

if ($_SESSION['usos_protocolos'] <= 0) {
    http_response_code(403); // Prohibido
    echo json_encode(['success' => false, 'error' => 'Has agotado tus consultas de protocolos disponibles.']);
    exit;
}
// === FIN DE VERIFICACIONES ===


// === CONFIGURACIÓN DE LAS CLAVES DE API (Tu código original) ===
$apiKey = 'REDACTED_OPENAI_API_KEY'; 
$assistantId = 'asst_BFz9EIi5blO5SUrYXFLgkGME';   


// === FUNCIÓN PARA COMUNICARSE CON LA API DE OPENAI (Tu código original) ===
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
        if (strpos($errorMessage, 'Incorrect API key') !== false) {
            $errorMessage = 'La API Key proporcionada es incorrecta. Asegúrate de haberla copiado bien y que esté activa.';
        }
        throw new Exception("Error de API (HTTP $httpCode): " . $errorMessage);
    }
    return $decodedResponse;
}


// === LÓGICA PRINCIPAL DEL SCRIPT (Tu código original) ===
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido. Solo se aceptan peticiones POST.', 405);
    }

    $input = json_decode(file_get_contents('php://input'));
    if (json_last_error() !== JSON_ERROR_NONE || !isset($input->consulta) || !isset($input->nivel)) {
        throw new Exception('Datos de entrada inválidos. Se requiere un JSON con "consulta" y "nivel".', 400);
    }
    $userQuery = $input->consulta;
    $userLevel = $input->nivel;
    $fullPrompt = "Nivel educativo: {$userLevel}. Situación a resolver: \"{$userQuery}\".";

    $thread = callOpenAI_API('POST', 'https://api.openai.com/v1/threads', $apiKey);
    $threadId = $thread['id'];

    $messageData = ['role' => 'user', 'content' => $fullPrompt];
    callOpenAI_API('POST', "https://api.openai.com/v1/threads/{$threadId}/messages", $apiKey, $messageData);

    $runData = ['assistant_id' => $assistantId];
    $run = callOpenAI_API('POST', "https://api.openai.com/v1/threads/{$threadId}/runs", $apiKey, $runData);
    $runId = $run['id'];

    $startTime = time();
    do {
        if (time() - $startTime > 30) { 
            throw new Exception("La ejecución del asistente tardó demasiado tiempo en responder.");
        }
        sleep(1);
        $run = callOpenAI_API('GET', "https://api.openai.com/v1/threads/{$threadId}/runs/{$runId}", $apiKey);
    } while (in_array($run['status'], ['queued', 'in_progress']));

    if ($run['status'] !== 'completed') {
        $errorDetails = $run['last_error']['message'] ?? 'La ejecución no se completó.';
        throw new Exception("La ejecución del asistente falló: " . $errorDetails);
    }

    $messagesList = callOpenAI_API('GET', "https://api.openai.com/v1/threads/{$threadId}/messages", $apiKey);
    $assistantResponseContent = $messagesList['data'][0]['content'][0]['text']['value'];
    
    // --- AÑADIDO: DESCUENTO DE USOS ---
    $usuario_id = $_SESSION['usuario_id'];
    $stmt = $conexion->prepare("UPDATE usuarios SET usos_protocolos = usos_protocolos - 1 WHERE id = ? AND usos_protocolos > 0");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['usos_protocolos']--;
    // --- FIN DE DESCUENTO ---
    
    $responseData = json_decode($assistantResponseContent, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $fallbackData = [
            'protocolo' => $assistantResponseContent,
            'fuentes' => ["Respuesta generada por IA, formato no estructurado."],
            'sugerencias' => ["Revisar la respuesta con cuidado."]
        ];
        echo json_encode(['success' => true, 'data' => $fallbackData]);
    } else {
        echo json_encode(['success' => true, 'data' => $responseData]);
    }

} catch (Exception $e) {
    $errorCode = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($errorCode);
    echo json_encode(['success' => false, 'error'   => $e->getMessage()]);
} finally {
    if (isset($conexion)) {
        $conexion->close();
    }
}

// CORRECCIÓN: La llave '}' extra que estaba aquí ha sido eliminada.
?>