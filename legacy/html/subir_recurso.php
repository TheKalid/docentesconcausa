<?php
// Incluimos la configuración y la conexión segura a la base de datos
require_once 'config.php';

// --- Lógica Principal ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // AÑADE ESTA LÍNEA AQUÍ PARA DEPURAR
    var_dump($_FILES);

    
    // 1. RECEPCIÓN DE DATOS DEL FORMULARIO
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $archivo = $_FILES['archivo'] ?? null;
    
    // 2. VALIDACIÓN RIGUROSA
    $errores = [];
    if (empty($titulo) || empty($descripcion) || empty($categoria)) {
        $errores[] = "Todos los campos de texto son obligatorios.";
    }
    
    // Validación del archivo
    if ($archivo === null || $archivo['error'] !== UPLOAD_ERR_OK) {
        $errores[] = "Hubo un error al subir el archivo o no se seleccionó ninguno.";
    } else {
        $maxFileSize = 50 * 1024 * 1024; // 50 MB
        $allowedMimeType = 'application/pdf';

        if ($archivo['size'] > $maxFileSize) {
            $errores[] = "El archivo es demasiado grande. El máximo permitido es 50 MB.";
        }
        if ($archivo['type'] !== $allowedMimeType) {
            $errores[] = "Solo se permiten archivos en formato PDF.";
        }
    }
    
    // Si no hay errores, procedemos a guardar
    if (empty($errores)) {
        // 3. PREPARAR EL ALMACENAMIENTO
        $uploadDir = 'uploads/'; 
        
        // Crear un nombre de archivo único para evitar sobreescrituras
        $nombreArchivoOriginal = basename($archivo['name']);
        $nombreArchivoUnico = uniqid('', true) . '-' . $nombreArchivoOriginal;
        $rutaDestino = $uploadDir . $nombreArchivoUnico;
        
        // 4. MOVER EL ARCHIVO
        if (move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            
            // 5. GUARDAR EN LA BASE DE DATOS
            try {
                // La variable $pdo viene de config.php y ya está lista para usarse aquí
                $sql = "INSERT INTO recursos (titulo, descripcion, categoria, nombre_archivo, ruta_archivo, fecha_subida) VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$titulo, $descripcion, $categoria, $nombreArchivoUnico, $rutaDestino]);
                
                header("Location: biblioteca.php?status=success");
                exit();
                
            } catch (PDOException $e) {
                unlink($rutaDestino); 
                die("Error al guardar en la base de datos: " . $e->getMessage());
            }
            
        } else {
            die("Hubo un error al mover el archivo subido.");
        }
        
    } else {
        // Si hubo errores de validación, mostrarlos
        echo "<h1>Errores de Validación</h1>";
        echo "<ul>";
        foreach ($errores as $error) {
            echo "<li>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul>";
        echo '<a href="biblioteca.php">Volver a intentarlo</a>';
    }
}
?>