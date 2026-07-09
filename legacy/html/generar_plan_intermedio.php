<?php
// ===== BLOQUE PHP INICIAL CON CONTADOR, SEGURIDAD Y CONTEXTO =====
session_start();

// [SEGURIDAD] 1. CABECERAS HTTP ANTI-ATAQUES Y ANTI-Caché
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains"); // Fuerza HTTPS
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0"); // Evita guardar en caché
header("Pragma: no-cache");

// Política de Seguridad de Contenido (CSP) básica para mitigar XSS severo
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; connect-src 'self' https://api.openai.com;");

// --- 1. VERIFICACIÓN DE LOGIN ---
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// [SEGURIDAD] 2. GENERACIÓN DEL TOKEN CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
}

// --- 2. VERIFICACIÓN DE NIVEL DE PLAN ---
$nivel_requerido = 2;
$plan_activo = $_SESSION['plan_activo'] ?? 0;
if ($plan_activo < $nivel_requerido) {
    header('Location: catalogo_de_pagos.php');
    exit();
}

// --- 3. OBTENCIÓN DE USOS Y CONTEXTO DESDE BD ---
include 'conexion.php';
$usuario_id = $_SESSION['usuario_id'];

// [SEGURIDAD] Excelente uso de sentencias preparadas
$stmt_usos = $conexion->prepare("SELECT usos_plan_intermedio, contexto_guardado FROM usuarios WHERE id = ?");
$stmt_usos->bind_param("i", $usuario_id);
$stmt_usos->execute();
$usos_result = $stmt_usos->get_result()->fetch_assoc();

$usos_actuales = $usos_result['usos_plan_intermedio'] ?? 0;
$contexto_guardado = $usos_result['contexto_guardado'] ?? ''; 

$_SESSION['usos_plan_intermedio'] = $usos_actuales; 
$stmt_usos->close();

