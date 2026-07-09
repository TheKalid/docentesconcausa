<?php
// extraer_pdf_ocr.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. SEGURIDAD BÁSICA
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sesión no válida.']);
    exit;
}

$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo json_encode(['success' => false, 'error' => 'Firma de seguridad inválida.']);
    exit;
}

if (!isset($_FILES['archivo_pdf']) || $_FILES['archivo_pdf']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo o hubo un error en la subida.']);
    exit;
}

$archivo = $_FILES['archivo_pdf'];
$nombreTmp = $archivo['tmp_name'];

// 2. SEGURIDAD: VERIFICACIÓN MIME REAL
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$tipoMime = finfo_file($finfo, $nombreTmp);
finfo_close($finfo);

if ($tipoMime !== "application/pdf") {
    echo json_encode(['success' => false, 'error' => 'El archivo no es un PDF válido.']);
    exit;
}

// 3. PREPARAR DIRECTORIO TEMPORAL SEGURO
$uploadDir = __DIR__ . "/temp_uploads/";
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generar nombre único para evitar sobreescrituras o ejecuciones maliciosas
$nombreUnico = uniqid('pdf_', true) . '.pdf';
$rutaFinal = $uploadDir . $nombreUnico;

if (!move_uploaded_file($nombreTmp, $rutaFinal)) {
    echo json_encode(['success' => false, 'error' => 'Error al guardar el archivo temporal.']);
    exit;
}

// ==========================================
// FUNCIONES DEL SISTEMA OPERATIVO
// ==========================================

function pdfEstaProtegido($ruta) {
    $info = shell_exec("pdfinfo " . escapeshellarg($ruta) . " 2>&1");
    return strpos($info, "Encrypted: yes") !== false;
}

function extraerTextoNormal($ruta) {
    return shell_exec("pdftotext " . escapeshellarg($ruta) . " - 2>&1");
}

function extraerTextoOCR($rutaPDF, $uploadDir) {
    $baseName = uniqid('img_');
    $outputBase = $uploadDir . $baseName;
    
    // Convertir PDF a imágenes PNG
    shell_exec("pdftoppm -png " . escapeshellarg($rutaPDF) . " " . escapeshellarg($outputBase));

    $textoFinal = "";
    // Leer cada imagen generada
    foreach (glob($outputBase . "-*.png") as $imagen) {
        $outputTxt = $imagen . "_out";
        // Ejecutar Tesseract OCR en español
        shell_exec("tesseract " . escapeshellarg($imagen) . " " . escapeshellarg($outputTxt) . " -l spa 2>&1");
        
        if (file_exists($outputTxt . ".txt")) {
            $textoFinal .= file_get_contents($outputTxt . ".txt") . "\n";
            unlink($outputTxt . ".txt"); // Limpiar TXT
        }
        unlink($imagen); // Limpiar PNG
    }
    return $textoFinal;
}

// ==========================================
// LÓGICA DE PROCESAMIENTO
// ==========================================

try {
    if (pdfEstaProtegido($rutaFinal)) {
        throw new Exception("El PDF está protegido con contraseña. Sube una versión sin restricciones.");
    }

    $texto = extraerTextoNormal($rutaFinal);

    // Si pdftotext devuelve vacío, es un documento escaneado (imagen)
    if (trim($texto) === "") {
        $texto = extraerTextoOCR($rutaFinal, $uploadDir);
    }

    $texto = trim($texto);

    if (empty($texto)) {
        throw new Exception("No pudimos extraer texto del documento, incluso usando reconocimiento visual. Asegúrate de que sea legible.");
    }

    // Limpieza final
    unlink($rutaFinal);

    echo json_encode([
        'success' => true, 
        'texto' => $texto
    ]);

} catch (Exception $e) {
    if (file_exists($rutaFinal)) unlink($rutaFinal);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>