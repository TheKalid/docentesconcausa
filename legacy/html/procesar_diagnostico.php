<?php
// procesar_diagnostico.php (VERSIÓN SÍNCRONA OPTIMIZADA, BLINDADA Y AUDITADA)
ob_start(); 
set_time_limit(120); 
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once 'conexion.php';
require_once 'credenciales.php';

header('Content-Type: application/json');

// --- Cabeceras de Seguridad ---
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// --- Validación estricta y CSRF ---
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

$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Firma de seguridad inválida. Recarga la página.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['grado']) || empty($input['areas'])) {
        throw new Exception('Faltan datos obligatorios (grado o áreas) para la evaluación.');
    }

    $grado = htmlspecialchars($input['grado']);
    $areas = implode(', ', array_map('htmlspecialchars', $input['areas']));
    $numEstudiantes = !empty($input['num_estudiantes']) ? htmlspecialchars($input['num_estudiantes']) : 'No especificado';
    $necesidad = !empty($input['necesidad']) ? htmlspecialchars($input['necesidad']) : 'Ninguna especificada.';

    // =========================================================================
    // --- COBRO OPTIMISTA, AUDITORÍA IP Y LIBERACIÓN INMEDIATA DE LA BD ---
    // =========================================================================
    $conexion->begin_transaction();
    
    $stmt = $conexion->prepare("SELECT usos_evaluacion_diagnostica FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || $user['usos_evaluacion_diagnostica'] <= 0) {
        $conexion->rollback();
        throw new Exception('Has agotado tus usos disponibles para Evaluaciones Diagnósticas.');
    }

    // A) Descontamos crédito
    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_evaluacion_diagnostica = usos_evaluacion_diagnostica - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $userId);
    $stmt_update->execute();
    
    // B) [CHIVATO] Auditoría para el Dashboard
    $herramienta_usada = "Evaluaciones Diagnósticas"; 
    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

    $stmt_log = $conexion->prepare("INSERT INTO historial_uso (usuario_id, herramienta, ip_usuario) VALUES (?, ?, ?)");
    $stmt_log->bind_param("iss", $userId, $herramienta_usada, $ip_cliente);
    $stmt_log->execute();
    $stmt_log->close();

    // C) Sellamos transacción
    $conexion->commit();
    
    $usosRestantesFinal = $user['usos_evaluacion_diagnostica'] - 1;
    $_SESSION['usos_evaluacion_diagnostica'] = $usosRestantesFinal;

    // Liberamos los recursos para que no se trabe el servidor
    $conexion->close();
    session_write_close();
    // =========================================================================

    // 4. El System Prompt
    $systemPrompt = <<<EOT
## 🧑‍🏫 PERFIL DEL ASISTENTE
Eres un asistente educativo experto en evaluaciones diagnósticas, especializado en educación básica en México (preescolar, primaria y secundaria). Tu objetivo es generar evaluaciones diagnósticas prácticas, útiles y adaptadas por grado escolar.

## 🧩 ÁREAS A DIAGNOSTICAR
1. Lenguaje y comunicación (Lectura, Escritura, Habla)
2. Pensamiento lógico-matemático (Reconocimiento numérico, Operaciones, Fracciones, Cálculo mental, Problemas)
3. Expresión artística y motricidad (Dibujo, modelado, motricidad fina/gruesa)
4. Desarrollo socioemocional (Regulación, colaboración, convivencia)
5. Estilos y canales de aprendizaje (Visual, auditivo, kinestésico)

## 📋 PASOS PARA ELABORAR LA EVALUACIÓN DIAGNÓSTICA
1. Identifica el grado y su nivel esperado según la Nueva Escuela Mexicana (NEM).
2. Selecciona las áreas solicitadas para el diagnóstico.
3. Diseña actividades concretas por área, organizadas en sesiones numeradas.
4. Da instrucciones claras por sesión y área.
5. Incluye indicadores esperados por actividad.
6. Sugiere una forma de registro de resultados.
7. Incluye observaciones para identificar el canal de aprendizaje.
8. Agrega sugerencias de seguimiento si hay bajo desempeño.
9. Adapta la evaluación a la necesidad específica o contexto indicado.

## ⚠️ REGLA CRÍTICA DE SALIDA
Tu respuesta DEBE ser EXCLUSIVAMENTE un objeto JSON válido con la siguiente estructura:
{
  "evaluacion": "Aquí va TODO el contenido de la evaluación estructurado en formato Markdown (usando ## para títulos, ** para negritas, * para listas, etc.)."
}
No agregues texto introductorio ni explicaciones fuera del JSON.
EOT;

    $userPrompt = "Genera la evaluación diagnóstica para:\n- Grado: " . str_replace('_', ' ', $grado) . "\n- Áreas: {$areas}\n- Alumnos: {$numEstudiantes}\n- Contexto/Necesidad: {$necesidad}";

    // 5. Petición Síncrona a OpenAI protegida por cURL
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
        CURLOPT_ENCODING => "", // Acepta gzip para acelerar transferencia
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
        throw new Exception("Error conectando con OpenAI: " . $curlError);
    }

    $decodedResponse = json_decode($response, true);
    if ($httpCode >= 400) {
        $errorMsg = $decodedResponse['error']['message'] ?? 'Error desconocido de la API';
        throw new Exception("OpenAI rechazó la solicitud: " . $errorMsg);
    }

    $ia_json_string = trim($decodedResponse['choices'][0]['message']['content']);
    $ia_data = json_decode($ia_json_string, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !isset($ia_data['evaluacion'])) {
        throw new Exception("La IA no devolvió el formato JSON correcto.");
    }

    ob_end_clean(); 
    echo json_encode([
        'status' => 'completo',
        'data' => $ia_data['evaluacion'],
        'usos_restantes' => $usosRestantesFinal
    ]);

} catch (Exception $e) {
    // =========================================================================
    // REEMBOLSO Y LIMPIEZA DE AUDITORÍA
    // =========================================================================
    if ($e->getMessage() !== 'Has agotado tus usos disponibles para Evaluaciones Diagnósticas.') {
        try {
            require 'conexion.php'; 
            $conexion->begin_transaction();
            
            // 1. Reembolso
            $stmt_refund = $conexion->prepare("UPDATE usuarios SET usos_evaluacion_diagnostica = usos_evaluacion_diagnostica + 1 WHERE id = ?");
            $stmt_refund->bind_param("i", $userId);
            $stmt_refund->execute();
            
            // 2. [LIMPIEZA] Borramos la auditoría de ese token fallido
            $stmt_del_log = $conexion->prepare("DELETE FROM historial_uso WHERE usuario_id = ? AND herramienta = 'Evaluaciones Diagnósticas' ORDER BY id DESC LIMIT 1");
            $stmt_del_log->bind_param("i", $userId);
            $stmt_del_log->execute();
            $stmt_del_log->close();

            $conexion->commit();
            $conexion->close();
        } catch (Exception $refundErr) {
            error_log("No se pudo reembolsar el crédito de Evaluación Diagnóstica al usuario ID: " . $userId);
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