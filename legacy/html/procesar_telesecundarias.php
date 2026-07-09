<?php
// procesar_telesecundarias.php (VERSIÓN ENDURECIDA, AUDITADA Y DE ALTA CONCURRENCIA)
ob_start(); // Amortiguador de errores
set_time_limit(160); 
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once 'conexion.php';
require_once 'credenciales.php';

header('Content-Type: application/json');
// [SEGURIDAD] Cabeceras de protección
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

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

$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Firma de seguridad inválida.']);
    exit;
}

$json_data = file_get_contents('php://input');
$data = json_decode($json_data);

if (!$data || empty($data->prompt)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Faltan datos.']);
    exit;
}

// 1. Limpiamos las etiquetas HTML para evitar inyecciones
$userMessageContent = trim(strip_tags($data->prompt));

// 2. LÍMITE DE PROTECCIÓN (10,000 caracteres)
if (mb_strlen($userMessageContent) > 10000) { 
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'El contexto proporcionado es demasiado extenso. Por favor, resume el diagnóstico de tu grupo.']);
    exit;
}

// =========================================================================
// COBRO OPTIMISTA, AUDITORÍA IP Y LIBERACIÓN INMEDIATA
// =========================================================================
$usosRestantesActualizados = 0;
$conexion->begin_transaction();

try {
    $stmt = $conexion->prepare("SELECT usos_plan_intermedio FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || $user['usos_plan_intermedio'] <= 0) {
        $conexion->rollback(); 
        throw new Exception('Usos agotados.');
    }
    
    // A) Descontamos de inmediato
    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $userId);
    $stmt_update->execute();
    
    // B) [CHIVATO] Auditoría para el Dashboard Analítico
    $herramienta_usada = "Planeaciones Telesecundarias"; // Match exacto con Analytics
    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

    $stmt_log = $conexion->prepare("INSERT INTO historial_uso (usuario_id, herramienta, ip_usuario) VALUES (?, ?, ?)");
    $stmt_log->bind_param("iss", $userId, $herramienta_usada, $ip_cliente);
    $stmt_log->execute();
    $stmt_log->close();

    // C) Confirmamos transacción
    $conexion->commit(); 
    
    $usosRestantesActualizados = $user['usos_plan_intermedio'] - 1;
    $_SESSION['usos_plan_intermedio'] = $usosRestantesActualizados;

    // [CRÍTICO] Cerramos la DB y liberamos sesión
    $conexion->close();
    session_write_close();
    // =========================================================================

    // =========================================================================
    // SYSTEM PROMPT CON INSTRUCCIONES PEDAGÓGICAS NEM DE ALTO NIVEL
    // =========================================================================
    $systemPrompt = <<<EOT
### ROL Y OBJETIVO PRINCIPAL ###
Eres un asesor técnico-pedagógico experto en Telesecundarias bajo el marco de la Nueva Escuela Mexicana (NEM). Tu objetivo es diseñar un Proyecto Integrador que ensamble de forma transversal las asignaturas seleccionadas, devolviendo la planeación estrictamente en un objeto JSON válido.

### LÓGICA DE TELESECUNDARIA Y ESTRATEGIA PEDAGÓGICA NEM ###
En Telesecundaria, un único docente imparte todas las asignaturas. La planeación debe tener una **fuerte ilación y coherencia** entre sesiones. Los saberes no se dan aislados; se construyen como un tejido continuo orientado a resolver un problema de la comunidad.

### REGLAS DE ORO OBLIGATORIAS (DURACIÓN Y ESTRUCTURA) ###
1. **TIEMPO EXACTO:** - "1 semana (5 sesiones)" = Desarrollar explícitamente de "Sesión 1" a "Sesión 5".
   - "1 quincena (10 sesiones)" = Desarrollar explícitamente de "Sesión 1" a "Sesión 10".
   - NUNCA agrupes días ni recortes la planeación.
2. **ESTRUCTURA DE CADA SESIÓN (MÍNIMO 4 ACTIVIDADES OBLIGATORIAS):**
   - **Vocabulario NEM (Restricción Absoluta):** Queda ESTRICTAMENTE PROHIBIDO usar las palabras "Introducción", "Desarrollo" o "Cierre" para nombrar las fases de la clase. En su lugar, usa conceptos afines a la NEM como: *Inicio de clase, Punto de partida, Rescate de saberes, Análisis de contenidos, Construcción del conocimiento, Consolidación, Reflexión final o Final de la clase*.
   - **Actividad 1 (Anclaje):** En la Sesión 1 será el rescate de saberes previos. **A partir de la Sesión 2**, esta primera actividad DEBE ser obligatoriamente un momento de feedback, recapitulación o refuerzo que tome los temas de la sesión anterior para anclar el conocimiento nuevo.
   - **Actividades 2 y 3 (Análisis y Construcción):** Aquí se abordan los Contenidos y PDA de las asignaturas. Debe existir una narrativa fluida (ilación) entre las diferentes disciplinas que se tocan ese día. No deben sentirse como materias separadas.
   - **Actividad 4 (Final de la clase):** Debe ser una actividad de consolidación que vincule explícitamente lo trabajado en el día con los PDA correspondientes, generando una reflexión crítica, retroalimentación o un avance medible del proyecto.

