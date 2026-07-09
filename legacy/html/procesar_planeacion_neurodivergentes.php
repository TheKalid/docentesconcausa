<?php
// procesar_planeacion_neurodivergentes.php (NUEVA VERSIÓN SÍNCRONA DE ALTO RENDIMIENTO + AUDITORÍA)
ob_start(); // Buffer para atrapar errores y no romper el JSON
set_time_limit(120); 
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once 'conexion.php';
require_once 'credenciales.php';

header('Content-Type: application/json');

// --- [NUEVO] Cabeceras de Seguridad ---
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

try {
    // 1. Verificación de sesión
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('No se ha iniciado sesión.');
    }
    $usuario_id = $_SESSION['usuario_id'];

    // --- [NUEVO] Validación CSRF Anti-Bots ---
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        throw new Exception('Firma de seguridad inválida. Recarga la página.');
    }

    // 2. Leer datos desde $_POST (El frontend envía FormData)
    $nivel = $_POST['nivel'] ?? null;
    $grado = $_POST['grado'] ?? null;
    $neuro_val = $_POST['neurodivergencia'] ?? null;
    $descripcion_libre = $_POST['descripcion_libre'] ?? '';
    $gustos = $_POST['observaciones_gustos'] ?? '';
    $fortalezas = $_POST['observaciones_fortalezas'] ?? '';
    $oportunidad = $_POST['observaciones_oportunidad'] ?? '';

    if (!$nivel || !$grado || !$neuro_val) {
        throw new Exception('Faltan datos clave (nivel, grado o perfil).');
    }

    // =========================================================================
    // --- [NUEVO] 3. COBRO OPTIMISTA, AUDITORÍA IP Y LIBERACIÓN DE BASE DE DATOS ---
    // =========================================================================
    $conexion->begin_transaction();
    $stmt_check = $conexion->prepare("SELECT usos_plan_intermedio FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt_check->bind_param("i", $usuario_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $user_check = $result_check->fetch_assoc();

    if (!$user_check || $user_check['usos_plan_intermedio'] <= 0) {
        $conexion->rollback();
        throw new Exception('Has agotado tus generaciones para este plan.');
    }

    // Descontamos crédito de inmediato
    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $usuario_id);
    $stmt_update->execute();

    // --- [CHIVATO] Registramos el movimiento en el Historial capturando la IP ---
    $herramienta_usada = "Planificador Inclusivo (DUA)";
    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

    $stmt_log = $conexion->prepare("INSERT INTO historial_uso (usuario_id, herramienta, ip_usuario) VALUES (?, ?, ?)");
    $stmt_log->bind_param("iss", $usuario_id, $herramienta_usada, $ip_cliente);
    $stmt_log->execute();
    $stmt_log->close();
    // ----------------------------------------------------------------------------

    // Confirmamos la compra y el registro en la bitácora
    $conexion->commit();
    $usosRestantesFinal = $user_check['usos_plan_intermedio'] - 1;
    $_SESSION['usos_plan_intermedio'] = $usosRestantesFinal;

    // Liberamos los recursos para que no se trabe el servidor
    $conexion->close();
    session_write_close();
    // =========================================================================

    // 4. Preparación del contexto para la IA
    $neuro_texto = $neuro_val;
    switch ($neuro_val) {
        case 'tea': $neuro_texto = "Trastorno del Espectro Autista (TEA)"; break;
        case 'tdah': $neuro_texto = "TDAH (Atención e Hiperactividad)"; break;
        case 'dislexia': $neuro_texto = "Dislexia"; break;
        case 'altas_capacidades': $neuro_texto = "Altas Capacidades"; break;
        case 'otra': $neuro_texto = "Otra: " . $descripcion_libre; break;
    }

    // 5. El System Prompt Maestro (Intacto)
    $systemPrompt = <<<EOT
Eres un asistente hiperespecializado en neurodivergencias y educación inclusiva, con amplio conocimiento en psicología educativa, pedagogía adaptativa, neurociencias, educación especial y políticas educativas de México.
Tu misión es apoyar a docentes, psicopedagogos y familias en la comprensión, identificación y atención de alumnos neurodivergentes, brindando información actualizada, empática y basada en evidencia.

