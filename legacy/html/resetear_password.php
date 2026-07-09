<?php
// resetear_password.php (VERSIÓN SEGURA)

$token = $_GET['token'] ?? null;

if (!$token || !ctype_xdigit($token)) {
    die("Token inválido.");
}

// --- BLOQUE DE VALIDACIÓN ---
require_once 'conexion.php'; 

// Buscamos el token y verificamos que no haya expirado (usando la hora del servidor de BD).
$stmt = $conexion->prepare("SELECT id FROM usuarios WHERE reset_token = ? AND reset_token_expires_at > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows !== 1) {
    die("Este enlace es inválido o ha expirado. Por favor, solicita uno nuevo.");
}
// --- FIN DEL BLOQUE ---
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña</title>
    <link rel="stylesheet" href="styles.css"> <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #f8f9fa; font-family: 'Poppins', sans-serif; }
        .container { max-width: 450px; padding: 30px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; }
        h1 { color: #1e3a8a; }
        form { display: flex; flex-direction: column; text-align: left; }
        label { font-weight: 600; margin-bottom: 8px; font-size: 0.9rem; }
        input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; margin-bottom: 20px; box-sizing: border-box; }
        button { width: 100%; padding: 15px; background-color: #1e3a8a; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1rem; }
        #error-msg { margin-top: 15px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Crea tu Nueva Contraseña</h1>
        <form action="procesar_reset.php" method="POST" onsubmit="return validarPassword();">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <label for="password">Nueva Contraseña (mín. 8 caracteres):</label>
            <input type="password" id="password" name="password" required>

            <label for="confirm_password">Confirmar Contraseña:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
            
            <p id="error-msg" style="color: red;"></p>

            <button type="submit">Guardar Nueva Contraseña</button>
        </form>
    </div>
    <script>
        function validarPassword() {
            const password = document.getElementById('password').value;
            const confirm_password = document.getElementById('confirm_password').value;
            const errorMsg = document.getElementById('error-msg');
            
            errorMsg.textContent = ''; 

            if (password.length < 8) {
                errorMsg.textContent = 'La contraseña debe tener al menos 8 caracteres.';
                return false;
            }
            if (password !== confirm_password) {
                errorMsg.textContent = 'Las contraseñas no coinciden.';
                return false;
            }
            return true;
        }
    </script>
</body>
</html>