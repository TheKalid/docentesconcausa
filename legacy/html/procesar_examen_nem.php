<?php
/**
 * procesar_examen_nem.php - Versión de Producción (Alta Concurrencia, Blindaje Total y Auditoría)
 */
ob_start();
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

// [SEGURIDAD] Validación estricta del método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No se ha iniciado sesión.']);
    exit;
}
$userId = $_SESSION['usuario_id'];

// [SEGURIDAD] Validación del Token CSRF (Anti-Bots)
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Firma de seguridad inválida. Recarga la página.']);
    exit;
}

try {
    // 3. Recibir y Validar los Datos del Frontend
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data);

    if (!$data || empty($data->grado) || empty($data->pda) || empty($data->complejidad)) {
        http_response_code(400); 
        echo json_encode(['success' => false, 'error' => 'Faltan datos para generar el examen (grado, pda o complejidad).']);
        exit;
    }

    // =========================================================================
    // COBRO OPTIMISTA, AUDITORÍA IP Y LIBERACIÓN DE BASE DE DATOS Y SESIÓN
    // =========================================================================
    $creditosRestantes = 0;
    $conexion->begin_transaction();
    
    $stmt = $conexion->prepare("SELECT usos_examenes FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || $user['usos_examenes'] <= 0) {
        $conexion->rollback();
        echo json_encode(['success' => false, 'error' => 'Lo sentimos, has agotado tus créditos para generar exámenes.', 'creditos_restantes' => 0]);
        exit;
    }

    // A) Descontamos de inmediato
    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_examenes = usos_examenes - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $userId);
    $stmt_update->execute();
    
    // B) [CHIVATO] Auditoría para el Dashboard
    $herramienta_usada = "Exámenes BIM/TRIM (NEM)"; // Match exacto con Analytics
    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

    $stmt_log = $conexion->prepare("INSERT INTO historial_uso (usuario_id, herramienta, ip_usuario) VALUES (?, ?, ?)");
    $stmt_log->bind_param("iss", $userId, $herramienta_usada, $ip_cliente);
    $stmt_log->execute();
    $stmt_log->close();

    // C) Confirmamos la transacción
    $conexion->commit();
    $creditosRestantes = $user['usos_examenes'] - 1;

    // [CRÍTICO] Cerramos la DB y liberamos la sesión de PHP para permitir concurrencia masiva
    $conexion->close();
    session_write_close();
    // =========================================================================

    $grado = strip_tags($data->grado);
    $asignatura = isset($data->asignatura) ? strip_tags($data->asignatura) : 'No especificada';
    $campoFormativo = isset($data->campoFormativo) ? strip_tags($data->campoFormativo) : 'No especificado';
    $contenido = isset($data->contenido) ? strip_tags($data->contenido) : 'No especificado';
    $pda = strip_tags($data->pda);
    $complejidad = strip_tags($data->complejidad);

    // 4. Preparar el Prompt estructurado para Chat Completions
    $systemPrompt = "Actúa como un experto pedagogo y diseñador de instrumentos de evaluación para educación básica en México, conforme a la NEM.
Tu tarea es generar un examen. 
Instrucciones para la complejidad:
- Si es 'Básico': 5 a 7 preguntas (opción múltiple y respuesta corta).
- Si es 'Intermedio': 7 a 10 preguntas (opción múltiple, relación de columnas, V/F, breve justificación).
- Si es 'Avanzado': 5 a 7 reactivos (preguntas abiertas, problemas, estudios de caso prácticos).

Formato de Salida Obligatorio: Debes devolver tu respuesta EXCLUSIVAMENTE como un objeto JSON válido. La estructura debe ser: { \"examen_texto\": \"... aquí el examen completo en formato Markdown ...\" }";

    $userPrompt = "Datos para el examen: \n- Grado: {$grado} \n- Asignatura: {$asignatura} \n- Campo Formativo: {$campoFormativo} \n- Contenido: {$contenido} \n- PDA: {$pda} \n- Nivel de Complejidad: {$complejidad}";

    // 5. Petición Directa a OpenAI protegida por cURL
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $postData = [
        'model' => 'gpt-4o-mini', 
        'response_format' => ['type' => 'json_object'], 
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ],
        'temperature' => 0.7
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
    curl_setopt($ch, CURLOPT_ENCODING, ""); // Acepta compresión GZIP
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY 
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpcode >= 400 || $response === false) {
        throw new Exception("Error en la comunicación con la Inteligencia Artificial (HTTP $httpcode).");
    }

    // 6. Decodificar la respuesta
    $responseData = json_decode($response, true);
    $respuestaIA_JSON_String = $responseData['choices'][0]['message']['content'] ?? '{}';
    $respuestaIA_Data = json_decode($respuestaIA_JSON_String, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($respuestaIA_Data['examen_texto'])) {
        throw new Exception('La respuesta de la IA no tuvo el formato JSON esperado.');
    }

    // 7. Enviar éxito al frontend
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'examen' => $respuestaIA_Data,
        'creditos_restantes' => $creditosRestantes
    ]);

} catch (Exception $e) {
    // =========================================================================
    // REEMBOLSO (REFUND) Y LIMPIEZA DE AUDITORÍA EN CASO DE FALLA TÉCNICA
    // =========================================================================
    if ($e->getMessage() !== 'Lo sentimos, has agotado tus créditos para generar exámenes.') {
        try {
            require 'conexion.php'; 
            $conexion->begin_transaction();
            
            // 1. Devolvemos el token
            $stmt_refund = $conexion->prepare("UPDATE usuarios SET usos_examenes = usos_examenes + 1 WHERE id = ?");
            $stmt_refund->bind_param("i", $userId);
            $stmt_refund->execute();
            
            // 2. Borramos la evidencia fallida del historial
            $stmt_del_log = $conexion->prepare("DELETE FROM historial_uso WHERE usuario_id = ? AND herramienta = 'Exámenes BIM/TRIM (NEM)' ORDER BY id DESC LIMIT 1");
            $stmt_del_log->bind_param("i", $userId);
            $stmt_del_log->execute();
            $stmt_del_log->close();

            $conexion->commit();
            $conexion->close();
        } catch (Exception $refundErr) {
            error_log("CRÍTICO: No se pudo reembolsar el crédito de Examen al usuario ID: " . $userId);
        }
    }
    
    ob_end_clean();
    http_response_code(200); // 200 para que el frontend atrape el JSON de error
    echo json_encode([
        'success' => false,
        'error' => 'Hubo un problema al generar el examen. Por favor, intenta de nuevo. Tu crédito ha sido devuelto.',
        'details' => $e->getMessage(),
        'creditos_restantes' => ($creditosRestantes ?? 0) + 1
    ]);
}
?>