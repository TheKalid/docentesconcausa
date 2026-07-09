<?php
// Muestra todos los errores para facilitar la depuración (puedes comentarlo o quitarlo en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Establecemos la cabecera para devolver una respuesta en formato JSON
header('Content-Type: application/json');

// --- 1. INICIAMOS SESIÓN Y CONEXIÓN A LA BASE DE DATOS ---
session_start();
require_once 'conexion.php'; // Incluimos la conexión a la base de datos

// --- 2. VERIFICAMOS LOGIN Y USOS DISPONIBLES ANTES DE HACER NADA ---
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401); // No autorizado
    echo json_encode(['error' => 'No has iniciado sesión.', 'details' => 'Por favor, inicia sesión para continuar.']);
    exit;
}

// Leemos la variable de sesión correcta que establecimos en el login
$usos_actuales = $_SESSION['usos_plan_intermedio'] ?? 0;
if ($usos_actuales <= 0) {
    http_response_code(403); // Prohibido (Forbidden)
    echo json_encode(['error' => 'Usos agotados.', 'details' => 'Has agotado las generaciones de tu plan. Mejora tu suscripción para obtener más.']);
    exit;
}

// --- FUNCIÓN COMPLETA PARA LLAMAR A LA API DE ASISTENTES DE OPENAI ---
function callOpenAI($apiKey, $assistantId, $prompt) {
    $apiBaseUrl = 'https://api.openai.com/v1';

    // Función interna para realizar llamadas cURL
    function makeCurlRequest($url, $method, $apiKey, $data = null) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'OpenAI-Beta: assistants=v2' // Cabecera requerida para la API de Asistentes v2
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = 'cURL Error: ' . curl_error($ch);
            curl_close($ch);
            throw new Exception($error);
        }
        curl_close($ch);
        return json_decode($response);
    }

    // Paso 1: Crear un Hilo (Thread)
    $thread = makeCurlRequest($apiBaseUrl . '/threads', 'POST', $apiKey);
    if (!isset($thread->id)) {
        throw new Exception('Error al crear el hilo (thread) de OpenAI.');
    }
    $threadId = $thread->id;

    // Paso 2: Añadir un Mensaje al Hilo
    $messageData = ['role' => 'user', 'content' => $prompt];
    makeCurlRequest($apiBaseUrl . '/threads/' . $threadId . '/messages', 'POST', $apiKey, $messageData);

    // Paso 3: Ejecutar el Asistente (Run)
    $runData = ['assistant_id' => $assistantId];
    // ***** ESTA ES LA LÍNEA QUE SE CORRIGIÓ *****
    $run = makeCurlRequest($apiBaseUrl . '/threads/' . $threadId . '/runs', 'POST', $apiKey, $runData);
    if (!isset($run->id)) {
        throw new Exception('Error al ejecutar el asistente de OpenAI.');
    }
    $runId = $run->id;

    // Paso 4: Esperar a que la ejecución se complete
    $startTime = time();
    do {
        if (time() - $startTime > 90) { // Timeout de 90 segundos
            throw new Exception('La solicitud a la IA ha tardado demasiado en responder.');
        }
        sleep(2); // Esperar 2 segundos entre cada verificación
        $runStatus = makeCurlRequest($apiBaseUrl . '/threads/' . $threadId . '/runs/' . $runId, 'GET', $apiKey);
    } while ($runStatus->status === 'in_progress' || $runStatus->status === 'queued');

    if ($runStatus->status !== 'completed') {
        throw new Exception('La ejecución del asistente falló con estado: ' . $runStatus->status);
    }

    // Paso 5: Obtener los mensajes del Hilo (la respuesta del asistente)
    $messages = makeCurlRequest($apiBaseUrl . '/threads/' . $threadId . '/messages', 'GET', $apiKey);
    
    // La respuesta más reciente del asistente es la primera en la lista
    foreach ($messages->data as $msg) {
        if ($msg->role === 'assistant') {
            return $msg->content[0]->text->value;
        }
    }

    throw new Exception('No se encontró una respuesta del asistente.');
}
// --- FIN DE LA FUNCIÓN DE LLAMADA A LA API ---


