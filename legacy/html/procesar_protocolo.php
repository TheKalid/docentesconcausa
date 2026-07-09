<?php
// procesar_protocolo.php (VERSIÓN ENDURECIDA, AUDITADA Y DE ALTO RENDIMIENTO)
ob_start(); 
set_time_limit(120);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once 'conexion.php';
require_once 'credenciales.php';

header('Content-Type: application/json');
// [SEGURIDAD] Cabeceras
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

    // [SEGURIDAD] Verificación CSRF (Anti-Bots)
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        throw new Exception('Firma de seguridad inválida. Recarga la página.');
    }

    // 2. Recibir datos
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['consulta']) || empty($input['nivel'])) {
        throw new Exception('Faltan datos de la consulta o el nivel educativo en la petición.');
    }
    $userQuery = htmlspecialchars($input['consulta']);
    $userLevel = htmlspecialchars($input['nivel']);

    // =========================================================================
    // 3. COBRO OPTIMISTA, AUDITORÍA IP Y LIBERACIÓN DE BASE DE DATOS Y SESIÓN
    // =========================================================================
    $conexion->begin_transaction();
    $stmt = $conexion->prepare("SELECT usos_protocolos FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || $user['usos_protocolos'] <= 0) {
        $conexion->rollback();
        throw new Exception('Has agotado tus consultas de protocolos.');
    }

    // A) Descontamos crédito
    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_protocolos = usos_protocolos - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $userId);
    $stmt_update->execute();
    
    // B) [CHIVATO] Auditoría para el Dashboard
    $herramienta_usada = "Protocolos Educativos"; // Match exacto con Analytics
    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

    $stmt_log = $conexion->prepare("INSERT INTO historial_uso (usuario_id, herramienta, ip_usuario) VALUES (?, ?, ?)");
    $stmt_log->bind_param("iss", $userId, $herramienta_usada, $ip_cliente);
    $stmt_log->execute();
    $stmt_log->close();

    // C) Confirmamos la transacción
    $conexion->commit();
    $usosRestantesFinal = $user['usos_protocolos'] - 1;
    $_SESSION['usos_protocolos'] = $usosRestantesFinal;

    // [CRÍTICO] Cerramos la DB y liberamos la sesión de PHP para permitir concurrencia
    $conexion->close();
    session_write_close();
    // =========================================================================

    // 4. Leer la carpeta de los JSONs (Ahora se hace con el servidor ya "libre")
    $contexto_oficial = "";
    $directorio_json = __DIR__ . '/protocolos_json';

    if (!is_dir($directorio_json)) {
        throw new Exception('FALTA CARPETA: No se encontró la carpeta "protocolos_json".');
    }

    $archivos = glob($directorio_json . '/*.json');
    if (empty($archivos)) {
        throw new Exception('CARPETA VACÍA: La carpeta "protocolos_json" no tiene archivos.');
    }

    foreach ($archivos as $archivo) {
        $nombre_archivo = basename($archivo);
        $contenido = @file_get_contents($archivo); 
        if ($contenido) {
            $contexto_oficial .= "\n\n--- DOCUMENTO: {$nombre_archivo} ---\n{$contenido}";
        }
    }

    if (empty(trim($contexto_oficial))) {
         throw new Exception('No se pudo leer el contenido de los JSON. Revisa los permisos.');
    }

    // 5. El System Prompt
    $systemPrompt = <<<EOT
### ROL Y OBJETIVO PRINCIPAL ###
Eres un asistente educativo experto en la normativa y protocolos de actuación en contextos escolares mexicanos. Tu única función es proveer orientación basada en documentos oficiales.

### BASE DE CONOCIMIENTO Y LEYES MEXICANAS ###
A continuación, se te proporcionan los manuales locales cargados en el sistema:
{$contexto_oficial}

