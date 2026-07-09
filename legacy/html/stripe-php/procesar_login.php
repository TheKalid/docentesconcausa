<?php
// procesar_login.php (VERSIÓN CORREGIDA Y FINAL)

// 1. Inicia la sesión.
session_start();

// 2. Incluye la conexión a la base de datos.
require_once 'conexion.php';

// 3. Verifica que se hayan enviado datos por POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

// 4. Obtiene los datos del formulario.
$correo = $_POST['correo'];
$password = $_POST['password'];

// 5. Prepara la consulta para buscar al usuario (¡LEEMOS LA COLUMNA CORRECTA!).
$stmt = $conexion->prepare("SELECT id, nombre, password, plan_activo, usos_plan_intermedio FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $correo);
$stmt->execute();
$resultado = $stmt->get_result();

// 6. Verifica si se encontró un usuario.
if ($resultado->num_rows === 1) {
    $usuario = $resultado->fetch_assoc();

    // Compara la contraseña.
    if (password_verify($password, $usuario['password'])) {
        // ¡La contraseña es correcta!
        
        // 7. Guardamos los datos clave del usuario en la sesión (¡CON EL NOMBRE CORRECTO!).
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['plan_activo'] = (int)$usuario['plan_activo'];
        // CORRECCIÓN CLAVE: Usamos el nombre de tu columna y lo guardamos en la sesión.
        $_SESSION['usos_plan_intermedio'] = (int)$usuario['usos_plan_intermedio'];

        // 8. Redirigimos al usuario a la página principal.
        header("Location: index.php");
        exit();
    }
}

// --- Si algo falla (usuario no existe o contraseña incorrecta) ---
$_SESSION['login_error'] = "El correo o la contraseña son incorrectos. Por favor, inténtalo de nuevo.";
header("Location: login.php");
exit();
?>