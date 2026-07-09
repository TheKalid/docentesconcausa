<?php
// Inicia la sesión para poder recibir mensajes de otros archivos.
session_start();

// Verificamos si existe un mensaje de error de login en la sesión.
$error_message = null;
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    // Una vez mostrado, lo eliminamos para que no aparezca de nuevo si se recarga la página.
    unset($_SESSION['login_error']);
}
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
        :root {
            --color-primario: #1e3a8a;
            --color-acento: #f39c12;
            --color-fondo: #f8f9fa;
            --color-texto: #334155;
            --color-tarjeta: #ffffff;
            --color-borde: #e2e8f0;
            --color-error: #dc2626; /* Rojo para errores */
            --fuente-principal: 'Poppins', sans-serif;
        }

        body {
            font-family: var(--fuente-principal);
            background-color: var(--color-fondo);
            margin: 0;
            color: var(--color-texto);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            box-sizing: border-box;
        }

        .login-container {
            background-color: var(--color-tarjeta);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .login-header {
            margin-bottom: 30px;
        }

        .logo {
            width: 80px;
            height: auto;
            margin-bottom: 15px;
        }

        .login-header h1 {
            font-size: 2rem;
            color: var(--color-primario);
            margin: 0;
        }

        /* --- ESTILO PARA EL MENSAJE DE ERROR --- */
        .error-message {
            background-color: #fee2e2;
            color: var(--color-error);
            border: 1px solid #fecaca;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.9rem;
            font-weight: 600;
        }

        form {
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        label {
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--color-borde);
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 1rem;
            box-sizing: border-box;
            background-color: #f8f9fa;
        }
        
        input:focus {
            outline: none;
            border-color: var(--color-acento);
            box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.2);
        }

        button[type="submit"] {
            background-color: var(--color-primario);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }

        button[type="submit"]:hover {
            background-color: #1c3178;
            transform: translateY(-2px);
        }

        .links-container {
            margin-top: 25px;
            font-size: 0.9rem;
        }

        .links-container a {
            color: var(--color-primario);
            text-decoration: none;
            font-weight: 600;
        }
        
        .links-container a:hover {
            text-decoration: underline;
        }

        .register-notice {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--color-borde);
            font-size: 0.9rem;
        }
        
        .register-notice p {
            margin: 0;
            color: #64748b;
        }

    </style>
</head>
<body>
    <div class="login-container">
        <header class="login-header">
            <img src="logo.png" alt="Logo Planeando con Causa" class="logo">
            <h1>Iniciar Sesión</h1>
        </header>

        <!-- BLOQUE PHP PARA MOSTRAR EL ERROR SI EXISTE -->
        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form action="procesar_login.php" method="POST">
            <label for="correo">Correo Electrónico:</label>
            <input type="email" id="correo" name="correo" required>

            <label for="password">Contraseña:</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Entrar</button>
        </form>

        <div class="links-container">
            <a href="recuperar_password.html">¿Olvidaste tu contraseña?</a>
        </div>
        
        <div class="register-notice">
            <p>¿Aún no eres socio? <a href="registro.php">Regístrate aquí</a></p>
        </div>
    </div>
</body>
</html>