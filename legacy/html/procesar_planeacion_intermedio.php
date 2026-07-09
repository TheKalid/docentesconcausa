<?php
// procesar_planeacion_intermedio.php (VERSIÓN OPTIMIZADA, BLINDADA Y CON AUDITORÍA ANALÍTICA)

// 1. CONFIGURACIÓN INICIAL Y MANEJO CIEGO DE ERRORES
set_time_limit(130); // Límite amplio para la IA

// [SEGURIDAD] Evitamos mostrar la ruta del servidor si hay errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once 'conexion.php';
require_once 'credenciales.php';

header('Content-Type: application/json');
// Evitar caché de la respuesta API
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// [SEGURIDAD] 2. FILTROS DE GATEKEEPING Y SANITIZACIÓN

// A. Bloqueo de métodos no autorizados. Solo aceptamos POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método HTTP no permitido.']);
    exit;
}

// B. Validación de Sesión. Identificamos al usuario.
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No se ha iniciado sesión.']);
    exit;
}
$userId = $_SESSION['usuario_id'];

// C. Validación del Token CSRF
$csrf_token = '';
if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'];
} elseif (isset($_SERVER['X_CSRF_TOKEN'])) {
    $csrf_token = $_SERVER['X_CSRF_TOKEN'];
}

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    error_log("Bloqueo de CSRF Intermedio para el usuario ID: " . $userId);
    echo json_encode(['success' => false, 'error' => 'Firma de seguridad inválida. Por favor, recarga la página.']);
    exit;
}

// D. Recepción y Sanitización de Datos
$json_data = file_get_contents('php://input');
$data = json_decode($json_data);

if (!$data || empty($data->prompt)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Faltan datos de la planeación.']);
    exit;
}

// [SEGURIDAD] Limpiamos el input
$userMessageContent = trim(strip_tags($data->prompt));

if (mb_strlen($userMessageContent) > 5000) {
    http_response_code(400);
    error_log("Intento de saturación bloqueado para el usuario ID: " . $userId);
    echo json_encode(['success' => false, 'error' => 'El contexto proporcionado es demasiado extenso.']);
    exit;
}

// =========================================================================
// 3. OPTIMIZACIÓN DE CONCURRENCIA Y AUDITORÍA (PRE-DEDUCT)
// =========================================================================
$usosRestantesActualizados = 0;
$conexion->begin_transaction();

