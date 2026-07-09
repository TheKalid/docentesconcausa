<?php
// =======================================================================
// 1. CONFIGURACIÓN Y BLINDAJE DE SEGURIDAD
// =======================================================================
session_start();

// [SEGURIDAD] Cabeceras HTTP Anti-Ataques
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
// Evitar caché para garantizar que el contador de usos diarios siempre sea preciso
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// [SEGURIDAD] Generación del Token CSRF para proteger la petición a la IA
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$userId = $_SESSION['usuario_id'];
$usos_actuales = 0;

// =======================================================================
// 2. CONEXIÓN A BD Y CÁLCULO DE USOS DIARIOS
// =======================================================================
require_once 'conexion.php';

// [RENDIMIENTO] Uso estricto de sentencias preparadas
$stmt = $conexion->prepare("SELECT usos_diarios_padres, ultimo_uso_padres FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    // Si la fecha guardada es de un día anterior, el usuario tiene 2 usos.
    // Si no, tiene los que le queden de hoy.
    if ($user['ultimo_uso_padres'] < date('Y-m-d')) {
        $usos_actuales = 2;
    } else {
        $usos_actuales = $user['usos_diarios_padres'];
    }
} else {
    $usos_actuales = 2; // Para un usuario que nunca la ha usado
}

// [RENDIMIENTO] Cerramos la conexión a la base de datos INMEDIATAMENTE
$stmt->close();
$conexion->close();

// [RENDIMIENTO] Liberar sesión para no bloquear otras pestañas
session_write_close();

