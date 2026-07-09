<?php
// procesar_reset.php
session_start();
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acceso no permitido.");
}

$token = $_POST['token'];
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];

// 1. Validaciones básicas.
if (empty($token) || empty($password) || $password !== $confirm_password || strlen($password) < 8) {
    // Redirigir a una página de error o de vuelta al formulario con un mensaje.
    // Por simplicidad, morimos, pero en producción deberías manejarlo mejor.
    die("Datos inválidos. Asegúrate de que las contraseñas coincidan y tengan al menos 8 caracteres.");
}

// 2. Volver a verificar el token por seguridad.
$stmt = $conexion->prepare("SELECT id FROM usuarios WHERE reset_token = ? AND reset_token_expires_at > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows !== 1) {
    die("Token inválido o expirado. No se puede procesar el cambio.");
}

// 3. Hashear la nueva contraseña.
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// 4. Actualizar la contraseña y anular el token para que no se reutilice.
$stmt_update = $conexion->prepare("UPDATE usuarios SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE reset_token = ?");
$stmt_update->bind_param("ss", $hashed_password, $token);
$stmt_update->execute();

// 5. Redirigir al login con un mensaje de éxito.
$_SESSION['login_success'] = "¡Contraseña actualizada con éxito! Ya puedes iniciar sesión.";
header("Location: login.php");
exit();
?>