try {
    // Bloqueamos SOLO por unos milisegundos
    $stmt = $conexion->prepare("SELECT usos_plan_intermedio FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || $user['usos_plan_intermedio'] <= 0) {
        $conexion->rollback(); // Cancelamos si no tiene usos
        echo json_encode(['success' => false, 'error' => 'Has agotado tus usos para generar planeaciones.']);
        exit;
    }
    
    // A) Descontamos el uso INMEDIATAMENTE
    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $userId);
    $stmt_update->execute();
    
    // B) [CHIVATO] Registramos el movimiento en el Historial capturando la IP
    $herramienta_usada = "Planeación Avanzada"; // Match exacto con el Dashboard
    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

    $stmt_log = $conexion->prepare("INSERT INTO historial_uso (usuario_id, herramienta, ip_usuario) VALUES (?, ?, ?)");
    $stmt_log->bind_param("iss", $userId, $herramienta_usada, $ip_cliente);
    $stmt_log->execute();
    $stmt_log->close();
    
    // C) Liberamos la base de datos
    $conexion->commit(); 
    
    $usosRestantesActualizados = $user['usos_plan_intermedio'] - 1;
    $_SESSION['usos_plan_intermedio'] = $usosRestantesActualizados;

} catch (Exception $e) {
    $conexion->rollback();
    http_response_code(500);
    error_log("Error BD al cobrar: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Error de base de datos.']);
    exit;
}

// =========================================================================
// [CRÍTICO] 4. LIBERACIÓN DE SESIÓN (SESSION UNLOCK)
// =========================================================================
// Al cerrar la sesión de PHP aquí, permitimos que el maestro pueda abrir 
// otras pestañas de tu plataforma sin quedarse "congelado" esperando a la IA.
session_write_close(); 


// 5. EL SYSTEM PROMPT MAESTRO 
$systemPrompt = <<<EOT
### ROL Y OBJETIVO PRINCIPAL ###
Eres un asistente educativo experto en la normativa y pedagogía de la Nueva Escuela Mexicana (NEM). Tu única función es generar una planeación didáctica de alta calidad y devolverla como un objeto JSON válido.

### LÓGICA DE PROCESAMIENTO ###
1. Analiza los datos de entrada proporcionados (Grado, PDA, Metodología, etc.).
2. Diseña una planeación didáctica completa y coherente, estructurada en el número de sesiones indicado.
3. Aplica rigurosamente todas las instrucciones pedagógicas y de materiales.
4. Construye tu respuesta siguiendo estrictamente la estructura JSON definida en el formato de salida obligatorio.

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
2.  **Evita la dependencia tecnológica:** No propongas recursos que requieran internet, proyectores, computadoras, videos o películas. La planeación debe ser 100% aplicable en un aula sin acceso a tecnología.
3.  **Consolida la lista:** En el campo `lista_materiales` del JSON, incluye una lista completa con **todos** los materiales necesarios para el proyecto completo.

### DETALLES PARA EL CAMPO "planeacion_completa" (MARKDOWN) ###
Para el contenido de este campo, es obligatorio usar formato Markdown. La estructura debe ser la siguiente:
1.  **Un encabezado completo** con los datos del proyecto.
2.  **Las sesiones detalladas**.

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

### INSTRUCCIONES PEDAGÓGICAS (A APLICAR DENTRO DEL CAMPO "planeacion_completa") ###
- **Metodología NEM:** Aplica la metodología indicada en las descripciones de las actividades.
- **Continuidad:** A partir de la Sesión 2, la primera actividad siempre debe incluir una retroalimentación o conexión con la sesión anterior.
- **Evidencias:** Asegúrate de que haya 1 o 2 actividades claramente marcadas como "(Evidencia de Aprendizaje)" por cada 5 sesiones.
- **Cierre del Proyecto:** La última sesión debe reflejar la culminación del proyecto.
- **Lenguaje:** Sé práctico, claro y directo en todas las descripciones.

### REGLAS ADICIONALES ###
- No saludes ni uses introducciones. Ve directo a la respuesta JSON.
- No excedas los 12000 tokens.
- Trata de que las actividades sean claras pero no breves, sino bien explicadas.
- No des la sesión de aviso.
- Evita decir groserías o algo que esté fuera de lugar. Recuerda que este plan tiene un botón al que se le da contexto y ese contexto se integra para dar una planeación más contextualizada.
- REGLA DE ORO DE SESIONES: Debes desarrollar la cantidad EXACTA de sesiones que se te solicita. Si se te piden 5 sesiones, es OBLIGATORIO escribir detalladamente desde la "Sesión 1" hasta la "Sesión 5". NUNCA agrupes días ni recortes la planeación.
EOT;

try {
    // 6. LLAMADA A OPENAI
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

    // [EFICIENCIA] Añadimos CURLOPT_ENCODING para aceptar compresión GZIP (acelera la transferencia)
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_ENCODING => '', 
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 115,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("Error de conexión con IA: " . $curlError);
    }

    $decodedResponse = json_decode($response, true);
    if ($httpCode >= 400) {
        $errorMsg = $decodedResponse['error']['message'] ?? 'Error desconocido en la API.';
        error_log("OpenAI Error: " . $errorMsg);
        throw new Exception("El motor de inteligencia artificial rechazó la petición.");
    }

    $ia_json_string = $decodedResponse['choices'][0]['message']['content'];
    $ia_data = json_decode($ia_json_string, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Incoherencia en los datos devueltos por el modelo.");
    }

    // 7. RESPUESTA AL FRONTEND EXITOSA
    echo json_encode([
        'status' => 'completo',
        'plan' => $ia_data,
        'usos_restantes' => $usosRestantesActualizados
    ]);

} catch (Exception $e) {
    // =========================================================================
    // 8. MANEJO DE ERRORES: REEMBOLSO DEL CRÉDITO Y LIMPIEZA
    // =========================================================================
    
    $conexion->begin_transaction();
    try {
        // 1. Reembolsamos el token
        $stmt_refund = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio + 1 WHERE id = ?");
        $stmt_refund->bind_param("i", $userId);
        $stmt_refund->execute();
        
        // 2. [LIMPIEZA] Borramos la auditoría para que no aparezca en Excel/PDF si falló
        $stmt_del_log = $conexion->prepare("DELETE FROM historial_uso WHERE usuario_id = ? AND herramienta = 'Planeación Avanzada' ORDER BY id DESC LIMIT 1");
        $stmt_del_log->bind_param("i", $userId);
        $stmt_del_log->execute();
        $stmt_del_log->close();

        $conexion->commit();
        
        // Es necesario reactivar la sesión temporalmente para actualizar su variable
        session_start();
        $_SESSION['usos_plan_intermedio'] = $usosRestantesActualizados + 1;
        session_write_close();
        
    } catch (Exception $refundError) {
        $conexion->rollback();
        error_log("CRÍTICO: No se pudo reembolsar el crédito al usuario " . $userId);
    }

    http_response_code(500);
    error_log("Error en Planeación Intermedia: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'error' => 'Hubo un problema procesando tu solicitud en la IA. No te preocupes, se te ha reembolsado el uso. Intenta de nuevo.', 
        'details' => $e->getMessage()
    ]);
}
?>