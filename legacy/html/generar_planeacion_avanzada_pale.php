<?php
// ===== INICIO DEL BLOQUE DE SEGURIDAD Y DATOS =====
session_start();

// [SEGURIDAD] Cabeceras HTTP Anti-Ataques y Anti-Caché
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// Verificación de sesión
if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit(); }

// [SEGURIDAD] Generación de Token CSRF (Anti-Bots)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$nivel_requerido = 2;
$plan_activo = $_SESSION['plan_activo'] ?? 0;
if ($plan_activo < $nivel_requerido) { header('Location: catalogo_de_pagos.php'); exit(); }

include 'conexion.php';
$usuario_id = $_SESSION['usuario_id'];

// Leemos de la BD para tener el dato más fresco
$stmt_usos = $conexion->prepare("SELECT usos_plan_intermedio FROM usuarios WHERE id = ?");
$stmt_usos->bind_param("i", $usuario_id);
$stmt_usos->execute();
$usos_result = $stmt_usos->get_result()->fetch_assoc();
$usos_actuales = $usos_result['usos_plan_intermedio'] ?? 0;
$_SESSION['usos_plan_intermedio'] = $usos_actuales; 
$puede_generar = $usos_actuales > 0;

// [RENDIMIENTO] Cerramos BD y liberamos sesión inmediatamente
$stmt_usos->close();
$conexion->close();
session_write_close();
// ===== FIN DEL BLOQUE =====
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planeación Diferenciada (Fase 3) - Docentes con Causa</title>
    <style>
        :root { --color-primario: #1e3a8a; --color-acento: #f39c12; --color-exito: #16a34a; --color-fondo: #f8f9fa; --color-texto: #334155; --color-tarjeta: #ffffff; --color-borde: #e2e8f0; --fuente-principal: 'Poppins', sans-serif; }
        body { font-family: var(--fuente-principal); background-color: var(--color-fondo); margin: 0; color: var(--color-texto); line-height: 1.6; }
        .container { max-width: 900px; margin: 0 auto; padding: 40px 20px; }
        header { text-align: center; margin-bottom: 20px; }
        header h1 { font-size: 2.5rem; color: var(--color-primario); margin: 0; }
        .contador-usos { background-color: #eef2ff; color: var(--color-primario); padding: 15px; border-radius: 8px; text-align: center; font-weight: 600; margin-bottom: 25px; border: 1px solid #c7d2fe; }
        .form-section, .resultado-section { background-color: var(--color-tarjeta); border-radius: 12px; padding: 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); margin-bottom: 40px; }
        .form-section h2 { font-size: 1.5rem; margin-top: 0; color: var(--color-primario); border-bottom: 2px solid var(--color-borde); padding-bottom: 10px; margin-bottom: 20px; }
        label { display: block; margin-top: 15px; margin-bottom: 8px; font-weight: 600; color: #475569; }
        select, input[type="number"] { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--color-borde); font-size: 1rem; font-family: var(--fuente-principal); background-color: #fdfdff; }
        select:disabled { background-color: #e9ecef; cursor: not-allowed; }
        select[multiple] { height: 120px; }
        small { font-size: 0.85rem; color: #64748b; display: block; margin-top: 5px; }
        #btnGenerar { display: block; width: 100%; margin-top: 30px; background-color: var(--color-primario); color: white; border: none; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: background-color 0.3s, transform 0.2s; padding: 15px; border-radius: 8px; }
        #btnGenerar:disabled { background-color: #9ca3af; cursor: not-allowed; transform: none; }
        #btnGenerar:not(:disabled):hover { background-color: #1c3178; transform: translateY(-2px); }
        .resultado-section h2 { margin-top: 0; }
        #btnCopiar { background-color: var(--color-exito); color: white; margin-top: 20px; border: none; font-weight: 600; cursor: pointer; transition: background-color 0.3s; padding: 10px 15px; border-radius: 8px; }
        .contenedor-botones { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }
        #btnDescargarPDF { background-color: #dc2626; color: white; border: none; font-weight: 600; cursor: pointer; transition: background-color 0.3s; padding: 10px 15px; border-radius: 8px; font-size: 1rem; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; }
        #btnDescargarPDF:hover { background-color: #b91c1c; }
        .encabezado-oficial { text-align: center; border-bottom: 2px solid var(--color-primario); padding-bottom: 15px; margin-bottom: 20px; }
        .encabezado-oficial h2 { margin: 0; color: var(--color-primario); text-transform: uppercase; font-size: 1.5rem; }
        .encabezado-oficial p { margin: 5px 0 0 0; font-size: 1.1rem; color: var(--color-texto); }
        .spinner { border: 4px solid rgba(0, 0, 0, 0.1); border-radius: 50%; border-top: 4px solid var(--color-acento); width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
        .spinner-small { display: inline-block; border: 2px solid rgba(255, 255, 255, 0.3); border-radius: 50%; border-top: 2px solid white; width: 16px; height: 16px; animation: spin 1s linear infinite; margin-right: 10px; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .error-message { color: #dc3545; font-weight: bold; background-color: #f8d7da; padding: 15px; border-radius: 8px; margin-top: 15px; }
        .markdown-content h1, .markdown-content h2, .markdown-content h3 { color: var(--color-primario); }
    </style>
</head>
<body>
    <div class="container">
        <header><h1>📶 Planeación Diferenciada por Niveles (PALE)</h1></header>
        
        <section class="form-section">
            <form id="planeacionForm" onsubmit="return false;">
                <div class="contador-usos">Usos restantes: <strong id="contador-display"><?php echo htmlspecialchars($usos_actuales); ?></strong></div>

                <h2>1. Datos Curriculares</h2>
                <div class="form-group">
                    <label for="grado">Grado Escolar (Fase 3):</label>
                    <select id="grado" name="grado" required>
                        <option value="" disabled selected>Selecciona un grado</option>
                        <option value="1_primaria">Primero de Primaria</option>
                        <option value="2_primaria">Segundo de Primaria</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="campoFormativo">Campo Formativo:</label>
                    <select id="campoFormativo" name="campoFormativo" required disabled><option value="">-- Selecciona grado --</option></select>
                </div>
                <div class="form-group">
                    <label for="contenido">Contenido:</label>
                    <select id="contenido" name="contenido" required disabled><option value="">-- Selecciona campo --</option></select>
                </div>
                <div class="form-group">
                    <label for="pda">Proceso de Desarrollo de Aprendizaje (PDA):</label>
                    <select id="pda" name="pda" required disabled><option value="">-- Selecciona contenido --</option></select>
                </div>
                <div class="form-group">
                    <label for="ejeArticulador">Ejes Articuladores (Ctrl+Clic para varios):</label>
                    <select id="ejeArticulador" name="ejesArticuladores[]" multiple required>
                        <option value="Inclusión">Inclusión</option><option value="Interculturalidad crítica">Interculturalidad crítica</option><option value="Pensamiento crítico">Pensamiento crítico</option><option value="Igualdad de género">Igualdad de género</option><option value="Vida saludable">Vida saludable</option><option value="Apropiación de las culturas a través de la lectura y la escritura">Apropiación de las culturas a través de la lectura y la escritura</option><option value="Artes y experiencias estéticas">Artes y experiencias estéticas</option>
                    </select>
                </div>
                
                <h2>2. Composición del Grupo (Lectoescritura)</h2>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="ninos_presilabico">Presilábicos:</label>
                        <input type="number" id="ninos_presilabico" value="0" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="ninos_silabico">Silábicos:</label>
                        <input type="number" id="ninos_silabico" value="0" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="ninos_silabico_alfabetico">Silábico-Alfabéticos:</label>
                        <input type="number" id="ninos_silabico_alfabetico" value="0" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="ninos_alfabetico">Alfabéticos:</label>
                        <input type="number" id="ninos_alfabetico" value="0" min="0" required>
                    </div>
                </div>

                <div style="background-color: #eef2ff; padding: 15px; border-radius: 8px; border: 1px solid #c7d2fe; margin-top: 25px; margin-bottom: 20px;">
                    <h4 style="margin-top: 0; color: var(--color-primario);">Datos para el documento (Se guardan automáticamente)</h4>
                    
                    <label for="nombreDocente" style="margin-top: 10px;">Nombre del Docente:</label>
                    <input type="text" id="nombreDocente" placeholder="Ej. Profr. Juan Pérez" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--color-borde); box-sizing: border-box;">
                    
                    <label for="nombreEscuela" style="margin-top: 10px;">Nombre de la Escuela:</label>
                    <input type="text" id="nombreEscuela" placeholder="Ej. Esc. Primaria Benito Juárez" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--color-borde); box-sizing: border-box;">
                </div>
                <button type="button" id="btnGenerar" <?php if (!$puede_generar) echo 'disabled'; ?>>
                    <?php echo $puede_generar ? '📄 Generar Planeación PALE' : '🚫 Usos Agotados'; ?>
                </button>
            </form>
        </section>
        
        <section class="resultado-section" id="seccionResultado" style="display:none;">
            <h2>📝 Tu Planeación PALE Generada</h2>
            <div id="resultadoPlaneacion"><p>Aquí aparecerá tu planeación...</p></div>
            
            <div id="contenedorBotones" class="contenedor-botones" style="display:none;">
                <button type="button" id="btnCopiar">📋 Copiar Planeación</button>
                <button type="button" id="btnDescargarPDF">📥 Descargar como PDF</button>
            </div>
        </section>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // [SEGURIDAD] Capturamos el Token CSRF
        const csrfToken = "<?php echo $csrf_token; ?>";

        const gradoSelect = document.getElementById('grado');
        const campoFormativoSelect = document.getElementById('campoFormativo');
        const contenidoSelect = document.getElementById('contenido');
        const pdaSelect = document.getElementById('pda');
        const ejesSelect = document.getElementById('ejeArticulador');
        const btnGenerar = document.getElementById('btnGenerar');
        const resultadoDiv = document.getElementById('resultadoPlaneacion');
        const seccionResultado = document.getElementById('seccionResultado');
        const btnCopiar = document.getElementById('btnCopiar');
        const contadorDisplay = document.getElementById('contador-display');
        
        let datosGradoActual = {};
        const contenedorBotones = document.getElementById('contenedorBotones');
        const btnDescargarPDF = document.getElementById('btnDescargarPDF');
        const inputNombreDocente = document.getElementById('nombreDocente');
        const inputNombreEscuela = document.getElementById('nombreEscuela');

        if (localStorage.getItem('docente_nombre')) inputNombreDocente.value = localStorage.getItem('docente_nombre');
        if (localStorage.getItem('docente_escuela')) inputNombreEscuela.value = localStorage.getItem('docente_escuela');

        function populateSelect(selectEl, options, defaultText) {
            selectEl.innerHTML = `<option value="">-- ${defaultText} --</option>`;
            options.forEach(opt => selectEl.add(new Option(opt, opt)));
        }
        function resetDependentSelects() {
            populateSelect(campoFormativoSelect, [], "Selecciona grado");
            campoFormativoSelect.disabled = true;
            populateSelect(contenidoSelect, [], "Selecciona campo formativo");
            contenidoSelect.disabled = true;
            populateSelect(pdaSelect, [], "Selecciona contenido");
            pdaSelect.disabled = true;
        }
        async function cargarDatosGrado() {
            resetDependentSelects(); 
            const grado = gradoSelect.value;
            if (!grado) return;
            campoFormativoSelect.innerHTML = '<option value="">Cargando...</option>';
            try {
                const response = await fetch(`datos_plan_pale/${grado}.json`); 
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                datosGradoActual = await response.json();
                populateSelect(campoFormativoSelect, Object.keys(datosGradoActual), "Selecciona un campo");
                campoFormativoSelect.disabled = false;
            } catch (error) {
                console.error("Error al cargar el archivo JSON:", error);
                campoFormativoSelect.innerHTML = '<option value="">Error al cargar datos</option>';
            }
        }

        gradoSelect.addEventListener('change', cargarDatosGrado);
        campoFormativoSelect.addEventListener('change', () => {
            const campo = campoFormativoSelect.value;
            populateSelect(contenidoSelect, Object.keys(datosGradoActual[campo] || {}), "Selecciona un contenido");
            contenidoSelect.disabled = !campo;
            populateSelect(pdaSelect, [], "Selecciona contenido");
            pdaSelect.disabled = true;
        });
        contenidoSelect.addEventListener('change', () => {
            const campo = campoFormativoSelect.value;
            const contenido = contenidoSelect.value;
            populateSelect(pdaSelect, datosGradoActual[campo]?.[contenido] || [], "Selecciona un PDA");
            pdaSelect.disabled = !contenido;
        });
        
        btnGenerar.addEventListener('click', async () => {
            const gradoTexto = gradoSelect.options[gradoSelect.selectedIndex]?.text;
            const ejesSeleccionados = [...ejesSelect.options].filter(o => o.selected).map(o => o.value);

            if (!gradoTexto || !campoFormativoSelect.value || !contenidoSelect.value || !pdaSelect.value || ejesSeleccionados.length === 0) {
                alert('Por favor, completa todos los campos curriculares obligatorios.');
                return;
            }

            const datosParaEnviar = {
                grado: gradoTexto,
                campoFormativo: campoFormativoSelect.value,
                contenido: contenidoSelect.value,
                pda: pdaSelect.value,
                ejesArticuladores: ejesSeleccionados,
                composicionGrupo: {
                    presilabico: parseInt(document.getElementById('ninos_presilabico').value, 10) || 0,
                    silabico: parseInt(document.getElementById('ninos_silabico').value, 10) || 0,
                    silabico_alfabetico: parseInt(document.getElementById('ninos_silabico_alfabetico').value, 10) || 0,
                    alfabetico: parseInt(document.getElementById('ninos_alfabetico').value, 10) || 0
                }
            };

            seccionResultado.style.display = 'block';
            resultadoDiv.innerHTML = `<div class="spinner"></div><p style="text-align:center; color:#1e3a8a;"><strong>Ensamblando metodologías PALE...</strong><br><span style="font-size:0.9rem; color:#64748b;">Este es un proceso avanzado. Toma de 20 a 40 segundos.</span></p>`;
            contenedorBotones.style.display = 'none'; 
            
            localStorage.setItem('docente_nombre', inputNombreDocente.value);
            localStorage.setItem('docente_escuela', inputNombreEscuela.value);
            btnGenerar.disabled = true;
            btnGenerar.innerHTML = `<span class="spinner-small"></span> Generando...`;

            try {
                // [SEGURIDAD] Inyectamos el Token CSRF en la cabecera
                const response = await fetch('procesar_planeacion_avanzada_pale.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(datosParaEnviar)
                });
                
                const textoCrudo = await response.text(); 
                let data;
                try {
                    data = JSON.parse(textoCrudo);
                } catch(parseError) {
                    throw new Error("<strong>Error del servidor:</strong><br><div style='background:#fff; color:#dc3545; padding:10px; border:1px solid #dc3545; margin-top:10px; font-family:monospace; font-size:12px;'>" + textoCrudo.replace(/</g, "&lt;").replace(/>/g, "&gt;") + "</div>");
                }

                if (!response.ok || data.status === 'error') { 
                    throw new Error(data.details || data.error || 'Error en el servidor al generar.'); 
                }

                if (data.status === 'completo') {
                    if (typeof data.usos_restantes !== 'undefined') {
                        contadorDisplay.innerText = data.usos_restantes;
                    }

                    const planData = data.plan;
                    const nomDoc = inputNombreDocente.value || 'Docente PALE';
                    const nomEsc = inputNombreEscuela.value || 'Institución Educativa';
                    
                    let htmlResultado = `
                    <div class="encabezado-oficial">
                        <h2>${nomEsc}</h2>
                        <p><strong>Docente:</strong> ${nomDoc} &nbsp;|&nbsp; <strong>Fase/Grado:</strong> ${gradoTexto}</p>
                    </div>`;

                    if (planData.planeacion_completa) {
                        htmlResultado += `<div class="markdown-content">${marked.parse(planData.planeacion_completa)}</div>`;
                    }
                    if (planData.lista_materiales && planData.lista_materiales.length > 0) {
                        htmlResultado += '<h3>📦 Materiales Necesarios</h3><ul>';
                        planData.lista_materiales.forEach(m => htmlResultado += `<li>${m}</li>`);
                        htmlResultado += '</ul>';
                    }
                    if (planData.sugerencias_didacticas && planData.sugerencias_didacticas.length > 0) {
                        htmlResultado += '<h3>💡 Sugerencias PALE</h3><ul>';
                        planData.sugerencias_didacticas.forEach(s => htmlResultado += `<li>${s}</li>`);
                        htmlResultado += '</ul>';
                    }

                    resultadoDiv.innerHTML = htmlResultado; 
                    contenedorBotones.style.display = 'flex';
                }

            } catch (error) {
                console.error(error);
                resultadoDiv.innerHTML = `<div class="error-message"><strong>Ocurrió un error:</strong><br>${error.message}<br><br><span style="color:#333; font-weight:normal;">No te preocupes, no se descontó tu crédito. Intenta de nuevo.</span></div>`;
            } finally {
                if (parseInt(contadorDisplay.innerText, 10) > 0) {
                    btnGenerar.disabled = false;
                    btnGenerar.innerHTML = '📄 Generar Planeación PALE';
                } else {
                    btnGenerar.disabled = true;
                    btnGenerar.innerHTML = '🚫 Usos Agotados';
                }
            }
        });

        btnDescargarPDF.addEventListener('click', () => {
            const elemento = document.getElementById('resultadoPlaneacion');
            const opciones = {
                margin:       [15, 15, 15, 15], 
                filename:     'Planeacion_PALE.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { 
                    scale: 2, 
                    useCORS: true, 
                    backgroundColor: '#ffffff', 
                    scrollY: 0,
                    windowHeight: elemento.scrollHeight + 200 
                },
                jsPDF:        { unit: 'mm', format: 'letter', orientation: 'portrait' },
                pagebreak:    { mode: ['css', 'legacy'] } 
            };

            const textoOriginal = btnDescargarPDF.innerHTML;
            btnDescargarPDF.innerHTML = '⏳ Generando PDF...';
            btnDescargarPDF.disabled = true;

            html2pdf().set(opciones).from(elemento).save().then(() => {
                btnDescargarPDF.innerHTML = textoOriginal;
                btnDescargarPDF.disabled = false;
            });
        });

        btnCopiar.addEventListener('click', () => {
            const texto = document.getElementById('resultadoPlaneacion').innerText;
            navigator.clipboard.writeText(texto).then(() => {
                const original = btnCopiar.innerHTML;
                btnCopiar.innerHTML = '¡Copiado!';
                setTimeout(() => btnCopiar.innerHTML = original, 2000);
            });
        });
    });
    </script>
</body>
</html>