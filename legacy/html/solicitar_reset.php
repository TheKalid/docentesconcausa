<?php
// =====================================================================
// ARCHIVO: solicitar_reset.php
// OBJETIVO: Buscar correo, generar token y enviar email (CON DEPURADOR)
// =====================================================================
ob_start(); 

// 1. Verificamos si la sesión ya está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conexion.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// [SEGURIDAD] Solo aceptamos peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['correo'])) {
    header('Location: olvide_password.php');
    exit();
}

// [SEGURIDAD] Blindaje del Token CSRF
$session_token = isset($_SESSION['csrf_token']) ? (string)$_SESSION['csrf_token'] : '';
$post_token = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';

if (empty($session_token) || empty($post_token) || !hash_equals($session_token, $post_token)) {
    $_SESSION['reset_error'] = "Por seguridad, tu sesión caducó. Por favor, intenta enviar tu correo de nuevo.";
    header('Location: olvide_password.php');
    exit();
}

// Limpiamos el correo
$correo = filter_var($_POST['correo'], FILTER_SANITIZE_EMAIL);

// 3. Verificamos si el correo realmente existe en la BD
$stmt = $conexion->prepare("SELECT id, nombre FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $correo);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 1) {
    $usuario = $resultado->fetch_assoc();
    $nombre = $usuario['nombre'];

    // 4. Creamos un token aleatorio
    $token = bin2hex(random_bytes(32));

    // 5. Guardamos el token en la BD (EXPIRA EN 1 HORA)
    $stmt_update = $conexion->prepare("UPDATE usuarios SET reset_token = ?, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?");
    $stmt_update->bind_param("ss", $token, $correo);
    $stmt_update->execute();

    // Guardamos éxito preliminar
    $_SESSION['reset_mensaje'] = "Si el correo está registrado, te hemos enviado un enlace para restablecer tu contraseña. Revisa tu carpeta de SPAM.";
    
    $stmt->close();
    $stmt_update->close();
    $conexion->close();
    session_write_close();

    // 6. Envío del correo 
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'soporte@docentesconcausa.com'; 
        $mail->Password   = 'DragonAzul11#'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        // --- PARCHE PARA LOCALHOST / XAMPP ---
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        // -------------------------------------

        $mail->CharSet = 'UTF-8';
        $mail->setFrom('soporte@docentesconcausa.com', 'Planeando con Causa');
        $mail->addAddress($correo, $nombre);

        // --- DETECCIÓN DINÁMICA DEL ENTORNO PARA EL ENLACE ---
        $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
        $dominio = $_SERVER['HTTP_HOST'];
        $is_local = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || $dominio == 'localhost';

        // Si estás en local añade la carpeta del proyecto, si estás en el servidor va directo a la raíz
        $ruta_base = $is_local ? "/Pagina%20web%201" : ""; 
        $enlace_reset = $protocolo . $dominio . $ruta_base . "/resetear_password.php?token=" . $token;
        // -----------------------------------------------------

        $mail->isHTML(true);
        $mail->Subject = 'Restablecimiento de Contraseña - Planeando con Causa';
        $mail->Body    = "
            <h2>Hola, $nombre</h2>
            <p>Has solicitado restablecer tu contraseña en Planeando con Causa.</p>
            <p>Para crear una nueva, haz clic en el siguiente botón:</p>
            <br>
            <a href='$enlace_reset' style='background-color:#1e3a8a; color:white; padding:12px 20px; text-decoration:none; border-radius:8px; font-weight:bold;'>Restablecer mi contraseña</a>
            <br><br>
            <p>Si no puedes dar clic al botón, copia este enlace en tu navegador:</p>
            <p>$enlace_reset</p>
        ";

        $mail->send();
        
        header('Location: olvide_password.php');
        exit();

    } catch (Exception $e) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['reset_mensaje']); 
        
        $_SESSION['reset_error'] = "Error del servidor de correos: " . $mail->ErrorInfo;
        header('Location: olvide_password.php');
        exit();
    }
} else {
    $_SESSION['reset_mensaje'] = "Si el correo está registrado, te hemos enviado un enlace para restablecer tu contraseña. Revisa tu carpeta de SPAM.";
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    
    header('Location: olvide_password.php');
    exit();
}
?>