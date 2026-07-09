<?php
// Usar las clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Requerir los archivos de PHPMailer manualmente
require 'vendor/phpmailer/src/Exception.php';
require 'vendor/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/src/SMTP.php';

// Incluir la conexión a la base de datos
include 'conexion.php';

// Verificar que se haya enviado el formulario por método POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = $_POST['correo'];

    // 1. VERIFICAR SI EL CORREO EXISTE EN LA BASE DE DATOS
    $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        // Si el correo existe, generamos el token
        $token = bin2hex(random_bytes(32)); 
        $expira_en = date('Y-m-d H:i:s', strtotime('+1 hour')); // El token será válido por 1 hora

        // 2. GUARDAR EL TOKEN EN LA BASE DE DATOS PARA ESE USUARIO
        $stmt_update = $conexion->prepare("UPDATE usuarios SET reset_token = ?, reset_token_expires_at = ? WHERE email = ?");
        $stmt_update->bind_param("sss", $token, $expira_en, $correo);
        $stmt_update->execute();

        // 3. CONFIGURAR Y ENVIAR EL CORREO
        $mail = new PHPMailer(true);

        try {
            // Configuración del servidor (ejemplo con Gmail)
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'ingarturodalimoran@gmail.com'; // <-- REEMPLAZA CON TU CORREO DE GMAIL
            $mail->Password   = 'swfnnvrtj'; // <-- REEMPLAZA CON TU CONTRASEÑA DE APLICACIÓN
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->CharSet    = 'UTF-8';

            // Configuración del correo
            $mail->setFrom('no-reply@planeandoconcausa.com', 'Planeando con Causa');
            $mail->addAddress($correo); 
            $mail->isHTML(true);
            $mail->Subject = 'Recuperación de Contraseña';
            
            // Construye el enlace que irá en el correo. ¡Asegúrate que la URL sea correcta!
            $enlace_recuperacion = "http://localhost/Pagina%20web%202/resetear_password.php?token=" . $token;
            
            $mail->Body    = "Hola,<br><br>Para restablecer tu contraseña, haz clic en el siguiente enlace:<br><br>"
                           . "<a href='" . $enlace_recuperacion . "' style='padding: 10px 15px; background-color: #1e3a8a; color: white; text-decoration: none; border-radius: 5px;'>Restablecer mi Contraseña</a><br><br>"
                           . "Este enlace es válido por 1 hora.";
            
            $mail->send();
            echo 'Se ha enviado un enlace de recuperación a tu correo electrónico. Revisa tu bandeja de entrada y la carpeta de spam.';

        } catch (Exception $e) {
            echo "El mensaje no pudo ser enviado. Error de PHPMailer: {$mail->ErrorInfo}";
        }

    } else {
        // Por seguridad, no revelamos si un correo existe o no. Mostramos un mensaje genérico.
        echo 'Si tu correo está en nuestra base de datos, recibirás un enlace de recuperación en breve.';
    }

    $stmt->close();
    $conexion->close();
}
?>