<?php
// Inicia o reanuda la sesión del usuario. Obligatorio para leer $_SESSION.
session_start();

// ==========================================
// [SEGURIDAD] CABECERAS HTTP DE PROTECCIÓN
// ==========================================
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// ==========================================
// VERIFICACIÓN DE IDENTIDAD Y PERMISOS
// ==========================================
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Genera un token CSRF único (Firma digital anti-bots)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
}

require_once 'conexion.php';

// Validamos que tenga el plan correcto (Nivel 2 = Intermedio/Mentor)
$nivel_requerido = 2; 
$plan_activo = $_SESSION['plan_activo'] ?? 0;

if ($plan_activo < $nivel_requerido) {
    header('Location: catalogo_de_pagos.php'); // Asegúrate que la extensión correcta sea .php o .html según tu web
    exit(); 
}

// ==========================================
// LECTURA DE CRÉDITOS DISPONIBLES
// ==========================================
$usos_plan_intermedio = 0; 

if (isset($_SESSION['usuario_id'])) {
    $userId = $_SESSION['usuario_id'];
    $stmt = $conexion->prepare("SELECT usos_plan_intermedio FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute(); 
    $result = $stmt->get_result(); 
    
    if ($user = $result->fetch_assoc()) {
        $usos_plan_intermedio = (int)$user['usos_plan_intermedio'];
        $_SESSION['usos_plan_intermedio'] = $usos_plan_intermedio; 
    }
    $stmt->close(); 
}

// [CRÍTICO PARA RENDIMIENTO] Cerramos la DB y la sesión inmediatamente
if (isset($conexion)) { $conexion->close(); }
session_write_close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title>Planeación Transversal (Competencias) - Planeando con Causa</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Variables globales de color para mantener coherencia en el diseño */
        :root {
            --color-primario: #1e3a8a;
            --color-acento: #f39c12;
            --color-exito: #16a34a;
            --color-fondo: #f8f9fa;
            --color-texto: #334155;
            --color-tarjeta: #ffffff;
            --color-borde: #e2e8f0;
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--color-fondo); margin: 0; color: var(--color-texto); line-height: 1.6; }
        .container { max-width: 1000px; margin: 0 auto; padding: 40px 20px; }
        header { text-align: center; margin-bottom: 40px; }
        header h1 { font-size: 2.2rem; color: var(--color-primario); margin: 0; }
        header p { color: #64748b; font-size: 1.1rem; }
        .form-section, .resultado-section { background-color: var(--color-tarjeta); border-radius: 12px; padding: 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); margin-bottom: 40px; }
        .eje-container { background-color: #f1f5f9; padding: 20px; border-radius: 8px; border: 1px solid var(--color-borde); margin-bottom: 25px; }
        .eje-container h3 { color: var(--color-primario); margin-top: 0; border-bottom: 2px solid var(--color-borde); padding-bottom: 10px; }
        label { display: block; margin-top: 15px; margin-bottom: 5px; font-weight: 600; color: var(--color-primario); font-size: 0.95rem; }
        select, input, button { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--color-borde); font-size: 1rem; font-family: 'Poppins', sans-serif; background-color: #ffffff; box-sizing: border-box; }
        select:focus, input:focus { outline: none; border-color: var(--color-acento); box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.2); }
        #btnGenerar { margin-top: 30px; background-color: var(--color-primario); color: white; border: none; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s; padding: 15px; }
        #btnGenerar:hover { background-color: #1c3178; transform: translateY(-2px); }
        #btnGenerar:disabled { background-color: #6c757d; cursor: not-allowed; transform: none; }
        .btn-action { background-color: #f8f9fa; color: var(--color-texto); border: 1px solid var(--color-borde); padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; box-sizing: border-box; margin-top: 10px; }
        .btn-copy { background-color: var(--color-exito); color: white; border-color: var(--color-exito); }
        .btn-pdf { background-color: #dc2626; color: white; border-color: #dc2626; }
        .btn-copy:hover { background-color: #15803d; }
        .btn-pdf:hover { background-color: #b91c1c; }
        .contenedor-botones { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }
        .aviso { background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; border: 1px solid #ffeeba; margin-top: 20px; }
        .markdown-content h3 { color: var(--color-primario); border-bottom: 2px solid var(--color-acento); padding-bottom: 5px; margin-top: 25px; }
        .markdown-content h4 { color: #334155; margin-top: 20px; margin-bottom: 10px; }
        .markdown-content ul { padding-left: 20px; }
        .btn-return { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background-color: var(--color-primario); color: white; padding: 12px 25px; border-radius: 50px; text-decoration: none; font-weight: 600; margin-top: 20px; width: fit-content; margin-left: auto; margin-right: auto;}
        .spinner { border: 4px solid rgba(0, 0, 0, 0.1); border-radius: 50%; border-top: 4px solid var(--color-acento); width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🔗 Planeación Transversal</h1>
            <p>Fusiona dos Campos Formativos y PDA en una sola secuencia didáctica estructurada.</p>
            <p>Usos restantes: <strong id="contador-usos"><?php echo htmlspecialchars($usos_plan_intermedio, ENT_QUOTES, 'UTF-8'); ?></strong></p>
        </header>
        
        <section class="form-section">
            <form id="planeacionForm">
                <label for="grado" style="font-size: 1.1rem;">Selecciona el grado general de la clase:</label>
                <select id="grado" name="grado" onchange="actualizarOpciones()" required style="margin-bottom: 25px; border: 2px solid var(--color-primario);">
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

                <div class="eje-container">
                    <h3>🎯 Eje Principal (Obligatorio)</h3>
                    <div id="contenedorAsignatura1" style="display:none;">
                        <label for="asignatura1">Asignatura 1:</label>
                        <select id="asignatura1" onchange="actualizarCamposFormativos(1)"></select>
                    </div>
                    <label for="campoFormativo1">Campo Formativo 1:</label>
                    <select id="campoFormativo1" onchange="actualizarContenidos(1)" required></select>
                    
                    <label for="contenido1">Contenido 1:</label>
                    <select id="contenido1" onchange="actualizarPDAs(1)" required></select>
                    
                    <label for="pda1">PDA Principal:</label>
                    <select id="pda1" required></select>
                </div>

                <div class="eje-container" style="background-color: #f8fafc;">
                    <h3>🔗 Eje Transversal (Opcional)</h3>
                    <p style="font-size: 0.85rem; color: #64748b; margin-top: 0;">Agrega un segundo campo/PDA para fusionarlo en la secuencia.</p>
                    
                    <div id="contenedorAsignatura2" style="display:none;">
                        <label for="asignatura2">Asignatura 2:</label>
                        <select id="asignatura2" onchange="actualizarCamposFormativos(2)"></select>
                    </div>
                    <label for="campoFormativo2">Campo Formativo 2:</label>
                    <select id="campoFormativo2" onchange="actualizarContenidos(2)"></select>
                    
                    <label for="contenido2">Contenido 2:</label>
                    <select id="contenido2" onchange="actualizarPDAs(2)"></select>
                    
                    <label for="pda2">PDA Transversal:</label>
                    <select id="pda2"></select>
                </div>

                <label for="tiempo">Duración de la secuencia integrada:</label>
                <select id="tiempo" required>
                    <option value="">-- Selecciona el tiempo --</option>
                    <option value="1 clase (Secuencia corta)">1 clase (Secuencia corta)</option>
                    <option value="3 clases (Secuencia media)">3 clases (Secuencia media)</option>
                    <option value="5 clases (Secuencia semanal)">5 clases (Secuencia semanal)</option>
                </select>

                <button type="button" id="btnGenerar"><i class="fas fa-magic"></i> Fusionar y Construir Secuencia</button>
            </form>
        </section>

        <section class="resultado-section" style="display:none;" id="seccionResultadoContenedor">
            <h2>📝 Tu Secuencia Transversal Generada</h2>
            <div id="resultadoPlaneacion"></div>
        </section>

        <div style="text-align: center;">
            <a href="index.php" class="btn-return"><span>⬅️</span><span>Regresar a Inicio</span></a>
        </div>
    </div>

    <script>
        // ==========================================
        // CONFIGURACIÓN DE JAVASCRIPT
        // ==========================================
        let usosRestantes = parseInt(<?php echo (int)$usos_plan_intermedio; ?>, 10);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const btnGenerar = document.getElementById('btnGenerar');
        const contadorUsosSpan = document.getElementById('contador-usos');
        const resultadoDiv = document.getElementById('resultadoPlaneacion');
        const seccionResultadoContenedor = document.getElementById('seccionResultadoContenedor');
        const formSection = document.querySelector('.form-section');

        document.addEventListener('DOMContentLoaded', () => {
            if (usosRestantes <= 0) {
                btnGenerar.disabled = true;
                btnGenerar.innerHTML = '🚫 Créditos Agotados';
            }
        });
        
        let datosGradoActual = {};
        
        // ==========================================
        // LÓGICA DE SELECTS DINÁMICOS (CASCADA)
        // ==========================================
        async function actualizarOpciones() {
            const gradoSeleccionado = document.getElementById("grado").value;
            const isSecundaria = gradoSeleccionado.includes('Secundaria');

            document.getElementById('contenedorAsignatura1').style.display = isSecundaria ? 'block' : 'none';
            document.getElementById('contenedorAsignatura2').style.display = isSecundaria ? 'block' : 'none';

            [1, 2].forEach(num => {
                document.getElementById(`campoFormativo${num}`).innerHTML = '<option value="">-- Selecciona --</option>';
                document.getElementById(`contenido${num}`).innerHTML = '<option value="">-- Selecciona --</option>';
                document.getElementById(`pda${num}`).innerHTML = '<option value="">-- Selecciona --</option>';
                if(isSecundaria) document.getElementById(`asignatura${num}`).innerHTML = '<option value="">-- Selecciona --</option>';
            });
            
            if (!gradoSeleccionado) return;

            const nombreArchivo = gradoSeleccionado.toLowerCase().replace(/º de /g, '_').replace(/ /g, '_') + '.json';

            try {
                const respuesta = await fetch(`datos_plan_basico/${nombreArchivo}`);
                if (!respuesta.ok) throw new Error('Archivo no encontrado');
                datosGradoActual = await respuesta.json(); 

                if (isSecundaria) {
                    const asignaturasUnicas = new Set();
                    Object.keys(datosGradoActual).forEach(campo => {
                        Object.keys(datosGradoActual[campo]).forEach(asignatura => asignaturasUnicas.add(asignatura));
                    });
                    [1, 2].forEach(num => {
                        const asigSelect = document.getElementById(`asignatura${num}`);
                        asignaturasUnicas.forEach(asignatura => asigSelect.appendChild(new Option(asignatura, asignatura)));
                    });
                } else {
                    actualizarCamposFormativos(1);
                    actualizarCamposFormativos(2);
                }
            } catch (error) {
                console.error("Error al cargar JSON de la base de datos local.");
            }
        }

        function actualizarCamposFormativos(num) {
            const grado = document.getElementById('grado').value;
            const asignatura = document.getElementById(`asignatura${num}`) ? document.getElementById(`asignatura${num}`).value : '';
            const select = document.getElementById(`campoFormativo${num}`);
            
            select.innerHTML = '<option value="">-- Selecciona Campo --</option>';
            
            if (!grado.includes('Secundaria')) {
                 Object.keys(datosGradoActual).forEach(campo => select.appendChild(new Option(campo, campo)));
            } else {
                if(!asignatura) return;
                Object.keys(datosGradoActual).forEach(campo => {
                    if(datosGradoActual[campo][asignatura]) select.appendChild(new Option(campo, campo));
                });
            }
        }

        function actualizarContenidos(num) {
            const campo = document.getElementById(`campoFormativo${num}`).value;
            const asignatura = document.getElementById(`asignatura${num}`) ? document.getElementById(`asignatura${num}`).value : '';
            const grado = document.getElementById('grado').value;
            const select = document.getElementById(`contenido${num}`);

            select.innerHTML = '<option value="">-- Selecciona Contenido --</option>';
            document.getElementById(`pda${num}`).innerHTML = '<option value="">-- Selecciona --</option>';
            
            if(!campo) return;

            let datosContenido = grado.includes('Secundaria') ? datosGradoActual[campo][asignatura] : datosGradoActual[campo];
            if(datosContenido) {
                Object.keys(datosContenido).forEach(cont => select.appendChild(new Option(cont, cont)));
            }
        }

        function actualizarPDAs(num) {
            const campo = document.getElementById(`campoFormativo${num}`).value;
            const asignatura = document.getElementById(`asignatura${num}`) ? document.getElementById(`asignatura${num}`).value : '';
            const grado = document.getElementById('grado').value;
            const contenido = document.getElementById(`contenido${num}`).value;
            const select = document.getElementById(`pda${num}`);

            select.innerHTML = '<option value="">-- Selecciona PDA --</option>';
            if(!contenido) return;

            let pdas = grado.includes('Secundaria') ? datosGradoActual[campo][asignatura][contenido] : datosGradoActual[campo][contenido];
            if(pdas) {
                pdas.forEach(pda => select.appendChild(new Option(pda.substring(0,100)+"...", pda)));
            }
        }
        
        // ==========================================
        // PROCESAMIENTO PRINCIPAL: BOTÓN "GENERAR"
        // ==========================================
        btnGenerar.addEventListener('click', async () => {
            const payload = {
                grado: document.getElementById('grado').value,
                tiempo: document.getElementById('tiempo').value,
                campo1: document.getElementById('campoFormativo1').value,
                cont1: document.getElementById('contenido1').value,
                pda1: document.getElementById('pda1').value,
                campo2: document.getElementById('campoFormativo2').value,
                cont2: document.getElementById('contenido2').value,
                pda2: document.getElementById('pda2').value
            };
            
            if (!payload.grado || !payload.campo1 || !payload.cont1 || !payload.pda1 || !payload.tiempo) {
                alert('Completa los datos del Eje Principal y la duración.'); return;
            }

            formSection.style.display = 'none'; 
            seccionResultadoContenedor.style.display = 'block'; 
            resultadoDiv.innerHTML = '<div class="spinner"></div><p style="text-align: center; color: #1e3a8a;">Estructurando y FUSIONANDO secuencia didáctica...<br><span style="font-size:0.9rem; color:#64748b;">Esto puede tardar entre 20 y 40 segundos.</span></p>';

            try {
                const response = await fetch('procesar_planeacion_transversal.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json', 
                        'X-CSRF-Token': csrfToken           
                    },
                    body: JSON.stringify(payload)           
                });

                const textoCrudo = await response.text(); 
                let data;
                try {
                    data = JSON.parse(textoCrudo);
                } catch(parseError) {
                    throw new Error("<strong>Error del servidor:</strong><br><div style='background:#fff; color:#dc3545; padding:10px; border:1px solid #dc3545; margin-top:10px; font-family:monospace; font-size:12px;'>" + textoCrudo.replace(/</g, "&lt;").replace(/>/g, "&gt;") + "</div>");
                }

                if (!response.ok || data.status === 'error') throw new Error(data.error || 'Ocurrió un error en el servidor.');

                if (data.status === 'completo') {
                    usosRestantes = data.usos_restantes;
                    contadorUsosSpan.innerText = usosRestantes;

                    const plan = data.plan; 
                    const escapeHTML = str => str.replace(/[&<>'"]/g, tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag]));

                    let html = `
                        <div id="area-impresion-pdf">
                            <div class="markdown-content">
                                <h3>${escapeHTML(plan.datos_principales.tema_central)}</h3>
                                <p><strong>PDA Ancla (Principal):</strong> ${escapeHTML(plan.datos_principales.pda_ancla)}</p>
                                ${plan.datos_principales.pda_transversal && plan.datos_principales.pda_transversal !== 'No aplica' ? `<p><strong>PDA Transversal:</strong> ${escapeHTML(plan.datos_principales.pda_transversal)}</p>` : ''}
                                <p><strong>Competencias a Desarrollar:</strong> ${escapeHTML(plan.datos_principales.competencias)}</p>
                                
                                <h4><i class="fas fa-box-open"></i> Materiales</h4>
                                <ul>${plan.lista_materiales.map(m => `<li>${escapeHTML(m)}</li>`).join('')}</ul>
                                
                                <h4><i class="fas fa-chalkboard-teacher"></i> Secuencia Didáctica Transversal</h4>
                                ${marked.parse(plan.secuencia_completa)}
                                
                                <h4><i class="fas fa-clipboard-check"></i> Evaluación Formativa Integrada</h4>
                                <ul>${plan.evaluacion_formativa.map(e => `<li>${escapeHTML(e)}</li>`).join('')}</ul>
                            </div>
                        </div>
                        <div class="contenedor-botones">
                            <button type="button" class="btn-action btn-copy"><i class="fas fa-copy"></i> Copiar Secuencia</button>
                            <button type="button" class="btn-action btn-pdf" id="btnDescargarPDF"><i class="fas fa-file-pdf"></i> Descargar como PDF</button>
                        </div>
                    `;

                    resultadoDiv.innerHTML = html;
                }
            } catch (error) {
                resultadoDiv.innerHTML = `<div class="aviso"><strong>Error:</strong> ${error.message}</div>`;
            } finally {
                if(usosRestantes > 0) {
                    formSection.style.display = 'block'; 
                }
                btnGenerar.disabled = usosRestantes <= 0;
            }
        });

        // ==========================================
        // DELEGACIÓN DE EVENTOS (Botones generados dinámicamente)
        // ==========================================
        resultadoDiv.addEventListener('click', function(e) {
            // ACCIÓN: BOTÓN COPIAR
            const copyBtn = e.target.closest('.btn-copy');
            if (copyBtn) {
                const content = document.getElementById('area-impresion-pdf').innerText;
                navigator.clipboard.writeText(content); 
                copyBtn.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
                setTimeout(() => copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copiar Secuencia', 2000);
            }

            // ACCIÓN: BOTÓN DESCARGAR PDF
            const pdfBtn = e.target.closest('#btnDescargarPDF');
            if (pdfBtn) {
                const elemento = document.getElementById('area-impresion-pdf');
                const textoOriginal = pdfBtn.innerHTML;
                pdfBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando PDF...';
                pdfBtn.disabled = true;

                html2pdf().set({
                    margin: [15, 15, 15, 15], 
                    filename: 'Secuencia_Transversal.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', scrollY: 0, windowHeight: elemento.scrollHeight + 500 },
                    jsPDF: { unit: 'mm', format: 'letter', orientation: 'portrait' },
                    pagebreak: { mode: ['css', 'legacy'] } 
                }).from(elemento).save().then(() => {
                    pdfBtn.innerHTML = textoOriginal;
                    pdfBtn.disabled = false;
                }).catch(err => {
                    console.error("Error PDF: ", err);
                    alert("Error generando el documento PDF.");
                    pdfBtn.innerHTML = textoOriginal;
                    pdfBtn.disabled = false;
                });
            }
        });
    </script>
</body>
</html>