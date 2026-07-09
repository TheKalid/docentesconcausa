<?php
// procesar_planeacion_potenciada.php (VERSIÓN ENDURECIDA, AUDITADA Y DE ALTA CONCURRENCIA)
ob_start(); // Aísla cualquier error de PHP para no romper el formato JSON
set_time_limit(150); // Límite extendido porque debe leer y reescribir mucha información
ini_set('display_errors', 0);
ini_set('log_errors', 1); 

session_start();
require_once 'conexion.php';
require_once 'credenciales.php';

header('Content-Type: application/json; charset=utf-8');
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// ==========================================
// CEREBRO DE LA IA: ADECUACIÓN Y PSICOPEDAGOGÍA
// ==========================================
$PROMPT_SISTEMA = <<<EOT
Eres un experto en Psicopedagogía, Educación Inclusiva y Diseño Curricular. Tu objetivo es analizar una planeación escolar existente y modificarla para adaptarla a una problemática específica (barreras de aprendizaje, neurodivergencias, violencia, abandono familiar, etc.).

METODOLOGÍA DE ADECUACIÓN:
1. No cambies el tema central de la planeación, mantén su esencia.
2. Intervén las actividades (Inicio, Desarrollo, Cierre) integrando estrategias específicas que mitiguen la problemática descrita.
3. Utiliza la etiqueta HTML <mark> para resaltar exactamente dónde hiciste la adecuación en el texto de la planeación (Ej. "Desarrollo: Los alumnos leerán el texto. <mark>[Adecuación TEA: Se le entregará el texto con pictogramas de apoyo visual]</mark>").

FORMATO DE SALIDA (JSON OBLIGATORIO):
{
  "analisis_problematica": "Breve análisis clínico-pedagógico de la situación descrita por el docente y por qué afecta el aprendizaje.",
  "estrategias_generales": ["Estrategia de contención 1", "Estrategia conductual 2", "Estrategia espacial/aula 3"],
  "planeacion_adecuada": "La planeación original REESCRITA en formato Markdown, insertando y resaltando con la etiqueta <mark> las modificaciones realizadas.",
  "recomendacion_docente": "Un mensaje empático, motivacional y profesional dirigido al docente para su autocuidado o manejo emocional ante esta situación."
}

REGLAS DE SEGURIDAD:
- Respeta estrictamente la estructura JSON.
- Mantén un tono empático pero científicamente riguroso.
- Si el contexto indica violencia grave o abuso, sugiere discretamente en la 'recomendacion_docente' activar los protocolos institucionales o de trabajo social escolar.
EOT;

