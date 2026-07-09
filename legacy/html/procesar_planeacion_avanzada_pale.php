<?php
// procesar_planeacion_avanzada_pale.php (NUEVA VERSIÓN SÍNCRONA DE ALTO RENDIMIENTO, BLINDADA Y AUDITADA)
ob_start(); 
set_time_limit(150); 
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

// [SEGURIDAD] Validación estricta del método POST y Autenticación
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'error' => 'Método no permitido.']);
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'No se ha iniciado sesión.']);
    exit;
}
$userId = $_SESSION['usuario_id'];

// [SEGURIDAD] Validación del Token CSRF
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Firma de seguridad inválida. Recarga la página.']);
    exit;
}

// --- FUNCIÓN PARA CARGAR JSON DE FICHAS PALE (Con Caché para ser ultra rápido) ---
function cargarFicheroJSON($nombreArchivo) {
    $ruta = __DIR__ . '/datos_pale/' . $nombreArchivo;
    $cache_key = 'pale_' . $nombreArchivo;
    $apcu_disponible = function_exists('apcu_fetch');

    if ($apcu_disponible) {
        $datos = apcu_fetch($cache_key);
        if ($datos !== false) return $datos; 
    }

    if (!file_exists($ruta)) {
        throw new Exception("FALTA ARCHIVO PALE: No se encontró '{$nombreArchivo}' en la carpeta 'datos_pale'.");
    }
    $contenido = file_get_contents($ruta);
    $datos = json_decode($contenido, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("El archivo '{$nombreArchivo}' tiene un error de formato JSON.");
    }

    if ($apcu_disponible) {
        apcu_store($cache_key, $datos, 3600); 
    }
    return $datos;
}

// --- FUNCIÓN PARA ELEGIR FICHAS ALEATORIAS ---
function seleccionarFichaAleatoria($lista_de_fichas, $cantidad = 1) {
    if (empty($lista_de_fichas)) return "No hay fichas disponibles.";
    if ($cantidad >= count($lista_de_fichas)) {
        $keys = array_keys($lista_de_fichas);
    } else {
        $keys = array_rand($lista_de_fichas, $cantidad);
    }
    
    $resultado_formateado = "";
    if ($cantidad == 1 && !is_array($keys)) $keys = [$keys];

    foreach ($keys as $key) {
        $ficha = $lista_de_fichas[$key];
        $objetivo = is_array($ficha['objetivo']) ? implode(", ", $ficha['objetivo']) : (string)$ficha['objetivo'];
        $descripcion_resumen = is_array($ficha['descripcion']) ? $ficha['descripcion'][0] : (string)$ficha['descripcion'];
        
        $resultado_formateado .= sprintf(
            "- **Título:** %s (ID: %s)\n  - **Objetivo:** %s\n  - **Resumen:** %s\n",
            $ficha['titulo'], $ficha['id'], $objetivo, $descripcion_resumen
        );
    }
    return $resultado_formateado;
}

try {
    // 1. Recibir y validar datos de entrada
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['pda']) || empty($input['composicionGrupo'])) {
        throw new Exception('Faltan datos curriculares o la composición del grupo de lectoescritura.');
    }

    // =========================================================================
    // 2. LÓGICA DE COBRO OPTIMISTA, AUDITORÍA IP Y LIBERACIÓN DE BASE DE DATOS
    // =========================================================================
    $conexion->begin_transaction();

    $stmt = $conexion->prepare("SELECT usos_plan_intermedio FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || $user['usos_plan_intermedio'] <= 0) {
        $conexion->rollback();
        throw new Exception('Has agotado tus generaciones para este plan.');
    }

    // A) Descontamos INMEDIATAMENTE
    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $userId);
    $stmt_update->execute();
    
    // B) [CHIVATO] Registramos el movimiento en el Historial capturando la IP
    $herramienta_usada = "Planeación PALE"; // Match exacto con el Dashboard
    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

    $stmt_log = $conexion->prepare("INSERT INTO historial_uso (usuario_id, herramienta, ip_usuario) VALUES (?, ?, ?)");
    $stmt_log->bind_param("iss", $userId, $herramienta_usada, $ip_cliente);
    $stmt_log->execute();
    $stmt_log->close();

    // C) Confirmamos la transacción
    $conexion->commit();
    $usosRestantesFinal = $user['usos_plan_intermedio'] - 1;
    $_SESSION['usos_plan_intermedio'] = $usosRestantesFinal;

    // [CRÍTICO] Cerramos la DB y liberamos la sesión de PHP para permitir concurrencia masiva
    $conexion->close();
    session_write_close();


    // 3. Cargar Fichas PALE (La caché ahora trabaja sin bloquear la DB)
    $fichas_azules = cargarFicheroJSON('fichas_azules_presilabico.json');
    $fichas_amarillas = cargarFicheroJSON('fichas_amarillas_silabico.json');
    $fichas_verdes = cargarFicheroJSON('fichas_verdes_alfabetico.json');
    $fichas_rosas = cargarFicheroJSON('fichas_rosas_generales_todos_los_niveles.json');
    $fichas_moradas = cargarFicheroJSON('fichas_moradas_docente_gestion_de_clase.json');
    $fichas_generales = array_merge($fichas_rosas, $fichas_moradas);

    // 4. Construir Banco de Actividades basado en el grupo
    $composicion = $input['composicionGrupo'];
    $banco_de_actividades_desarrollo = "";
    
    if ($composicion['presilabico'] > 0) {
        $banco_de_actividades_desarrollo .= "**Fichas Azules (Presilábicos):**\n" . seleccionarFichaAleatoria($fichas_azules, 3) . "\n";
    }
    if ($composicion['silabico'] > 0 || $composicion['silabico_alfabetico'] > 0) {
        $banco_de_actividades_desarrollo .= "**Fichas Amarillas (Silábicos / Silábico-Alfabéticos):**\n" . seleccionarFichaAleatoria($fichas_amarillas, 3) . "\n";
    }
    if ($composicion['alfabetico'] > 0) {
        $banco_de_actividades_desarrollo .= "**Fichas Verdes (Alfabéticos):**\n" . seleccionarFichaAleatoria($fichas_verdes, 3) . "\n";
    }
    $banco_actividades_generales = "**Fichas Rosas/Moradas (Grupales/Inicio/Cierre):**\n" . seleccionarFichaAleatoria($fichas_generales, 4);

    // 5. El System Prompt para OpenAI
    $ejes_texto = htmlspecialchars(implode(', ', $input['ejesArticuladores']));
    
    $systemPrompt = <<<EOT
