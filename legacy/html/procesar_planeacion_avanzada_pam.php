<?php
// procesar_planeacion_avanzada_pam.php (NUEVA VERSIÓN SÍNCRONA DE ALTO RENDIMIENTO, BLINDADA Y AUDITADA)
ob_start(); 
set_time_limit(150); 
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once 'conexion.php';
require_once 'credenciales.php';

header('Content-Type: application/json');

// --- [NUEVO] Cabeceras y Escudos de Seguridad ---
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

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

// --- [NUEVO] Validación Token CSRF ---
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Firma de seguridad inválida. Recarga la página.']);
    exit;
}

// --- FUNCIÓN PARA CARGAR JSON DE FICHAS PAM ---
function cargarFicheroJSON($nombreArchivo) {
    $ruta = __DIR__ . '/datos_pam/' . $nombreArchivo;
    $cache_key = 'pam_' . $nombreArchivo;
    $apcu_disponible = function_exists('apcu_fetch');

    if ($apcu_disponible) {
        $datos = apcu_fetch($cache_key);
        if ($datos !== false) return $datos; 
    }

    if (!file_exists($ruta)) {
        throw new Exception("FALTA ARCHIVO PAM: No se encontró '{$nombreArchivo}' en la carpeta 'datos_pam'.");
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

// --- FUNCIÓN PARA ELEGIR FICHAS PAM ALEATORIAS ---
function seleccionarFichaAleatoria($lista_de_fichas, $cantidad = 1) {
    if (empty($lista_de_fichas)) return "No hay fichas disponibles.";
    $fichas_validas = array_values($lista_de_fichas);
    $count_validas = count($fichas_validas);
    if ($count_validas == 0) return "No hay fichas válidas disponibles."; 

    if ($cantidad >= $count_validas) {
        $keys = array_keys($fichas_validas);
    } else {
        $keys = array_rand($fichas_validas, $cantidad);
    }

    $resultado_formateado = "";
    if (!is_array($keys)) $keys = [$keys];

    foreach ($keys as $key) {
        if (isset($fichas_validas[$key])) {
            $ficha = $fichas_validas[$key];
            $titulo = $ficha['Titulo'] ?? '(Título no especificado)';
            $numero = $ficha['Numero'] ?? 'N/A';
            $proposito = $ficha['Proposito'] ?? '(Propósito no especificado)';
            $pasos_resumen = (isset($ficha['Pasos']) && is_array($ficha['Pasos']) && isset($ficha['Pasos'][0])) ? $ficha['Pasos'][0] : '(Pasos no especificados)';
            
            $resultado_formateado .= sprintf("- **Título:** %s (ID: %s)\n  - **Propósito:** %s\n  - **Resumen:** %s\n", $titulo, $numero, $proposito, $pasos_resumen);
        }
    }
    return $resultado_formateado;
}

try {
    // 1. Recibir datos
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['pda']) || empty($input['composicionGrupo'])) {
        throw new Exception('Faltan datos curriculares o la composición matemática del grupo.');
    }

    // =========================================================================
    // --- 2. COBRO OPTIMISTA, AUDITORÍA IP Y LIBERACIÓN INMEDIATA DE LA BD ---
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

    // A) Descontamos de inmediato
    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $userId);
    $stmt_update->execute();
    
    // B) [CHIVATO] Registramos el movimiento en el Historial capturando la IP
    $herramienta_usada = "Planeación PAM"; // Match exacto con el Dashboard
    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

    $stmt_log = $conexion->prepare("INSERT INTO historial_uso (usuario_id, herramienta, ip_usuario) VALUES (?, ?, ?)");
    $stmt_log->bind_param("iss", $userId, $herramienta_usada, $ip_cliente);
    $stmt_log->execute();
    $stmt_log->close();

    // C) Confirmamos la transacción
    $conexion->commit();
    $usosRestantesFinal = $user['usos_plan_intermedio'] - 1;
    $_SESSION['usos_plan_intermedio'] = $usosRestantesFinal;

    // Liberamos los recursos para que no se trabe el servidor
    $conexion->close();
    session_write_close();
    // =========================================================================


    // 3. Cargar Fichas PAM
    $fichas_numero = cargarFicheroJSON('fichas1_Actividades_de_numero.json');
    $fichas_sistema_decimal = cargarFicheroJSON('fichas2_Actividades_de_sistema_de_numeracion_decimal.json');
    $fichas_geometria = cargarFicheroJSON('fichas3_Actividades_de_geometria.json');
    $fichas_medicion = cargarFicheroJSON('fichas4_Actividades_de_medicion.json');

    // 4. Construir Banco de Actividades basado en los niveles del grupo (Variables idénticas a las originales)
    $composicion = $input['composicionGrupo'];
    $totalAlumnosBajo = $composicion['presilabico']; // Nivel 1 Básico
    $totalAlumnosMedio = $composicion['silabico'];  // Nivel 2 Intermedio
    $totalAlumnosConsolidado = $composicion['silabico_alfabetico']; // Nivel 3 Consolidado
    $totalAlumnosAvanzado = $composicion['alfabetico']; // Nivel 4 Avanzado

    $banco_de_actividades_desarrollo = "";
    if ($totalAlumnosBajo > 0) {
        $banco_de_actividades_desarrollo .= "**Banco de Fichas de 'Número' (Sugeridas para Nivel 1 - Básico):**\n" . seleccionarFichaAleatoria($fichas_numero, 3) . "\n";
    }
    if ($totalAlumnosMedio > 0) {
        $banco_de_actividades_desarrollo .= "**Banco de Fichas de 'Sistema de Numeración Decimal' (Sugeridas para Nivel 2 - Intermedio):**\n" . seleccionarFichaAleatoria($fichas_sistema_decimal, 3) . "\n";
    }
    if ($totalAlumnosConsolidado > 0 || $totalAlumnosAvanzado > 0) {
       $banco_de_actividades_desarrollo .= "**Banco de Fichas de 'Sistema de Numeración Decimal' (Sugeridas para Niveles 3 y 4 - Consolidado/Avanzado):**\n" . seleccionarFichaAleatoria($fichas_sistema_decimal, 4) . "\n"; 
    }
    $banco_actividades_generales = "**Banco de Fichas de 'Geometría' y 'Medición' (Sugeridas para Inicio/Cierre grupales):**\n" . seleccionarFichaAleatoria(array_merge($fichas_geometria, $fichas_medicion), 4);

    $ejes_texto = implode(', ', $input['ejesArticuladores']);

    // 5. El System Prompt intacto
    $systemPrompt = <<<EOT
