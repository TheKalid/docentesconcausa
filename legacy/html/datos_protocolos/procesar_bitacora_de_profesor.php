<?php
// Muestra todos los errores para facilitar la depuración inicial.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ===== NUEVO: Iniciar sesión y conectar a la BD =====
session_start();
require_once 'conexion.php'; 

// Siempre devolveremos respuestas en formato JSON.
header('Content-Type: application/json');

// --- MANEJADOR GLOBAL DE ERRORES ---
set_exception_handler(function ($exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Excepción no controlada en el servidor.',
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ]);
});

// --- CONFIGURACIÓN PRINCIPAL ---
define('OPENAI_API_KEY', 'REDACTED_OPENAI_API_KEY');
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');

function call_openai_api($system_prompt, $user_content) {
    if (!defined('OPENAI_API_KEY') || empty(OPENAI_API_KEY) || OPENAI_API_KEY === 'tu_clave_de_api_de_openai_aqui') {
         throw new Exception('La clave de API de OpenAI no ha sido configurada en el archivo PHP.');
    }
    $ch = curl_init(OPENAI_API_URL);
    $post_data = json_encode([
        'model' => 'gpt-4o',
        'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_content]
        ],
        'response_format' => ['type' => 'json_object']
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('Error de cURL: ' . $error);
    }
    curl_close($ch);
    if ($http_code != 200) {
        throw new Exception("La API de OpenAI devolvió un error (Código: {$http_code}). Detalles: " . $response);
    }
    $api_response = json_decode($response, true);
    $assistant_json_content = $api_response['choices'][0]['message']['content'] ?? null;
    if (!$assistant_json_content) {
         throw new Exception('La API no devolvió contenido JSON válido en su respuesta.');
    }
    return json_decode($assistant_json_content, true);
}


// --- LÓGICA PRINCIPAL DEL SCRIPT ---
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Acción no válida o datos no recibidos.']);
    exit;
}

// ===== NUEVO: BLOQUE DE VERIFICACIÓN Y DECREMENTO DE USOS =====
$usuario_id = $_SESSION['usuario_id'] ?? 0;
if ($usuario_id === 0) {
    http_response_code(401); // No autorizado
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado. Por favor, inicie sesión.']);
    exit;
}

// 1. Consultar usos actuales
$stmt = $conexion->prepare("SELECT usos_bitacora FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();
$usuario_data = $resultado->fetch_assoc();
$usos_actuales = (int)($usuario_data['usos_bitacora'] ?? 0);
$stmt->close();

// 2. Verificar si quedan usos
if ($usos_actuales <= 0) {
    http_response_code(403); // Prohibido (Forbidden)
    echo json_encode(['success' => false, 'error' => 'Has agotado tus intentos disponibles.']);
    exit;
}

// 3. Si hay usos, decrementarlos EN LA BASE DE DATOS
$stmt_update = $conexion->prepare("UPDATE usuarios SET usos_bitacora = usos_bitacora - 1 WHERE id = ?");
$stmt_update->bind_param("i", $usuario_id);
$stmt_update->execute();
$stmt_update->close();

// 4. El nuevo número de usos para devolver al frontend
$usos_restantes = $usos_actuales - 1;

// =============================================================

$action = $input['action'];
$data = $input['data'] ?? [];
$response_data = [];

switch ($action) {
    case 'analizar':
        $system_prompt = "Tu única tarea es analizar la calidad de una bitácora docente. Recibirás datos en JSON. Devuelve un objeto JSON con una clave 'feedback' que contenga un array de objetos. Cada objeto debe tener 'status' ('OK' o 'WARN') y 'mensaje'. Valida que los campos no estén vacíos y que la descripción sea objetiva (sin juicios de valor ni opiniones).";
        $user_content = json_encode(['datos_bitacora' => $data['datos_bitacora']]);
        $response_data = call_openai_api($system_prompt, $user_content);
        break;

    case 'obtener_protocolo':
        $system_prompt = "Eres un Asesor Experto en Protocolos Educativos. Basado en la descripción de un incidente, genera una recomendación clara y paso a paso. Tu respuesta debe ser un objeto JSON con una clave 'recomendacion_html' que contenga el string HTML con la guía. El HTML debe estar bien formateado con títulos (<h3>), párrafos (<p>) y listas (<ul><li>). Incluye siempre un aviso de responsabilidad al final del HTML.";
        $user_content = json_encode($data);
        $response_data = call_openai_api($system_prompt, $user_content);
        break;

    case 'generar_vista_previa':
        $system_prompt = "Tu única tarea es formatear los datos de una bitácora en un HTML limpio y profesional para una vista previa. Recibirás los datos en JSON. Devuelve un objeto JSON con una clave 'html_preview' que contenga el string HTML completo y bien estructurado.";
        $user_content = json_encode(['datos_bitacora' => $data['datos_bitacora']]);
        $response_data = call_openai_api($system_prompt, $user_content);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Acción no reconocida.']);
        exit;
}

// Si todo salió bien, enviamos la respuesta exitosa al frontend CON LOS USOS RESTANTES
http_response_code(200);
echo json_encode([
    'success' => true, 
    'data' => $response_data,
    'usos_restantes' => $usos_restantes // <-- NUEVO: Enviamos el contador actualizado
]);

?>