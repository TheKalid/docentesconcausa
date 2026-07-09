<?php
session_start();

// Verificamos si existen mensajes de error de registro que nos envió el otro archivo.
$errores = [];
if (isset($_SESSION['registro_errores'])) {
    $errores = $_SESSION['registro_errores'];
    // Limpiamos los errores de la sesión para que no se muestren de nuevo si se recarga la página.
    unset($_SESSION['registro_errores']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Maestros - Planeando con Causa</title>
    
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
            --color-error: #dc2626;
            --fuente-principal: 'Poppins', sans-serif;
        }
        body {
            font-family: var(--fuente-principal);
            background-color: var(--color-fondo);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            box-sizing: border-box;
        }
        .register-container {
            background-color: var(--color-tarjeta);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        .register-header {
            margin-bottom: 30px;
        }
        .logo {
            width: 80px;
            height: auto;
            margin-bottom: 15px;
        }
        .register-header h1 {
            font-size: 2rem;
            color: var(--color-primario);
            margin: 0;
        }
        .error-container {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: left;
        }
        .error-container ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
            color: var(--color-error);
            font-size: 0.9rem;
            font-weight: 600;
        }
        .error-container li {
            margin-bottom: 5px;
        }
        form { display: flex; flex-direction: column; text-align: left; }
        label { font-weight: 600; margin-bottom: 8px; font-size: 0.9rem; }
        input {
            width: 100%; padding: 12px; border: 1px solid var(--color-borde);
            border-radius: 8px; margin-bottom: 20px; font-size: 1rem;
            box-sizing: border-box; background-color: #f8f9fa;
        }
        input:focus {
            outline: none; border-color: var(--color-acento);
            box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.2);
        }
        /* --- ESTILO PARA EL CONTENEDOR DE TÉRMINOS Y CONDICIONES --- */
        .terminos-container {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
            text-align: left;
            font-size: 0.85rem;
        }
        .terminos-container input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
            margin-top: 4px; /* Alineación vertical */
        }
        .terminos-container a {
             color: var(--color-primario);
             font-weight: 600;
        }
        button[type="submit"] {
            background-color: var(--color-primario); color: white; border: none;
            padding: 15px; border-radius: 8px; font-size: 1.1rem;
            font-weight: 700; cursor: pointer; transition: background-color 0.3s, opacity 0.3s;
        }
        button[type="submit"]:hover { background-color: #1c3178; }
        /* --- AÑADIMOS ESTILO PARA EL BOTÓN DESHABILITADO --- */
        button[type="submit"]:disabled {
            background-color: #9ca3af; /* Un color gris */
            cursor: not-allowed;
            opacity: 0.7;
        }
        .login-link {
            margin-top: 25px; font-size: 0.9rem; padding-top: 20px;
            border-top: 1px solid var(--color-borde);
        }
        .login-link a { color: var(--color-primario); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="register-container">
        <header class="register-header">
            <img src="logo.png" alt="Logo Planeando con Causa" class="logo">
            <h1>Crea tu Cuenta</h1>
        </header>

        <?php if (!empty($errores)): ?>
            <div class="error-container">
                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="procesar_registro.php" method="POST">
            <label for="nombre">Nombre Completo:</label>
            <input type="text" id="nombre" name="nombre" required>

            <label for="telefono">Teléfono (10 dígitos):</label>
            <input type="tel" id="telefono" name="telefono" required>

            <label for="correo">Correo Electrónico:</label>
            <input type="email" id="correo" name="correo" required>

            <label for="password">Crea una Contraseña (mín. 8 caracteres):</label>
            <input type="password" id="password" name="password" required>

            <div class="terminos-container">
                <input type="checkbox" id="terminos" name="terminos" required>
                <label for="terminos">
                    He leído y acepto los <a href="terminos.html" target="_blank">Términos y Condiciones</a> de la plataforma.
                </label>
            </div>
            
            <button type="submit" id="submitBtn" disabled>Registrarse</button>
            </form>

        <div class="login-link">
            <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión aquí</a></p>
        </div>
    </div>

    <script>
        const terminosCheckbox = document.getElementById('terminos');
        const submitButton = document.getElementById('submitBtn');

        terminosCheckbox.addEventListener('change', function() {
            // Habilita el botón si el checkbox está marcado, de lo contrario lo deshabilita.
            submitButton.disabled = !this.checked;
        });
    </script>
</body>
</html>