Eres un experto en diseño curricular y maestro especialista en la metodología de Propuestas de Aprendizaje para Matemáticas (PAM) dentro del campo de Saberes y Pensamiento Científico de la NEM.
Tu tarea es crear una planeación didáctica detallada para una SEMANA COMPLETA (5 DÍAS), diferenciada por 4 niveles de desempeño matemático.

### DATOS CURRICULARES ###
- Grado: {$input['grado']}
- Campo Formativo: {$input['campoFormativo']}
- Contenido: {$input['contenido']}
- PDA: {$input['pda']}
- Ejes Articuladores: {$ejes_texto}

### COMPOSICIÓN DEL GRUPO ###
- Nivel 1 - Básico: {$totalAlumnosBajo} alumnos
- Nivel 2 - Intermedio: {$totalAlumnosMedio} alumnos
- Nivel 3 - Consolidado: {$totalAlumnosConsolidado} alumnos
- Nivel 4 - Avanzado: {$totalAlumnosAvanzado} alumnos

### BANCO DE FICHAS PAM SELECCIONADAS PARA ESTA SEMANA ###
$banco_actividades_generales
$banco_de_actividades_desarrollo

### INSTRUCCIONES ###
1. Estructura una planeación de 5 sesiones (Día 1 al Día 5).
2. Cada sesión debe tener Inicio, Desarrollo y Cierre.
3. En el Desarrollo de CADA SESIÓN, detalla actividades para los 4 niveles de desempeño utilizando OBLIGATORIAMENTE las Fichas PAM proporcionadas arriba. Menciona TIPO e ID de la ficha usada (ej. 'Ficha de Número, ID: #XX'). Adapta la exigencia al nivel de los alumnos.
4. Devuelve la planeación completa en formato Markdown (##, **, etc.).

### REGLA DE SALIDA ###
Tu respuesta DEBE ser EXCLUSIVAMENTE un objeto JSON válido con la siguiente estructura exacta:
{
  "planeacion_completa": "Todo el texto de la planeación en formato Markdown aquí.",
  "lista_materiales": ["Material 1", "Material 2"],
  "sugerencias_didacticas": ["Sugerencia evaluación formativa 1", "Sugerencia 2"]
}
EOT;

    // 6. Petición a OpenAI
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    $payload = [
        'model' => 'gpt-4o-mini', 
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Genera la planeación avanzada PAM de matemáticas (5 sesiones)."]
        ],
        'temperature' => 0.6 
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_ENCODING => "", // Compresión GZIP añadida para mayor eficiencia
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

    ob_end_clean(); 
    echo json_encode([
        'status' => 'completo',
        'plan' => $ia_data,
        'usos_restantes' => $usosRestantesFinal
    ]);

} catch (Exception $e) {
    // =========================================================================
    // --- [NUEVO] REEMBOLSO Y LIMPIEZA DE AUDITORÍA SI LA IA FALLA ---
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
            $stmt_del_log = $conexion->prepare("DELETE FROM historial_uso WHERE usuario_id = ? AND herramienta = 'Planeación PAM' ORDER BY id DESC LIMIT 1");
            $stmt_del_log->bind_param("i", $userId);
            $stmt_del_log->execute();
            $stmt_del_log->close();

            $conexion->commit();
            $conexion->close();
        } catch (Exception $refundErr) {
            error_log("No se pudo reembolsar el crédito PAM al usuario ID: " . $userId);
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