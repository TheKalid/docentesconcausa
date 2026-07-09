<?php
// ===== BLOQUE DE SEGURIDAD Y CONTADOR =====
session_start();

// [SEGURIDAD] Cabeceras HTTP Anti-Ataques y Anti-Caché
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// 1. Verificación de Nivel
$nivel_requerido = 2; 
$plan_activo = $_SESSION['plan_activo'] ?? 0;
if ($plan_activo < $nivel_requerido) {
    header('Location: catalogo_de_pagos.html');
    exit();
}

// [SEGURIDAD] Generación de Token CSRF (Anti-Bots)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// 2. OBTENCIÓN DE USOS DESDE LA BD
require_once 'conexion.php';
$usos_protocolos = 0; // Valor por defecto
if (isset($_SESSION['usuario_id'])) {
    $userId = $_SESSION['usuario_id'];
    // Siempre leemos de la BD al cargar la página para tener el dato más fresco
    $stmt = $conexion->prepare("SELECT usos_protocolos FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        $usos_protocolos = (int)$user['usos_protocolos'];
        $_SESSION['usos_protocolos'] = $usos_protocolos; // Sincronizamos sesión
    }
    $stmt->close();
    $conexion->close();
}
$puede_consultar = $usos_protocolos > 0;

// [RENDIMIENTO] Liberamos la pestaña inmediatamente
session_write_close();

