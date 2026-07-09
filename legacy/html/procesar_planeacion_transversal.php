<?php
// procesar_planeacion_transversal.php (VERSIÓN ENDURECIDA, AUDITADA Y DE ALTA CONCURRENCIA)
ob_start(); // Aísla cualquier error de PHP para no romper el formato JSON
set_time_limit(120); 

ini_set('display_errors', 0);
ini_set('log_errors', 1); 

session_start();
require_once 'conexion.php';
require_once 'credenciales.php';

header('Content-Type: application/json; charset=utf-8');
// [SEGURIDAD] Cabeceras de protección
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// ==========================================
// CEREBRO DE LA IA: MODELO TRANSVERSAL
// ==========================================
$PROMPT_SISTEMA = <<<EOT
Eres un experto en diseño curricular tradicional y constructivista. Tu tarea es diseñar una secuencia didáctica que tome como punto de partida un "Eje Principal" (PDA y Campo) y, si el usuario lo proporciona, lo fusione estratégicamente con un "Eje Transversal" (Un segundo PDA y Campo).

MANDATO DE TRANSVERSALIDAD:
Ambos PDA deben entrelazarse de forma natural. Por ejemplo, si el Eje 1 es Matemáticas (Sumas) y el Eje 2 es Lenguajes (Redacción), la actividad principal podría ser que los alumnos redacten un cuento donde el personaje resuelva problemas matemáticos.

PRIORIDAD EDUCATIVA:
Estructura la clase ESTRICTAMENTE bajo el modelo de Competencias (Habilidades, Actitudes y Valores). Prioriza el desarrollo cognitivo duro (ejercicios prácticos, análisis, resolución de problemas).

FORMATO DE SALIDA (JSON OBLIGATORIO):
{
  "datos_principales": {
    "tema_central": "Nombre creativo del tema o lección integrada",
    "pda_ancla": "El PDA principal utilizado",
    "pda_transversal": "El PDA transversal utilizado (Si no enviaron, pon 'No aplica')",
    "competencias": "Describe las Competencias y Habilidades a desarrollar en conjunto"
  },
  "lista_materiales": ["Material 1", "Material 2"],
  "secuencia_completa": "Todo el contenido detallado de las sesiones en formato Markdown.",
  "evaluacion_formativa": ["Rúbrica que evalúe ambos ejes a la vez", "Criterio de evaluación 2"],
  "aviso": "Recuerde adaptar esta secuencia al nivel real de comprensión de sus alumnos."
}

INSTRUCCIONES PARA "secuencia_completa" (Markdown):
Por cada sesión, DEBES dividir las actividades explícitamente en los tres momentos pedagógicos tradicionales:

## Sesión 1
**Inicio (Introducción):** - Actividades para rescatar saberes previos vinculando ambos ejes.
- Enganche
- Explicacion

**Desarrollo:** - Instrucción directa del docente.
- Práctica guiada e independiente. Aquí es donde los dos PDA deben fusionarse en una misma tarea.

**Cierre:** - Reflexión sobre lo aprendido.
- Revisión de ejercicios.
- Propon un juego para cerrar la sesion de manera divertida

Reglas: No uses jerga exclusiva de la NEM como "Proyectos Comunitarios" a menos que se integren lógicamente. Mantén el enfoque en la competencia académica. Si no se te proporciona un Eje Transversal, simplemente crea una clase magistral con el Eje Principal.
EOT;

