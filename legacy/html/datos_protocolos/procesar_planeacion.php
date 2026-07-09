<?php
// ========= INICIO DE LA SECCIÓN DE LÓGICA Y CRÉDITOS =========
session_start();

// Incluimos nuestro archivo de conexión centralizado.
require_once 'conexion.php';

// Validar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No se ha iniciado sesión.']);
    exit;
}
$userId = $_SESSION['usuario_id'];

// --- Lógica para verificar y decrementar usos ---
$usosRestantes = 0;
// Usamos la variable $conexion que viene del archivo 'conexion.php'
$conexion->begin_transaction();
try {
    $stmt = $conexion->prepare("SELECT usos_plan_basico FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || $user['usos_plan_basico'] <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Lo sentimos, has agotado tus usos para generar planeaciones básicas.', 'usos_restantes' => 0]);
        $conexion->rollback();
        exit;
    }

    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_plan_basico = usos_plan_basico - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $userId);
    $stmt_update->execute();
    $conexion->commit();
    $usosRestantes = $user['usos_plan_basico'] - 1;

} catch (Exception $e) {
    $conexion->rollback();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al gestionar los créditos del usuario: ' . $e->getMessage()]);
    exit;
}
// ========= FIN DE LA SECCIÓN =========

// === CONFIGURACIÓN DE RESPUESTA Y DATOS (El código original empieza aquí) ===
header('Content-Type: application/json');

// --- Paso 1: Recibir y Validar los Datos del Frontend ---
$json_data = file_get_contents('php://input');
$data = json_decode($json_data);

if (!$data || empty($data->grado) || empty($data->pda) || empty($data->tiempo)) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'error' => 'Faltan datos para generar la planeación (grado, pda o tiempo).']);
    exit;
}

// --- Paso 2: Preparar los Datos para el Asistente ---
$grado = $data->grado;
$asignatura = $data->asignatura;
$campoFormativo = $data->campoFormativo;
$contenido = $data->contenido;
$pda = $data->pda;
$ejes = implode(', ', $data->ejesArticuladores);
$tiempo = $data->tiempo;

$userMessageContent = "
Genera una planeación didáctica con los siguientes datos específicos.
MUY IMPORTANTE: Formatea los datos principales (Proyecto, Grado, Campo Formativo, etc.) como una lista con viñetas (usando el símbolo de guion '-') al inicio de la planeación. El resto de la planeación debe seguir el formato de sesiones.

- Grado: {$grado}
- Asignatura: {$asignatura}
- Campo Formativo: {$campoFormativo}
- Contenido: {$contenido}
- PDA: {$pda}
- Ejes Articuladores: {$ejes}
- Duración sugerida: {$tiempo}
";


// --- Paso 3: Flujo de Comunicación con la API de Asistentes de OpenAI ---
$apiKey = 'REDACTED_OPENAI_API_KEY';
$assistantId = 'asst_RqcniT2LtB1ahDRS4GTb6zmO';
$openAiApiVersion = 'v2';

function callOpenAI($method, $url, $apiKey, $data = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'OpenAI-Beta: assistants=v2'
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode >= 400) {
       error_log("OpenAI API call failed with status code $httpcode: $response");
       return null;
    }
    
    return json_decode($response, true);
}


try {
    $thread = callOpenAI('POST', 'https://api.openai.com/v1/threads', $apiKey, new stdClass());
    if (!$thread) throw new Exception('No se pudo crear el hilo (thread).');
    $threadId = $thread['id'];

    $messageData = ['role' => 'user', 'content' => $userMessageContent];
    callOpenAI('POST', "https://api.openai.com/v1/threads/{$threadId}/messages", $apiKey, $messageData);

    $runData = ['assistant_id' => $assistantId];
    $run = callOpenAI('POST', "https://api.openai.com/v1/threads/{$threadId}/runs", $apiKey, $runData);
    if (!$run) throw new Exception('No se pudo iniciar la ejecución (run).');
    $runId = $run['id'];
    
    do {
        sleep(1); 
        $runStatus = callOpenAI('GET', "https://api.openai.com/v1/threads/{$threadId}/runs/{$runId}", $apiKey);
    } while (in_array($runStatus['status'], ['in_progress', 'queued']));

    if ($runStatus['status'] !== 'completed') {
        throw new Exception('La ejecución del asistente no se completó. Estado: ' . $runStatus['status']);
    }

    $messages = callOpenAI('GET', "https://api.openai.com/v1/threads/{$threadId}/messages", $apiKey);
    
    $respuestaIA_JSON_String = $messages['data'][0]['content'][0]['text']['value'] ?? '{}';

    // ========= SECCIÓN MODIFICADA: Devolver la Respuesta Estructurada al Frontend =========
    $respuestaIA_Data = json_decode($respuestaIA_JSON_String, true);

    echo json_encode([
        'success' => true,
        'plan' => $respuestaIA_Data, // La planeación va anidada aquí
        'usos_restantes' => $usosRestantes // Enviamos el nuevo total de usos
    ]);

} catch (Exception $e) {
    // Si la llamada a la IA falla, debemos intentar devolver el crédito al usuario.
    $mysqli_rev = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$mysqli_rev->connect_error) {
        $stmt_rev = $mysqli_rev->prepare("UPDATE usuarios SET usos_plan_basico = usos_plan_basico + 1 WHERE id = ?");
        $stmt_rev->bind_param("i", $userId);
        $stmt_rev->execute();
        $mysqli_rev->close();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Hubo un problema al generar la planeación. Tu crédito ha sido devuelto.',
        'details' => $e->getMessage(),
        'usos_restantes' => $usosRestantes + 1
    ]);
    exit;
}
?>