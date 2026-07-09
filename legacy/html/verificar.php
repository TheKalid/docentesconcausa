<?php
session_start();
require 'conexion.php';

// Verificamos si nos llegó un token por la URL
if (!isset($_GET['token'])) {
    header('Location: registro.php');
    exit();
}

$token = $_GET['token'];

// Buscamos un usuario con ese token y que aún esté 'pendiente'
$stmt = $conexion->prepare("SELECT id FROM usuarios WHERE token_verificacion = ? AND estado = 'pendiente'");
$stmt->bind_param("s", $token);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 1) {
    // ¡Encontramos al usuario! Procedemos a activarlo.
    $usuario = $resultado->fetch_assoc();
    $usuario_id = $usuario['id'];

    // Actualizamos el estado a 'activo' y limpiamos el token para que no se pueda reusar
    $stmt_update = $conexion->prepare("UPDATE usuarios SET estado = 'activo', token_verificacion = NULL WHERE id = ?");
    $stmt_update->bind_param("i", $usuario_id);
    $stmt_update->execute();

    // Enviamos al usuario al login con un mensaje de éxito
    $_SESSION['login_success'] = "¡Tu cuenta ha sido activada! Ya puedes iniciar sesión.";
    header("Location: login.php");
    exit();

} else {
    // Si no se encuentra el token o la cuenta ya está activa, mostramos un error
    $_SESSION['registro_errores'] = ["El enlace de verificación no es válido o ya ha expirado."];
    header("Location: registro.php");
    exit();
}

$stmt->close();
$conexion->close();
?>