$puede_generar = $usos_actuales > 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Herramientas para Padres de Familia</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        :root {
            --color-primario: #1e3a8a;
            --color-secundario: #3b82f6;
            --color-acento: #10b981;
            --color-crisis: #ef4444;
            --color-fondo: #f8fafc;
            --color-texto: #334155;
            --color-tarjeta: #ffffff;
            --fuente-principal: 'Poppins', sans-serif;
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body { font-family: var(--fuente-principal); background-color: var(--color-fondo); color: var(--color-texto); line-height: 1.6; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: var(--color-tarjeta); padding: 40px; border-radius: 20px; box-shadow: var(--shadow-lg); }
        .header-section { text-align: center; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
        .header-section h1 { color: var(--color-primario); font-size: 2.5rem; margin-bottom: 10px; }
        .header-section p { font-size: 1.1rem; color: #64748b; }
        .contador-usos { background-color: #e0f2fe; color: #0369a1; padding: 10px 20px; border-radius: 50px; font-weight: 600; display: inline-block; margin-top: 10px; border: 1px solid #bae6fd; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; font-weight: 600; color: var(--color-primario); margin-bottom: 10px; font-size: 1.1rem; }
        .form-group select, .form-group textarea { width: 100%; padding: 15px; border: 2px solid #cbd5e1; border-radius: 12px; font-size: 1rem; font-family: var(--fuente-principal); transition: all 0.3s ease; background-color: #f8fafc; box-sizing: border-box; }
        .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--color-secundario); background-color: #ffffff; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
        .btn-submit { width: 100%; padding: 18px; background-color: var(--color-primario); color: white; border: none; border-radius: 12px; font-size: 1.2rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: var(--shadow-md); margin-top: 10px; }
        .btn-submit:hover:not(:disabled) { background-color: var(--color-secundario); transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .btn-submit:disabled { background-color: #94a3b8; cursor: not-allowed; transform: none; box-shadow: none; }
        .resultado-container { margin-top: 40px; display: none; background: #ffffff; padding: 30px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: var(--shadow-md); }
        .resultado-container h3 { color: var(--color-primario); margin-top: 0; padding-bottom: 15px; border-bottom: 2px solid #e2e8f0; }
        .contenido-ia { line-height: 1.8; color: #475569; }
        .contenido-ia h3, .contenido-ia h4 { color: var(--color-primario); margin-top: 25px; }
        .contenido-ia ul { padding-left: 20px; }
        .contenido-ia li { margin-bottom: 10px; }
        .btn-regresar { display: block; text-align: center; margin-top: 30px; color: var(--color-secundario); text-decoration: none; font-weight: 600; transition: color 0.3s; }
        .btn-regresar:hover { color: var(--color-primario); text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-section">
        <h1>👨‍👩‍👧‍👦 Herramientas para Padres</h1>
        <p>Encuentra orientación y estrategias prácticas para apoyar el desarrollo y bienestar de tus hijos.</p>
        <div class="contador-usos">
            Intentos gratuitos de hoy: <span id="contador-display"><?php echo $usos_actuales; ?></span>
        </div>
    </div>

    <form id="formulario-padres">
        <div class="form-group">
            <label for="categoria-select">1. ¿Qué etapa o área te interesa?</label>
            <select id="categoria-select" required>
                <option value="">-- Selecciona una categoría --</option>
                <option value="infancia">Primera Infancia (0-5 años)</option>
                <option value="escolar">Etapa Escolar (6-11 años)</option>
                <option value="adolescencia">Adolescencia (12+ años)</option>
                <option value="comunicacion">Comunicación y Vínculos Familiares</option>
                <option value="emociones">Manejo de Emociones y Disciplina Positiva</option>
                <option value="tecnologia">Uso de Tecnología y Pantallas</option>
            </select>
        </div>

        <div class="form-group">
            <label for="problema-select">2. ¿Sobre qué tema específico buscas orientación?</label>
            <select id="problema-select" required disabled>
                <option value="">-- Selecciona primero la categoría --</option>
            </select>
        </div>

        <div class="form-group">
            <label for="descripcion-extra">3. Contexto adicional (Opcional pero recomendado)</label>
            <textarea id="descripcion-extra" rows="3" placeholder="Ej. Mi hijo de 8 años se frustra mucho cuando pierde en los juegos, ¿cómo puedo ayudarle a manejar su enojo?"></textarea>
        </div>

        <button type="submit" id="btn-submit" class="btn-submit" <?php echo !$puede_generar ? 'disabled' : ''; ?>>
            <?php echo $puede_generar ? 'Obtener Orientación' : 'Intentos Diarios Agotados'; ?>
        </button>
    </form>

    <div id="resultado-container" class="resultado-container"></div>
    <a href="index.php" class="btn-regresar">← Volver al inicio</a>
</div>

<script>
    const subtemas = {
        'infancia': [
            "Establecimiento de rutinas (sueño, alimentación)",
            "Control de esfínteres",
            "Manejo de berrinches o rabietas",
            "Estimulación del lenguaje"
        ],
        'escolar': [
            "Apoyo con las tareas escolares",
            "Dificultades de atención o concentración",
            "Problemas de socialización con compañeros",
            "Fomento de la lectura"
        ],
        'adolescencia': [
            "Cambios de humor y rebeldía",
            "Presión de grupo y amistades",
            "Educación afectivo-sexual",
            "Motivación y proyecto de vida"
        ],
        'comunicacion': [
            "Cómo hablar para que me escuchen",
            "Cómo escuchar para que me hablen",
            "Resolución de conflictos entre hermanos",
            "Tiempo de calidad en familia"
        ],
        'emociones': [
            "Disciplina positiva (límites sin castigos físicos)",
            "Cómo validar las emociones de mis hijos",
            "Fomento de la autoestima y confianza",
            "Manejo de la ansiedad infantil"
        ],
        'tecnologia': [
            "Límites de tiempo en pantallas",
            "Peligros de redes sociales y ciberacoso",
            "Uso educativo de la tecnología",
            "Videojuegos: pros y contras"
        ]
    };

    const categoriaSelect = document.getElementById('categoria-select');
    const problemaSelect = document.getElementById('problema-select');
    const form = document.getElementById('formulario-padres');
    const submitBtn = document.getElementById('btn-submit');
    const resultadoDiv = document.getElementById('resultado-container');
    
    // [SEGURIDAD] Capturamos el Token CSRF generado por PHP
    const csrfToken = "<?php echo $csrf_token; ?>";

    categoriaSelect.addEventListener('change', function() {
        const categoria = this.value;
        problemaSelect.innerHTML = '<option value="">-- Selecciona un tema --</option>';
        
        if (categoria && subtemas[categoria]) {
            subtemas[categoria].forEach(tema => {
                const option = document.createElement('option');
                option.value = tema;
                option.textContent = tema;
                problemaSelect.appendChild(option);
            });
            problemaSelect.disabled = false;
        } else {
            problemaSelect.disabled = true;
        }
    });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        resultadoDiv.style.display = 'block';
        resultadoDiv.innerHTML = '<p style="text-align: center; font-weight: bold; color: var(--color-primario);">Buscando orientación y estrategias... 🧠<br><span style="font-size: 0.9em; color: #64748b;">(Esto puede tardar unos segundos)</span></p>';
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Procesando...';

        const datos = { 
            categoria: categoriaSelect.options[categoriaSelect.selectedIndex].text, 
            problema: problemaSelect.value, 
            descripcion: document.getElementById('descripcion-extra').value 
        };

        try {
            const response = await fetch('procesar_herramientas_de_padres_de_familia.php', { 
                method: 'POST', 
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken // [SEGURIDAD] Enviamos el token al procesador
                }, 
                body: JSON.stringify(datos) 
            });

            const data = await response.json();

            if (!data.success) { 
                throw new Error(data.error || 'Ocurrió un error en el servidor.'); 
            }

            // Sanitización básica del título para evitar inyección en el DOM
            const problemaSeguro = data.problema.replace(/</g, "&lt;").replace(/>/g, "&gt;");

            resultadoDiv.innerHTML = `<h3>Recomendaciones para: ${problemaSeguro}</h3><div class="contenido-ia">${marked.parse(data.recomendacion)}</div>`;
            
            const contadorDisplay = document.getElementById('contador-display');
            contadorDisplay.textContent = data.usos_restantes;

            if (data.usos_restantes <= 0) {
                submitBtn.textContent = 'Intentos Diarios Agotados';
                submitBtn.disabled = true;
            } else {
                submitBtn.textContent = 'Obtener Orientación';
                submitBtn.disabled = false;
            }

        } catch (error) {
            resultadoDiv.innerHTML = `
                <div style="background-color: #fee2e2; border-left: 4px solid var(--color-crisis); padding: 15px; border-radius: 8px;">
                    <h4 style="color: var(--color-crisis); margin-top: 0;">Error de procesamiento</h4>
                    <p style="color: #991b1b; margin-bottom: 0;">${error.message}</p>
                </div>`;
            submitBtn.textContent = 'Obtener Orientación';
            submitBtn.disabled = false;
        }
    });
</script>
</body>
</html>