<?php
session_start();

// [SEGURIDAD] Cabeceras HTTP Anti-Ataques y Anti-Caché
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
}

require_once 'conexion.php';

$nivel_requerido = 2; // Plan Intermedio o Mentor
$plan_activo = $_SESSION['plan_activo'] ?? 0;

if ($plan_activo < $nivel_requerido) {
    header('Location: catalogo_de_pagos.html');
    exit(); 
}

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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.14.305/pdf.min.js"></script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title>Adecuación Inteligente - Planeando con Causa</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Estilos originales intactos */
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
        .problematica-container { background-color: #fffbeb; padding: 20px; border-radius: 8px; border: 1px solid #fde68a; margin-bottom: 25px; border-left: 5px solid var(--color-acento); }
        .planeacion-container { background-color: #f1f5f9; padding: 20px; border-radius: 8px; border: 1px solid var(--color-borde); margin-bottom: 25px; }
        .pdf-upload-box { background-color: #ffffff; border: 2px dashed #cbd5e1; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 15px; transition: all 0.3s; cursor: pointer; position: relative; }
        .pdf-upload-box:hover { border-color: var(--color-primario); background-color: #f8fafc; }
        .pdf-upload-box.disabled { opacity: 0.5; pointer-events: none; background-color: #f1f5f9; border-color: #e2e8f0; cursor: not-allowed; }
        label { display: block; margin-top: 15px; margin-bottom: 5px; font-weight: 600; color: var(--color-primario); font-size: 0.95rem; }
        select, input[type="text"], textarea, button { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--color-borde); font-size: 1rem; font-family: 'Poppins', sans-serif; background-color: #ffffff; box-sizing: border-box; transition: background-color 0.3s; }
        textarea { resize: vertical; min-height: 150px; }
        select:focus, input:focus, textarea:focus { outline: none; border-color: var(--color-acento); box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.2); }
        textarea[readonly] { background-color: #f1f5f9; cursor: not-allowed; border-color: #cbd5e1; color: #64748b; }
        #btnGenerar { margin-top: 20px; background-color: var(--color-primario); color: white; border: none; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s; padding: 15px; }
        #btnGenerar:hover { background-color: #1c3178; transform: translateY(-2px); }
        #btnGenerar:disabled { background-color: #6c757d; cursor: not-allowed; transform: none; }
        .btn-action { background-color: #f8f9fa; color: var(--color-texto); border: 1px solid var(--color-borde); padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; box-sizing: border-box; margin-top: 10px; }
        .btn-copy { background-color: var(--color-exito); color: white; border-color: var(--color-exito); }
        .btn-pdf { background-color: #dc2626; color: white; border-color: #dc2626; }
        .btn-copy:hover { background-color: #15803d; }
        .btn-pdf:hover { background-color: #b91c1c; }
        #btnQuitarPdf { display:none; background-color: #ef4444; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 0.9rem; width: fit-content; margin: 15px auto 0 auto; transition: 0.2s; }
        #btnQuitarPdf:hover { background-color: #b91c1c; }
        .contenedor-botones { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }
        .aviso { background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; border: 1px solid #ffeeba; margin-top: 20px; }
        .markdown-content h3 { color: var(--color-primario); border-bottom: 2px solid var(--color-acento); padding-bottom: 5px; margin-top: 25px; page-break-after: avoid; }
        .markdown-content h4 { color: #334155; margin-top: 20px; margin-bottom: 10px; page-break-after: avoid; }
        .markdown-content ul, .markdown-content p { page-break-inside: avoid; }
        .markdown-content mark { background-color: #fef08a; padding: 2px 4px; border-radius: 4px; font-weight: 600; }
        .btn-return { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background-color: var(--color-primario); color: white; padding: 12px 25px; border-radius: 50px; text-decoration: none; font-weight: 600; margin-top: 20px; width: fit-content; margin-left: auto; margin-right: auto;}
        .spinner { border: 4px solid rgba(0, 0, 0, 0.1); border-radius: 50%; border-top: 4px solid var(--color-acento); width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .helper-text { font-size: 0.85rem; color: #64748b; margin-top: 5px; display: block; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>💡 Adecuación Inteligente de Planeaciones</h1>
            <p>Sube tu planeación en PDF o pégala manualmente para recibir adaptaciones curriculares.</p>
            <p>Usos restantes: <strong id="contador-usos"><?php echo htmlspecialchars($usos_plan_intermedio, ENT_QUOTES, 'UTF-8'); ?></strong></p>
        </header>
        
        <section class="form-section">
            <form id="planeacionForm">
                <div class="planeacion-container">
                    <h3 style="margin-top:0; color: var(--color-primario);"><i class="fas fa-file-alt"></i> 1. Tu Planeación Original</h3>
                    
                    <div class="pdf-upload-box" id="drop_zone">
                        <i class="fas fa-file-pdf" style="color: #dc2626; font-size: 2rem; margin-bottom: 10px;"></i> 
                        <p id="pdf_status" style="margin: 0; color: #334155; font-weight: 600;">Haz clic aquí para subir tu planeación en PDF</p>
                        <span id="pdf_helper" style="font-size: 0.85rem; color: #64748b; display: block; margin-top: 5px;">El texto se extraerá automáticamente.</span>
                        
                        <input type="file" id="pdf_upload" accept="application/pdf" style="display: none;">
                        <button type="button" id="btnQuitarPdf"><i class="fas fa-trash-alt"></i> Quitar PDF y escribir manualmente</button>
                    </div>

                    <label for="planeacion_texto">O pega directamente el texto de tu planeación aquí:</label>
                    <textarea id="planeacion_texto" placeholder="Ej. Sesión 1: Inicio... Desarrollo... Cierre..." required></textarea>
                    <span class="helper-text" id="text_helper"><i class="fas fa-save"></i> Se guarda automáticamente en tu navegador por si cierras la pestaña.</span>
                </div>

                <div class="problematica-container">
                    <h3 style="margin-top:0; color: #b45309;"><i class="fas fa-hands-helping"></i> 2. Identificación de Barreras o Problemáticas</h3>
                    
                    <label for="categoria_problema">Categoría principal de la problemática:</label>
                    <select id="categoria_problema" required>
                        <option value="">-- Selecciona el foco de la adaptación --</option>
                        <option value="Rezago educativo o Lento aprendizaje">Rezago educativo o Lento aprendizaje</option>
                        <option value="Condición del Neurodesarrollo (Autismo, TDAH, Discapacidad intelectual)">Condición del Neurodesarrollo (Autismo, TDAH, Discapacidad intelectual)</option>
                        <option value="Problemas conductuales y Acoso escolar (Bullying)">Problemas conductuales y Acoso escolar (Bullying)</option>
                        <option value="Abandono emocional o Negligencia familiar (Padres ausentes)">Abandono emocional o Negligencia familiar (Padres ausentes)</option>
                        <option value="Violencia intrafamiliar o comunitaria">Violencia intrafamiliar o comunitaria</option>
                        <option value="Adicción a pantallas o Redes sociales">Exceso de uso de dispositivos o Redes sociales</option>
                        <option value="Otra necesidad específica">Otra necesidad específica</option>
                    </select>

                    <label for="descripcion_contexto">Describe la situación específica de tu(s) alumno(s):</label>
                    <textarea id="descripcion_contexto" style="min-height: 100px;" placeholder="Ej. Tengo dos alumnos que no logran concentrarse por más de 5 minutos..." required></textarea>
                </div>

                <button type="button" id="btnGenerar"><i class="fas fa-bolt"></i> Potenciar y Adecuar Planeación</button>
            </form>
        </section>

        <section class="resultado-section" style="display:none;" id="seccionResultadoContenedor">
            <h2>✨ Planeación Adecuada y Análisis</h2>
            <div id="resultadoPlaneacion"></div>
        </section>

        <div style="text-align: center;">
            <a href="index.php" class="btn-return"><span>⬅️</span><span>Regresar a Inicio</span></a>
        </div>
    </div>

    <script>
        let usosRestantes = parseInt(<?php echo (int)$usos_plan_intermedio; ?>, 10);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const btnGenerar = document.getElementById('btnGenerar');
        const contadorUsosSpan = document.getElementById('contador-usos');
        const resultadoDiv = document.getElementById('resultadoPlaneacion');
        const seccionResultadoContenedor = document.getElementById('seccionResultadoContenedor');
        const formSection = document.querySelector('.form-section');
        
        const textPlaneacion = document.getElementById('planeacion_texto');
        const textContexto = document.getElementById('descripcion_contexto');
        const dropZone = document.getElementById('drop_zone');
        const pdfUpload = document.getElementById('pdf_upload');
        const pdfStatus = document.getElementById('pdf_status');
        const pdfHelper = document.getElementById('pdf_helper');
        const btnQuitarPdf = document.getElementById('btnQuitarPdf');
        const textHelper = document.getElementById('text_helper');

        let isPdfLoaded = false;

        document.addEventListener('DOMContentLoaded', () => {
            if (usosRestantes <= 0) {
                btnGenerar.disabled = true;
                btnGenerar.innerHTML = '🚫 Créditos Agotados';
            }
            if(localStorage.getItem('saved_planeacion')) {
                textPlaneacion.value = localStorage.getItem('saved_planeacion');
                verificarCajaTexto(); 
            }
            if(localStorage.getItem('saved_contexto')) textContexto.value = localStorage.getItem('saved_contexto');
        });

        dropZone.addEventListener('click', (e) => {
            if (!isPdfLoaded && e.target.id !== 'btnQuitarPdf') {
                pdfUpload.click();
            }
        });

        textPlaneacion.addEventListener('input', () => {
            localStorage.setItem('saved_planeacion', textPlaneacion.value);
            verificarCajaTexto();
        });

        function verificarCajaTexto() {
            if (!isPdfLoaded) {
                if (textPlaneacion.value.trim().length > 0) {
                    dropZone.classList.add('disabled');
                    pdfStatus.innerHTML = '<i class="fas fa-keyboard"></i> Caja de texto en uso';
                    pdfHelper.innerHTML = 'Borra el texto de abajo si deseas subir un archivo PDF en su lugar.';
                } else {
                    dropZone.classList.remove('disabled');
                    pdfStatus.innerHTML = 'Haz clic aquí para subir tu planeación en PDF';
                    pdfHelper.innerHTML = 'El texto se extraerá automáticamente.';
                }
            }
        }

        // EXTRACCIÓN DE PDF 
        pdfUpload.addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return; 

            if (file.type === 'application/pdf') {
                pdfStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Extrayendo texto...';
                textPlaneacion.value = "Leyendo documento, por favor espere...";
                textPlaneacion.readOnly = true;

                try {
                    const pdfjsLib = window.pdfjsLib || window['pdfjs-dist/build/pdf'];
                    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.14.305/pdf.worker.min.js';
                    
                    const fileURL = URL.createObjectURL(file);
                    const loadingTask = pdfjsLib.getDocument(fileURL);
                    
                    const pdf = await loadingTask.promise;
                    let fullText = '';
                    
                    for (let i = 1; i <= pdf.numPages; i++) {
                        const page = await pdf.getPage(i);
                        const textContent = await page.getTextContent();
                        const pageText = textContent.items.map(item => item.str).join(' ');
                        fullText += pageText + '\n\n';
                    }
                    
                    URL.revokeObjectURL(fileURL);

                    if(fullText.trim() === '') throw new Error("IMAGEN_DETECTADA");

                    isPdfLoaded = true;
                    textPlaneacion.value = fullText.trim();
                    textPlaneacion.readOnly = true;
                    textHelper.innerHTML = '<i class="fas fa-lock"></i> Texto extraído correctamente. Para editar manualmente, quita el PDF.';
                    
                    dropZone.classList.remove('disabled');
                    dropZone.style.cursor = 'default';
                    pdfStatus.innerHTML = `<span style="color: var(--color-exito);"><i class="fas fa-check-circle"></i> Archivo cargado: <strong>${file.name}</strong></span>`;
                    pdfHelper.style.display = 'none';
                    btnQuitarPdf.style.display = 'block';
                    
                } catch (err) {
                    console.error("Error PDF:", err);
                    restaurarInterfazPDF();
                    textPlaneacion.value = localStorage.getItem('saved_planeacion') || "";
                    
                    if(err.message === "IMAGEN_DETECTADA") {
                        pdfStatus.innerHTML = '<span style="color: #b45309;"><i class="fas fa-exclamation-triangle"></i> <strong>El PDF es una imagen escaneada o foto.</strong> Este sistema solo lee texto digital. Por favor, copia y pega el texto de tu planeación abajo manualmente.</span>';
                    } else {
                        pdfStatus.innerHTML = '<span style="color: #dc2626;"><i class="fas fa-times-circle"></i> No pudimos procesar el PDF. Por favor, copia y pega el texto manualmente.</span>';
                    }
                }
            } else {
                alert("Por favor, selecciona un archivo con formato PDF.");
            }
            pdfUpload.value = ''; 
        });

        btnQuitarPdf.addEventListener('click', (e) => {
            e.stopPropagation(); 
            restaurarInterfazPDF();
            textPlaneacion.value = '';
            localStorage.removeItem('saved_planeacion');
            verificarCajaTexto(); 
        });

        function restaurarInterfazPDF() {
            isPdfLoaded = false;
            textPlaneacion.readOnly = false;
            dropZone.style.cursor = 'pointer';
            pdfStatus.innerHTML = 'Haz clic aquí para subir tu planeación en PDF';
            pdfHelper.style.display = 'block';
            pdfHelper.innerHTML = 'El texto se extraerá automáticamente.';
            btnQuitarPdf.style.display = 'none';
            textHelper.innerHTML = '<i class="fas fa-save"></i> Se guarda automáticamente en tu navegador por si cierras la pestaña.';
        }

        textContexto.addEventListener('input', () => localStorage.setItem('saved_contexto', textContexto.value));

        btnGenerar.addEventListener('click', async () => {
            const payload = {
                planeacion: textPlaneacion.value.trim(),
                categoria: document.getElementById('categoria_problema').value,
                descripcion: textContexto.value.trim()
            };
            
            if (!payload.planeacion || !payload.categoria || !payload.descripcion || payload.planeacion === "Leyendo documento, por favor espere...") {
                alert('Por favor, asegúrate de que el texto de la planeación y la problemática estén completos.'); return;
            }

            formSection.style.display = 'none'; 
            seccionResultadoContenedor.style.display = 'block'; 
            
            resultadoDiv.innerHTML = '<div class="spinner"></div><p style="text-align: center; color: var(--color-primario); font-weight:600;">Analizando situación psicopedagógica y reescribiendo planeación...</p>';

            try {
                const response = await fetch('procesar_planeacion_potenciada.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify(payload) 
                });

                const data = await response.json();

                if (!response.ok || data.status === 'error') throw new Error(data.error);

                if (data.status === 'completo') {
                    usosRestantes = data.usos_restantes;
                    contadorUsosSpan.innerText = usosRestantes;

                    const plan = data.plan;
                    const escapeHTML = str => (str || '').toString().replace(/[&<>'"]/g, tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag]));

                    let html = `
                        <div id="area-impresion-pdf">
                            <div class="markdown-content">
                                <div style="background-color: #f0fdf4; border-left: 4px solid #16a34a; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                                    <h3 style="margin-top:0; border:none; color: #166534; padding-bottom:0;"><i class="fas fa-microscope"></i> Análisis Psicopedagógico Breve</h3>
                                    <p>${escapeHTML(plan.analisis_problematica)}</p>
                                </div>

                                <h4><i class="fas fa-shield-alt"></i> Estrategias Generales de Contención</h4>
                                <ul>${(plan.estrategias_generales || []).map(e => `<li>${escapeHTML(e)}</li>`).join('')}</ul>
                                
                                <h4><i class="fas fa-file-signature"></i> Planeación con Adecuaciones Curriculares</h4>
                                <p style="font-size: 0.9rem; color: #64748b;"><em>* Las modificaciones sugeridas están marcadas en el texto original.</em></p>
                                ${marked.parse(plan.planeacion_adecuada || '')}
                                
                                <div style="background-color: #eff6ff; border: 1px solid #bfdbfe; padding: 15px; margin-top: 30px; border-radius: 8px;">
                                    <h4 style="margin-top:0; color: #1d4ed8; padding-bottom:0;"><i class="fas fa-heart"></i> Recomendación para el Docente</h4>
                                    <p style="margin-bottom:0;">${escapeHTML(plan.recomendacion_docente)}</p>
                                </div>
                            </div>
                        </div>
                        <div class="contenedor-botones">
                            <button type="button" class="btn-action btn-copy"><i class="fas fa-copy"></i> Copiar Resultado</button>
                            <button type="button" class="btn-action btn-pdf" id="btnDescargarPDF"><i class="fas fa-file-pdf"></i> Descargar como PDF</button>
                            <button type="button" class="btn-action" id="btnNueva" style="background-color: var(--color-primario); color:white;"><i class="fas fa-redo"></i> Adecuar otra planeación</button>
                        </div>
                    `;

                    resultadoDiv.innerHTML = html;
                }
            } catch (error) {
                resultadoDiv.innerHTML = `<div class="aviso"><strong>Error:</strong> ${error.message}</div>
                <div style="text-align:center; margin-top:20px;"><button onclick="location.reload()" class="btn-action">Volver a intentar</button></div>`;
            } 
        });

        resultadoDiv.addEventListener('click', function(e) {
            const copyBtn = e.target.closest('.btn-copy');
            if (copyBtn) {
                const content = document.getElementById('area-impresion-pdf').innerText;
                navigator.clipboard.writeText(content);
                copyBtn.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
                setTimeout(() => copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copiar Resultado', 2000);
            }

            const nuevaBtn = e.target.closest('#btnNueva');
            if(nuevaBtn) {
                formSection.style.display = 'block';
                seccionResultadoContenedor.style.display = 'none';
                window.scrollTo(0,0);
            }

            const pdfBtn = e.target.closest('#btnDescargarPDF');
            if (pdfBtn) {
                const elemento = document.getElementById('area-impresion-pdf');
                const textoOriginal = pdfBtn.innerHTML;
                pdfBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando PDF...';
                pdfBtn.disabled = true;

                // [SEGURIDAD] Generación PDF Blindada
                html2pdf().set({
                    margin: [15, 15, 15, 15], 
                    filename: 'Planeacion_Adecuada.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', scrollY: 0, windowHeight: elemento.scrollHeight + 500 },
                    jsPDF: { unit: 'mm', format: 'letter', orientation: 'portrait' },
                    pagebreak: { mode: ['css', 'legacy'] }
                }).from(elemento).save().then(() => {
                    pdfBtn.innerHTML = textoOriginal;
                    pdfBtn.disabled = false;
                }).catch(err => {
                    console.error(err);
                    alert("Error al generar PDF. Inténtalo de nuevo.");
                    pdfBtn.innerHTML = textoOriginal;
                    pdfBtn.disabled = false;
                });
            }
        });
    </script>
</body>
</html>