### LÓGICA DE PROCESAMIENTO Y EXCEPCIONES ###
1. Analiza cuidadosamente a los actores involucrados (Ej. distingue si el conflicto es entre adultos/personal docente o si involucra a menores de edad).
2. Busca la respuesta PRIMERO en los manuales locales proporcionados.
3. EXCEPCIÓN CRÍTICA: Si la situación (como hostigamiento sexual, acoso laboral entre adultos o conflictos sindicales) NO está claramente definida en los manuales locales, ESTÁ ESTRICTAMENTE PROHIBIDO forzar un protocolo de alumnos o maltrato infantil.
4. En esos casos, TIENES PERMITIDO usar tu conocimiento general avanzado sobre las leyes de México (Ley Federal del Trabajo, Ley General de Acceso de las Mujeres a una Vida Libre de Violencia y los Protocolos de la SEP/Gobierno Federal sobre hostigamiento y acoso sexual).
5. Construye tu respuesta siguiendo estrictamente el formato de salida.
5. Fundamenta las desiciones tomadas con los docuemnos oficiales.

### REGLA CRÍTICA: FORMATO DE SALIDA OBLIGATORIO ###
Tu única respuesta debe ser un objeto JSON válido. La estructura es exactamente:
{
  "protocolo": "Aquí va el contenido principal en formato Markdown detallado (Incluye ## Fundamentación Legal, ## Procedimiento, etc.).",
  "fuentes": ["Nombre del Documento extraído", "Referencia"],
  "sugerencias": ["Sugerencia 1", "Sugerencia 2"],
  "aviso": "Este asistente se basa en documentos oficiales...La aplicacion es responsabilidad de docente."
}
EOT;

    // 6. Petición a OpenAI (Protegida con GZIP y Timeout)
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    $payload = [
        'model' => 'gpt-4o-mini', 
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Situación: \"{$userQuery}\"\nNivel Educativo: {$userLevel}"]
        ],
        'temperature' => 0.3 
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_ENCODING => "", 
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

    // 7. Extraer JSON y Limpiar
    $ia_json_string = trim($decodedResponse['choices'][0]['message']['content']);
    if (strpos($ia_json_string, '```') === 0) {
        $ia_json_string = preg_replace('/^```json\s*/', '', $ia_json_string);
        $ia_json_string = preg_replace('/\s*```$/', '', $ia_json_string);
    }

    $ia_data = json_decode($ia_json_string, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($ia_data['protocolo'])) {
        throw new Exception("La IA no devolvió el JSON correcto.");
    }

    // 8. Éxito: Enviar al Frontend
    ob_end_clean(); 
    echo json_encode([
        'status' => 'completo',
        'data' => $ia_data,
        'usos_restantes' => $usosRestantesFinal
    ]);

} catch (Exception $e) {
    // =========================================================================
    // REEMBOLSO (REFUND) Y LIMPIEZA DE AUDITORÍA EN CASO DE FALLA
    // =========================================================================
    if ($e->getMessage() !== 'Has agotado tus consultas de protocolos.') {
        try {
            require 'conexion.php'; 
            $conexion->begin_transaction();
            
            // 1. Reembolsar token
            $stmt_refund = $conexion->prepare("UPDATE usuarios SET usos_protocolos = usos_protocolos + 1 WHERE id = ?");
            $stmt_refund->bind_param("i", $userId);
            $stmt_refund->execute();
            
            // 2. Limpiar registro de auditoría
            $stmt_del_log = $conexion->prepare("DELETE FROM historial_uso WHERE usuario_id = ? AND herramienta = 'Protocolos Educativos' ORDER BY id DESC LIMIT 1");
            $stmt_del_log->bind_param("i", $userId);
            $stmt_del_log->execute();
            $stmt_del_log->close();

            $conexion->commit();
            $conexion->close();
        } catch (Exception $refundErr) {
            error_log("CRÍTICO: No se pudo reembolsar el crédito de protocolo al usuario ID: " . $userId);
        }
    }

    ob_end_clean();
    http_response_code(200); 
    echo json_encode([
        'status' => 'error', 
        'error' => $e->getMessage()
    ]);
}
?>