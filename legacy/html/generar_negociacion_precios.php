<?php
// =====================================================================
// ARCHIVO: generar_negociacion_precios.php
// OBJETIVO: Interfaz de inyección manual de créditos y puente táctico
// =====================================================================

// 1. Iniciamos la sesión para poder rastrear y utilizar las variables del usuario activo.
session_start();

// --- [SEGURIDAD 1] CABECERAS HTTP ESTRICTAS ---
header("X-Frame-Options: DENY"); // Evita que clonen tu página en iframes
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// ===================================================================================
// BLINDAJE NIVEL 1: VERIFICACIÓN DE SESIÓN PRINCIPAL 
// ===================================================================================
// Comprobamos si la variable 'usuario_nombre' NO existe o está vacía. 
// Esto significa que la persona no pasó por tu login.php
if (!isset($_SESSION['usuario_nombre']) || empty($_SESSION['usuario_nombre'])) {
    // Si no hay sesión válida, lo expulsamos inmediatamente a la página de inicio de sesión.
    header('Location: login.php');
    exit(); 
}

// --- [SEGURIDAD 2] GENERACIÓN DE FIRMA DIGITAL (TOKEN CSRF) ---
// Este candado evitará que bots inyecten créditos fantasma
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// --- CONFIGURACIÓN DE SEGURIDAD DEL PANEL ---
$CONTRASEÑA_MAESTRA = 'Golosa69';

// ===================================================================================
// BLINDAJE NIVEL 2: VERIFICACIÓN DE ACCESO DE ADMINISTRADOR
// ===================================================================================
// Verifica si la petición que llega al servidor es de tipo POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_key'])) {
    
    // [SEGURIDAD 3] Validamos que el formulario venga realmente de tu panel y no de un bot
    $token_post = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token_post)) {
        die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Firma Inválida</h2><p>Petición rechazada por seguridad.</p></div>");
    }

    // Compara la clave enviada en el formulario con tu contraseña maestra
    if ($_POST['admin_key'] === $CONTRASEÑA_MAESTRA) {
        // Si la contraseña es exacta, creamos una credencial de sesión
        $_SESSION['acceso_admin_concedido'] = true;
    } else {
        header('Location: generador_adm.php');
        exit();
    }
    
// Si no están enviando el formulario, verificamos si ya tenían el permiso activo
} elseif (!isset($_SESSION['acceso_admin_concedido']) || $_SESSION['acceso_admin_concedido'] !== true) {
    header('Location: generador_adm.php');
    exit();
}