Eres un experto en diseño curricular y maestro de primer ciclo especialista en la metodología de lectoescritura PALE.
Tu tarea es crear una planeación didáctica detallada para una SEMANA COMPLETA (5 DÍAS).

### DATOS CURRICULARES ###
- Grado: {$input['grado']}
- Campo Formativo: {$input['campoFormativo']}
- Contenido: {$input['contenido']}
- PDA: {$input['pda']}
- Ejes Articuladores: {$ejes_texto}

### COMPOSICIÓN DEL GRUPO ###
- Presilábico: {$composicion['presilabico']} alumnos
- Silábico: {$composicion['silabico']} alumnos
- Silábico-Alfabético: {$composicion['silabico_alfabetico']} alumnos
- Alfabético: {$composicion['alfabetico']} alumnos

### FICHAS PALE SELECCIONADAS PARA ESTA SEMANA ###
$banco_actividades_generales
$banco_de_actividades_desarrollo

### INSTRUCCIONES ###
1. Estructura una planeación de 5 sesiones.
2. Cada sesión debe tener Inicio, Desarrollo y Cierre.
3. En el Desarrollo de CADA SESIÓN, incluye actividades diferenciadas por nivel de lectoescritura utilizando OBLIGATORIAMENTE las Fichas PALE proporcionadas arriba. No inventes otras metodologías, céntrate en aplicar las fichas extraídas.
4. Devuelve la planeación en formato Markdown (##, **, etc.).

### REGLA DE SALIDA ###
Tu respuesta DEBE ser EXCLUSIVAMENTE un objeto JSON válido con la siguiente estructura exacta:
{
  "planeacion_completa": "Todo el texto de la planeación en formato Markdown aquí.",
  "lista_materiales": ["Material 1", "Material 2"],
  "sugerencias_didacticas": ["Sugerencia 1", "Sugerencia 2"]
}
EOT;

    // 6. Petición Síncrona a OpenAI protegida por cURL
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    $payload = [
        'model' => 'gpt-4o-mini', 
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Genera la planeación avanzada PALE de 5 sesiones."]
        ],
        'temperature' => 0.6 
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
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) throw new Exception("Error conectando con OpenAI: " . $curlError);

    $decodedResponse = json_decode($response, true);
    if ($httpCode >= 400) {
        $errorMsg = $decodedResponse['error']['message'] ?? 'Error desconocido de OpenAI.';
        throw new Exception("OpenAI rechazó la solicitud: " . $errorMsg);
    }

    $ia_json_string = trim($decodedResponse['choices'][0]['message']['content']);
    $ia_data = json_decode($ia_json_string, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !isset($ia_data['planeacion_completa'])) {
        throw new Exception("La IA no devolvió el formato JSON correcto.");
    }

    // 7. RESPUESTA FINAL AL FRONTEND
    ob_end_clean(); 
    echo json_encode([
        'status' => 'completo',
        'plan' => $ia_data,
        'usos_restantes' => $usosRestantesFinal
    ]);

} catch (Exception $e) {
    // =========================================================================
    // REEMBOLSO (REFUND) Y LIMPIEZA EN CASO DE FALLA TÉCNICA
    // =========================================================================
    if ($e->getMessage() !== 'Has agotado tus generaciones para este plan.') {
        try {
            require 'conexion.php'; 
            $conexion->begin_transaction();
            
            // 1. Reembolso
            $stmt_refund = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio + 1 WHERE id = ?");
            $stmt_refund->bind_param("i", $userId);
            $stmt_refund->execute();
            
            // 2. [LIMPIEZA] Borramos la auditoría de ese token fallido
            $stmt_del_log = $conexion->prepare("DELETE FROM historial_uso WHERE usuario_id = ? AND herramienta = 'Planeación PALE' ORDER BY id DESC LIMIT 1");
            $stmt_del_log->bind_param("i", $userId);
            $stmt_del_log->execute();
            $stmt_del_log->close();

            $conexion->commit();
            $conexion->close();
        } catch (Exception $refundErr) {
            error_log("CRÍTICO: No se pudo reembolsar el crédito PALE al usuario ID: " . $userId);
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