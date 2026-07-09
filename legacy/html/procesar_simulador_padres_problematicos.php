<?php
// procesar_simulador_padres_problematicos.php (VERSIÓN ENDURECIDA, AUDITADA Y DE ALTA CONCURRENCIA)
ob_start(); // Amortiguador para evitar que advertencias de PHP rompan el JSON
set_time_limit(120); 
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once 'conexion.php';
require_once 'credenciales.php';

header('Content-Type: application/json');
// Cabeceras Anti-Caché y Seguridad
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// [SEGURIDAD] Validación Básica y HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método HTTP no permitido.']);
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No se ha iniciado sesión.']);
    exit;
}
$userId = $_SESSION['usuario_id'];

// [SEGURIDAD] Validación CSRF
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Firma de seguridad inválida. Por favor, recargue la página.']);
    exit;
}

try {
    // [SEGURIDAD] Lectura y Deserialización
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true); 

    // Verificación de campos obligatorios
    $campos_requeridos = ['docente', 'alumno', 'tutor', 'nivel', 'motivo', 'perfil', 'rasgos'];
    foreach ($campos_requeridos as $campo) {
        if (empty($data[$campo])) {
            throw new Exception("Falta el campo obligatorio: $campo");
        }
    }

    // [SEGURIDAD] Sanitización Individual
    $docente = trim(strip_tags($data['docente']));
    $alumno  = trim(strip_tags($data['alumno']));
    $tutor   = trim(strip_tags($data['tutor']));
    $nivel   = trim(strip_tags($data['nivel']));
    $motivo  = trim(strip_tags($data['motivo']));
    $perfil  = trim(strip_tags($data['perfil']));
    $contexto = !empty($data['contexto']) ? trim(strip_tags($data['contexto'])) : 'Ninguno proporcionado';

    $rasgos_array = is_array($data['rasgos']) ? $data['rasgos'] : [];
    $rasgos_limpios = array_map(function($val) { return trim(strip_tags($val)); }, $rasgos_array);
    $rasgos_string = implode(', ', $rasgos_limpios);

    // Límite de longitud del contexto para evitar DoS por tokens
    if (mb_strlen($contexto) > 3000) {
        throw new Exception('El contexto proporcionado es demasiado extenso.');
    }

    $userMessageContent = "Analiza el siguiente escenario y genera el JSON táctico:\n"
        . "Docente: $docente\n"
        . "Nivel Educativo: $nivel\n"
        . "Alumno: $alumno\n"
        . "Tutor (Padre/Madre): $tutor\n"
        . "Motivo de la Reunión: $motivo\n"
        . "Actitud Dominante: $perfil\n"
        . "Comportamientos/Rasgos Específicos: $rasgos_string\n"
        . "Contexto Adicional: $contexto";

    // =========================================================================
    // COBRO OPTIMISTA, AUDITORÍA IP Y LIBERACIÓN INMEDIATA DE LA BASE DE DATOS
    // =========================================================================
    $conexion->begin_transaction();

    $stmt = $conexion->prepare("SELECT usos_plan_intermedio FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || $user['usos_plan_intermedio'] <= 0) {
        $conexion->rollback();
        throw new Exception('Has agotado tus usos para esta herramienta.');
    }
    
    // A) Descontamos crédito de inmediato
    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $userId);
    $stmt_update->execute();
    
    // B) [CHIVATO] Auditoría para el Dashboard Analítico
    $herramienta_usada = "Simulador Padres Problemáticos"; // Match exacto con Analytics
    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

    $stmt_log = $conexion->prepare("INSERT INTO historial_uso (usuario_id, herramienta, ip_usuario) VALUES (?, ?, ?)");
    $stmt_log->bind_param("iss", $userId, $herramienta_usada, $ip_cliente);
    $stmt_log->execute();
    $stmt_log->close();

    // C) Confirmamos la transacción
    $conexion->commit();
    
    $usosRestantesFinal = $user['usos_plan_intermedio'] - 1;
    $_SESSION['usos_plan_intermedio'] = $usosRestantesFinal;

    // [CRÍTICO] Cerramos la DB y liberamos la sesión de PHP para permitir alta concurrencia
    $conexion->close();
    session_write_close();
    // =========================================================================

    // SYSTEM PROMPT
    $systemPrompt = <<<EOT
Eres un psicólogo educativo experto en mediación escolar, asertividad y contención emocional de adultos conflictivos. Tu tarea es analizar el perfil de un padre de familia problemático basado en los datos proporcionados y generar una estrategia táctica para que el docente pueda manejar la entrevista con éxito.

### REGLA CRÍTICA: FORMATO DE SALIDA JSON OBLIGATORIO ###
Tu única respuesta debe ser un objeto JSON válido. No incluyas texto fuera del JSON. Debes seguir estrictamente esta estructura:

{
  "analisis_perfil": {
    "arquetipo": "Nombre descriptivo del comportamiento (ej. El Defensor Ciego, El Proyector Agresivo, El Negacionista)",
    "descripcion": "Análisis psicológico breve de 3 líneas sobre qué motiva la actitud de este tutor (miedos, carencias, evasión de culpa)."
  },
  "errores_fatales": [
    "Error 1 que el docente NUNCA debe cometer con este perfil específico (ej. Nunca alzar la voz para competir con él).",
    "Error 2...",
    "Error 3..."
  ],
  "estrategia_comunicacion": [
    "Paso 1: Qué hacer físicamente o cómo iniciar (ej. Sentarse del mismo lado del escritorio).",
    "Paso 2: Técnica de desescalada emocional a aplicar.",
    "Paso 3: Cómo centrar la conversación en la evidencia y el alumno."
  ],
  "frases_salvavidas": [
    "Frase empática pero firme 1",
    "Frase para redirigir insultos o quejas al objetivo real 2",
    "Frase para poner un límite si cruza la línea 3"
  ],
  "simulacion_dialogo": [
    {
      "actor": "Tutor",
      "dialogo": "¡Ejemplo de lo primero agresivo/evasivo que dirá el padre al llegar!",
      "analisis_oculto": "Qué busca lograr realmente con esta frase."
    },
    {
      "actor": "Docente",
      "dialogo": "Respuesta ideal del docente aplicando comunicación asertiva.",
      "tecnica_aplicada": "Nombre de la técnica psicológica usada (ej. Disco Rayado, Banco de Niebla, Empatía Táctica)."
    },
    {
      "actor": "Tutor",
      "dialogo": "Réplica del padre mostrando resistencia o intentando otra táctica.",
      "analisis_oculto": "Análisis de su resistencia."
    },
    {
      "actor": "Docente",
      "dialogo": "Cierre asertivo del docente redirigiendo al plan de acción escolar.",
      "tecnica_aplicada": "Técnica de reorientación."
    }
  ],
  "aviso": "Recuerda que si en algún momento sientes que tu integridad física o emocional está en riesgo, tienes el derecho de suspender la entrevista y solicitar la presencia del director escolar."
}

### DIRECTRICES DE CALIDAD ###
- La respuesta debe estar en Español neutro y altamente profesional.
- Adapta las frases y estrategias EXPLÍCITAMENTE a los "Comportamientos Específicos" y al "Motivo de la reunión" enviados por el usuario.
- Si el padre usa lenguaje altisonante, la estrategia debe incluir cómo detener la entrevista asertivamente.
- No uses saludos, genera únicamente el JSON.
EOT;

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    $payload = [
        'model' => 'gpt-4o-mini', 
        'response_format' => ['type' => 'json_object'], 
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessageContent]
        ],
        'temperature' => 0.7
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_ENCODING => "", // Compresión GZIP para acelerar
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 110,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode >= 400) {
        error_log("Error OpenAI en Simulador Padres: " . $response);
        throw new Exception("Error al procesar el perfil psicológico con la Inteligencia Artificial.");
    }

    $decodedResponse = json_decode($response, true);
    $ia_json_string = $decodedResponse['choices'][0]['message']['content'] ?? '{}';
    
    // Limpieza de Markdown extra '```json'
    if (strpos($ia_json_string, '```') === 0) {
        $ia_json_string = preg_replace('/^```json\s*/', '', $ia_json_string);
        $ia_json_string = preg_replace('/\s*```$/', '', $ia_json_string);
    }
    
    $ia_data = json_decode($ia_json_string, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("La Inteligencia artificial devolvió un formato ilegible.");
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'reporte' => $ia_data,
        'usos_restantes' => $usosRestantesFinal
    ]);

} catch (Exception $e) {
    // REEMBOLSO Y LIMPIEZA DE AUDITORÍA EN CASO DE FALLO (Si no fue por falta de créditos)
    if ($e->getMessage() !== 'Has agotado tus usos para esta herramienta.') {
        try {
            require 'conexion.php'; 
            $conexion->begin_transaction();
            
            // 1. Reembolsar token
            $stmt_refund = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio + 1 WHERE id = ?");
            $stmt_refund->bind_param("i", $userId);
            $stmt_refund->execute();
            
            // 2. Limpiar huella en historial
            $stmt_del_log = $conexion->prepare("DELETE FROM historial_uso WHERE usuario_id = ? AND herramienta = 'Simulador Padres Problemáticos' ORDER BY id DESC LIMIT 1");
            $stmt_del_log->bind_param("i", $userId);
            $stmt_del_log->execute();
            $stmt_del_log->close();

            $conexion->commit();
            $conexion->close();
        } catch (Exception $refundErr) {
            error_log("CRÍTICO: No se pudo reembolsar el crédito del Simulador al usuario ID: " . $userId);
        }
    }

    ob_end_clean();
    http_response_code(200); // Enviamos 200 para que el catch del JS lo atrape bonito
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>