// 3. PREPARAMOS EL MENSAJE DE "SIN USOS"
$mensaje_sin_usos = '';
if (!$puede_consultar) {
    $mensaje_sin_usos = '<div class="aviso-usos-agotados">Has agotado tus consultas de protocolos. <br>Si necesitas más, por favor, <a href="servicio_cliente.html">contacta a servicio al cliente</a> o espera a tu renovación mensual.</div>';
}
// ===== FIN DEL BLOQUE =====
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asesor de Protocolos NEM - Docentes con Causa</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        :root {
            --color-primario: #0d2c4b;
            --color-acento: #007bff;
            --color-acento-hover: #0056b3;
            --color-fondo: #f8f9fa;
            --color-texto: #212529;
            --color-tarjeta: #ffffff;
            --color-borde: #dee2e6;
            --color-exito: #28a745;
            --color-error: #dc3545;
        }
        body { font-family: 'Source Sans Pro', sans-serif; background-color: var(--color-fondo); color: var(--color-texto); line-height: 1.6; }
        .container { max-width: 800px; margin: 0 auto; padding: 40px 20px; }
        .page-header { text-align: center; margin-bottom: 40px; }
        .page-header h1 { font-size: 2.5rem; color: var(--color-primario); margin: 0 0 10px 0; }
        .page-header p { font-size: 1.1rem; color: #6c757d; }
        .card { background-color: var(--color-tarjeta); border-radius: 12px; padding: 30px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); border: 1px solid var(--color-borde); }
        .form-group { margin-bottom: 20px; }
        #form-consulta label { display: block; font-weight: 600; margin-bottom: 10px; font-size: 1.1rem; color: var(--color-primario); }
        #nivel-educativo, #prompt-docente { width: 100%; padding: 12px 15px; border: 1px solid var(--color-borde); border-radius: 8px; font-family: 'Source Sans Pro', sans-serif; font-size: 1rem; }
        #prompt-docente { min-height: 120px; resize: vertical; }
        .disclaimer-box { background-color: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 20px; border-radius: 8px; display: flex; align-items: flex-start; gap: 15px; margin-bottom: 25px; }
        .disclaimer-icon { font-size: 1.5rem; color: #ffc107; }
        .final-notice { background-color: #eef2ff; border-color: #c7d2fe; color: #4338ca; }
        .final-notice strong { color: #312e81; }
        .return-section { display: flex; justify-content: center; margin-top: 40px; }
        .btn-return { display: inline-flex; align-items: center; gap: 8px; background-color: var(--color-primario); color: white; padding: 12px 25px; border-radius: 50px; text-decoration: none; font-weight: 600; }
        .btn-accept { width: 100%; margin-top: 15px; padding: 12px; background-color: var(--color-exito); color: white; border: none; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: background-color 0.3s; }
        .btn-accept:hover { background-color: #218838; }
        #btn-consultar { display: inline-flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 15px; background-color: var(--color-acento); color: white; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s; }
        #btn-consultar:hover { background-color: var(--color-acento-hover); }
        #btn-consultar:disabled { background-color: #cccccc; cursor: not-allowed; }
        .bitacora-section { background-color: #eef2ff; border: 1px solid #c7d2fe; text-align: center; }
        .bitacora-section h2 { color: var(--color-primario); margin-bottom: 15px; }
        .btn-bitacora { display: inline-flex; align-items: center; gap: 10px; background-color: var(--color-exito); color: white; padding: 12px 25px; border-radius: 50px; text-decoration: none; font-weight: 600; transition: background-color 0.3s; }
        .btn-bitacora:hover { background-color: #218838; }
        .spinner { border: 4px solid rgba(0, 0, 0, 0.1); border-radius: 50%; border-top: 4px solid var(--color-acento); width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
        .spinner-small { border: 2px solid rgba(255, 255, 255, 0.3); border-radius: 50%; border-top: 2px solid white; width: 16px; height: 16px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .loading-text { text-align: center; color: var(--color-primario); margin-top: 10px; }
        .error-message { background-color: rgba(220,53,69,0.1); border-left: 4px solid var(--color-error); padding: 15px; border-radius: 0 8px 8px 0; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .error-message i { color: var(--color-error); font-size: 1.2rem; }
        #btn-copiar { display: none; width: 100%; padding: 12px; margin-top: 15px; background-color: var(--color-tarjeta); color: var(--color-primario); border: 1px solid var(--color-borde); border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        #btn-copiar:hover { background-color: var(--color-primario); color: white; }

        /* ================= ESTILOS DEL BOTÓN PDF Y ENCABEZADO ================= */
        .contenedor-botones { display: flex; flex-direction: column; gap: 10px; margin-top: 15px; }
        
        #btn-descargar-pdf { 
            display: flex; align-items: center; justify-content: center; gap: 8px; 
            width: 100%; padding: 12px; background-color: var(--color-error); 
            color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; 
        }
        #btn-descargar-pdf:hover { background-color: #b91c1c; }
        
        .encabezado-oficial { text-align: center; border-bottom: 2px solid var(--color-primario); padding-bottom: 15px; margin-bottom: 20px; }
        .encabezado-oficial h2 { margin: 0; color: var(--color-primario); text-transform: uppercase; font-size: 1.5rem; }
        .encabezado-oficial p { margin: 5px 0 0 0; font-size: 1.1rem; color: var(--color-texto); }

        .contador-usos { background-color: #eef2ff; color: var(--color-primario); padding: 15px; border-radius: 8px; text-align: center; font-weight: 600; margin-bottom: 25px; border: 1px solid #c7d2fe; }
        .aviso-usos-agotados { background-color: #fffbe6; color: #854d0e; padding: 15px; border-radius: 8px; border: 1px solid #fde68a; text-align: center; font-weight: 600; }
        .aviso-usos-agotados a { color: var(--color-primario); font-weight: 700; }
        
        /* CSS PARA EL FORMATO DE SALIDA "BONITO" */
        .protocolo-respuesta { font-size: 1rem; line-height: 1.65; color: #333; }
        .protocolo-respuesta h3 { font-size: 1.2rem; font-weight: 600; color: var(--color-primario); margin-top: 1.75rem; margin-bottom: 1rem; padding-bottom: 5px; border-bottom: 1px solid var(--color-borde); }
        .protocolo-respuesta h3:first-child { margin-top: 0; }
        .protocolo-respuesta h3::before { font-family: "Font Awesome 6 Free"; font-weight: 900; margin-right: 10px; color: var(--color-acento); }
        .protocolo-respuesta h3:contains("Fundamentación")::before, .protocolo-respuesta h3:contains("Legal")::before { content: "\f02d"; } 
        .protocolo-respuesta h3:contains("Procedimiento")::before, .protocolo-respuesta h3:contains("Actuación")::before { content: "\f085"; } 
        .protocolo-respuesta h3:contains("Sugerencias")::before, .protocolo-respuesta h3:contains("Consideraciones")::before { content: "\f0eb"; } 
        .protocolo-respuesta h3:contains("Error")::before { content: "\f071"; color: var(--color-error); } 
        .protocolo-respuesta p { margin-bottom: 1rem; } 
        .protocolo-respuesta ul, .protocolo-respuesta ol { padding-left: 2rem; margin-bottom: 1rem; }
        .protocolo-respuesta li { margin-bottom: 0.5rem; }
        .protocolo-respuesta strong { color: var(--color-texto); font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <h1><i class="fas fa-graduation-cap"></i> Asesor de Protocolos NEM</h1>
            <p>Orientación basada en los protocolos de la Nueva Escuela Mexicana</p>
        </header>

        <div class="disclaimer-box">
            <div class="disclaimer-icon"><i class="fas fa-info-circle"></i></div>
            <div class="disclaimer-content">
                <strong>Aviso Importante:</strong> Esta herramienta proporciona orientación con fines meramente informativos. Su contenido no constituye una asesoría legal formal. Es fundamental que todas las recomendaciones sean verificadas con las autoridades de su plantel y la normativa específica de su entidad.
            </div>
            <button id="btn-accept-disclaimer" class="btn-accept">He leído y acepto los términos</button>
        </div>

        <section class="card" id="form-card" style="display: none;">
            <div class="contador-usos">
                Consultas restantes: <strong id="contador-display"><?php echo htmlspecialchars($usos_protocolos); ?></strong>
            </div>

            <form id="form-consulta">
                <div class="form-group">
                    <label for="nivel-educativo"><i class="fas fa-layer-group"></i> Nivel educativo:</label>
                    <select id="nivel-educativo" required>
                        <option value="" disabled selected>Selecciona un nivel</option>
                        <option value="preescolar">Preescolar</option>
                        <option value="primaria">Primaria</option>
                        <option value="secundaria">Secundaria</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="prompt-docente"><i class="fas fa-question-circle"></i> Describe la situación o tu consulta:</label>
                    <textarea id="prompt-docente" name="prompt-docente" 
                              placeholder="Ejemplo: ¿Cómo debo proceder si detecto un caso de bullying en mi salón de 3er grado?" 
                              required></textarea>
                </div>

                <div style="background-color: #eef2ff; padding: 15px; border-radius: 8px; border: 1px solid #c7d2fe; margin-bottom: 20px;">
                    <h4 style="margin-top: 0; color: var(--color-primario);"><i class="fas fa-id-card"></i> Datos para el reporte (Se guardan automáticamente)</h4>
                    
                    <label for="nombreDocente" style="margin-top: 10px; font-size: 0.95rem;">Nombre del Docente/Directivo:</label>
                    <input type="text" id="nombreDocente" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--color-borde); font-family: 'Source Sans Pro', sans-serif;" placeholder="Ej. Profr. Juan Pérez">
                    
                    <label for="nombreEscuela" style="margin-top: 10px; font-size: 0.95rem;">Nombre de la Escuela:</label>
                    <input type="text" id="nombreEscuela" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--color-borde); font-family: 'Source Sans Pro', sans-serif;" placeholder="Ej. Esc. Primaria Benito Juárez">
                </div>
                <button type="submit" id="btn-consultar" <?php if (!$puede_consultar) echo 'disabled'; ?>>
                    <i class="fas fa-search"></i>
                    <span><?php echo $puede_consultar ? 'Consultar Protocolo' : 'Consultas Agotadas'; ?></span>
                </button>
            </form>
        </section>

        <section class="card resultado-protocolo">
            <h2><i class="fas fa-file-alt"></i> Recomendación basada en protocolos NEM</h2>
            <div id="respuestaProtocolo">
                <?php
                    if (!empty($mensaje_sin_usos)) {
                        echo $mensaje_sin_usos;
                    } else {
                        echo '<p style="color: #6c757d; text-align: center;">Acepta el aviso de responsabilidad para usar el asistente...</p>';
                    }
                ?>
            </div>
            <div id="contenedorBotones" class="contenedor-botones" style="display: none;">
                <button id="btn-copiar" style="margin-top: 0; display: flex;">
                    <i class="far fa-copy"></i>
                    <span>Copiar recomendación</span>
                </button>
                <button type="button" id="btn-descargar-pdf">
                    <i class="fas fa-file-pdf"></i>
                    <span>Descargar como PDF</span>
                </button>
            </div>

            <div id="final-disclaimer" class="disclaimer-box final-notice" style="display: none; margin-top: 20px;">
                 <div class="disclaimer-icon"><i class="fas fa-info-circle"></i></div>
                 <div class="disclaimer-content">
                     <strong>Aviso:</strong> Esta herramienta es solo con fines de carácter informativo. No nos hacemos responsables por el uso de la información proporcionada.
                 </div>
            </div>
        </section>

        <section class="card bitacora-section">
            <h2><i class="fas fa-edit"></i> Registro de Incidentes</h2>
            <p style="margin-bottom: 20px; color: #6c757d;">¿Necesitas documentar un incidente? Utiliza nuestra herramienta avanzada de gestión de bitácoras.</p>
            <a href="generar_bitacora_de_profesor.php" class="btn-bitacora">
                <i class="fas fa-arrow-right"></i>
                <span>Ir a la Bitácora</span>
            </a>
        </section>

        <section class="return-section">
            <a href="index.php" class="btn-return">
                <span>⬅️</span>
                <span>Regresar a la Página Principal</span>
            </a>
        </section>
    </div>

    <script>
        // [SEGURIDAD] Captura del Token CSRF en JS
        const csrfToken = "<?php echo $csrf_token; ?>";

        const formConsulta = document.getElementById('form-consulta');
        const nivelEducativo = document.getElementById('nivel-educativo');
        const promptDocente = document.getElementById('prompt-docente');
        const btnConsultar = document.getElementById('btn-consultar');
        const respuestaProtocolo = document.getElementById('respuestaProtocolo');
        const btnCopiar = document.getElementById('btn-copiar');
        const finalDisclaimer = document.getElementById('final-disclaimer');
        const btnAcceptDisclaimer = document.getElementById('btn-accept-disclaimer');
        const formCard = document.getElementById('form-card');
        const disclaimerBox = document.querySelector('.disclaimer-box:not(.final-notice)');
        const contadorDisplay = document.getElementById('contador-display');

        const contenedorBotones = document.getElementById('contenedorBotones');
        const btnDescargarPDF = document.getElementById('btn-descargar-pdf');
        const inputNombreDocente = document.getElementById('nombreDocente');
        const inputNombreEscuela = document.getElementById('nombreEscuela');

        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('docente_nombre')) inputNombreDocente.value = localStorage.getItem('docente_nombre');
            if (localStorage.getItem('docente_escuela')) inputNombreEscuela.value = localStorage.getItem('docente_escuela');
        });

        function formatMarkdown(text) {
            if (!text) return '';

            let html = '';
            const lines = text.split('\n');
            let inList = null; 
            let inParagraph = false;

            function closeList() {
                if (inList === 'ul') html += '</ul>\n';
                if (inList === 'ol') html += '</ol>\n';
                inList = null;
            }
            function closeParagraph() {
                if (inParagraph) {
                    html += '</p>\n';
                    inParagraph = false;
                }
            }
            function applyInlineFormatting(line) {
                return line.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            }

            for (let line of lines) {
                line = line.trim(); 
                
                if (line.startsWith('# ')) { continue; } 
                
                if (line.startsWith('## ')) {
                    closeParagraph();
                    closeList();
                    html += `<h3>${applyInlineFormatting(line.substring(3))}</h3>\n`;
                    continue;
                }
                if (line.startsWith('* ') || line.startsWith('- ')) {
                    closeParagraph();
                    if (inList !== 'ul') {
                        closeList();
                        inList = 'ul';
                        html += '<ul>\n';
                    }
                    html += `  <li>${applyInlineFormatting(line.substring(2))}</li>\n`;
                    continue;
                }
                if (line.match(/^\d+\.\s/)) {
                    closeParagraph();
                    if (inList !== 'ol') {
                        closeList();
                        inList = 'ol';
                        html += '<ol>\n';
                    }
                    html += `  <li>${applyInlineFormatting(line.replace(/^\d+\.\s/, ''))}</li>\n`;
                    continue;
                }
                if (line === '') {
                    closeParagraph();
                    closeList();
                    continue;
                }
                
                closeList(); 
                if (!inParagraph) {
                    html += '<p>';
                    inParagraph = true;
                }
                html += applyInlineFormatting(line) + ' '; 
            }
            
            closeParagraph();
            closeList();
            return html.replace(/ \<\/p\>/g, '</p>'); 
        }

        function mostrarRespuesta(data, usosRestantes) {
            const { protocolo } = data; 
            const nivel = nivelEducativo.options[nivelEducativo.selectedIndex].text; 
            const nomDoc = inputNombreDocente.value || 'Docente / Directivo';
            const nomEsc = inputNombreEscuela.value || 'Institución Educativa';

            const htmlResult = `
                <div class="encabezado-oficial">
                    <h2>${nomEsc}</h2>
                    <p><strong>Responsable:</strong> ${nomDoc} &nbsp;|&nbsp; <strong>Nivel:</strong> ${nivel}</p>
                </div>
                <div class="protocolo-respuesta">${formatMarkdown(protocolo)}</div>
            `;
            
            respuestaProtocolo.innerHTML = htmlResult;
            contenedorBotones.style.display = 'flex';
            finalDisclaimer.style.display = 'flex';
            contadorDisplay.innerText = usosRestantes;
            
            if (usosRestantes <= 0) {
                btnConsultar.disabled = true;
                btnConsultar.innerHTML = `<span><i class="fas fa-times-circle"></i> Consultas Agotadas</span>`;
            }
        }
        
        function mostrarError(errorMsg) {
             respuestaProtocolo.innerHTML = `<div class="error-message"><i class="fas fa-exclamation-triangle"></i><div><strong>Error al obtener recomendaciones.</strong><p>${errorMsg}</p></div></div>`;
             contenedorBotones.style.display = 'none'; 
             finalDisclaimer.style.display = 'none';
        }
        
        formConsulta.addEventListener('submit', async (e) => {
            e.preventDefault();
            const pregunta = promptDocente.value.trim();
            const nivel = nivelEducativo.value;
            
            if (pregunta.length < 15) {
                mostrarError('Por favor, describe tu situación con más detalle.');
                return;
            }
            if (!nivel) {
                mostrarError('Selecciona un nivel educativo.');
                return;
            }
            
            btnConsultar.disabled = true;
            btnConsultar.innerHTML = `<div class="spinner-small"></div><span>Analizando Protocolos...</span>`;
            respuestaProtocolo.innerHTML = `<div class="spinner"></div><p class="loading-text">Revisando la normativa y manuales oficiales...<br><span style="font-size: 0.9rem; color: #6c757d;">Esto toma entre 10 y 20 segundos.</span></p>`;
            contenedorBotones.style.display = 'none'; 
            
            localStorage.setItem('docente_nombre', inputNombreDocente.value);
            localStorage.setItem('docente_escuela', inputNombreEscuela.value);
            finalDisclaimer.style.display = 'none';
            
            try {
                const response = await fetch('procesar_protocolo.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json', 
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken // [SEGURIDAD] Enviamos token a PHP
                    },
                    body: JSON.stringify({ consulta: pregunta, nivel: nivel })
                });
                
                const textoCrudo = await response.text(); 
                let resultado;
                
                try {
                    resultado = JSON.parse(textoCrudo);
                } catch(parseError) {
                    throw new Error("<strong>El servidor devolvió texto no válido.</strong><br><br>Esto es lo que dijo el servidor:<br><div style='background:#fff; color:#dc3545; padding:10px; border:1px solid #dc3545; margin-top:10px; max-height:150px; overflow-y:auto; text-align:left; font-family:monospace; font-size:12px;'>" + textoCrudo.replace(/</g, "&lt;").replace(/>/g, "&gt;") + "</div>");
                }
                
                if (!response.ok || resultado.status === 'error') {
                    throw new Error(resultado.error || resultado.details || 'Error desconocido al consultar el protocolo.');
                }
                
                if (resultado.status === 'completo') {
                    mostrarRespuesta(resultado.data, resultado.usos_restantes);
                }

            } catch (error) {
                console.error('Error en el proceso:', error);
                mostrarError(error.message + "<br><br><strong>Nota:</strong> No se descontó ningún crédito de tu cuenta.");
            } finally {
                if (parseInt(contadorDisplay.innerText, 10) > 0) {
                     btnConsultar.disabled = false;
                     btnConsultar.innerHTML = `<i class="fas fa-search"></i><span>Consultar Protocolo</span>`;
                } else {
                     btnConsultar.disabled = true;
                     btnConsultar.innerHTML = `<i class="fas fa-times-circle"></i><span>Consultas Agotadas</span>`;
                }
            }
        });
        
        btnDescargarPDF.addEventListener('click', () => {
            const elemento = document.querySelector('.resultado-protocolo');
            
            contenedorBotones.style.display = 'none';
            finalDisclaimer.style.display = 'none';
            
            const opciones = {
                margin:       [15, 15, 15, 15], 
                filename:     'Reporte_Protocolo_NEM.pdf',
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
            btnDescargarPDF.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Generando PDF...</span>';
            btnDescargarPDF.disabled = true;

            html2pdf().set(opciones).from(elemento).save().then(() => {
                btnDescargarPDF.innerHTML = textoOriginal;
                btnDescargarPDF.disabled = false;
                contenedorBotones.style.display = 'flex';
                finalDisclaimer.style.display = 'flex';
            });
        });
        
        btnCopiar.addEventListener('click', () => {
            const texto = respuestaProtocolo.querySelector(".protocolo-respuesta").textContent; 
            navigator.clipboard.writeText(texto)
                .then(() => {
                    btnCopiar.innerHTML = `<i class="fas fa-check"></i><span>¡Copiado!</span>`;
                    setTimeout(() => {
                        btnCopiar.innerHTML = `<i class="far fa-copy"></i><span>Copiar recomendación</span>`;
                    }, 2000);
                })
                .catch(err => console.error('Error al copiar: ', err));
        });

        btnAcceptDisclaimer.addEventListener('click', () => {
            const puedeConsultar = <?php echo json_encode($puede_consultar); ?>;
            disclaimerBox.style.display = 'none';

            if (puedeConsultar) {
                formCard.style.display = 'block';
                respuestaProtocolo.innerHTML = '<p style="color: #6c757d; text-align: center;">Ingresa tu consulta para recibir orientación específica...</p>';
            }
        });
    </script>
</body>
</html>