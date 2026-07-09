<?php
// chat_backend.php - VERSIÓN ACTUALIZADA (GEMINI 2.5)

// 1. Cabeceras para permitir que nuestra página web lea la respuesta del servidor en formato JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 2. Apagamos los errores visuales de PHP. Nosotros manejaremos los errores de forma limpia abajo.
error_reporting(0);

try {
    // 3. LA LLAVE DE ACCESO A GOOGLE (API KEY)
    $apiKey = 'REDACTED_GEMINI_API_KEY';

    // 4. EL MODELO ACTUALIZADO
    // SOLUCIÓN AL ERROR 404: Google retiró los modelos 1.5. Ahora usamos la versión 'gemini-2.5-flash', 
    // que es la versión estable y moderna soportada actualmente por la API.
    $modelo = 'gemini-2.5-flash';

    // 5. CAPTURAR EL MENSAJE DEL USUARIO
    // Recibimos lo que el JavaScript de la página web nos envió
    $jsonInput = file_get_contents('php://input');
    $input = json_decode($jsonInput, true);
    $mensajeUsuario = $input['mensaje'] ?? '';

    // Si no hay mensaje, detenemos el proceso
    if (empty($mensajeUsuario)) {
        throw new Exception("El mensaje que llegó al servidor estaba vacío.");
    }

    // 6. CARGAR EL CONTEXTO INSTITUCIONAL
    $archivoContexto = 'contextoinstitucional.txt';
    $contexto = "Eres un asistente útil de 'Docentes con Causa'."; 
    
    // Si el archivo de texto existe, lo leemos y aseguramos que los acentos (UTF-8) no rompan nada
    if (file_exists($archivoContexto)) {
        $contexto = file_get_contents($archivoContexto);
        $contexto = mb_convert_encoding($contexto, 'UTF-8', 'auto'); 
    }

    // 7. ARMAR LA ESTRUCTURA DE LA PETICIÓN
    // Esta es la forma exacta en la que Google espera recibir las instrucciones y el mensaje
    $datosPost = [
        "systemInstruction" => [
            "parts" => [
                ["text" => "Instrucciones: Responde basándote EXCLUSIVAMENTE en este contexto. Si no sabes la respuesta, di que no tienes esa información. CONTEXTO: " . $contexto]
            ]
        ],
        "contents" => [
            [
                "role" => "user",
                "parts" => [
                    ["text" => $mensajeUsuario]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.4, // Nivel de creatividad equilibrado
            "maxOutputTokens" => 900 // Límite de texto para no saturar la pantalla del chat
        ]
    ];

    // Convertimos el arreglo de PHP a texto JSON
    $jsonPost = json_encode($datosPost);
    
    // Si hubo un error al convertir (por caracteres extraños), lo detectamos aquí
    if ($jsonPost === false) {
        throw new Exception("Error al procesar los textos: " . json_last_error_msg());
    }

    // 8. ENVIAR LA PETICIÓN A GOOGLE
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$modelo:generateContent?key=" . $apiKey;

    // Configuramos cURL, que es la herramienta de PHP para hacer peticiones por internet
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_POST, true); 
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPost); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']); 
    // Evitamos bloqueos de certificados SSL (común en servidores locales o en desarrollo)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Si no responde en 30 segundos, cancelamos
    
    // Ejecutamos la petición
    $respuestaRaw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
    
    // Verificamos si falló nuestra conexión a internet
    if ($respuestaRaw === false) {
        $errorCurl = curl_error($ch);
        curl_close($ch);
        throw new Exception("Fallo de conexión CURL: " . $errorCurl);
    }
    
    curl_close($ch);

    // 9. PROCESAR LA RESPUESTA
    $jsonGoogle = json_decode($respuestaRaw, true);

    // Si Google responde con algo diferente a 200 (Éxito), significa que nos rechazó
    if ($httpCode !== 200) {
        $msgError = $jsonGoogle['error']['message'] ?? 'Error desconocido de Google';
        throw new Exception("Google rechazó la solicitud (Código $httpCode): $msgError");
    }

    // 10. EXTRAER EL TEXTO GENERADO POR LA IA
    // Navegamos por el JSON de respuesta de Google para encontrar el texto
    if (isset($jsonGoogle['candidates'][0]['content']['parts'][0]['text'])) {
        $textoIA = $jsonGoogle['candidates'][0]['content']['parts'][0]['text'];
        
        // Limpiamos formatos de markdown que podrían verse mal en HTML
        $textoIA = str_replace(['**', '##'], '', $textoIA); 
        // Convertimos el texto a formato seguro para evitar inyección XSS y respetamos los saltos de línea
        $textoIA = nl2br(htmlspecialchars($textoIA, ENT_QUOTES, 'UTF-8')); 
        
        // Enviamos el éxito en formato JSON para que el JavaScript lo lea
        echo json_encode(['respuesta' => $textoIA]);
    } else {
        // Si no hay texto en la respuesta (por ejemplo, si fue censurado por seguridad)
        echo json_encode(['respuesta' => 'Lo siento, no pude formular una respuesta válida para eso.']);
    }

} catch (Exception $e) {
    // Si cualquier paso anterior falla, el código salta directamente aquí.
    // Esto previene que la pantalla se rompa y en su lugar envía un aviso claro al chat.
    echo json_encode(['respuesta' => '⚠️ Error del Sistema: ' . $e->getMessage()]);
}
?>