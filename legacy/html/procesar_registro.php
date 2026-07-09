<?php
// =====================================================================
// ARCHIVO: procesar_registro.php
// OBJETIVO: Recibir datos, validar seguridad, guardar en BD y enviar correo.
// =====================================================================

// === INICIAMOS LA SESIÓN PARA PODER GUARDAR ERRORES ===
session_start(); 

require_once 'conexion.php'; // Nos conectamos a la BD

// === CARGAMOS PHPMAILER ===
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// [SEGURIDAD] Validación de método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['registro_errores'] = ["Acceso no autorizado."];
    header('Location: registro.php');
    exit();
}

// [SEGURIDAD] Validación del Token CSRF (Evita creación masiva de cuentas falsas)
$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    $_SESSION['registro_errores'] = ["Firma de seguridad inválida. Intenta de nuevo."];
    header('Location: registro.php');
    exit();
}

if (!isset($_POST['terminos'])) {
    $_SESSION['registro_errores'] = ["Debes aceptar los Términos y Condiciones para poder registrarte."];
    header('Location: registro.php');
    exit(); 
}

// Limpieza de datos
$nombre = trim(strip_tags($_POST['nombre']));
$telefono = trim(strip_tags($_POST['telefono']));
$correo = trim(filter_var($_POST['correo'], FILTER_SANITIZE_EMAIL));
$password_cruda = $_POST['password'];

if (strlen($password_cruda) < 8) {
    $_SESSION['registro_errores'] = ["La contraseña debe tener al menos 8 caracteres."];
    header('Location: registro.php');
    exit();
}

// 1. VERIFICAR si el correo ya existe en la BD
$stmt_check = $conexion->prepare("SELECT id FROM usuarios WHERE email = ?");
$stmt_check->bind_param("s", $correo);
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
    $_SESSION['registro_errores'] = ["Este correo electrónico ya está registrado. Intenta iniciar sesión."];
    header('Location: registro.php');
    exit();
}
$stmt_check->close();

// [SEGURIDAD] Cifrado fuerte de contraseña
$password_hash = password_hash($password_cruda, PASSWORD_DEFAULT);
$token_activacion = bin2hex(random_bytes(32)); 

// 2. CREACIÓN DEL USUARIO EN LA BASE DE DATOS
$stmt_insert = $conexion->prepare("INSERT INTO usuarios (nombre, email, password, telefono, estado, token_verificacion) VALUES (?, ?, ?, ?, 0, ?)");
$stmt_insert->bind_param("sssss", $nombre, $correo, $password_hash, $telefono, $token_activacion);

if ($stmt_insert->execute()) {
    
    // [CRÍTICO PARA RENDIMIENTO] 
    // Guardamos las variables de sesión y LIBERAMOS EL SERVIDOR antes de enviar el correo.
    $_SESSION['login_success'] = "¡Registro casi listo! Te hemos enviado un correo electrónico. Por favor, revisa tu bandeja de entrada (y la de SPAM) para activar tu cuenta.";
    session_write_close(); 

    // 3. ENVÍO DEL CORREO CON PHPMAILER
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com'; 
        $mail->SMTPAuth   = true;
        
        // Credenciales correctas
        $mail->Username   = 'soporte@docentesconcausa.com'; 
        $mail->Password   = 'DragonAzul11#'; 
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        // PARCHE PARA LOCALHOST / XAMPP
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Remitente
        $mail->setFrom('soporte@docentesconcausa.com', 'Docentes con Causa');
        $mail->addAddress($correo, $nombre);

        // Detección dinámica del entorno para generar el enlace correcto
        $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
        $dominio = $_SERVER['HTTP_HOST'];
        $is_local = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || $dominio == 'localhost';
        $ruta_base = $is_local ? "/Pagina%20web%201" : ""; 
        
        $enlace_activacion = $protocolo . $dominio . $ruta_base . "/activar.php?token=" . $token_activacion;

        $mail->isHTML(true);
        $mail->Subject = 'Activa tu cuenta - Docentes con Causa';
        $mail->Body    = "
            <h2>¡Hola Maestro(a) $nombre!</h2>
            <p>Gracias por unirte a Docentes con Causa.</p>
            <p>Haz clic en el siguiente enlace para activar tu cuenta:</p>
            <br>
            <a href='$enlace_activacion' style='background-color: #1e3a8a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Activar mi cuenta</a>
            <br><br>
            <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
            <p>$enlace_activacion</p>
        ";

        $mail->send();
        header("Location: login.php");
        exit();

    } catch (Exception $e) {
        $stmt_delete = $conexion->prepare("DELETE FROM usuarios WHERE email = ?");
        $stmt_delete->bind_param("s", $correo);
        $stmt_delete->execute();
        
        session_start();
        // Regresamos a un mensaje de error más amigable para el usuario final
        $_SESSION['registro_errores'] = ["Error al enviar el correo de activación. Por favor, intenta más tarde."];
        header('Location: registro.php');
        exit();
    }

} else {
    $_SESSION['registro_errores'] = ["Ocurrió un error en la base de datos al crear la cuenta."];
    header('Location: registro.php');
    exit();
}
?>