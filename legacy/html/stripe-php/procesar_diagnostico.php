<?php
// procesar_diagnostico.php

// === NUEVO: INICIAR SESIÓN Y CONECTAR A LA BASE DE DATOS ===
// Es indispensable para verificar los usos del usuario.
session_start();
require_once 'conexion.php';

header('Content-Type: application/json');

// === NUEVO: BLOQUE DE VERIFICACIÓN Y DECREMENTO DE USOS ===
// 1. Obtener el ID del usuario que ha iniciado sesión.
$usuario_id = $_SESSION['usuario_id'] ?? 0;
if ($usuario_id === 0) {
    http_response_code(401); // Error: No Autorizado
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado. Por favor, inicie sesión.']);
    exit;
}

// 2. Consultar los usos actuales de la base de datos.
$stmt_check = $conexion->prepare("SELECT usos_evaluacion_diagnostica FROM usuarios WHERE id = ?");
$stmt_check->bind_param("i", $usuario_id);
$stmt_check->execute();
$resultado = $stmt_check->get_result();
$usuario_data = $resultado->fetch_assoc();
$usos_actuales = (int)($usuario_data['usos_evaluacion_diagnostica'] ?? 0);
$stmt_check->close();

// 3. Verificar si al usuario le quedan usos. Si no, detener el script.
if ($usos_actuales <= 0) {
    http_response_code(403); // Error: Prohibido
    echo json_encode(['success' => false, 'error' => 'Has agotado tus usos disponibles para esta herramienta.']);
    exit;
}

// 4. Si hay usos disponibles, se descuenta 1 de la base de datos.
$stmt_update = $conexion->prepare("UPDATE usuarios SET usos_evaluacion_diagnostica = usos_evaluacion_diagnostica - 1 WHERE id = ?");
$stmt_update->bind_param("i", $usuario_id);
$stmt_update->execute();
$stmt_update->close();

// 5. Calculamos el nuevo total para devolverlo al frontend.
$usos_restantes_despues_del_descuento = $usos_actuales - 1;
// === FIN DEL BLOQUE DE VERIFICACIÓN ===


// ❗ IMPORTANTE: Reemplaza con tu clave API.
$apiKey = 'REDACTED_OPENAI_API_KEY';
$assistantId = 'asst_ioEqKORagJBPT3x2bHdokLFR';
$apiUrl = 'https://api.openai.com/v1/';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['grado']) || !isset($input['areas'])) {
    echo json_encode(['success' => false, 'error' => 'Datos de entrada inválidos.']);
    exit;
}

$grado = htmlspecialchars($input['grado']);
$areas = implode(', ', array_map('htmlspecialchars', $input['areas']));
$numEstudiantes = isset($input['num_estudiantes']) && !empty($input['num_estudiantes']) ? htmlspecialchars($input['num_estudiantes']) : 'No especificado';
$necesidad = isset($input['necesidad']) && !empty($input['necesidad']) ? htmlspecialchars($input['necesidad']) : 'Ninguna especificada.';

$userPrompt = "Genera una evaluación diagnóstica para el siguiente caso:\n\n" .
              "- **Grado escolar:** " . str_replace('_', ' ', $grado) . "\n" .
              "- **Áreas a diagnosticar:** " . $areas . "\n" .
              "- **Número de estudiantes en el grupo:** " . $numEstudiantes . "\n" .
              "- **Necesidad o contexto específico:** " . $necesidad . "\n\n" .
              "Asegúrate de seguir el perfil, las áreas a diagnosticar y los pasos para elaborar la evaluación que tienes definidos en tus instrucciones. La respuesta debe ser clara, estructurada y lista para que un docente la aplique.";

function callOpenAI_API($method, $url, $apiKey, $data = null) {
    $ch = curl_init($url);
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'OpenAI-Beta: assistants=v2'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpcode >= 400) {
        return ['error' => true, 'details' => json_decode($response, true)];
    }
    return json_decode($response, true);
}

function formatAIResponse($text) {
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/^\* (.*$)/m', '<li>$1</li>', $text);
    $text = preg_replace('/^- (.*$)/m', '<li>$1</li>', $text);
    $text = preg_replace('/(<li>.*<\/li>\s*)+/s', "<ul>\n$0</ul>\n", $text);
    $text = nl2br(trim($text), false);
    $text = str_replace(["<ul>\n<br />", "<br />\n</ul>", "</li><br />"], ["<ul>", "</ul>", "</li>"], $text);
    return $text;
}

try {
    $thread = callOpenAI_API('POST', $apiUrl . 'threads', $apiKey, new stdClass());
    if (isset($thread['error'])) throw new Exception("Error al crear el hilo: " . json_encode($thread['details']));
    $threadId = $thread['id'];

    $messageData = ['role' => 'user', 'content' => $userPrompt];
    callOpenAI_API('POST', $apiUrl . 'threads/' . $threadId . '/messages', $apiKey, $messageData);

    $runData = ['assistant_id' => $assistantId];
    $run = callOpenAI_API('POST', $apiUrl . 'threads/' . $threadId . '/runs', $apiKey, $runData);
    $runId = $run['id'];

    $status = '';
    $max_retries = 30;
    $retries = 0;
    while ($status !== 'completed' && $retries < $max_retries) {
        sleep(2);
        $runStatus = callOpenAI_API('GET', $apiUrl . 'threads/' . $threadId . '/runs/' . $runId, $apiKey);
        $status = $runStatus['status'];
        if (in_array($status, ['failed', 'cancelled', 'expired'])) {
            throw new Exception("La ejecución del asistente falló con el estado: " . $status);
        }
        $retries++;
    }

    if ($status !== 'completed') {
        throw new Exception("La ejecución del asistente no se completó a tiempo.");
    }

    $messages = callOpenAI_API('GET', $apiUrl . 'threads/' . $threadId . '/messages', $apiKey);
    $aiResponse = '';
    foreach ($messages['data'] as $msg) {
        if ($msg['role'] === 'assistant') {
            $aiResponse = $msg['content'][0]['text']['value'];
            break; 
        }
    }

    if (empty($aiResponse)) {
         throw new Exception("No se recibió una respuesta del asistente.");
    }

    $formattedResponse = formatAIResponse($aiResponse);
    
    // === NUEVO: SE AÑADE EL CONTEO DE USOS A LA RESPUESTA EXITOSA ===
    echo json_encode([
        'success' => true, 
        'data' => $formattedResponse,
        'usos_restantes' => $usos_restantes_despues_del_descuento // Se envía el nuevo total.
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>