// --- BLOQUE PRINCIPAL DE EJECUCIÓN ---
try {
    // Verificar si la extensión cURL está disponible
    if (!function_exists('curl_init')) {
        throw new Exception('La extensión cURL de PHP no está instalada o habilitada en el servidor.');
    }

    // Obtener los datos enviados desde el frontend
    $json_data = file_get_contents('php://input');
    if ($json_data === false) {
        throw new Exception('Error al leer los datos de entrada.');
    }
    $data = json_decode($json_data);

    if (!$data || !isset($data->grado) || !isset($data->contexto)) {
        throw new Exception('Faltan datos clave de la planeación o del contexto.');
    }

    // --- CONSTRUCCIÓN DEL PROMPT (Tu lógica original se mantiene) ---
    $contexto = $data->contexto;
    $prompt_contexto = "
**A. CONTEXTO DEL GRUPO (Información Obligatoria a Considerar):**
- Grado General del Grupo: {$contexto->grado_planeacion}
- Duración deseada para la planeación: {$contexto->duracion_planeacion}
- Número Total de Alumnos: {$contexto->numero_total_estudiantes}
- Canales de Aprendizaje: {$contexto->auditivos} auditivos, {$contexto->visuales} visuales, y {$contexto->kinestesicos} kinestésicos.
- Apoyo General en Lectoescritura: {$contexto->refuerzo_lecto_general}. Estrategias solicitadas: {$contexto->estrategias_lecto_general}.
- Apoyo General en Cálculo Mental: {$contexto->refuerzo_calculo}. Estrategias solicitadas: {$contexto->estrategias_calculo}.
- Apoyo en Operaciones Básicas: {$contexto->refuerzo_operaciones}. Estrategias solicitadas: {$contexto->estrategias_operaciones}.
- Adecuaciones Curriculares en Lectoescritura: {$contexto->tiene_no_lectoescritura}. Afecta a {$contexto->numero_alumnos_lectoescritura} alumnos. Estrategias de adecuación solicitadas: {$contexto->estrategias_lecto_adecuacion}.
";

    $prompt_planeacion = "
**B. DATOS DE LA PLANEACIÓN A DESARROLLAR:**
- Grado Específico: {$data->grado}
- Asignatura: {$data->asignatura}
- Campo Formativo: {$data->campoFormativo}
- Contenido: {$data->contenido}
- Proceso de Desarrollo de Aprendizaje (PDA): {$data->pda}
- Ejes Articuladores seleccionados: " . implode(', ', $data->ejesArticuladores) . "
- Duración solicitada: {$data->tiempo}

**INSTRUCCIÓN FINAL: Con base en TODA la información anterior (Contexto y Datos de Planeación), genera la planeación didáctica completa. Tu respuesta DEBE ser EXCLUSIVAMENTE un objeto JSON válido, sin ningún texto introductorio, explicaciones, ni bloques de código markdown como \`\`\`json. Solo el JSON.**
";
    
    // --- LLAMADA REAL A LA API ---
    // ⚠️ ¡¡¡ASEGÚRATE DE QUE TU API KEY Y ASSISTANT ID SEAN CORRECTOS!!! ⚠️
    $apiKey = 'REDACTED_OPENAI_API_KEY'; // Reemplaza con tu clave real
    $assistantId = 'asst_Qj3hqD50tBoULSmaNCYPbTRY'; // Tu ID de asistente avanzado

    $assistantResponse = callOpenAI($apiKey, $assistantId, $prompt_contexto . $prompt_planeacion);

    // --- ¡ACCIÓN CLAVE! SI LA IA RESPONDE, DESCONTAMOS EL USO ---
    $usuario_id = $_SESSION['usuario_id'];
    $stmt = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio - 1 WHERE id = ? AND usos_plan_intermedio > 0");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->close();
    
    // Actualizamos la sesión para que el cambio se refleje inmediatamente en la página del usuario
    $_SESSION['usos_plan_intermedio']--;

    // --- LIMPIEZA Y VALIDACIÓN DE LA RESPUESTA JSON ---
    $json_string = $assistantResponse;
    // A veces la IA puede añadir ```json al inicio y ``` al final. Los quitamos.
    if (strpos(trim($json_string), '```json') === 0) {
        $json_string = preg_replace('/^```json\s*/', '', $json_string);
        $json_string = preg_replace('/\s*```$/', '', $json_string);
    }
    
    json_decode($json_string);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Si aún así no es un JSON válido, lanzamos un error claro.
        throw new Exception("La respuesta de la IA no fue un JSON válido. Respuesta recibida: " . $json_string);
    }
    
    // Devolvemos el JSON limpio directamente al frontend
    echo $json_string;

} catch (Exception $e) {
    // Si algo falla, se envía una respuesta de error en formato JSON
    http_response_code(500); // Código de error del servidor
    echo json_encode(['error' => 'Ha ocurrido un error en el servidor.', 'details' => $e->getMessage()]);
    exit;
} finally {
    // Es una buena práctica cerrar la conexión a la base de datos al final de la ejecución
    if (isset($conexion)) {
        $conexion->close();
    }
}
?>