⚙️ Instrucciones principales:
- Tono: profesional, empático, inclusivo y libre de estigmas.
- Enfoque: centrado en las fortalezas del alumno, no en el déficit.
- Contexto base: México (utiliza fuentes oficiales y actualizadas del país como SEP, CONADIS, IMEDIS, UDG, UNAM, etc.).
- Propósito: ofrecer estrategias pedagógicas, adaptaciones curriculares, orientaciones familiares y comprensión general de la neurodiversidad.
- Precisión: cada respuesta debe ser práctica, fundamentada y adaptada al nivel educativo.
- Nunca uses lenguaje patologizante (ej. "padece de", "sufre de"); utiliza "presenta", "manifiesta" o "forma distinta de procesar".
- Usa siempre lenguaje inclusivo y claro. Evita tecnicismos sin explicación.
- Cuando se soliciten adaptaciones, genera respuestas estructuradas con base en el Diseño Universal para el Aprendizaje (DUA).

**FORMATO DE RESPUESTA OBLIGATORIO:**
Tu respuesta DEBE ser EXCLUSIVAMENTE un objeto JSON válido. NO escribas bloques de código markdown, ni saludos, ni notas. El JSON debe tener exactamente esta estructura:
{
  "titulo_recomendaciones": "Recomendaciones para [Perfil/Nombre]",
  "lista_recomendaciones": [
    { "estrategia": "Título corto de la estrategia", "descripcion": "Explicación detallada de cómo implementarla, conectando gustos/fortalezas con áreas de oportunidad." },
    { "estrategia": "...", "descripcion": "..." }
  ],
  "sugerencias_adicionales": [
      "Sugerencia corta 1",
      "Sugerencia corta 2"
  ]
}
EOT;

    $userPrompt = "Genera 5 recomendaciones de estrategias pedagógicas específicas y accionables.\n\n**PERFIL DEL ESTUDIANTE:**\n- Nivel Educativo: {$nivel}\n- Grado/Año: {$grado}\n- Condición: {$neuro_texto}\n\n**OBSERVACIONES DEL DOCENTE:**\n- Gustos e Intereses: {$gustos}\n- Fortalezas: {$fortalezas}\n- Áreas de Oportunidad: {$oportunidad}\n\n**ENFOQUE OBLIGATORIO:** Las recomendaciones DEBEN centrarse en CÓMO usar los 'Gustos e Intereses' y las 'Fortalezas' para abordar las 'Áreas de Oportunidad'.";

    // 6. Petición Síncrona a OpenAI
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
    
    // Limpieza de Markdown extra si la IA se pone terca
    if (strpos($ia_json_string, '```') === 0) {
        $ia_json_string = preg_replace('/^```json\s*/', '', $ia_json_string);
        $ia_json_string = preg_replace('/\s*```$/', '', $ia_json_string);
    }

    $ia_data = json_decode($ia_json_string, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($ia_data['lista_recomendaciones'])) {
        throw new Exception("La IA no devolvió el JSON con el formato correcto.");
    }

    // 8. Enviar respuesta final
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => $ia_data,
        'usos_restantes' => $usosRestantesFinal
    ]);

} catch (Exception $e) {
    // --- [NUEVO] REEMBOLSO Y LIMPIEZA DE BITÁCORA SI LA IA FALLA ---
    if ($e->getMessage() !== 'Has agotado tus generaciones para este plan.') {
        try {
            require 'conexion.php'; 
            $conexion->begin_transaction();
            
            // 1. Reembolso del token
            $stmt_refund = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio + 1 WHERE id = ?");
            $stmt_refund->bind_param("i", $usuario_id);
            $stmt_refund->execute();
            
            // 2. [LIMPIEZA] Borramos la auditoría de ese token fallido
            $stmt_del_log = $conexion->prepare("DELETE FROM historial_uso WHERE usuario_id = ? AND herramienta = 'Planificador Inclusivo (DUA)' ORDER BY id DESC LIMIT 1");
            $stmt_del_log->bind_param("i", $usuario_id);
            $stmt_del_log->execute();
            $stmt_del_log->close();

            $conexion->commit();
            $conexion->close();
        } catch (Exception $refundErr) {
            error_log("CRÍTICO: No se pudo reembolsar el crédito Neurodivergente al usuario ID: " . $usuario_id);
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