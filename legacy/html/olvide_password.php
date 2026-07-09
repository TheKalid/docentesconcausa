<?php
// =====================================================================
// ARCHIVO: olvide_password.php
// OBJETIVO: Interfaz visual segura para solicitar el enlace de recuperación.
// =====================================================================
session_start();

// [SEGURIDAD] Cabeceras HTTP Anti-Ataques
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// [SEGURIDAD] Generación de Token CSRF (Firma digital)
// Evita que un bot externo envíe miles de correos y sature tu servidor de Hostinger.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Captura de mensajes desde el backend (solicitar_reset.php)
$mensaje_exito = $_SESSION['reset_mensaje'] ?? null;
unset($_SESSION['reset_mensaje']);

$mensaje_error = $_SESSION['reset_error'] ?? null;
unset($_SESSION['reset_error']);

// [RENDIMIENTO] Liberamos la sesión de inmediato
session_write_close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Planeando con Causa</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        /* Diseño homologado con el resto de la plataforma */
        :root {
            --color-primario: #1e3a8a;
            --color-acento: #f39c12;
            --color-fondo: #f8f9fa;
            --color-texto: #334155;
            --color-tarjeta: #ffffff;
            --color-borde: #e2e8f0;
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--color-fondo); display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .recovery-container { background-color: var(--color-tarjeta); padding: 40px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); width: 100%; max-width: 450px; text-align: center; }
        .recovery-container img { width: 80px; margin-bottom: 20px; }
        .recovery-container h1 { color: var(--color-primario); font-size: 1.6rem; margin-bottom: 10px; margin-top: 0; }
        .recovery-container p { color: #64748b; font-size: 0.95rem; margin-bottom: 25px; line-height: 1.5; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--color-texto); font-size: 0.95rem; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid var(--color-borde); border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 1rem; box-sizing: border-box; }
        .form-group input:focus { outline: none; border-color: var(--color-acento); }
        button[type="submit"] { width: 100%; padding: 15px; background-color: var(--color-primario); color: white; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: background-color 0.3s; margin-top: 10px; }
        button[type="submit"]:hover { background-color: #152c69; }
        
        .login-link { margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--color-borde); font-size: 0.95rem; }
        .login-link a { color: var(--color-primario); text-decoration: none; font-weight: 700; }
        .login-link a:hover { text-decoration: underline; }

        .error-msg { background-color: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; border: 1px solid #fecaca; text-align: left; }
        .success-msg { background-color: #dcfce7; color: #15803d; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; border: 1px solid #bbf7d0; text-align: left;}
    </style>
</head>
<body>
    <div class="recovery-container">
        <img src="logo.png" alt="Logo Planeando con Causa">
        <h1>¿Olvidaste tu Contraseña?</h1>
        <p>No te preocupes. Ingresa tu correo electrónico registrado y te enviaremos instrucciones para crear una nueva.SI NO TE LLEGA EL MSN MANDA WHASTAPP AL 3322564069</p>

        <?php if ($mensaje_error): ?>
            <div class="error-msg"><strong>Error:</strong> <?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <?php if ($mensaje_exito): ?>
            <div class="success-msg"><strong>¡Listo!</strong> <?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>

        <form action="solicitar_reset.php" method="POST" id="recoveryForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="form-group">
                <label for="correo">Correo Electrónico:</label>
                <input type="email" id="correo" name="correo" placeholder="ejemplo@correo.com" required>
            </div>

            <button type="submit" id="btnSubmit">Enviar Enlace de Recuperación</button>
        </form>

        <div class="login-link">
            <p>¿Recordaste tu contraseña? <a href="login.php">Vuelve al Login</a></p>
        </div>
    </div>

    <script>
        // Evitamos múltiples envíos del formulario que podrían saturar los correos
        const form = document.getElementById('recoveryForm');
        const btnSubmit = document.getElementById('btnSubmit');

        form.addEventListener('submit', function() {
            btnSubmit.innerHTML = 'Enviando correo... ⏳';
            btnSubmit.style.backgroundColor = '#64748b';
            btnSubmit.style.cursor = 'not-allowed';
            btnSubmit.disabled = true;
        });
    </script>
</body>
</html>