// [RENDIMIENTO] Aprobadas todas las barreras, liberamos el servidor
session_write_close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Activación Manual (Efectivo/OXXO) - Docentes</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f8f9fa; padding: 20px; color: #334155; }
        .container { max-width: 600px; margin: auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        h1, h2 { color: #1e3a8a; }
        .formulario-manual { background: #fffbeb; padding: 25px; border-radius: 10px; border: 1px solid #fde68a; }
        label { font-weight: 600; font-size: 0.9rem; }
        input, select, button { width: 100%; padding: 12px; margin-top: 8px; margin-bottom: 15px; border-radius: 8px; border: 1px solid #cbd5e1; font-family: inherit; font-size: 1rem; box-sizing: border-box; }
        .btn-activar { background: #10b981; color: white; font-weight: bold; border: none; cursor: pointer; margin-top: 10px; font-size: 1.1rem; transition: background 0.3s; }
        .btn-activar:hover { background: #059669; }
        .btn-salir { background: #1e3a8a; color: white; font-weight: bold; margin-top: 30px; border: none; cursor: pointer; transition: background 0.3s; }
        .btn-salir:hover { background: #1c3178; }
        .btn-panel { background: #475569; color: white; font-weight: bold; margin-top: 10px; border: none; cursor: pointer; transition: background 0.3s; }
        .btn-panel:hover { background: #334155; }
    </style>
</head>
<body>

<div class="container">
    <h1>💵 Negociación en Efectivo / OXXO</h1>
    
    <p style="color: #64748b; font-size: 1.1rem;">Usa este panel <b>exclusivamente</b> para activar cuentas o sumar créditos a maestros que pagaron por transferencia bancaria o depósito directo de manera única.</p>
    
    <div class="formulario-manual">
        <form id="formActivacion" onsubmit="activarManual(event)">
            <label for="email_usuario">Correo del Maestro (Ya registrado en la web):</label>
            <input type="email" id="email_usuario" required placeholder="ejemplo@maestro.com">
            
            <label for="plan_seleccionar">Plan a Inyectar (Pago Único):</label>
            <select id="plan_seleccionar" required>
                <option value="1">Plan 1: Docente Básico (5 usos en herramientas)</option>
                <option value="2">Plan 2: Mentor Intermedio (7 usos + Exámenes)</option>
            </select>

            <button type="submit" class="btn-activar">🚀 Inyectar Créditos al Maestro</button>
        </form>
        <div id="mensaje-resultado" style="margin-top: 15px; font-weight: bold; text-align: center; padding: 10px; border-radius: 6px; display: none;"></div>
    </div>

    <button class="btn-salir" onclick="window.location.href='generador_adm.php'">⬅️ Volver al Gestor Principal</button>
    
    <button class="btn-panel" onclick="verificarAccesoPanel()">⚙️ Ir al Panel de Administración</button>
</div>

<script>
    // Rescatamos el Token PHP para usarlo en Javascript
    const csrfToken = "<?php echo htmlspecialchars($csrf_token); ?>";

    // =========================================================================
    // FUNCIÓN: VALIDACIÓN DE ACCESO CON ENVÍO AL BACKEND
    // =========================================================================
    function verificarAccesoPanel() {
        // 1. Pedimos la contraseña al usuario mediante el cuadro de diálogo.
        let intentoPass = prompt("⚠️ ACCESO RESTRINGIDO ⚠️\n\nIngrese la contraseña de seguridad táctica:");
        
        // 2. Comprobamos si escribió la llave maestra.
        if (intentoPass === "DragonNegro") {
            
            // 3. Creamos un formulario HTML invisible usando código JavaScript.
            let form = document.createElement('form');
            form.method = 'POST'; // Usamos POST para que la contraseña viaje oculta
            form.action = 'panel_de_administracion.php'; 

            // 4. Inyectamos la llave táctica
            let inputTactica = document.createElement('input');
            inputTactica.type = 'hidden';
            inputTactica.name = 'llave_tactica'; 
            inputTactica.value = intentoPass; 

            // [SEGURIDAD 4] Inyectamos la firma CSRF para que el siguiente panel te deje entrar
            let inputCsrf = document.createElement('input');
            inputCsrf.type = 'hidden';
            inputCsrf.name = 'csrf_token'; 
            inputCsrf.value = csrfToken;

            // 5. Ensamblamos el formulario oculto y lo enviamos
            form.appendChild(inputTactica);
            form.appendChild(inputCsrf);
            document.body.appendChild(form);
            form.submit(); 
            
        } 
        // Si el usuario no canceló pero se equivocó de clave:
        else if (intentoPass !== null) {
            alert("❌ Contraseña incorrecta. Protocolo de seguridad activado. Acceso denegado.");
        }
    }

    // =========================================================================
    // FUNCIÓN: ACTIVACIÓN MANUAL DE CRÉDITOS
    // =========================================================================
    async function activarManual(e) {
        e.preventDefault();
        const email = document.getElementById('email_usuario').value;
        const plan = document.getElementById('plan_seleccionar').value;
        const mensaje = document.getElementById('mensaje-resultado');

        mensaje.style.display = "block";
        mensaje.style.backgroundColor = "#eef2ff";
        mensaje.style.color = "#1e3a8a";
        mensaje.innerText = "Procesando activación en la base de datos...";

        try {
            const response = await fetch('procesar_negociacion_precios.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken // [SEGURIDAD 5] Enviamos el token en la cabecera
                },
                body: JSON.stringify({ email: email, plan: plan })
            });
            const data = await response.json();

            if (data.success) {
                mensaje.style.backgroundColor = "#dcfce7";
                mensaje.style.color = "#16a34a";
                mensaje.innerText = `✅ ¡Éxito! Plan activado correctamente para ${email}.`;
                document.getElementById('formActivacion').reset();
            } else {
                mensaje.style.backgroundColor = "#fee2e2";
                mensaje.style.color = "#ef4444";
                mensaje.innerText = `❌ Error: ${data.error}`;
            }
        } catch (error) {
            mensaje.style.backgroundColor = "#fee2e2";
            mensaje.style.color = "#ef4444";
            mensaje.innerText = "❌ Error de conexión. Revisa que exista el archivo procesar_negociacion_precios.php.";
        }
    }
</script>

</body>
</html>