<?php
// === INICIAMOS LA SESIÓN PARA PODER GUARDAR ERRORES ===
session_start(); 

include 'conexion.php'; // Nos conectamos a la BD

// === VALIDACIÓN DE TÉRMINOS Y CONDICIONES (SERVER-SIDE) ===
// Verificamos si la casilla de 'terminos' fue enviada en el formulario.
// Si no existe, significa que no la marcaron (o que manipularon el HTML).
if (!isset($_POST['terminos'])) {
    // Guardamos el error en una variable de sesión.
    $_SESSION['registro_errores'] = ["Debes aceptar los Términos y Condiciones para poder registrarte."];
    
    // Redirigimos de vuelta al formulario de registro.
    header('Location: registro.php');
    exit(); // Detenemos la ejecución del script.
}
// =========================================================


// Obtenemos los datos del formulario.
$nombre = $_POST['nombre'];
$telefono = $_POST['telefono'];
$correo = $_POST['correo'];
$password = $_POST['password'];

// 1. ANTES de insertar, vamos a VERIFICAR si el correo ya existe.
$stmt_check = $conexion->prepare("SELECT id FROM usuarios WHERE email = ?");
$stmt_check->bind_param("s", $correo);
$stmt_check->execute();
$stmt_check->store_result(); // Guardamos el resultado para poder contar las filas

// 2. Comprobamos cuántas filas se encontraron.
if ($stmt_check->num_rows > 0) {
    // Si se encontró 1 o más filas, el correo ya existe.
    
    // --- MODIFICADO ---
    // Mensaje más útil con enlace a login.html y a recuperar contraseña.
    // === USAMOS EL SISTEMA DE SESIONES PARA MOSTRAR EL ERROR EN LA OTRA PÁGINA ===
    $_SESSION['registro_errores'] = [
        'Este correo electrónico ya está registrado. Por favor, <a href="login.php">inicia sesión</a> o <a href="recuperar_password.html">recupera tu contraseña</a>.'
    ];
    header('Location: registro.php');
    exit();
    // --- FIN DE LA MODIFICACIÓN ---

} else {
    // Si no se encontraron filas, el correo está libre y procedemos a registrar.
    
    // Ciframos la contraseña para guardarla de forma segura.
    $password_cifrada = password_hash($password, PASSWORD_DEFAULT);

    // Preparamos la consulta SQL para insertar los datos.
    $stmt_insert = $conexion->prepare("INSERT INTO usuarios (nombre, email, telefono, password) VALUES (?, ?, ?, ?)");
    $stmt_insert->bind_param("ssss", $nombre, $correo, $telefono, $password_cifrada);

    if ($stmt_insert->execute()) {
        // Si el registro es exitoso, redirigimos al usuario a la página de login.
        header("Location: login.php");
        exit();
    } else {
        // Si hay un error inesperado, lo mostramos.
        $_SESSION['registro_errores'] = ["Error en el registro: " . $stmt_insert->error];
        header('Location: registro.php');
        exit();
    }
    $stmt_insert->close();
}

// Cerramos las conexiones.
$stmt_check->close();
$conexion->close();
?>
?>