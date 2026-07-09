<?php
// procesar_bitacora_de_profesor.php (VERSIÓN ENDURECIDA, AUDITADA Y DE ALTA VELOCIDAD)
ob_start(); // Buffer para evitar que errores de PHP rompan el JSON
set_time_limit(120); 
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once 'conexion.php'; 
require_once 'credenciales.php';

header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

// --- MANEJADOR DE ERRORES ---
set_exception_handler(function ($exception) {
    http_response_code(500);
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Excepción en el servidor: ' . $exception->getMessage()
    ]);
    exit;
});

// --- GATEKEEPING Y VALIDACIÓN CSRF ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'] ?? 0;
if ($usuario_id === 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado. Inicie sesión.']);
    exit;
}

$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Firma de seguridad inválida. Recarga la página.']);
    exit;
}

$raw_data = file_get_contents('php://input');
$input = json_decode($raw_data, true);

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos no recibidos correctamente.']);
    exit;
}

$action = $input['action'];
$data = $input['data'] ?? [];


// =======================================================================
// COBRO OPTIMISTA, AUDITORÍA IP Y LIBERACIÓN DE BASE DE DATOS
// =======================================================================
$conexion->begin_transaction();

try {
    $stmt = $conexion->prepare("SELECT usos_bitacora FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $usuario_data = $stmt->get_result()->fetch_assoc();
    
    if (!$usuario_data || $usuario_data['usos_bitacora'] <= 0) {
        throw new Exception("Has agotado tus intentos disponibles.");
    }
    
    // A) Descontamos de inmediato
    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_bitacora = usos_bitacora - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $usuario_id);
    $stmt_update->execute();
    
    // B) [CHIVATO] Auditoría para el Dashboard Analítico
    $herramienta_usada = "Bitácoras Docentes"; // Match exacto con Excel
    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

    $stmt_log = $conexion->prepare("INSERT INTO historial_uso (usuario_id, herramienta, ip_usuario) VALUES (?, ?, ?)");
    $stmt_log->bind_param("iss", $usuario_id, $herramienta_usada, $ip_cliente);
    $stmt_log->execute();
    $stmt_log->close();

    // C) Confirmamos transacción
    $conexion->commit();
    $usos_restantes = $usuario_data['usos_bitacora'] - 1;

} catch (Exception $e) {
    $conexion->rollback();
    $codigo_error = ($e->getMessage() == "Has agotado tus intentos disponibles.") ? 403 : 500;
    http_response_code($codigo_error);
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

// [CRÍTICO] Cerramos la BD y liberamos la sesión en el servidor para evitar bloqueos
$conexion->close();
session_write_close();


// --- FUNCIÓN PARA LLAMAR A LA IA RÁPIDA (CHAT COMPLETIONS) ---
function call_openai_api($system_prompt, $user_content) {
    if (!defined('OPENAI_API_KEY') || empty(OPENAI_API_KEY)) {
         throw new Exception('La clave de API de OpenAI no está configurada.');
    }
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    $post_data = json_encode([
        'model' => 'gpt-4o-mini', 
        'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_content]
        ],
        'response_format' => ['type' => 'json_object'] 
    ]);
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_ENCODING => "", // Compresión GZIP para mayor velocidad
        CURLOPT_POSTFIELDS => $post_data,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === false) {
        throw new Exception('Error de conexión: ' . curl_error($ch));
    }
    curl_close($ch);
    
    if ($http_code >= 400) {
        throw new Exception("La API devolvió un error (HTTP {$http_code}).");
    }
    
    $api_response = json_decode($response, true);
    $json_content = $api_response['choices'][0]['message']['content'] ?? null;
    
    if (!$json_content) {
         throw new Exception('La IA no devolvió contenido válido.');
    }
    
    return json_decode($json_content, true);
}


// --- LÓGICA PRINCIPAL (EJECUCIÓN DE IA) ---
try {
    switch ($action) {
        case 'analizar':
            $system_prompt = "Tu única tarea es analizar la calidad de una bitácora docente. Recibirás datos en JSON. Devuelve un objeto JSON con una clave 'feedback' que contenga un array de objetos. Cada objeto debe tener 'status' ('OK' o 'WARN') y 'mensaje'. Valida que los campos no estén vacíos y que la descripción sea objetiva (sin juicios de valor ni opiniones).";
            $user_content = json_encode(['datos_bitacora' => $data['datos_bitacora']]);
            break;

        case 'obtener_protocolo':
            $system_prompt = "Eres un Asesor Experto en Protocolos Educativos. Basado en la descripción de un incidente, genera una recomendación clara y paso a paso. Tu respuesta debe ser un objeto JSON con una clave 'recomendacion_html' que contenga el string HTML con la guía. El HTML debe estar bien formateado con títulos (<h3>), párrafos (<p>) y listas (<ul><li>). Incluye siempre un aviso de responsabilidad al final del HTML.";
            $user_content = json_encode($data);
            break;

        case 'generar_vista_previa':
            $system_prompt = "Tu única tarea es formatear los datos de una bitácora en un HTML limpio y profesional para una vista previa oficial escolar. Usa divs, clases para estilos y una estructura formal (incluyendo espacios para firmas del docente y directivo). Recibirás los datos en JSON. Devuelve un objeto JSON con una clave 'html_preview' que contenga el string HTML completo y bien estructurado.";
            $user_content = json_encode(['datos_bitacora' => $data['datos_bitacora']]);
            break;

        default:
            throw new Exception("Acción no reconocida.");
    }

    // Llamar a la IA
    $response_data = call_openai_api($system_prompt, $user_content);

    // Devolvemos la información al Frontend
    ob_end_clean();
    echo json_encode([
        'success' => true, 
        'data' => $response_data,
        'usos_restantes' => $usos_restantes
    ]);

} catch (Exception $e) {
    // =========================================================================
    // REEMBOLSO DE CRÉDITO Y LIMPIEZA EN CASO DE FALLO TÉCNICO
    // =========================================================================
    error_log("Fallo IA en Bitácora. Reembolsando al usuario: " . $usuario_id);
    try {
        require 'conexion.php'; 
        $conexion->begin_transaction();
        
        // 1. Devolver token a la tabla de usuarios
        $stmt_refund = $conexion->prepare("UPDATE usuarios SET usos_bitacora = usos_bitacora + 1 WHERE id = ?");
        $stmt_refund->bind_param("i", $usuario_id);
        $stmt_refund->execute();
        
        // 2. Borrar huella de auditoría fallida
        $stmt_del_log = $conexion->prepare("DELETE FROM historial_uso WHERE usuario_id = ? AND herramienta = 'Bitácoras Docentes' ORDER BY id DESC LIMIT 1");
        $stmt_del_log->bind_param("i", $usuario_id);
        $stmt_del_log->execute();
        $stmt_del_log->close();

        $conexion->commit();
        $conexion->close();
    } catch (Exception $refundErr) {
        error_log("CRÍTICO: No se pudo reembolsar el crédito de bitácora. ID: " . $usuario_id);
    }

    http_response_code(500);
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Hubo un error al procesar con IA. Se ha reembolsado tu crédito. Intenta de nuevo.'
    ]);
}
?>