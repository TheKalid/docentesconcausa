<?php
// procesar_planeacion.php (VERSIÓN ENDURECIDA, SEGURA Y CON AUDITORÍA ANALÍTICA)

// 1. CONFIGURACIÓN INICIAL DEL SERVIDOR
set_time_limit(120); 
ini_set('display_errors', 0);
ini_set('log_errors', 1); 

session_start();
require_once 'conexion.php';
require_once 'credenciales.php';

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

// 2. FILTROS DE SEGURIDAD EXTREMA (GATEKEEPING)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'error' => 'Acceso denegado. Método no permitido.']);
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401); 
    echo json_encode(['success' => false, 'error' => 'No se ha iniciado sesión en la plataforma.']);
    exit;
}
$userId = $_SESSION['usuario_id'];

// CSRF
$csrf_token = '';
if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'];
} elseif (isset($_SERVER['X_CSRF_TOKEN'])) {
    $csrf_token = $_SERVER['X_CSRF_TOKEN'];
}

if (empty($_SESSION['csrf_token'])) {
    http_response_code(403); 
    error_log("Error de CSRF: La sesión no tiene token para el usuario ID: " . $userId);
    echo json_encode(['success' => false, 'error' => 'Tu sesión expiró o las cookies están bloqueadas. Por favor, recarga la página.']);
    exit;
}

if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    error_log("Ataque o error CSRF bloqueado. Token recibido no coincide con el de la sesión para el usuario ID: " . $userId);
    echo json_encode(['success' => false, 'error' => 'Firma de seguridad inválida. Por favor, recarga la página.']);
    exit;
}

// 3. RECEPCIÓN Y SANITIZACIÓN DE DATOS
$json_data = file_get_contents('php://input');
$data = json_decode($json_data);

if (!$data || empty($data->prompt)) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'error' => 'Datos de planeación incompletos o corruptos.']);
    exit;
}

$userMessageContent = trim(strip_tags($data->prompt));

if (mb_strlen($userMessageContent) > 1500) {
    http_response_code(400);
    error_log("Payload demasiado grande del usuario ID: " . $userId);
    echo json_encode(['success' => false, 'error' => 'La solicitud es demasiado extensa.']);
    exit;
}

// =========================================================================
// 4. LÓGICA DE NEGOCIO (COBRO OPTIMISTA Y AUDITORÍA)
// =========================================================================
$usosRestantesFinal = 0;
$conexion->begin_transaction();