$puede_generar = $usos_actuales > 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Planeación Intermedia - Planeando con Causa</title>
    
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* (Se mantienen los estilos CSS originales intactos) */
        :root {
            --color-primario: #1e3a8a;
            --color-acento: #f39c12;
            --color-exito: #16a34a;
            --color-fondo: #f8f9fa;
            --color-texto: #334155;
            --color-tarjeta: #ffffff;
            --color-borde: #e2e8f0;
            --fuente-principal: 'Poppins', sans-serif;
        }
        body { font-family: var(--fuente-principal); background-color: var(--color-fondo); color: var(--color-texto); line-height: 1.6; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        header { text-align: center; margin-bottom: 30px; }
        header h1 { color: var(--color-primario); font-size: 2.2rem; }
        .form-section, .resultado-section { background-color: var(--color-tarjeta); border-radius: 12px; padding: 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); margin-bottom: 30px; }
        label { display: block; margin-top: 15px; font-weight: 600; color: var(--color-primario); }
        select, input, textarea, button { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--color-borde); font-size: 1rem; font-family: inherit; margin-top: 5px; box-sizing: border-box; }
        textarea { resize: vertical; min-height: 100px; }
        select:focus, input:focus, textarea:focus { outline: none; border-color: var(--color-acento); box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.2); }
        select[multiple] { height: 120px; }
        #btnGenerar { margin-top: 25px; background-color: var(--color-primario); color: white; border: none; font-weight: 700; cursor: pointer; transition: 0.3s; font-size: 1.1rem; }
        #btnGenerar:hover { background-color: #152c69; transform: translateY(-2px); }
        #btnGenerar:disabled { background-color: #94a3b8; cursor: not-allowed; transform: none; }
        #btnCopiar { background-color: var(--color-exito); color: white; border: none; font-weight: 600; cursor: pointer; padding: 12px; border-radius: 8px; font-size: 1rem; transition: background-color 0.3s; }
        #btnCopiar:hover { background-color: #15803d; }
        #btnDescargarPDF { background-color: #dc2626; color: white; margin-top: 10px; border: none; font-weight: 600; cursor: pointer; transition: background-color 0.3s; padding: 12px; border-radius: 8px; font-size: 1rem; }
        #btnDescargarPDF:hover { background-color: #b91c1c; }
        .contenedor-botones { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }
        .encabezado-oficial { text-align: center; border-bottom: 2px solid var(--color-primario); padding-bottom: 15px; margin-bottom: 20px; }
        .encabezado-oficial h2 { margin: 0; color: var(--color-primario); text-transform: uppercase; font-size: 1.5rem; }
        .encabezado-oficial p { margin: 5px 0 0 0; font-size: 1.1rem; color: var(--color-texto); }
        .btn-guardar-contexto { background-color: #0ea5e9; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 0.9rem; font-weight: 600; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; width: auto; }
        .btn-guardar-contexto:hover { background-color: #0284c7; }
        .btn-guardar-contexto:disabled { background-color: #94a3b8; cursor: not-allowed; }
        .aviso { background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; border: 1px solid #ffeeba; margin-top: 20px; }
        .markdown-content h1, .markdown-content h2, .markdown-content h3 { color: var(--color-primario); border-bottom: 2px solid var(--color-acento); padding-bottom: 5px; margin-top: 20px; }
        .markdown-content ul { padding-left: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📘 Generador de Planeación Intermedia</h1>
            <p>Usos restantes para este plan: <strong id="contador-usos"><?php echo htmlspecialchars($usos_actuales, ENT_QUOTES, 'UTF-8'); ?></strong></p>
        </header>
        
        <section class="form-section">
            <form id="planeacionForm">
                <label for="grado">1. Grado:</label>
                <select id="grado" onchange="actualizarOpciones()" required>
                    <option value="">-- Selecciona un grado --</option>
                    <optgroup label="Preescolar">
                        <option value="1º de Preescolar">1º de Preescolar</option>
                        <option value="2º de Preescolar">2º de Preescolar</option>
                        <option value="3º de Preescolar">3º de Preescolar</option>
                    </optgroup>
                    <optgroup label="Primaria">
                        <option value="1º de Primaria">1º de Primaria</option>
                        <option value="2º de Primaria">2º de Primaria</option>
                        <option value="3º de Primaria">3º de Primaria</option>
                        <option value="4º de Primaria">4º de Primaria</option>
                        <option value="5º de Primaria">5º de Primaria</option>
                        <option value="6º de Primaria">6º de Primaria</option>
                    </optgroup>
                    <optgroup label="Secundaria">
                        <option value="1º de Secundaria">1º de Secundaria</option>
                        <option value="2º de Secundaria">2º de Secundaria</option>
                        <option value="3º de Secundaria">3º de Secundaria</option>
                    </optgroup>
                </select>

                <div id="contenedorAsignatura" style="display:none;">
                    <label for="asignatura">2. Asignatura:</label>
                    <select id="asignatura" onchange="actualizarCamposFormativos()"></select>
                </div>

                <label for="campoFormativo">3. Campo Formativo:</label>
                <select id="campoFormativo" onchange="actualizarContenidos()" required>
                    <option value="">-- Selecciona primero el grado --</option>
                </select>

                <label for="contenido">4. Contenido:</label>
                <select id="contenido" onchange="actualizarPDAs()" required>
                    <option value="">-- Selecciona primero el Campo Formativo --</option>
                </select>

                <label for="pda">5. PDA (Proceso de Desarrollo de Aprendizaje):</label>
                <select id="pda" required>
                    <option value="">-- Selecciona primero el Contenido --</option>
                </select>

                <label for="ejeArticulador">6. Ejes Articuladores (Ctrl+Clic para varios):</label>
                <select id="ejeArticulador" multiple required>
                    <option value="Inclusión">Inclusión</option>
                    <option value="Interculturalidad crítica">Interculturalidad crítica</option>
                    <option value="Pensamiento crítico">Pensamiento crítico</option>
                    <option value="Igualdad de género">Igualdad de género</option>
                    <option value="Vida saludable">Vida saludable</option>
                    <option value="Apropiación de las culturas">Apropiación de las culturas</option>
                    <option value="Artes y experiencias estéticas">Artes y experiencias estéticas</option>
                </select>

                <label for="metodologia">7. Metodología NEM Sugerida:</label>
                <select id="metodologia" required>
                    <option value="">-- Selecciona una metodología --</option>
                    <option value="Aprendizaje Basado en Proyectos Comunitarios">Aprendizaje Basado en Proyectos Comunitarios "Ideal para Ética, De lo Humano o Lenguajes""</option>
                    <option value="Aprendizaje Basado en Indagación (STEAM)">Aprendizaje Basado en Indagación (STEAM) "Ideal para Lenguajes o Saberes"</option>
                    <option value="Aprendizaje Basado en Problemas (ABP)">Aprendizaje Basado en Problemas (ABP) "Ideal para Saberes, Ética o De lo Humano"</option>
                    <option value="Aprendizaje Servicio (AS)">Aprendizaje Servicio (AS) "Ideal para Ética o De lo humano"</option>
                </select>

                <div style="background-color: #e2e8f0; padding: 15px; border-radius: 8px; margin-top: 20px; margin-bottom: 20px;">
                    <h4 style="margin-top: 0; color: var(--color-primario);"><i class="fas fa-id-card"></i> Datos para el documento (Se guardan automáticamente)</h4>
                    <label for="nombreDocente" style="margin-top: 10px;">Nombre del Docente:</label>
                    <input type="text" id="nombreDocente" placeholder="Ej. Profr. Juan Pérez">
                    <label for="nombreEscuela">Nombre de la Escuela:</label>
                    <input type="text" id="nombreEscuela" placeholder="Ej. Esc. Primaria Benito Juárez">
                </div>
        
                <label for="contexto">8. Contexto del Grupo (Opcional pero recomendado):</label>
                <textarea id="contexto" placeholder="Ej. El grupo tiene 25 alumnos, muestran interés por la naturaleza..."><?php echo htmlspecialchars($contexto_guardado, ENT_QUOTES, 'UTF-8'); ?></textarea>
                
                <div style="text-align: right; margin-top: 10px;">
                    <span id="mensajeGuardado" style="color: #16a34a; font-weight: 600; font-size: 0.9rem; margin-right: 15px; display: none;">
                        <i class="fas fa-check-circle"></i> ¡Contexto guardado!
                    </span>
                    <button type="button" id="btnGuardarContexto" class="btn-guardar-contexto">
                        <i class="fas fa-save"></i> Guardar como mi contexto por defecto
                    </button>
                </div>

                <label for="tiempo">9. Duración:</label>
                <select id="tiempo" required>
                    <option value="">-- Selecciona el tiempo --</option>
                    <option value="5 días">5 días (Semanal)</option>
                    <option value="10 días">10 días (Quincenal)</option>
                </select>

                <button type="button" id="btnGenerar"><i class="fas fa-magic"></i> Generar Planeación Intermedia</button>
            </form>
        </section>

        <section class="resultado-section" id="seccionResultadoContenedor" style="display:none;">
            <h2>📝 Tu Planeación Intermedia</h2>
            <div id="resultadoPlaneacion"></div>
            <div id="contenedorBotones" class="contenedor-botones" style="display:none;">
                <button type="button" id="btnCopiar"><i class="fas fa-copy"></i> Copiar Planeación</button>
                <button type="button" id="btnDescargarPDF"><i class="fas fa-file-pdf"></i> Descargar como PDF</button>
            </div>
        </section>
    </div>

    <script>
        // ==========================================================
        // VARIABLES Y NAVEGACIÓN DE DATOS
        // ==========================================================
        let usosRestantes = parseInt(<?php echo (int)$usos_actuales; ?>, 10);
        let datosGradoActual = {};
        
        // [SEGURIDAD] Capturamos el token CSRF para usarlo en nuestras peticiones Fetch
        const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";

        const btnGenerar = document.getElementById('btnGenerar');
        const formSection = document.querySelector('.form-section');
        const seccionResultadoContenedor = document.getElementById('seccionResultadoContenedor');
        const resultadoDiv = document.getElementById('resultadoPlaneacion');
        const btnCopiar = document.getElementById('btnCopiar');
        const contenedorBotones = document.getElementById('contenedorBotones');
        const btnDescargarPDF = document.getElementById('btnDescargarPDF');
        const inputNombreDocente = document.getElementById('nombreDocente');
        const inputNombreEscuela = document.getElementById('nombreEscuela');

        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('docente_nombre')) inputNombreDocente.value = localStorage.getItem('docente_nombre');
            if (localStorage.getItem('docente_escuela')) inputNombreEscuela.value = localStorage.getItem('docente_escuela');
            
            if (usosRestantes <= 0) {
                btnGenerar.disabled = true;
                btnGenerar.innerHTML = '<i class="fas fa-ban"></i> Usos Agotados';
            }
        });

        async function actualizarOpciones() {
            const gradoSeleccionado = document.getElementById("grado").value;
            const contenedorAsignatura = document.getElementById('contenedorAsignatura');
            const asignaturaSelect = document.getElementById('asignatura');

            document.getElementById('campoFormativo').innerHTML = '<option value="">-- Selecciona --</option>';
            document.getElementById('contenido').innerHTML = '<option value="">-- Selecciona --</option>';
            document.getElementById('pda').innerHTML = '<option value="">-- Selecciona --</option>';
            
            if (!gradoSeleccionado) {
                contenedorAsignatura.style.display = 'none';
                return;
            }

            const nombreArchivo = gradoSeleccionado.toLowerCase().replace(/º de /g, '_').replace(/ /g, '_') + '.json';

            try {
                const respuesta = await fetch(`datos_plan_basico/${nombreArchivo}`);
                if (!respuesta.ok) throw new Error('Archivo no encontrado');
                datosGradoActual = await respuesta.json();

                if (gradoSeleccionado.includes('Secundaria')) {
                    contenedorAsignatura.style.display = 'block';
                    asignaturaSelect.innerHTML = '<option value="">-- Selecciona Asignatura --</option>';
                    const asignaturasUnicas = new Set();
                    Object.keys(datosGradoActual).forEach(campo => {
                        Object.keys(datosGradoActual[campo]).forEach(asig => asignaturasUnicas.add(asig));
                    });
                    asignaturasUnicas.forEach(asig => asignaturaSelect.add(new Option(asig, asig)));
                } else {
                    contenedorAsignatura.style.display = 'none';
                    actualizarCamposFormativos();
                }
            } catch (error) {
                console.error('Error cargando JSON:', error);
            }
        }

        function actualizarCamposFormativos() {
            const grado = document.getElementById('grado').value;
            const asignatura = document.getElementById('asignatura').value;
            const campoFormativoSelect = document.getElementById("campoFormativo");
            campoFormativoSelect.innerHTML = '<option value="">-- Selecciona Campo Formativo --</option>';
            
            if (!grado.includes('Secundaria')) {
                 Object.keys(datosGradoActual).forEach(campo => campoFormativoSelect.add(new Option(campo, campo)));
            } else {
                Object.keys(datosGradoActual).forEach(campo => {
                    if(datosGradoActual[campo][asignatura]) campoFormativoSelect.add(new Option(campo, campo));
                });
            }
        }

        function actualizarContenidos() {
            const campo = document.getElementById('campoFormativo').value;
            const asig = document.getElementById('asignatura').value;
            const grado = document.getElementById('grado').value;
            const contenidoSelect = document.getElementById("contenido");

            contenidoSelect.innerHTML = '<option value="">-- Selecciona Contenido --</option>';
            document.getElementById("pda").innerHTML = '<option value="">-- Selecciona --</option>';
            
            let datos = grado.includes('Secundaria') ? datosGradoActual[campo][asig] : datosGradoActual[campo];
            if(!datos) return;

            Object.keys(datos).forEach(cont => {
                let opt = new Option(cont, cont);
                opt.title = cont;
                contenidoSelect.add(opt);
            });
        }

        function actualizarPDAs() {
            const campo = document.getElementById('campoFormativo').value;
            const asig = document.getElementById('asignatura').value;
            const grado = document.getElementById('grado').value;
            const cont = document.getElementById("contenido").value;
            const pdaSelect = document.getElementById("pda");

            pdaSelect.innerHTML = '<option value="">-- Selecciona PDA --</option>';
            
            let pdas = grado.includes('Secundaria') ? datosGradoActual[campo][asig][cont] : datosGradoActual[campo][cont];
            if(!pdas || !Array.isArray(pdas)) return;

            pdas.forEach(pda => {
                let opt = new Option(pda.length > 100 ? pda.substring(0, 97) + '...' : pda, pda);
                opt.title = pda;
                pdaSelect.add(opt);
            });
        }

        // ==========================================================
        // GUARDAR CONTEXTO EN LA BASE DE DATOS
        // ==========================================================
        const btnGuardarContexto = document.getElementById('btnGuardarContexto');
        if(btnGuardarContexto) {
            btnGuardarContexto.addEventListener('click', async () => {
                const contextoText = document.getElementById('contexto').value;
                const msj = document.getElementById('mensajeGuardado');
                
                const textoOriginal = btnGuardarContexto.innerHTML;
                btnGuardarContexto.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                btnGuardarContexto.disabled = true;

                try {
                    const response = await fetch('guardar_contexto.php', {
                        method: 'POST',
                        // [SEGURIDAD] Enviamos el token CSRF también aquí.
                        headers: { 
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({ contexto: contextoText })
                    });
                    
                    const data = await response.json();
                    
                    if(data.success) {
                        msj.style.display = 'inline';
                        setTimeout(() => { msj.style.display = 'none'; }, 3000);
                    } else {
                        alert("Error al guardar: " + data.error);
                    }
                } catch(e) {
                    console.error("Error en la petición:", e);
                    alert("No se pudo conectar con el servidor para guardar el contexto.");
                } finally {
                    btnGuardarContexto.innerHTML = textoOriginal;
                    btnGuardarContexto.disabled = false;
                }
            });
        }

        // ==========================================================
        // LÓGICA CORE: GENERACIÓN CON IA (SÍNCRONA)
        // ==========================================================
        btnGenerar.addEventListener('click', generarPlaneacionIA);

        async function generarPlaneacionIA() {
            const grado = document.getElementById('grado').value;
            const asignatura = document.getElementById('asignatura') ? document.getElementById('asignatura').value : '';
            const campoFormativo = document.getElementById('campoFormativo').value;
            const contenido = document.getElementById('contenido').value;
            const pda = document.getElementById('pda').value;
            const tiempo = document.getElementById('tiempo').value;
            const metodologia = document.getElementById('metodologia').value;
            const contexto = document.getElementById('contexto').value;
            
            const selectEjes = document.getElementById('ejeArticulador');
            const ejesSeleccionados = Array.from(selectEjes.selectedOptions).map(opt => opt.value).join(', ');

            if (!grado || !campoFormativo || !contenido || !pda || !tiempo || !metodologia) {
                alert('Por favor, completa todos los campos obligatorios antes de generar.');
                return;
            }

            formSection.style.display = 'none';
            seccionResultadoContenedor.style.display = 'block';
            contenedorBotones.style.display = 'none'; 
            
            localStorage.setItem('docente_nombre', inputNombreDocente.value);
            localStorage.setItem('docente_escuela', inputNombreEscuela.value);
            
            resultadoDiv.innerHTML = '<p style="text-align: center; font-size: 1.2rem; color: #1e3a8a;">🧠 Analizando pedagogía e integrando el contexto del grupo...<br><br><span style="font-size:0.9rem; color:#64748b;">Este proceso toma de 30 a 90 segundos. Por favor, no recargues la página.</span></p>';
            
            btnGenerar.disabled = true;
            btnGenerar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';

            let promptText = `Grado: ${grado}\nCampo Formativo: ${campoFormativo}\n`;
            if (asignatura && document.getElementById('contenedorAsignatura').style.display !== 'none') {
                promptText += `Asignatura: ${asignatura}\n`;
            }
            promptText += `Contenido: ${contenido}\nPDA: ${pda}\nEjes Articuladores: ${ejesSeleccionados}\nMetodología: ${metodologia}\nDuración: ${tiempo}\n`;
            if (contexto) {
                promptText += `\nCONTEXTO ESPECÍFICO DEL GRUPO:\n${contexto}\n`;
            }

            try {
                const response = await fetch('procesar_planeacion_intermedio.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken // [SEGURIDAD] Inyectamos la firma digital
                    },
                    body: JSON.stringify({ prompt: promptText })
                });

                const data = await response.json();

                if (!response.ok || data.status === 'error' || data.success === false) {
                    throw new Error(data.error || data.details || 'Error desconocido del servidor.');
                }

                if (data.status === 'completo') {
                    usosRestantes = data.usos_restantes;
                    document.getElementById('contador-usos').innerText = usosRestantes;

                    const plan = data.plan;
                    // [SEGURIDAD] Función sencilla para evitar XSS al renderizar texto que provenga de la IA
                    const escapeHTML = str => str.replace(/[&<>'"]/g, tag => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'}[tag] || tag));

                    const nomDoc = escapeHTML(inputNombreDocente.value || 'Docente de Grupo');
                    const nomEsc = escapeHTML(inputNombreEscuela.value || 'Institución Educativa');

                    let htmlRespuesta = `<div class="plan-resultado">`;
                    
                    htmlRespuesta += `
                    <div class="encabezado-oficial">
                        <h2>${nomEsc}</h2>
                        <p><strong>Docente:</strong> ${nomDoc} &nbsp;|&nbsp; <strong>Grado:</strong> ${escapeHTML(grado)}</p>
                    </div>`;
                    htmlRespuesta += `<h3>${escapeHTML(plan.datos_principales.proyecto || 'Proyecto Integrador')}</h3>`;
                    htmlRespuesta += `<p><strong>Metodología:</strong> ${escapeHTML(plan.datos_principales.metodologia)}</p>`;
                    htmlRespuesta += `<p><strong>PDA:</strong> ${escapeHTML(plan.datos_principales.pda)}</p>`;
                    
                    htmlRespuesta += `<h4><i class="fas fa-box-open"></i> Materiales Necesarios</h4><ul>`;
                    if(plan.lista_materiales) plan.lista_materiales.forEach(mat => htmlRespuesta += `<li>${escapeHTML(mat)}</li>`);
                    htmlRespuesta += `</ul>`;
                    
                    htmlRespuesta += `<h4><i class="fas fa-chalkboard-teacher"></i> Planeación Didáctica</h4>`;
                    if (typeof marked !== 'undefined') {
                        htmlRespuesta += `<div class="markdown-content">${marked.parse(plan.planeacion_completa)}</div>`;
                    } else {
                        htmlRespuesta += `<div style="white-space: pre-wrap;">${escapeHTML(plan.planeacion_completa)}</div>`;
                    }
                    
                    htmlRespuesta += `<h4><i class="fas fa-lightbulb"></i> Sugerencias Didácticas</h4><ul>`;
                    if(plan.sugerencias_didacticas) plan.sugerencias_didacticas.forEach(sug => htmlRespuesta += `<li>${escapeHTML(sug)}</li>`);
                    htmlRespuesta += `</ul>`;
                    
                    htmlRespuesta += `
                    <div class="html2pdf__page-break"></div>
                    <div class="aviso" style="margin-top: 20px;"><strong>Aviso:</strong> ${escapeHTML(plan.aviso)}</div>
                    </div>`;

                    resultadoDiv.innerHTML = htmlRespuesta;
                    contenedorBotones.style.display = 'flex';
                }

            } catch (error) {
                console.error('Error FETCH:', error);
                resultadoDiv.innerHTML = `<div class="aviso" style="border-color: #ef4444; color: #b91c1c;">
                    <h3><i class="fas fa-exclamation-triangle"></i> Ocurrió un error</h3>
                    <p>${error.message}<br><br>No te preocupes, no se descontó ningún crédito si la IA falló. Inténtalo de nuevo.</p>
                </div>`;
            } finally {
                formSection.style.display = 'block'; 
                if (usosRestantes > 0) {
                    btnGenerar.disabled = false;
                    btnGenerar.innerHTML = '<i class="fas fa-magic"></i> Generar Planeación Intermedia';
                } else {
                    btnGenerar.disabled = true;
                    btnGenerar.innerHTML = '<i class="fas fa-ban"></i> Usos Agotados';
                }
            }
        }

        btnDescargarPDF.addEventListener('click', () => {
            const elemento = document.getElementById('resultadoPlaneacion');
            const opciones = {
                margin:       [15, 15, 15, 15], 
                filename:     'Planeacion_Intermedia.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, backgroundColor: '#ffffff', scrollY: 0, windowHeight: document.getElementById('resultadoPlaneacion').scrollHeight + 200 },
                jsPDF:        { unit: 'mm', format: 'letter', orientation: 'portrait' },
                pagebreak:    { mode: ['css', 'legacy'] } 
            };

            const textoOriginal = btnDescargarPDF.innerHTML;
            btnDescargarPDF.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando PDF...';
            btnDescargarPDF.disabled = true;

            html2pdf().set(opciones).from(elemento).save().then(() => {
                btnDescargarPDF.innerHTML = textoOriginal;
                btnDescargarPDF.disabled = false;
            });
        });
        
        btnCopiar.addEventListener('click', () => {
            const texto = document.getElementById('resultadoPlaneacion').innerText;
            navigator.clipboard.writeText(texto).then(() => {
                const originalText = btnCopiar.innerHTML;
                btnCopiar.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
                setTimeout(() => btnCopiar.innerHTML = originalText, 2000);
            });
        });
    </script>
</body>
</html>