<?php
// =====================================================================
// ARCHIVO: registro.php
// OBJETIVO: Formulario visual para que nuevos maestros creen su cuenta.
// =====================================================================

// 1. Iniciamos sesión para rastrear errores de validación
session_start();

// =====================================================================
// BLOQUE DE SEGURIDAD: CABECERAS HTTP
// =====================================================================
// Estas cabeceras aseguran que el navegador del usuario lo proteja de ataques cruzados
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// =====================================================================
// SEGURIDAD: TOKEN CSRF (ANTI-BOTS Y SPAM)
// =====================================================================
// Creamos una firma digital única. Sin ella, no aceptaremos registros.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// =====================================================================
// GESTIÓN DE ERRORES DEL FORMULARIO
// =====================================================================
// Leemos si el backend (procesar_registro.php) nos devolvió errores
// Ej: "El correo ya existe" o "La contraseña es muy corta"
$errores = [];
if (isset($_SESSION['registro_errores'])) {
    $errores = $_SESSION['registro_errores']; // Extraemos el arreglo de errores
    unset($_SESSION['registro_errores']);     // Lo borramos de la sesión
}

// =====================================================================
// RENDIMIENTO: LIBERACIÓN DE SESIÓN
// =====================================================================
session_write_close(); // Soltamos el hilo de PHP para que la página sea ultra rápida
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
        /* Variables y diseño base igual al login para mantener homogeneidad */
        :root {
            --color-primario: #1e3a8a;
            --color-acento: #f39c12;
            --color-fondo: #f8f9fa;
            --color-texto: #334155;
            --color-tarjeta: #ffffff;
            --color-borde: #e2e8f0;
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--color-fondo); display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box;}
        
        .registro-container { background-color: var(--color-tarjeta); padding: 40px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); width: 100%; max-width: 500px; }
        .registro-container h1 { color: var(--color-primario); font-size: 1.8rem; margin-top: 0; text-align: center; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: var(--color-texto); font-size: 0.95rem; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid var(--color-borde); border-radius: 8px; font-family: 'Poppins', sans-serif; box-sizing: border-box; }
        .form-group input:focus { outline: none; border-color: var(--color-acento); }
        
        /* Estilos del checkbox de Términos y Condiciones */
        .checkbox-group { display: flex; align-items: flex-start; gap: 10px; margin-top: 20px; font-size: 0.9rem; }
        .checkbox-group input { width: auto; margin-top: 4px; }
        
        button[type="submit"] { width: 100%; padding: 15px; background-color: var(--color-primario); color: white; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: background-color 0.3s; margin-top: 20px; }
        button[type="submit"]:hover:not(:disabled) { background-color: #152c69; }
        /* Apariencia gris cuando el botón está desactivado porque no aceptó términos */
        button[type="submit"]:disabled { background-color: #94a3b8; cursor: not-allowed; }
        
        .login-link { margin-top: 25px; text-align: center; padding-top: 20px; border-top: 1px solid var(--color-borde); font-size: 0.95rem; }
        .login-link a { color: var(--color-acento); text-decoration: none; font-weight: 700; }
        
        /* Estilos de la caja de errores */
        .error-msg { background-color: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; border: 1px solid #fecaca; }
        .error-msg ul { margin: 5px 0 0 0; padding-left: 20px; }
    </style>
</head>
<body>
    <div class="registro-container">
        <h1>Crear Cuenta</h1>
        <p style="text-align: center; color: #64748b; margin-bottom: 25px;">Únete a la comunidad de Planeando con Causa</p>

        <?php if (!empty($errores)): ?>
            <div class="error-msg">
                <strong>Por favor, revisa lo siguiente:</strong>
                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="procesar_registro.php" method="POST">
            
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="form-group">
                <label for="nombre">Nombre Completo:</label>
                <input type="text" id="nombre" name="nombre" required>
            </div>

            <div class="form-group">
                <label for="telefono">Teléfono (WhatsApp):</label>
                <input type="tel" id="telefono" name="telefono" required>
            </div>

            <div class="form-group">
                <label for="correo">Correo Electrónico:</label>
                <input type="email" id="correo" name="correo" required>
            </div>

            <div class="form-group">
                <label for="password">Contraseña (Mín. 8 caracteres):</label>
                <input type="password" id="password" name="password" minlength="8" required>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="terminos" name="terminos" required>
                <label for="terminos">He leído y acepto los <a href="terminos.html" target="_blank" style="color: var(--color-primario);">Términos y Condiciones</a> y la Política de Privacidad.</label>
            </div>
            
            <button type="submit" id="submitBtn" disabled>Registrarse</button>
        </form>

        <div class="login-link">
            <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión aquí</a></p>
        </div>
    </div>

    <script>
        // Referencias a los elementos HTML
        const terminosCheckbox = document.getElementById('terminos');
        const submitButton = document.getElementById('submitBtn');
        const formulario = document.querySelector('form'); 

        // 1. Activar/Desactivar Botón
        // Escuchamos cada vez que cambia el cuadrito de los términos.
        // Si está palomeado (!this.checked es FALSO), el botón pierde el 'disabled'.
        terminosCheckbox.addEventListener('change', function() {
            submitButton.disabled = !this.checked;
        });

        // 2. Alerta de Confirmación de Datos (Mejora experiencia de usuario UX)
        // Antes de enviar los datos al servidor, lanzamos una alerta del navegador.
        formulario.addEventListener('submit', function(evento) {
            const confirmacion = confirm("¡Maestro! 🛑\n\nPor favor, revisa que la información que escribiste (especialmente tu correo electrónico) sea correcta antes de continuar.\n\n¿Están correctos tus datos?");
            
            // Si el maestro dice "Cancelar", detenemos el envío (preventDefault)
            if (!confirmacion) {
                evento.preventDefault(); 
            }
        });
    </script>
</body>
</html>