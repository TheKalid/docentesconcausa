<?php
// procesar_cultura_paz.php (VERSIÓN SÍNCRONA DE ALTO RENDIMIENTO, BLINDADA Y AUDITADA)
ob_start(); // Buffer para evitar que errores de PHP rompan el JSON
set_time_limit(90); 
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once 'conexion.php';
require_once 'credenciales.php';

header('Content-Type: application/json');

// --- Cabeceras de Seguridad ---
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

try {
    // 1. Verificación de sesión
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('No se ha iniciado sesión.');
    }
    $userId = $_SESSION['usuario_id'];

    // --- Validación CSRF Anti-Bots ---
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        throw new Exception('Firma de seguridad inválida. Recarga la página.');
    }

    // 2. Recibir y validar datos del frontend
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);

    if (!$data || empty($data['tema_principal']) || empty($data['subtema'])) {
        throw new Exception('Datos de entrada inválidos o incompletos.');
    }

    $tema_principal = htmlspecialchars($data['tema_principal']);
    $subtema = htmlspecialchars($data['subtema']);
    $estrategia = htmlspecialchars($data['estrategia_didactica'] ?? 'No especificada');
    $duracion = htmlspecialchars($data['duracion'] ?? 'No especificada');
    $contexto_grupo = htmlspecialchars($data['contexto_grupo'] ?? 'No especificado');

    // =========================================================================
    // --- 3. COBRO OPTIMISTA, AUDITORÍA IP Y LIBERACIÓN DE BASE DE DATOS ---
    // =========================================================================
    $conexion->begin_transaction();
    $stmt = $conexion->prepare("SELECT usos_plan_intermedio FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || $user['usos_plan_intermedio'] <= 0) {
        $conexion->rollback();
        throw new Exception('Has agotado tus usos para esta herramienta.');
    }

    // A) Descontamos crédito de inmediato
    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $userId);
    $stmt_update->execute();
    
    // B) [CHIVATO] Auditoría para el Dashboard
    $herramienta_usada = "Cultura de Paz y Convivencia"; // Match exacto con Analytics
    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

    $stmt_log = $conexion->prepare("INSERT INTO historial_uso (usuario_id, herramienta, ip_usuario) VALUES (?, ?, ?)");
    $stmt_log->bind_param("iss", $userId, $herramienta_usada, $ip_cliente);
    $stmt_log->execute();
    $stmt_log->close();

    // C) Confirmamos la transacción
    $conexion->commit();
    $usosRestantesFinal = $user['usos_plan_intermedio'] - 1;
    $_SESSION['usos_plan_intermedio'] = $usosRestantesFinal;

    // Liberamos los recursos de BD y Sesión para que el servidor no se trabe
    $conexion->close();
    session_write_close();
    // =========================================================================

    // 4. Construir Prompt para OpenAI
    $systemPrompt = <<<EOT
Eres un pedagogo experto en la Nueva Escuela Mexicana (NEM), especializado en la creación de estrategias y actividades que promueven la Cultura de Paz, la convivencia escolar armónica, la resolución pacífica de conflictos y el bienestar socioemocional.
Tu misión es diseñar una propuesta de intervención didáctica detallada, práctica y adaptada al contexto escolar.

Instrucciones Críticas:
1. Centra la actividad en el diálogo, la empatía y la reflexión.
2. Evita enfoques punitivos; prioriza la justicia restaurativa.
3. La propuesta debe incluir: Objetivo, Materiales sugeridos (sencillos), Inicio, Desarrollo y Cierre de la actividad.
4. Genera el contenido estrictamente usando formato Markdown (## para títulos, ** para negritas, etc.).

**FORMATO DE SALIDA OBLIGATORIO:**
Debes devolver EXCLUSIVAMENTE un objeto JSON válido con esta estructura exacta, sin texto adicional fuera del JSON:
{
  "titulo_actividad": "Un título creativo para la estrategia",
  "contenido_markdown": "Todo el desarrollo de la actividad aquí en formato markdown."
}
EOT;

    $userPrompt = "Diseña una actividad para promover la Cultura de Paz.\n- Tema Principal: {$tema_principal}\n- Subtema/Enfoque: {$subtema}\n- Estrategia preferida: {$estrategia}\n- Duración esperada: {$duracion}\n- Contexto del Grupo: {$contexto_grupo}";

    // 5. Petición a OpenAI (Síncrona con cURL optimizado)
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    $payload = [
        'model' => 'gpt-4o-mini', 
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ],
        'temperature' => 0.7 
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_ENCODING => "", // Compresión GZIP para mayor velocidad
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 90,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("Error de red conectando con OpenAI: " . $curlError);
    }

    $decodedResponse = json_decode($response, true);
    if ($httpCode >= 400) {
        $errorMsg = $decodedResponse['error']['message'] ?? 'Error desconocido de la API';
        throw new Exception("OpenAI rechazó la solicitud: " . $errorMsg);
    }

    $ia_json_string = trim($decodedResponse['choices'][0]['message']['content']);
    
    // Limpieza de Markdown extra si OpenAI lo manda
    if (strpos($ia_json_string, '```') === 0) {
        $ia_json_string = preg_replace('/^```json\s*/', '', $ia_json_string);
        $ia_json_string = preg_replace('/\s*```$/', '', $ia_json_string);
    }

    $ia_data = json_decode($ia_json_string, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($ia_data['titulo_actividad'])) {
        throw new Exception("La IA no devolvió el formato JSON correcto.");
    }

    // 6. Retornar al frontend con éxito
    ob_end_clean(); 
    echo json_encode([
        'success' => true,
        'data' => $ia_data,
        'usos_restantes' => $usosRestantesFinal
    ]);

} catch (Exception $e) {
    // =========================================================================
    // REEMBOLSO Y LIMPIEZA DE AUDITORÍA EN CASO DE FALLA
    // =========================================================================
    if ($e->getMessage() !== 'Has agotado tus usos para esta herramienta.') {
        try {
            require 'conexion.php'; 
            $conexion->begin_transaction();
            
            // 1. Reembolsar token
            $stmt_refund = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio + 1 WHERE id = ?");
            $stmt_refund->bind_param("i", $userId);
            $stmt_refund->execute();
            
            // 2. Limpiar registro de auditoría
            $stmt_del_log = $conexion->prepare("DELETE FROM historial_uso WHERE usuario_id = ? AND herramienta = 'Cultura de Paz y Convivencia' ORDER BY id DESC LIMIT 1");
            $stmt_del_log->bind_param("i", $userId);
            $stmt_del_log->execute();
            $stmt_del_log->close();

            $conexion->commit();
            $conexion->close();
        } catch (Exception $refundErr) {
            error_log("CRÍTICO: No se pudo reembolsar el crédito de Cultura de Paz al usuario ID: " . $userId);
        }
    }

    ob_end_clean();
    http_response_code(200); 
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>