try {
    // ==========================================
    // [SEGURIDAD] VALIDACIÓN HTTP Y CSRF
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido.');
    }

    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('No se ha iniciado sesión.');
    }
    $userId = $_SESSION['usuario_id'];

    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        throw new Exception('Firma de seguridad inválida. Recarga la página.');
    }

    // ==========================================
    // RECEPCIÓN Y LIMPIEZA DE DATOS
    // ==========================================
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    $obligatorios = ['grado', 'tiempo', 'campo1', 'cont1', 'pda1'];
    foreach ($obligatorios as $campo) {
        if (empty($data[$campo])) {
            throw new Exception("Falta el campo obligatorio: $campo");
        }
    }

    $grado  = trim(strip_tags($data['grado']));
    $tiempo = trim(strip_tags($data['tiempo']));
    $campo1 = trim(strip_tags($data['campo1']));
    $cont1  = trim(strip_tags($data['cont1']));
    $pda1   = trim(strip_tags($data['pda1']));

    $campo2 = !empty($data['campo2']) ? trim(strip_tags($data['campo2'])) : '';
    $cont2  = !empty($data['cont2']) ? trim(strip_tags($data['cont2'])) : '';
    $pda2   = !empty($data['pda2']) ? trim(strip_tags($data['pda2'])) : '';

    $userMessageContent = "Grado General: $grado\nDuración: $tiempo\n\n";
    $userMessageContent .= "=== EJE PRINCIPAL ===\nCampo Formativo 1: $campo1\nContenido 1: $cont1\nPDA Principal: $pda1\n\n";

    if ($campo2 && $cont2 && $pda2) {
        $userMessageContent .= "=== EJE TRANSVERSAL ===\nCampo Formativo 2: $campo2\nContenido 2: $cont2\nPDA Transversal: $pda2\n\n";
    } else {
        $userMessageContent .= "(Sin eje transversal, trabajar solo con el eje principal).\n";
    }

    if (mb_strlen($userMessageContent) > 3000) {
        throw new Exception('La solicitud excede la longitud máxima permitida.');
    }

    // =========================================================================
    // COBRO OPTIMISTA, AUDITORÍA IP Y LIBERACIÓN DE BASE DE DATOS
    // =========================================================================
    $conexion->begin_transaction();

    $stmt = $conexion->prepare("SELECT usos_plan_intermedio FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || $user['usos_plan_intermedio'] <= 0) {
        $conexion->rollback();
        throw new Exception('Has agotado tus créditos para este plan.');
    }
    
    // A) Descontar INMEDIATAMENTE
    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $userId);
    $stmt_update->execute();
    
    // B) [CHIVATO] Auditoría para el Dashboard
    $herramienta_usada = "Planeación Transversal"; // Match exacto con Analytics
    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

    $stmt_log = $conexion->prepare("INSERT INTO historial_uso (usuario_id, herramienta, ip_usuario) VALUES (?, ?, ?)");
    $stmt_log->bind_param("iss", $userId, $herramienta_usada, $ip_cliente);
    $stmt_log->execute();
    $stmt_log->close();

    // C) Confirmamos la transacción
    $conexion->commit();
    
    $usosRestantesFinal = $user['usos_plan_intermedio'] - 1;
    $_SESSION['usos_plan_intermedio'] = $usosRestantesFinal;

    // [CRÍTICO] Cerramos la DB y la sesión de PHP para permitir concurrencia masiva
    $conexion->close();
    session_write_close();
    // =========================================================================

    // ==========================================
    // CONEXIÓN A OPENAI (Optimizada con GZIP)
    // ==========================================
    $payload = [
        'model' => 'gpt-4o-mini', 
        'response_format' => ['type' => 'json_object'], 
        'messages' => [
            ['role' => 'system', 'content' => $PROMPT_SISTEMA], 
            ['role' => 'user', 'content' => $userMessageContent] 
        ],
        'temperature' => 0.7 
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, 
        CURLOPT_POST => true,           
        CURLOPT_ENCODING => "", // GZIP para acelerar transferencia
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY 
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 110, 
        CURLOPT_SSL_VERIFYPEER => true 
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("Error interno de comunicación del servidor.");
    }

    if ($httpCode >= 400) {
        error_log("OpenAI API Error HTTP Code: " . $httpCode . " Response: " . $response);
        throw new Exception("El motor de IA está saturado temporalmente. Intente nuevamente.");
    }

    $decodedResponse = json_decode($response, true);
    $ia_json_string = trim($decodedResponse['choices'][0]['message']['content'] ?? '{}');
    
    // Limpieza en caso de Markdown extra '```json'
    if (strpos($ia_json_string, '```') === 0) {
        $ia_json_string = preg_replace('/^```json\s*/', '', $ia_json_string);
        $ia_json_string = preg_replace('/\s*```$/', '', $ia_json_string);
    }

    $ia_data = json_decode($ia_json_string, true); 

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Respuesta JSON malformada desde OpenAI: " . $ia_json_string);
        throw new Exception("Error estructurando la secuencia didáctica transversal.");
    }

    ob_end_clean();
    echo json_encode([
        'status' => 'completo',
        'plan' => $ia_data, 
        'usos_restantes' => $usosRestantesFinal
    ]);

} catch (Exception $e) {
    // =========================================================================
    // REEMBOLSO Y LIMPIEZA EN CASO DE FALLO
    // =========================================================================
    if ($e->getMessage() !== 'Has agotado tus créditos para este plan.') {
        try {
            require 'conexion.php'; 
            $conexion->begin_transaction();
            
            // 1. Reembolsar uso
            $stmt_refund = $conexion->prepare("UPDATE usuarios SET usos_plan_intermedio = usos_plan_intermedio + 1 WHERE id = ?");
            $stmt_refund->bind_param("i", $userId);
            $stmt_refund->execute();
            
            // 2. Limpiar huella en historial
            $stmt_del_log = $conexion->prepare("DELETE FROM historial_uso WHERE usuario_id = ? AND herramienta = 'Planeación Transversal' ORDER BY id DESC LIMIT 1");
            $stmt_del_log->bind_param("i", $userId);
            $stmt_del_log->execute();
            $stmt_del_log->close();

            $conexion->commit();
            $conexion->close();
        } catch (Exception $refundErr) {
            error_log("CRÍTICO: No se pudo reembolsar el crédito transversal al usuario ID: " . $userId);
        }
    }

    ob_end_clean();
    http_response_code(200); 
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
?>