try {
    $stmt = $conexion->prepare("SELECT usos_plan_basico FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || $user['usos_plan_basico'] <= 0) {
        $conexion->rollback();
        echo json_encode(['success' => false, 'error' => 'Has agotado tus créditos para generar planeaciones básicas.']);
        exit;
    }
    
    // A) Descontamos el crédito INMEDIATAMENTE
    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_plan_basico = usos_plan_basico - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $userId);
    $stmt_update->execute();
    
    // B) [CHIVATO] Registramos el movimiento en el Historial con IP
    $herramienta_usada = "Planeación Básica"; // Match exacto con el Dashboard
    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

    $stmt_log = $conexion->prepare("INSERT INTO historial_uso (usuario_id, herramienta, ip_usuario) VALUES (?, ?, ?)");
    $stmt_log->bind_param("iss", $userId, $herramienta_usada, $ip_cliente);
    $stmt_log->execute();
    $stmt_log->close();
    
    // C) Confirmamos la transacción
    $conexion->commit();
    
    $usosRestantesFinal = $user['usos_plan_basico'] - 1;
    $_SESSION['usos_plan_basico'] = $usosRestantesFinal;

} catch (Exception $e) {
    $conexion->rollback();
    http_response_code(500);
    error_log("Error BD al cobrar uso básico: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error de conexión con la base de datos local.']);
    exit;
}

// [CRÍTICO] Cerramos la DB y liberamos la sesión de PHP para permitir concurrencia masiva
$conexion->close();
session_write_close();


// =========================================================================
// 5. COMUNICACIÓN SEGURA CON OPENAI
// =========================================================================
$systemPrompt = <<<EOT
### ROL Y OBJETIVO PRINCIPAL ###
Eres un asistente educativo experto en la normativa y pedagogía de la Nueva Escuela Mexicana (NEM). Tu única función es generar una planeación didáctica de alta calidad y devolverla como un objeto JSON válido.

### LÓGICA DE PROCESAMIENTO ###
1. Analiza los datos de entrada proporcionados (Grado, PDA, Metodología, etc.).
2. Diseña una planeación didáctica completa y coherente, estructurada en el número de sesiones indicado.
3. Aplica rigurosamente todas las instrucciones pedagógicas y de materiales.
4. Construye tu respuesta siguiendo estrictamente la estructura JSON definida en el formato de salida obligatorio.
5. Apartir de la sesion 2 debe de tener un feed back , o debe de tomar en cuenta la sesion anterior.Ojo a partir de la sesion 2, o un recuperacion de saberes, lluvia de ideas o preguntas , utiliza la que mas convenga.

### REGLA CRÍTICA: FORMATO DE SALIDA JSON OBLIGATORIO ###
Tu única respuesta debe ser un objeto JSON válido, sin texto introductorio, explicaciones, ni comentarios fuera del JSON. La estructura debe ser exactamente la siguiente:

{
  "datos_principales": {
    "proyecto": "El nombre creativo del proyecto",
    "pda": "El Proceso de Desarrollo de Aprendizaje que se está trabajando",
    "metodologia": "La metodología específica de la NEM seleccionada"
  },
  "lista_materiales": [
    "Material simple y económico 1 (ej. Hojas blancas)",
    "Material simple y económico 2 (ej. Colores o crayolas)",
    "Material reciclado (ej. Cajas de cartón)"
  ],
  "planeacion_completa": "Aquí va toda la planeación, comenzando con el encabezado completo y seguido por las sesiones, todo en formato Markdown.",
  "sugerencias_didacticas": [
    "Primera sugerencia práctica para el docente sobre la implementación del proyecto.",
    "Segunda sugerencia, como una posible adaptación para alumnos con diferentes ritmos de aprendizaje.",
    "Tercera sugerencia sobre la gestión del aula o la conexión con otros temas."
  ],
  "aviso": "Esta planeación es una propuesta base. Adáptala a las necesidades y contexto específico de tu grupo. La responsabilidad final recae en el docente."
}

### INSTRUCCIONES SOBRE RECURSOS Y MATERIALES ###
1.  **Prioriza materiales económicos y accesibles:** Basa tus sugerencias en materiales de papelería básicos, reciclados y de fácil acceso.
2.  **Evita la dependencia tecnológica:** No propongas recursos que requieran internet, proyectores, computadoras.
3.  **Consolida la lista:** Incluye una lista completa con **todos** los materiales necesarios.

### DETALLES PARA EL CAMPO "planeacion_completa" (MARKDOWN) ###
Usa el siguiente formato exacto:

**PROYECTO:** [Nombre del Proyecto]
**GRADO Y ASIGNATURA:** [Grado y Asignatura]
**CAMPO FORMATIVO:** [Campo Formativo]
**CONTENIDO:** [Contenido]
**PDA:** [Proceso de Desarrollo de Aprendizaje]
**EJES ARTICULADORES:** [Lista de Ejes Articuladores]
**METODOLOGÍA:** [Metodología Específica de la NEM]
**NÚMERO DE SESIONES:** [Número de Sesiones]

## Sesión 1: Título de la Sesión
**Objetivo:** ...
**Actividades:**
1. ...
2. ...
3. ...
4. ...

(Continúa con el resto de las sesiones)

### REGLAS ADICIONALES ###
- No saludes ni uses introducciones. Ve directo a la respuesta JSON.
- No excedas los 12000 tokens
- Trata de que las actividades sean claras pero no breves, sino bien explicadas.
EOT;

try {
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
        CURLOPT_ENCODING => "", // Acepta gzip para acelerar respuesta
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

    if ($curlError) {
        throw new Exception("Error interno de comunicación (cURL)."); 
    }

    $decodedResponse = json_decode($response, true);
    
    if ($httpCode >= 400) {
        $realError = $decodedResponse['error']['message'] ?? 'Error desconocido API';
        error_log("OpenAI API Error: " . $realError);
        throw new Exception("El motor de inteligencia artificial está temporalmente fuera de servicio.");
    }

    $ia_json_string = $decodedResponse['choices'][0]['message']['content'];
    $ia_data = json_decode($ia_json_string, true); 

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("OpenAI devolvió un JSON corrupto: " . $ia_json_string);
        throw new Exception("Incoherencia en los datos generados por el modelo.");
    }

    // ENVÍO DEL RESULTADO AL USUARIO
    echo json_encode([
        'status' => 'completo',
        'plan' => $ia_data, 
        'usos_restantes' => $usosRestantesFinal 
    ]);

} catch (Exception $e) {
    // =========================================================================
    // REEMBOLSO (REFUND) EN CASO DE FALLA TÉCNICA
    // =========================================================================
    error_log("Fallo crítico en Planeación Básica. Reembolsando: " . $e->getMessage());
    
    try {
        require 'conexion.php'; 
        $conexion->begin_transaction();
        
        // 1. Devolvemos el crédito
        $stmt_refund = $conexion->prepare("UPDATE usuarios SET usos_plan_basico = usos_plan_basico + 1 WHERE id = ?");
        $stmt_refund->bind_param("i", $userId);
        $stmt_refund->execute();

        // 2. [LIMPIEZA] Borramos la auditoría de ese token fallido
        $stmt_del_log = $conexion->prepare("DELETE FROM historial_uso WHERE usuario_id = ? AND herramienta = 'Planeación Básica' ORDER BY id DESC LIMIT 1");
        $stmt_del_log->bind_param("i", $userId);
        $stmt_del_log->execute();
        $stmt_del_log->close();

        $conexion->commit();
        $conexion->close();
        
        // Ajustar sesión si es posible
        @session_start();
        $_SESSION['usos_plan_basico'] = $usosRestantesFinal + 1;
        @session_write_close();
    } catch (Exception $refundErr) {
        error_log("CRÍTICO: No se pudo reembolsar el crédito básico al usuario ID: " . $userId);
    }

    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'error' => 'No pudimos generar la planeación en este momento. Inténtelo de nuevo más tarde. No se han descontado créditos.',
        'details' => $e->getMessage() 
    ]);
}
?>