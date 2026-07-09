<?php
include 'conexion.php';

// Verificamos que los datos lleguen por POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. Validar que las contraseñas coincidan (doble verificación)
    if ($password !== $confirm_password) {
        die("Error: Las contraseñas no coinciden. Por favor, vuelve a intentarlo.");
    }

    // 2. Verificar que el token sea válido y no haya expirado
    $current_time = date('Y-m-d H:i:s');
    
    // Buscamos un usuario con ese token Y cuya fecha de expiración sea mayor a la actual
    $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE reset_token = ? AND reset_token_expires_at > ?");
    $stmt->bind_param("ss", $token, $current_time);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        // ¡El token es válido!
        $usuario = $resultado->fetch_assoc();
        $user_id = $usuario['id'];

        // 3. Cifrar la nueva contraseña
        $password_cifrada = password_hash($password, PASSWORD_DEFAULT);

        // 4. Actualizar la contraseña en la BD y limpiar los campos del token
        $stmt_update = $conexion->prepare("UPDATE usuarios SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?");
        $stmt_update->bind_param("si", $password_cifrada, $user_id);
        
        if ($stmt_update->execute()) {
            echo '¡Tu contraseña ha sido actualizada con éxito! Ya puedes <a href="login.html">iniciar sesión</a>.';
        } else {
            echo "Hubo un error al actualizar tu contraseña. Por favor, intenta de nuevo.";
        }
        $stmt_update->close();

    } else {
        // Si no se encuentra ninguna fila, el token no es válido o ya expiró
        echo "El enlace de recuperación no es válido o ha expirado. Por favor, <a href='recuperar_password.html'>solicita uno nuevo</a>.";
    }
    $stmt->close();
}
$conexion->close();
?>