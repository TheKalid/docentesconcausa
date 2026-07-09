<?php
// =====================================================================
// ARCHIVO: login.php
// OBJETIVO: Interfaz visual para que los usuarios inicien sesión.
// =====================================================================

// 1. Iniciamos la sesión de PHP para poder usar la variable global $_SESSION
session_start();

// =====================================================================
// BLOQUE DE SEGURIDAD: CABECERAS HTTP (HTTP HEADERS)
// =====================================================================
// Estas instrucciones le dicen al navegador del usuario cómo comportarse para protegerlo:

// Impide que un atacante meta tu página dentro de un <frame> o <iframe> invisible (Ataque Clickjacking)
header("X-Frame-Options: DENY");

// Le dice al navegador que bloquee la página si detecta código malicioso inyectado (Ataque XSS)
header("X-XSS-Protection: 1; mode=block");

// Evita que el navegador intente "adivinar" el tipo de archivo, obligándolo a respetar lo que dice el servidor
header("X-Content-Type-Options: nosniff");

// Obliga al navegador a pedir siempre una versión fresca de la página y no usar la que tiene guardada (Anti-caché)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// =====================================================================
// BLOQUE DE SEGURIDAD: PROTECCIÓN ANTI-BOTS (TOKEN CSRF)
// =====================================================================
// Verificamos si ya existe un "Gafete Virtual" (Token CSRF) para esta sesión.
// Si no existe, creamos uno usando una cadena de 32 bytes aleatorios convertidos a formato hexadecimal.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Guardamos el token en una variable para imprimirlo más abajo en el formulario de HTML
$csrf_token = $_SESSION['csrf_token'];

// =====================================================================
// GESTIÓN DE MENSAJES (ERRORES Y ÉXITOS)
// =====================================================================
// Revisamos si el archivo procesador (procesar_login.php) nos mandó algún mensaje de error
$error_message = null;
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']); // Lo borramos para que no salga de nuevo al recargar la página
}

// Revisamos si el archivo procesador de registro (procesar_registro.php) nos mandó un mensaje de éxito
$success_message = null;
if (isset($_SESSION['login_success'])) {
    $success_message = $_SESSION['login_success'];
    unset($_SESSION['login_success']); // Lo borramos tras leerlo
}

// =====================================================================
// RENDIMIENTO: LIBERACIÓN DE SESIÓN
// =====================================================================
// Como en este archivo ya leímos todo lo que necesitábamos de $_SESSION, la cerramos inmediatamente.
// Esto permite que el usuario pueda abrir otras pestañas de tu página sin que el servidor lo ponga en "lista de espera".
session_write_close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Planeando con Causa</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- VARIABLES DE COLOR Y FUENTE --- */
        /* Centralizamos los colores para poder cambiarlos fácilmente en el futuro */
        :root {
            --color-primario: #1e3a8a; /* Azul marino institucional */
            --color-acento: #f39c12;   /* Naranja para botones y alertas */
            --color-fondo: #f8f9fa;    /* Gris muy claro para el fondo */
            --color-texto: #334155;    /* Gris oscuro para lectura cómoda */
            --color-tarjeta: #ffffff;  /* Blanco puro para el cuadro del formulario */
            --color-borde: #e2e8f0;    /* Gris claro para separar elementos */
        }

        /* Estilos generales del cuerpo de la página */
        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: var(--color-fondo); 
            display: flex; /* Usamos Flexbox para centrar el cuadro */
            justify-content: center; /* Centrado horizontal */
            align-items: center; /* Centrado vertical */
            min-height: 100vh; /* Ocupa el 100% de la altura de la pantalla */
            margin: 0; 
        }

        /* La caja blanca donde va el formulario */
        .login-container { 
            background-color: var(--color-tarjeta); 
            padding: 40px; 
            border-radius: 12px; /* Bordes redondeados modernos */
            box-shadow: 0 8px 25px rgba(0,0,0,0.08); /* Sombra suave para dar efecto 3D */
            width: 100%; 
            max-width: 400px; /* Evita que se estire demasiado en pantallas grandes */
            text-align: center; 
        }

        .login-container img { width: 80px; margin-bottom: 20px; }
        .login-container h1 { color: var(--color-primario); font-size: 1.8rem; margin-bottom: 25px; margin-top: 0; }
        
        /* Estilos de los campos del formulario */
        .form-group { margin-bottom: 20px; text-align: left; position: relative; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--color-texto); }
        .form-group input { width: 100%; padding: 12px; border: 1px solid var(--color-borde); border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 1rem; box-sizing: border-box; }
        .form-group input:focus { outline: none; border-color: var(--color-acento); } /* Color naranja al seleccionar el campo */
        
        /* Botón del ojito para ver la contraseña */
        .toggle-btn { position: absolute; right: 10px; top: 38px; background: none; border: none; cursor: pointer; font-size: 1.2rem; }
        
        /* Botón de enviar */
        button[type="submit"] { width: 100%; padding: 15px; background-color: var(--color-primario); color: white; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: background-color 0.3s; margin-top: 10px; }
        button[type="submit"]:hover { background-color: #152c69; } /* Color un poco más oscuro al pasar el mouse */
        
        /* Enlaces secundarios */
        .links-container { margin-top: 20px; }
        .links-container a { color: var(--color-primario); text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .register-notice { margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--color-borde); font-size: 0.95rem; }
        .register-notice a { color: var(--color-acento); text-decoration: none; font-weight: 700; }
        
        /* Cajas de notificación de errores y éxitos */
        .error-msg { background-color: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; border: 1px solid #fecaca; }
        .success-msg { background-color: #dcfce7; color: #15803d; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; border: 1px solid #bbf7d0; }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="logo.png" alt="Logo">
        <h1>Iniciar Sesión</h1>

        <?php if ($error_message): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="success-msg"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form action="procesar_login.php" method="POST">
            
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="form-group">
                <label for="correo">Correo Electrónico:</label>
                <input type="email" id="correo" name="correo" required>
            </div>

            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
                <button type="button" id="togglePassword" class="toggle-btn" aria-label="Mostrar u ocultar contraseña">👁️</button>
            </div>

            <button type="submit">Entrar</button>
        </form>

        <div class="links-container">
            <a href="olvide_password.php">¿Olvidaste tu contraseña?</a>
        </div>
        
        <div class="register-notice">
            <p>¿Aún no eres socio? <a href="registro.php">Regístrate aquí</a></p>
        </div>
    </div>

    <script>
        // Capturamos el botón y el input de la contraseña
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        // Escuchamos cuando le den clic al ojito
        togglePassword.addEventListener('click', function () {
            // Le preguntamos al HTML qué tipo de campo es actualmente
            const tipoActual = passwordInput.getAttribute('type');
            
            // Si es 'password' (oculto con puntitos), lo cambiamos a 'text' (visible). Si no, lo devolvemos a 'password'.
            const tipoNuevo = tipoActual === 'password' ? 'text' : 'password';
            
            // Aplicamos el cambio al campo
            passwordInput.setAttribute('type', tipoNuevo);
            
            // Cambiamos el dibujito del ojo dependiendo de si está oculto o visible
            this.textContent = tipoNuevo === 'password' ? '👁️‍🗨️' : '👁️';
        });
    </script>
</body>
</html>