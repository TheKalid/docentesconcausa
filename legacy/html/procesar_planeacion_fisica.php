<?php
// procesar_planeacion_fisica.php (VERSIÓN SÍNCRONA OPTIMIZADA - CHAT COMPLETIONS)
set_time_limit(120);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once 'conexion.php';
require_once 'credenciales.php';

header('Content-Type: application/json');

// 1. VERIFICACIÓN DE SESIÓN
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No se ha iniciado sesión.']);
    exit;
}
$userId = $_SESSION['usuario_id'];

// Recibir datos del frontend
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!$data || empty($data['prompt'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Faltan datos de la planeación.']);
    exit;
}

$userMessageContent = $data['prompt'];

// 2. TRANSACCIÓN SEGURA Y VERIFICACIÓN DE CRÉDITOS
$usosRestantesFinal = 0;
$conexion->begin_transaction();

try {
    $stmt = $conexion->prepare("SELECT usos_fisica FROM usuarios WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || $user['usos_fisica'] <= 0) {
        $conexion->rollback();
        echo json_encode(['success' => false, 'error' => 'Has agotado tus usos para Educación Física.']);
        exit;
    }
    
    // 3. EL SYSTEM PROMPT MAESTRO
    $systemPrompt = <<<EOT
Eres un pedagogo experto y maestro de Educación Física con más de 20 años de experiencia, especializado en la Nueva Escuela Mexicana.

Tu única función es diseñar una planeación didáctica de una sesión utilizando estrictamente el formato Markdown. Usa encabezados (##), negritas (**), viñetas (*) y líneas horizontales (---) para una presentación clara, profesional y fácil de leer.

**## DATOS FUNDAMENTALES DE LA PLANEACIÓN ##**
Toma los datos de Grado, Contenido y PDA proporcionados por el usuario y plásmalos al inicio.
* **Campo Formativo:** De lo Humano y lo Comunitario

---
**## Título de la Sesión**
*Crea un nombre creativo y descriptivo aquí para la clase que refleje el Contenido a trabajar.*

---
**## Objetivo(s) de la Sesión**
* *Describe aquí 1 o 2 objetivos claros que parafraseen directamente el PDA proporcionado.*

---
**## Ejes Articuladores que se favorecen**
* *Identifica y menciona 1 o 2 Ejes Articuladores que se conecten de forma evidente con las actividades.*

---
**## Secuencia Didáctica (Momentos de la Sesión)**
### **INICIO (5-10 min): Calentamiento y Activación**
* **Movilidad Articular y Calentamiento Dinámico:** *Describe un calentamiento adecuado y seguro para el grado especificado.*
* **Actividad Rompehielos:** *Propón un juego corto y enérgico que introduzca de manera lúdica el contenido del día.*

### **DESARROLLO (25-30 min): Construcción del Aprendizaje**
* **Actividad Principal 1 (Exploratoria):** *Explica un juego o circuito motor diseñado para que los alumnos exploren las habilidades del PDA. Detalla reglas y organización.*
* **Actividad Principal 2 (Progresión y Reto):** *Propón una variante que incremente la complejidad o fomente la colaboración, desafiando a los alumnos a refinar su ejecución del PDA.*
* **Actividad Principal 3 (Aplicación Lúdica):** *Describe un juego colectivo donde los alumnos apliquen lo aprendido en un entorno de disfrute y cooperación.*

### **CIERRE (5-10 min): Vuelta a la Calma y Reflexión**
* **Relajación:** *Describe ejercicios de estiramiento suave y respiración.*
* **Retroalimentación Dialógica:** *Propón 2-3 preguntas guía para que los alumnos verbalicen su experiencia, asegurando que una pregunta conecte directamente con el PDA.*

---
**## Recursos y Materiales**
* *Haz una lista clara (usando viñetas) de todos los materiales necesarios.*

---
**## Sugerencias de Evaluación Formativa**
* *Describe 2-3 criterios de evaluación observables, formulados como afirmaciones que confirmen el logro del PDA.*

---
**## Adaptaciones Curriculares**
* **Apoyos para la Inclusión:** *Ofrece sugerencias concisas para simplificar la actividad.*
* **Retos para Avanzados:** *Ofrece sugerencias concisas para aumentar el reto.*
EOT;

    // 4. LLAMADA SÍNCRONA A OPENAI (CHAT COMPLETIONS)
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    $payload = [
        'model' => 'gpt-4o-mini', 
        // Nota: Quitamos el "json_object" porque este prompt exige Markdown puro
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Por favor elabora la planeación de educación física con estos datos:\n" . $userMessageContent]
        ],
        'temperature' => 0.7
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 90 
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("Error de conexión con OpenAI: " . $curlError);
    }

    $decodedResponse = json_decode($response, true);
    if ($httpCode >= 400) {
        $errorMsg = $decodedResponse['error']['message'] ?? 'Error desconocido en la API.';
        throw new Exception("OpenAI rechazó la petición: " . $errorMsg);
    }

    // Extraemos el texto Markdown generado por la IA
    $markdown_text = $decodedResponse['choices'][0]['message']['content'];

    // 5. ÉXITO: DESCONTAMOS EL CRÉDITO
    $stmt_update = $conexion->prepare("UPDATE usuarios SET usos_fisica = usos_fisica - 1 WHERE id = ?");
    $stmt_update->bind_param("i", $userId);
    $stmt_update->execute();
    
    $usosRestantesFinal = $user['usos_fisica'] - 1;
    $_SESSION['usos_fisica'] = $usosRestantesFinal;

    $conexion->commit();

    // 6. RESPUESTA AL FRONTEND
    echo json_encode([
        'status' => 'completo',
        'plan' => $markdown_text,
        'usos_restantes' => $usosRestantesFinal
    ]);

} catch (Exception $e) {
    $conexion->rollback();
    http_response_code(500);
    error_log("Error en Planeación Física: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'error' => 'Hubo un problema procesando tu solicitud.', 
        'details' => $e->getMessage()
    ]);
}
?>