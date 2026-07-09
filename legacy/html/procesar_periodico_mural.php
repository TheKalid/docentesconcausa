<?php
// ==========================================
// CONFIGURACIÓN DEL SERVIDOR Y SEGURIDAD
// ==========================================
ob_start(); // Evita que PHP rompa el JSON con advertencias invisibles
set_time_limit(120);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once 'conexion.php';
require_once 'credenciales.php';

header('Content-Type: application/json; charset=utf-8');

// [SEGURIDAD] Cabeceras Anti-Caché y Clickjacking
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// ==========================================
// CAPA DE SEGURIDAD (GATEKEEPING)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'error' => 'Método no permitido.']);
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Sesión inválida.']);
    exit;
}

// Validación CSRF para evitar ataques de sesión cruzada
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Firma de seguridad inválida. Recarga la página.']);
    exit;
}

$userId = $_SESSION['usuario_id'];
$json_data = file_get_contents('php://input');
$data = json_decode($json_data);

// Prevención de DoS por payloads enormes o vacíos
if (!$data || empty($data->tipo_proyecto) || empty($data->efemeride)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Faltan datos en el formulario.']);
    exit;
}

// =========================================================================
// COBRO OPTIMISTA, AUDITORÍA IP Y LIBERACIÓN INMEDIATA DE LA BASE DE DATOS
// =========================================================================
$conexion->begin_transaction();

try {
    $stmt = $conexion->prepare("SELECT usos_plan_intermedio FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || $user['usos_plan_intermedio'] <= 0) {
        $conexion->rollback();
        echo json_encode(['status' => 'error', 'error' => 'Créditos insuficientes.']);
        exit;
    }

    // A) Descontar inmediatamente
    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $userId);
    $stmt_update->execute();
    
    // B) [CHIVATO] Auditoría para el Dashboard Analítico
    $herramienta_usada = "Periódico Mural y Efemérides"; // Match exacto con Analytics
    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

    $stmt_log = $conexion->prepare("INSERT INTO historial_uso (usuario_id, herramienta, ip_usuario) VALUES (?, ?, ?)");
    $stmt_log->bind_param("iss", $userId, $herramienta_usada, $ip_cliente);
    $stmt_log->execute();
    $stmt_log->close();

    // C) Sellar en Base de Datos
    $conexion->commit();
    $usosRestantesFinal = $user['usos_plan_intermedio'] - 1;
    $_SESSION['usos_plan_intermedio'] = $usosRestantesFinal;

    // [CRÍTICO] Liberamos la BD y la sesión de PHP para permitir alta concurrencia
    $conexion->close();
    session_write_close();

    // ==========================================
    // ÁREA DE EDICIÓN DEL PROMPT DE LA IA (MEJORADO)
    // ==========================================
    $PROMPT_SISTEMA = <<<EOT
Eres un experto coordinador escolar y maestro de ceremonias en México. Tu labor es ayudar a docentes a organizar eventos cívicos, kermeses, altares, representaciones teatrales o periódicos murales con un enfoque profesional, pedagógico y altamente realista.

Genera una propuesta logística y creativa altamente estructurada, que destaque por su profesionalismo pero que sea lo suficientemente sencilla para integrarse fácilmente como un "Proyecto Escolar" dentro de la planeación docente de la Nueva Escuela Mexicana (NEM).

Tu única respuesta debe ser un objeto JSON válido con la siguiente estructura exacta:
{
  "titulo_propuesta": "Nombre creativo y formal del evento o mural",
  "lista_materiales": ["Material 1", "Material 2", "Material 3"],
  "desarrollo_proyecto": "Aquí va el cuerpo principal en formato Markdown. Incluye justificación pedagógica breve, cronograma sencillo, distribución de tareas y el guion o diseño paso a paso.",
  "consejos_practicos": ["Consejo logístico 1", "Consejo sobre control de grupo 2", "Consejo de seguridad 3"]
}

REGLAS CRÍTICAS DE CONTEXTO ESCOLAR:
1. PRESUPUESTO Y RECURSOS LIMITADOS: Asume que es una escuela con muy bajo presupuesto. Usa exclusivamente materiales de fácil acceso, económicos o reciclables. No sugieras rentas costosas ni equipos tecnológicos avanzados.
2. CERO PADRES DE FAMILIA: La planeación debe ser ejecutable por la maestra y los alumnos dentro del aula.
3. VINCULACIÓN A PROYECTOS (NEM): El diseño del evento debe sentirse como la culminación de un proyecto escolar. Usa un lenguaje profesional y estructurado (Justificación, Propósito) que el maestro pueda presentar y justificar ante directivos.
4. LIGAS MULTIMEDIA (YOUTUBE): Sugiere obligatoriamente al menos 2 enlaces reales de YouTube (o términos exactos de búsqueda) útiles para el docente. Ejemplo: Coreografías, pistas musicales, tutoriales o audios. Fórmatealos como enlaces Markdown: [Título del Video/Audio](Enlace).
EOT;

    // Sanitización de las variables que van al prompt
    $userPrompt = "Tipo de Proyecto: " . trim(strip_tags($data->tipo_proyecto)) . "\n" .
                  "Efeméride: " . trim(strip_tags($data->efemeride)) . "\n" .
                  "Grado/Nivel: " . trim(strip_tags($data->nivel_grado)) . "\n" .
                  "Contexto Extra: " . trim(strip_tags($data->contexto_extra ?? 'Ninguno'));

    $payload = [
        'model' => 'gpt-4o-mini',
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => $PROMPT_SISTEMA],
            ['role' => 'user', 'content' => $userPrompt]
        ],
        'temperature' => 0.7
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_ENCODING => "", // Compresión GZIP
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 90,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        throw new Exception("El motor de IA está saturado. Intente de nuevo en unos segundos.");
    }

    $decodedResponse = json_decode($response, true);
    $ia_json_string = $decodedResponse['choices'][0]['message']['content'] ?? '{}';
    
    // Limpieza de Markdown extra
    if (strpos($ia_json_string, '```') === 0) {
        $ia_json_string = preg_replace('/^```json\s*/', '', $ia_json_string);
        $ia_json_string = preg_replace('/\s*```$/', '', $ia_json_string);
    }
    
    $ia_data = json_decode($ia_json_string, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Incoherencia en los datos generados. Intenta de nuevo.");
    }

    ob_end_clean();
    echo json_encode([
        'status' => 'completo',
        'plan' => $ia_data,
        'usos_restantes' => $usosRestantesFinal
    ]);

} catch (Exception $e) {
    // REEMBOLSO Y LIMPIEZA DE AUDITORÍA EN CASO DE FALLA
    if ($e->getMessage() !== 'Créditos insuficientes.') {
        try {
            require 'conexion.php'; 
            $conexion->begin_transaction();
            
            // 1. Reembolsar token
            $stmt_refund = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio + 1 WHERE id = ?");
            $stmt_refund->bind_param("i", $userId);
            $stmt_refund->execute();
            
            // 2. Limpiar huella en historial
            $stmt_del_log = $conexion->prepare("DELETE FROM historial_uso WHERE usuario_id = ? AND herramienta = 'Periódico Mural y Efemérides' ORDER BY id DESC LIMIT 1");
            $stmt_del_log->bind_param("i", $userId);
            $stmt_del_log->execute();
            $stmt_del_log->close();

            $conexion->commit();
            $conexion->close();
        } catch (Exception $refundErr) {
            error_log("CRÍTICO: No se pudo reembolsar el crédito de periodico mural al usuario ID: " . $userId);
        }
    }

    ob_end_clean();
    http_response_code(200);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
?>