### REGLA CRÍTICA: FORMATO DE SALIDA JSON ###
Tu única respuesta debe ser este JSON válido, sin textos externos:
{
  "datos_principales": {
    "proyecto": "Nombre creativo e integrador del proyecto",
    "justificacion_articulacion": "Explicación breve de cómo se vinculan las disciplinas (hilo conductor)."
  },
  "lista_materiales": [
    "Material físico y económico 1",
    "Material físico y económico 2"
  ],
  "planeacion_completa": "Toda la estructura de sesiones en formato Markdown, respetando el vocabulario NEM y las 4 actividades por sesión.",
  "sugerencias_didacticas": [
    "Sugerencia de evaluación formativa transversal.",
    "Sugerencia para adaptar el contenido a ritmos de aprendizaje distintos."
  ],
  "aviso": "Esta planeación para Telesecundaria es una propuesta base alineada a la Nueva Escuela Mexicana..."
}

### PAUTAS PARA "planeacion_completa" (MARKDOWN) ###
Especifica con claridad qué disciplina se moviliza en cada momento.
Ejemplo de estructura esperada por sesión:
## Sesión X: [Título de la sesión]
**Actividades:**
1. **[Punto de partida / Recapitulación]:** (Feedback de la sesión anterior y detonador)...
2. **[Análisis de contenidos - Disciplina A]:** ...
3. **[Construcción del conocimiento - Disciplina B]:** (Actividad hilada con la anterior)...
4. **[Reflexión final]:** (Vinculación con el PDA y cierre cognitivo de la clase)...

NO dependas de internet ni proyectores. Usa materiales rurales o semiurbanos accesibles.
EOT;

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    $payload = [
        'model' => 'gpt-4o-mini', 
        'response_format' => ['type' => 'json_object'], 
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessageContent]
        ],
        'temperature' => 0.75 
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_ENCODING => '', 
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 150,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) throw new Exception("Error de red: " . $curlError);
    $decodedResponse = json_decode($response, true);
    if ($httpCode >= 400) throw new Exception("Fallo en la respuesta del motor pedagógico.");

    $ia_json_string = $decodedResponse['choices'][0]['message']['content'];
    
    // Limpieza de Markdown extra
    if (strpos($ia_json_string, '```') === 0) {
        $ia_json_string = preg_replace('/^```json\s*/', '', $ia_json_string);
        $ia_json_string = preg_replace('/\s*```$/', '', $ia_json_string);
    }
    
    $ia_data = json_decode($ia_json_string, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error estructurando la respuesta del servidor.");
    }

    ob_end_clean();
    echo json_encode([
        'status' => 'completo',
        'plan' => $ia_data,
        'usos_restantes' => $usosRestantesActualizados
    ]);

} catch (Exception $e) {
    // REEMBOLSO Y LIMPIEZA DE AUDITORÍA EN CASO DE ERROR DE IA
    if ($e->getMessage() !== 'Usos agotados.') {
        try {
            require 'conexion.php'; 
            $conexion->begin_transaction();
            
            // 1. Reembolsar uso
            $stmt_refund = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio + 1 WHERE id = ?");
            $stmt_refund->bind_param("i", $userId);
            $stmt_refund->execute();
            
            // 2. Borrar huella en historial
            $stmt_del_log = $conexion->prepare("DELETE FROM historial_uso WHERE usuario_id = ? AND herramienta = 'Planeaciones Telesecundarias' ORDER BY id DESC LIMIT 1");
            $stmt_del_log->bind_param("i", $userId);
            $stmt_del_log->execute();
            $stmt_del_log->close();

            $conexion->commit();
            $conexion->close();
            
            session_start();
            $_SESSION['usos_plan_intermedio'] = $usosRestantesActualizados + 1;
            session_write_close();
        } catch (Exception $refundError) {
            error_log("CRÍTICO: No se pudo reembolsar el crédito de Telesecundarias al usuario ID: " . $userId);
        }
    }

    ob_end_clean();
    http_response_code(200);
    echo json_encode([
        'status' => 'error', 
        'error' => 'Error al procesar la solicitud. ' . ($e->getMessage() !== 'Usos agotados.' ? 'Tu uso ha sido reembolsado.' : ''), 
        'details' => $e->getMessage()
    ]);
}
?>