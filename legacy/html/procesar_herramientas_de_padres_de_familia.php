<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No se ha iniciado sesión.']);
    exit;
}
$userId = $_SESSION['usuario_id'];

$usosRestantesFinal = 0;
$conexion->begin_transaction();
try {
    $stmt = $conexion->prepare("SELECT usos_diarios_padres, ultimo_uso_padres FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    $hoy = date('Y-m-d');
    $ultimoUso = $user['ultimo_uso_padres'];

    if ($ultimoUso < $hoy) {
        $stmt_reset = $conexion->prepare("UPDATE usuarios SET usos_diarios_padres = 2, ultimo_uso_padres = ? WHERE id = ?");
        $stmt_reset->bind_param("si", $hoy, $userId);
        $stmt_reset->execute();
        $usosRestantes = 2;
    } else {
        $usosRestantes = $user['usos_diarios_padres'];
    }

    if ($usosRestantes <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Has agotado tus 2 intentos diarios. Vuelve a intentarlo mañana.', 'usos_restantes' => 0]);
        $conexion->rollback();
        exit;
    }

    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_diarios_padres = usos_diarios_padres - 1, ultimo_uso_padres = ? WHERE id = ?");
    $stmt_update->bind_param("si", $hoy, $userId);
    $stmt_update->execute();
    
    $conexion->commit();
    $usosRestantesFinal = $usosRestantes - 1;

} catch (Exception $e) {
    $conexion->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al gestionar los créditos del usuario: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

$json_data = file_get_contents('php://input');
$data = json_decode($json_data);

if (!$data || empty($data->categoria) || empty($data->problema)) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'error' => 'Faltan datos para generar la asesoría.']);
    exit;
}

$categoria = $data->categoria;
$problema = $data->problema;
$descripcion = !empty($data->descripcion) ? $data->descripcion : "No se proporcionó descripción adicional.";

$userMessageContent = "
Actúa como un psicólogo educativo y experto en crianza positiva con gran empatía. Un padre o madre de familia te pide orientación sobre un problema. Tu respuesta debe ser estructurada, clara y práctica.
**Contexto del problema:**
- **Categoría General:** {$categoria}
- **Problema Específico:** {$problema}
- **Descripción Adicional:** {$descripcion}
**Instrucciones para tu respuesta:**
1.  **Formato:** Utiliza Markdown. Usa encabezados (###), listas (*) y negritas (**).
2.  **Tono:** Sé empático y tranquilizador.
3.  **Estructura Obligatoria:** Tu respuesta debe contener: `### 1. Entendiendo la Situación:`, `### 2. Estrategias Prácticas para Aplicar en Casa:`, `### 3. Cómo Comunicarte con tu Hijo/a:`, `### 4. Cuándo Considerar Ayuda Profesional:`.
4.  **Aviso Final:** Termina con: 'Recuerda que esta es una guía informativa.Esta herramienta se deslinda de malas practicas. Si tu preocupación persiste, consulta a un profesional de la salud o tema especifico.'
";

$apiKey = 'REDACTED_OPENAI_API_KEY'; // REEMPLAZA ESTO
$assistantId = 'asst_Z7j3VCMgyrAjA4nWROXQXY5a'; // REEMPLAZA ESTO

function callOpenAI($method, $url, $apiKey, $data = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey, 'OpenAI-Beta: assistants=v2']);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
    }
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpcode >= 400) { return null; }
    return json_decode($response, true);
}

try {
    $thread = callOpenAI('POST', 'https://api.openai.com/v1/threads', $apiKey, new stdClass());
    if (!$thread) throw new Exception('No se pudo crear el hilo.');
    $threadId = $thread['id'];

    callOpenAI('POST', "https://api.openai.com/v1/threads/{$threadId}/messages", $apiKey, ['role' => 'user', 'content' => $userMessageContent]);

    $run = callOpenAI('POST', "https://api.openai.com/v1/threads/{$threadId}/runs", $apiKey, ['assistant_id' => $assistantId]);
    if (!$run) throw new Exception('No se pudo iniciar la ejecución.');
    
    do {
        sleep(1); 
        $runStatus = callOpenAI('GET', "https://api.openai.com/v1/threads/{$threadId}/runs/{$run['id']}", $apiKey);
    } while (in_array($runStatus['status'], ['in_progress', 'queued']));

    if ($runStatus['status'] !== 'completed') { throw new Exception('La ejecución del asistente no se completó.'); }

    $messages = callOpenAI('GET', "https://api.openai.com/v1/threads/{$threadId}/messages", $apiKey);
    $respuestaDeLaIA = $messages['data'][0]['content'][0]['text']['value'] ?? 'No se pudo obtener una respuesta clara.';

    echo json_encode([
        'success' => true,
        'problema' => $problema,
        'recomendacion' => $respuestaDeLaIA,
        'usos_restantes' => $usosRestantesFinal
    ]);

    // ===== CORRECCIÓN 1: Se añade 'exit' para asegurar que el script termine aquí =====
    exit;

} catch (Exception $e) {
    if (isset($conexion) && $conexion->ping()) {
        $stmt_rev = $conexion->prepare("UPDATE usuarios SET usos_diarios_padres = usos_diarios_padres + 1 WHERE id = ?");
        $stmt_rev->bind_param("i", $userId);
        $stmt_rev->execute();
        $stmt_rev->close();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Hubo un problema al generar la asesoría. Tu crédito ha sido devuelto.',
        'details' => $e->getMessage(),
        // ===== CORRECCIÓN 2: Se usa '??' para evitar error si la variable no existe =====
        'usos_restantes' => ($usosRestantesFinal ?? 0) + 1 
    ]);
    exit;
}
?>