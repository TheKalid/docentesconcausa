<?php
// ===== BLOQUE PHP INICIAL CON CONTADOR, SEGURIDAD Y CONTEXTO =====
session_start();

// [SEGURIDAD] CABECERAS HTTP
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains"); 
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0"); 
header("Pragma: no-cache");

// Política de Seguridad de Contenido (CSP)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; connect-src 'self' https://api.openai.com;");

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
}

// VERIFICACIÓN DE PLAN 2
$nivel_requerido = 2; 
$plan_activo = $_SESSION['plan_activo'] ?? 0;
if ($plan_activo < $nivel_requerido) {
    header('Location: catalogo_de_pagos.php');
    exit();
}

include 'conexion.php';
$usuario_id = $_SESSION['usuario_id'];

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
    <title>Planeación Telesecundarias - Docentes con Causa</title>
    
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
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
        #btnGenerar, .btn-agregar { margin-top: 25px; background-color: var(--color-primario); color: white; border: none; font-weight: 700; cursor: pointer; transition: 0.3s; font-size: 1.1rem; }
        .btn-agregar { background-color: #0284c7; font-size: 1rem; margin-top: 15px; width: auto; padding: 10px 20px; display: inline-block;}
        #btnGenerar:hover { background-color: #152c69; transform: translateY(-2px); }
        .btn-agregar:hover { background-color: #0369a1; }
        #btnGenerar:disabled { background-color: #94a3b8; cursor: not-allowed; transform: none; }
        .bloque-asignatura { background-color: #f1f5f9; padding: 15px; border-radius: 8px; margin-top: 15px; position: relative; border: 1px solid #cbd5e1; }
        .btn-eliminar { position: absolute; top: 10px; right: 10px; background: #ef4444; color: white; border: none; border-radius: 4px; padding: 5px 10px; cursor: pointer; font-size: 0.8rem; width: auto;}
        .btn-eliminar:hover { background: #dc2626; }
        #btnCopiar, #btnDescargarPDF { color: white; border: none; font-weight: 600; cursor: pointer; padding: 12px; border-radius: 8px; font-size: 1rem; transition: 0.3s; }
        #btnCopiar { background-color: var(--color-exito); } #btnCopiar:hover { background-color: #15803d; }
        #btnDescargarPDF { background-color: #dc2626; margin-top: 10px; } #btnDescargarPDF:hover { background-color: #b91c1c; }
        .contenedor-botones { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }
        .aviso { background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; border: 1px solid #ffeeba; margin-top: 20px; }
        .alerta-campos { background-color: #fee2e2; color: #991b1b; padding: 10px; border-radius: 8px; margin-top: 10px; display: none; font-size: 0.9rem;}
        
        .markdown-content h1, .markdown-content h2, .markdown-content h3 { color: var(--color-primario); border-bottom: 2px solid var(--color-borde); padding-bottom: 5px; margin-top: 20px; }
        .markdown-content ul { padding-left: 20px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📺 Planeación Telesecundarias</h1>
            <p>Usos restantes en Plan 2: <strong id="contador-usos"><?php echo htmlspecialchars($usos_actuales, ENT_QUOTES, 'UTF-8'); ?></strong></p>
        </header>
        
        <section class="form-section">
            <form id="planeacionForm">
                <label for="grado">1. Grado (Telesecundaria):</label>
                <select id="grado" onchange="iniciarTelesecundaria()" required>
                    <option value="">-- Selecciona un grado --</option>
                    <option value="1º de Secundaria">1º de Telesecundaria</option>
                    <option value="2º de Secundaria">2º de Telesecundaria</option>
                    <option value="3º de Secundaria">3º de Telesecundaria</option>
                </select>

                <div id="alertaCampos" class="alerta-campos">
                    <i class="fas fa-info-circle"></i> Límite de estructura: Puedes agregar hasta 3 asignaturas por Campo Formativo (Máx. 2 campos, Total 6 asignaturas).
                </div>

                <div id="contenedorAsignaturas"></div>
                
                <button type="button" id="btnAgregarAsignatura" class="btn-agregar" style="display:none;" onclick="agregarBloqueAsignatura()">
                    <i class="fas fa-plus"></i> Agregar otra Asignatura
                </button>

                <hr style="margin: 30px 0; border: 0; border-top: 1px solid var(--color-borde);">

                <label for="ejeArticulador">Ejes Articuladores (Ctrl+Clic para varios):</label>
                <select id="ejeArticulador" multiple required>
                    <option value="Inclusión">Inclusión</option>
                    <option value="Interculturalidad crítica">Interculturalidad crítica</option>
                    <option value="Pensamiento crítico">Pensamiento crítico</option>
                    <option value="Igualdad de género">Igualdad de género</option>
                    <option value="Vida saludable">Vida saludable</option>
                    <option value="Apropiación de las culturas">Apropiación de las culturas a través de la lectura y la escritura</option>
                    <option value="Artes y experiencias estéticas">Artes y experiencias estéticas</option>
                </select>

                <label for="metodologia">Metodología NEM Sugerida:</label>
                <select id="metodologia" required>
                    <option value="">-- Selecciona una metodología --</option>
                    <option value="Aprendizaje Basado en Proyectos Comunitarios">Aprendizaje Basado en Proyectos Comunitarios</option>
                    <option value="Aprendizaje Basado en Indagación (STEAM)">Aprendizaje Basado en Indagación (STEAM)</option>
                    <option value="Aprendizaje Basado en Problemas (ABP)">Aprendizaje Basado en Problemas (ABP)</option>
                    <option value="Aprendizaje Servicio (AS)">Aprendizaje Servicio (AS)</option>
                </select>
        
                <label for="contexto">Contexto del Grupo o Diagnóstico (Opcional):</label>
                <textarea id="contexto" placeholder="Ej. Aula con pocos recursos tecnológicos..."><?php echo htmlspecialchars($contexto_guardado, ENT_QUOTES, 'UTF-8'); ?></textarea>
                
                <label for="tiempo">Temporalidad del Proyecto:</label>
                <select id="tiempo" required>
                    <option value="">-- Selecciona el período --</option>
                    <option value="1 semana (5 sesiones)">1 Semana (5 sesiones) - Planeación Semanal</option>
                    <option value="1 quincena (10 sesiones)">1 Quincena (10 sesiones) - Planeación Quincenal</option>
                </select>

                <button type="button" id="btnGenerar"><i class="fas fa-magic"></i> Generar Proyecto Articulado</button>
            </form>
        </section>

        <section class="resultado-section" id="seccionResultadoContenedor" style="display:none;">
            <h2>📝 Proyecto Integrador Diseñado</h2>
            <div id="resultadoPlaneacion"></div>
            <div id="contenedorBotones" class="contenedor-botones" style="display:none;">
                <button type="button" id="btnCopiar"><i class="fas fa-copy"></i> Copiar Planeación</button>
                <button type="button" id="btnDescargarPDF"><i class="fas fa-file-pdf"></i> Descargar como PDF</button>
            </div>
        </section>
    </div>

    <template id="templateBloqueAsignatura">
        <div class="bloque-asignatura" data-id="{ID}">
            <button type="button" class="btn-eliminar" onclick="eliminarBloque({ID})" title="Quitar Asignatura"><i class="fas fa-times"></i></button>
            <label>Campo Formativo:</label>
            <select class="sel-campo" onchange="cargarAsignaturasDeCampo(this, {ID})" required>
                <option value="">-- Selecciona Campo Formativo --</option>
            </select>
            <label>Asignatura:</label>
            <select class="sel-asignatura" onchange="cargarContenidos(this, {ID})" required disabled>
                <option value="">-- Selecciona Asignatura --</option>
            </select>
            <label>Contenido:</label>
            <select class="sel-contenido" onchange="cargarPDAs(this, {ID})" required disabled>
                <option value="">-- Selecciona Contenido --</option>
            </select>
            <label>PDA:</label>
            <select class="sel-pda" required disabled>
                <option value="">-- Selecciona PDA --</option>
            </select>
        </div>
    </template>

    <script>
        let usosRestantes = parseInt(<?php echo (int)$usos_actuales; ?>, 10);
        let datosGradoActual = {};
        const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";
        let contadorBloques = 0;
        
        // --- NUEVOS LÍMITES ---
        const maxCamposFormativos = 2;
        const maxAsignaturasPorCampo = 3;
        const maxBloquesTotal = maxCamposFormativos * maxAsignaturasPorCampo; // Total 6

        const btnGenerar = document.getElementById('btnGenerar');
        const formSection = document.querySelector('.form-section');
        const seccionResultadoContenedor = document.getElementById('seccionResultadoContenedor');
        const resultadoDiv = document.getElementById('resultadoPlaneacion');

        async function iniciarTelesecundaria() {
            const gradoSeleccionado = document.getElementById("grado").value;
            document.getElementById('contenedorAsignaturas').innerHTML = '';
            document.getElementById('btnAgregarAsignatura').style.display = 'none';
            contadorBloques = 0;
            
            if (!gradoSeleccionado) return;
            const nombreArchivo = gradoSeleccionado.toLowerCase().replace(/º de /g, '_').replace(/ /g, '_') + '.json';

            try {
                const respuesta = await fetch(`datos_plan_basico/${nombreArchivo}`);
                if (!respuesta.ok) throw new Error('Archivo no encontrado');
                datosGradoActual = await respuesta.json();
                
                document.getElementById('btnAgregarAsignatura').style.display = 'inline-block';
                agregarBloqueAsignatura(); 
            } catch (error) {
                console.error('Error:', error);
                alert("Error al cargar los mapas curriculares.");
            }
        }

        function agregarBloqueAsignatura() {
            const bloquesActuales = document.querySelectorAll('.bloque-asignatura').length;
            if (bloquesActuales >= maxBloquesTotal) {
                alert("Has alcanzado el límite máximo de 6 asignaturas en total.");
                return;
            }

            contadorBloques++;
            const template = document.getElementById('templateBloqueAsignatura').innerHTML;
            const nuevoBloqueHTML = template.replace(/{ID}/g, contadorBloques);
            
            const div = document.createElement('div');
            div.innerHTML = nuevoBloqueHTML;
            document.getElementById('contenedorAsignaturas').appendChild(div.firstElementChild);
            
            const selCampo = document.querySelector(`.bloque-asignatura[data-id="${contadorBloques}"] .sel-campo`);
            Object.keys(datosGradoActual).forEach(campo => {
                selCampo.add(new Option(campo, campo));
            });
            
            validarLimitesEstructura();
        }

        function eliminarBloque(id) {
            const bloques = document.querySelectorAll('.bloque-asignatura');
            if(bloques.length <= 1) {
                alert("El proyecto integrador requiere al menos 1 asignatura.");
                return;
            }
            const bloque = document.querySelector(`.bloque-asignatura[data-id="${id}"]`);
            if (bloque) {
                bloque.remove();
                validarLimitesEstructura();
            }
        }

        function cargarAsignaturasDeCampo(selectContext, id) {
            const campoSeleccionado = selectContext.value;
            const bloque = document.querySelector(`.bloque-asignatura[data-id="${id}"]`);
            const selAsignatura = bloque.querySelector('.sel-asignatura');
            const selContenido = bloque.querySelector('.sel-contenido');
            const selPda = bloque.querySelector('.sel-pda');
            
            selAsignatura.innerHTML = '<option value="">-- Asignatura --</option>';
            selContenido.innerHTML = '<option value="">-- Contenido --</option>';
            selPda.innerHTML = '<option value="">-- PDA --</option>';
            
            selAsignatura.disabled = true;
            selContenido.disabled = true;
            selPda.disabled = true;

            if (campoSeleccionado && datosGradoActual[campoSeleccionado]) {
                Object.keys(datosGradoActual[campoSeleccionado]).forEach(asig => {
                    selAsignatura.add(new Option(asig, asig));
                });
                selAsignatura.disabled = false;
            }
            validarLimitesEstructura();
        }

        function cargarContenidos(selectContext, id) {
            const bloque = document.querySelector(`.bloque-asignatura[data-id="${id}"]`);
            const campo = bloque.querySelector('.sel-campo').value;
            const asignatura = selectContext.value;
            const selContenido = bloque.querySelector('.sel-contenido');
            const selPda = bloque.querySelector('.sel-pda');

            selContenido.innerHTML = '<option value="">-- Contenido --</option>';
            selPda.innerHTML = '<option value="">-- PDA --</option>';
            selContenido.disabled = true;
            selPda.disabled = true;

            if (campo && asignatura && datosGradoActual[campo][asignatura]) {
                Object.keys(datosGradoActual[campo][asignatura]).forEach(cont => {
                    let opt = new Option(cont.length > 80 ? cont.substring(0, 77) + '...' : cont, cont);
                    selContenido.add(opt);
                });
                selContenido.disabled = false;
            }
        }

        function cargarPDAs(selectContext, id) {
            const bloque = document.querySelector(`.bloque-asignatura[data-id="${id}"]`);
            const campo = bloque.querySelector('.sel-campo').value;
            const asignatura = bloque.querySelector('.sel-asignatura').value;
            const contenido = selectContext.value;
            const selPda = bloque.querySelector('.sel-pda');

            selPda.innerHTML = '<option value="">-- PDA --</option>';
            selPda.disabled = true;

            if (campo && asignatura && contenido && datosGradoActual[campo][asignatura][contenido]) {
                const pdas = datosGradoActual[campo][asignatura][contenido];
                pdas.forEach(pda => {
                    let opt = new Option(pda.length > 90 ? pda.substring(0, 87) + '...' : pda, pda);
                    selPda.add(opt);
                });
                selPda.disabled = false;
            }
        }

        function validarLimitesEstructura() {
            const bloques = document.querySelectorAll('.bloque-asignatura');
            const btnAgregar = document.getElementById('btnAgregarAsignatura');
            const selectsCampos = document.querySelectorAll('.sel-campo');
            const alerta = document.getElementById('alertaCampos');
            
            btnAgregar.style.display = bloques.length >= maxBloquesTotal ? 'none' : 'inline-block';

            let conteoCampos = {};
            selectsCampos.forEach(sel => {
                if (sel.value) {
                    conteoCampos[sel.value] = (conteoCampos[sel.value] || 0) + 1;
                }
            });

            const camposSeleccionados = Object.keys(conteoCampos);
            let mostrarAlerta = false;

            selectsCampos.forEach(sel => {
                Array.from(sel.options).forEach(opt => {
                    if (opt.value === "") return;

                    if (camposSeleccionados.length >= maxCamposFormativos && !camposSeleccionados.includes(opt.value)) {
                        opt.disabled = true;
                        mostrarAlerta = true;
                    } 
                    else if (conteoCampos[opt.value] >= maxAsignaturasPorCampo && sel.value !== opt.value) {
                        opt.disabled = true;
                        mostrarAlerta = true;
                    } 
                    else {
                        opt.disabled = false;
                    }
                });
            });

            alerta.style.display = mostrarAlerta ? 'block' : 'none';
        }

        btnGenerar.addEventListener('click', async () => {
            const grado = document.getElementById('grado').value;
            const tiempo = document.getElementById('tiempo').value;
            const metodologia = document.getElementById('metodologia').value;
            const contexto = document.getElementById('contexto').value;
            const selectEjes = document.getElementById('ejeArticulador');
            const ejesSeleccionados = Array.from(selectEjes.selectedOptions).map(opt => opt.value).join(', ');

            const bloques = document.querySelectorAll('.bloque-asignatura');
            let arrayAsignaturas = [];
            let validacionFallida = false;

            bloques.forEach(bloque => {
                const campo = bloque.querySelector('.sel-campo').value;
                const asignatura = bloque.querySelector('.sel-asignatura').value;
                const contenido = bloque.querySelector('.sel-contenido').value;
                const pda = bloque.querySelector('.sel-pda').value;
                
                if(!campo || !asignatura || !contenido || !pda) validacionFallida = true;
                
                arrayAsignaturas.push(`- [Campo: ${campo}] Asignatura: ${asignatura} | Contenido: ${contenido} | PDA: ${pda}`);
            });

            if (validacionFallida || arrayAsignaturas.length === 0 || !grado || !tiempo || !metodologia) {
                alert('Por favor, completa todos los campos requeridos en todas las asignaturas.');
                return;
            }

            formSection.style.display = 'none';
            seccionResultadoContenedor.style.display = 'block';
            document.getElementById('contenedorBotones').style.display = 'none'; 
            
            resultadoDiv.innerHTML = '<p style="text-align: center; font-size: 1.2rem; color: #1e3a8a;">🧠 Articulando disciplinas (con 4 actividades por sesión)...<br><br><span style="font-size:0.9rem; color:#64748b;">Por favor, espera.</span></p>';
            btnGenerar.disabled = true;
            
            let promptText = `Grado: ${grado} (Telesecundaria)\n`;
            promptText += `Duración: ${tiempo}\n`;
            promptText += `Metodología: ${metodologia}\n`;
            promptText += `Ejes Articuladores: ${ejesSeleccionados}\n\n`;
            promptText += `MAPEO CURRICULAR ARTICULADO:\n${arrayAsignaturas.join('\n')}\n\n`;
            if (contexto) {
                promptText += `CONTEXTO INTERNO Y DIAGNÓSTICO:\n${contexto}\n`;
            }

            try {
                const response = await fetch('procesar_telesecundarias.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({ prompt: promptText })
                });

                const data = await response.json();

                if (!response.ok || data.status === 'error') {
                    throw new Error(data.error || 'Error del servidor.');
                }

                if (data.status === 'completo') {
                    usosRestantes = data.usos_restantes;
                    document.getElementById('contador-usos').innerText = usosRestantes;
                    const plan = data.plan;
                    
                    const escapeHTML = str => str.replace(/[&<>'"]/g, tag => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'}[tag] || tag));

                    let htmlRespuesta = `<div class="plan-resultado">`;
                    htmlRespuesta += `<h3>${escapeHTML(plan.datos_principales.proyecto || 'Proyecto Integrador')}</h3>`;
                    htmlRespuesta += `<p><strong>Articulación:</strong> ${escapeHTML(plan.datos_principales.justificacion_articulacion)}</p>`;
                    
                    htmlRespuesta += `<h4><i class="fas fa-box-open"></i> Recursos y Materiales</h4><ul>`;
                    if(plan.lista_materiales) plan.lista_materiales.forEach(mat => htmlRespuesta += `<li>${escapeHTML(mat)}</li>`);
                    htmlRespuesta += `</ul>`;
                    
                    htmlRespuesta += `<h4><i class="fas fa-chalkboard-teacher"></i> Desarrollo Didáctico</h4>`;
                    if (typeof marked !== 'undefined') {
                        htmlRespuesta += `<div class="markdown-content">${marked.parse(plan.planeacion_completa)}</div>`;
                    } else {
                        htmlRespuesta += `<div style="white-space: pre-wrap;">${escapeHTML(plan.planeacion_completa)}</div>`;
                    }
                    
                    htmlRespuesta += `<div class="html2pdf__page-break"></div><div class="aviso" style="margin-top: 20px;"><strong>Aviso Pedagógico:</strong> ${escapeHTML(plan.aviso)}</div></div>`;

                    resultadoDiv.innerHTML = htmlRespuesta;
                    document.getElementById('contenedorBotones').style.display = 'flex';
                }
            } catch (error) {
                resultadoDiv.innerHTML = `<div class="aviso" style="border-color: #ef4444; color: #b91c1c;"><h3><i class="fas fa-exclamation-triangle"></i> Error</h3><p>${error.message}</p></div>`;
            } finally {
                formSection.style.display = 'block'; 
                if (usosRestantes > 0) btnGenerar.disabled = false;
            }
        });
        
        document.getElementById('btnCopiar').addEventListener('click', () => {
            navigator.clipboard.writeText(document.getElementById('resultadoPlaneacion').innerText);
            alert("Planeación copiada.");
        });

        // ==========================================================
        // FUNCIÓN REPARADA PARA DESCARGAR PDF (EVITA PÁGINAS EN BLANCO)
        // ==========================================================
        function descargarPlaneacionPDF() {
            const elemento = document.getElementById('resultadoPlaneacion'); 
            const btnPDF = document.getElementById('btnDescargarPDF'); 
            
            const textoOriginal = btnPDF.innerHTML;
            btnPDF.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando PDF...';
            btnPDF.disabled = true;

            const opciones = {
                margin:       [15, 15, 15, 15], 
                filename:     'Planeacion_Telesecundaria.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { 
                    scale: 2, 
                    useCORS: true,
                    backgroundColor: '#ffffff',
                    scrollY: 0,
                    windowHeight: elemento.scrollHeight + 200 // Asegura que no corte textos largos
                },
                jsPDF: { unit: 'mm', format: 'letter', orientation: 'portrait' },
                pagebreak: { mode: ['css', 'legacy'] }
            };

            html2pdf().set(opciones).from(elemento).save()
                .then(() => {
                    btnPDF.innerHTML = textoOriginal;
                    btnPDF.disabled = false;
                })
                .catch(error => {
                    console.error("Error de html2pdf:", error);
                    alert("Hubo un problema al crear el PDF. Intenta copiar el texto.");
                    btnPDF.innerHTML = textoOriginal;
                    btnPDF.disabled = false;
                });
        }

        // Enlazamos el botón de descargar PDF a su función
        document.getElementById('btnDescargarPDF').addEventListener('click', descargarPlaneacionPDF);

    </script>
</body>
</html>