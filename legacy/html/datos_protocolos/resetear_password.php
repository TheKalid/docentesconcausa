<?php
// Obtenemos el token de la URL. Si no hay token, no se puede continuar.
$token = $_GET['token'] ?? null;

if (!$token || !ctype_xdigit($token)) { // ctype_xdigit verifica que sea un token hexadecimal válido
    die("Token inválido o no proporcionado.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña</title>
    <link rel="stylesheet" href="styles.css"> 
</head>
<body>
    <header>
        <h1>Crea tu Nueva Contraseña</h1>
    </header>

    <form action="procesar_reset.php" method="POST" onsubmit="return validarPassword();">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

        <label for="password">Nueva Contraseña:</label>
        <input type="password" id="password" name="password" required>

        <label for="confirm_password">Confirmar Nueva Contraseña:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
        
        <p id="error-msg" style="color: red;"></p>

        <button type="submit">Guardar Nueva Contraseña</button>
    </form>

    <script>
        // Pequeño script para verificar que las contraseñas coincidan antes de enviar
        function validarPassword() {
            const password = document.getElementById('password').value;
            const confirm_password = document.getElementById('confirm_password').value;
            const errorMsg = document.getElementById('error-msg');

            if (password !== confirm_password) {
                errorMsg.textContent = 'Las contraseñas no coinciden.';
                return false; // Evita que el formulario se envíe
            }
            return true; // Permite que el formulario se envíe
        }
    </script>
</body>
</html>