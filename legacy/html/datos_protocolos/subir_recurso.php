<?php
// --- Conexión a la Base de Datos (Usa tus propios datos) ---
$host = 'localhost';
$dbname = 'causa_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// --- Lógica Principal ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
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
        $maxFileSize = 5 * 1024 * 1024; // 5 MB
        $allowedMimeType = 'application/pdf';

        if ($archivo['size'] > $maxFileSize) {
            $errores[] = "El archivo es demasiado grande. El máximo permitido es 5 MB.";
        }
        if ($archivo['type'] !== $allowedMimeType) {
            $errores[] = "Solo se permiten archivos en formato PDF.";
        }
    }
    
    // Si no hay errores, procedemos a guardar
    if (empty($errores)) {
        // 3. PREPARAR EL ALMACENAMIENTO
        $uploadDir = 'uploads/'; // Asegúrate de que esta carpeta exista y tenga permisos de escritura
        
        // Crear un nombre de archivo único para evitar sobreescrituras
        $nombreArchivoOriginal = basename($archivo['name']);
        $nombreArchivoUnico = uniqid('', true) . '-' . $nombreArchivoOriginal;
        $rutaDestino = $uploadDir . $nombreArchivoUnico;
        
        // 4. MOVER EL ARCHIVO
        if (move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            
            // 5. GUARDAR EN LA BASE DE DATOS
            try {
                $sql = "INSERT INTO recursos (titulo, descripcion, categoria, nombre_archivo, ruta_archivo, fecha_subida) VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$titulo, $descripcion, $categoria, $nombreArchivoUnico, $rutaDestino]);
                
                // Redirigir de vuelta a la biblioteca con un mensaje de éxito
                header("Location: biblioteca.php?status=success");
                exit();
                
            } catch (PDOException $e) {
                // Si falla la base de datos, borrar el archivo subido para no dejar basura
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
        echo '<a href="biblioteca.html">Volver a intentarlo</a>';
    }
}
?>