try {
    // ==========================================
    // SEGURIDAD Y GATEKEEPING HTTP
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido.');
    }

    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('No se ha iniciado sesión.');
    }
    $userId = $_SESSION['usuario_id'];

    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        throw new Exception('Firma de seguridad inválida.');
    }

    // ==========================================
    // EXTRACCIÓN Y VALIDACIÓN DE DATOS
    // ==========================================
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    $campos_requeridos = ['planeacion', 'categoria', 'descripcion'];
    foreach ($campos_requeridos as $campo) {
        if (empty($data[$campo])) {
            throw new Exception("Falta el campo obligatorio: $campo");
        }
    }

    // Sanitización Rigurosa
    $planeacion  = trim(strip_tags($data['planeacion']));
    $categoria   = trim(strip_tags($data['categoria']));
    $descripcion = trim(strip_tags($data['descripcion']));

    // Límite DoS aumentado porque procesa PDFs largos
    if (mb_strlen($planeacion) > 18000 || mb_strlen($descripcion) > 3000) {
        error_log("Intento de desbordamiento de payload en adecuación por usuario ID: " . $userId);
        throw new Exception('El texto excede la longitud máxima. Acorte su planeación e intente de nuevo.');
    }

    $userMessageContent = "CATEGORÍA DEL PROBLEMA: $categoria\n\nDESCRIPCIÓN DEL CONTEXTO/ALUMNOS:\n$descripcion\n\n=== PLANEACIÓN ORIGINAL ===\n$planeacion";

    // =========================================================================
    // COBRO OPTIMISTA, AUDITORÍA IP Y LIBERACIÓN INMEDIATA DE LA BASE DE DATOS
    // =========================================================================
    $conexion->begin_transaction();

    $stmt = $conexion->prepare("SELECT usos_plan_intermedio FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || $user['usos_plan_intermedio'] <= 0) {
        $conexion->rollback();
        throw new Exception('Has agotado tus créditos para este plan.');
    }

    // A) Descontar INMEDIATAMENTE
    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $userId);
    $stmt_update->execute();
    
    // B) [CHIVATO] Auditoría para el Dashboard Analítico
    $herramienta_usada = "Adecuaciones Curriculares"; // Match exacto con Analytics
    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

    $stmt_log = $conexion->prepare("INSERT INTO historial_uso (usuario_id, herramienta, ip_usuario) VALUES (?, ?, ?)");
    $stmt_log->bind_param("iss", $userId, $herramienta_usada, $ip_cliente);
    $stmt_log->execute();
    $stmt_log->close();

    // C) Confirmamos transacción
    $conexion->commit();
    
    $usosRestantesFinal = $user['usos_plan_intermedio'] - 1;
    $_SESSION['usos_plan_intermedio'] = $usosRestantesFinal;

    // [CRÍTICO] Cerramos la DB y la sesión de PHP para permitir concurrencia masiva
    $conexion->close();
    session_write_close();
    // =========================================================================

    // ==========================================
    // CONEXIÓN A OPENAI (Optimizada con GZIP)
    // ==========================================
    $payload = [
        'model' => 'gpt-4o-mini', 
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => $PROMPT_SISTEMA],
            ['role' => 'user', 'content' => $userMessageContent]
        ],
        'temperature' => 0.6 
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_ENCODING => "", // Compresión GZIP para acelerar envío de grandes textos
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 120, 
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("Error interno de comunicación del servidor.");
    }

    if ($httpCode >= 400) {
        error_log("OpenAI API Error HTTP Code: " . $httpCode . " Response: " . $response);
        throw new Exception("El motor de IA está saturado temporalmente. Intente nuevamente.");
    }

    $decodedResponse = json_decode($response, true);
    $ia_json_string = trim($decodedResponse['choices'][0]['message']['content'] ?? '{}');
    
    // Limpieza de Markdown extra '```json'
    if (strpos($ia_json_string, '```') === 0) {
        $ia_json_string = preg_replace('/^```json\s*/', '', $ia_json_string);
        $ia_json_string = preg_replace('/\s*```$/', '', $ia_json_string);
    }
    
    $ia_data = json_decode($ia_json_string, true); 

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Respuesta JSON malformada desde OpenAI: " . $ia_json_string);
        throw new Exception("Error procesando la adecuación curricular.");
    }

    // Éxito: Enviar al frontend
    ob_end_clean();
    echo json_encode([
        'status' => 'completo',
        'plan' => $ia_data, 
        'usos_restantes' => $usosRestantesFinal
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // =========================================================================
    // REEMBOLSO Y LIMPIEZA DE AUDITORÍA EN CASO DE FALLO
    // =========================================================================
    if ($e->getMessage() !== 'Has agotado tus créditos para este plan.') {
        try {
            require 'conexion.php'; 
            $conexion->begin_transaction();
            
            // 1. Reembolsar token
            $stmt_refund = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio + 1 WHERE id = ?");
            $stmt_refund->bind_param("i", $userId);
            $stmt_refund->execute();
            
            // 2. Limpiar huella en historial
            $stmt_del_log = $conexion->prepare("DELETE FROM historial_uso WHERE usuario_id = ? AND herramienta = 'Adecuaciones Curriculares' ORDER BY id DESC LIMIT 1");
            $stmt_del_log->bind_param("i", $userId);
            $stmt_del_log->execute();
            $stmt_del_log->close();

            $conexion->commit();
            $conexion->close();
        } catch (Exception $refundErr) {
            error_log("CRÍTICO: No se pudo reembolsar el crédito potenciado al usuario ID: " . $userId);
        }
    }

    ob_end_clean();
    http_response_code(200); 
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
?>