<?php
// ===== BLOQUE PHP INICIAL CON CONTADOR, SEGURIDAD Y CONTEXTO =====
session_start();

// [SEGURIDAD] 1. CABECERAS HTTP ANTI-ATAQUES Y ANTI-CACHÉ
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

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

// --- 3. OBTENCIÓN DE USOS DESDE BD ---
include 'conexion.php';
$usuario_id = $_SESSION['usuario_id'];

$stmt_usos = $conexion->prepare("SELECT usos_plan_intermedio FROM usuarios WHERE id = ?");
$stmt_usos->bind_param("i", $usuario_id);
$stmt_usos->execute();
$usos_result = $stmt_usos->get_result()->fetch_assoc();

$usos_actuales = $usos_result['usos_plan_intermedio'] ?? 0;
$_SESSION['usos_plan_intermedio'] = $usos_actuales; 
$stmt_usos->close();

// [RENDIMIENTO] Cerramos la DB y la sesión INMEDIATAMENTE
if (isset($conexion)) { $conexion->close(); }
session_write_close();

$puede_generar = $usos_actuales > 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulador de Crisis con Padres - Docentes con Causa</title>
    
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* (Tus estilos se mantienen intactos por eficiencia) */
        :root {
            --color-primario: #1e3a8a;
            --color-acento: #dc2626; 
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
        .form-group-title { color: var(--color-primario); border-bottom: 2px solid var(--color-borde); padding-bottom: 8px; margin-top: 25px; margin-bottom: 15px; font-size: 1.2rem; }
        label { display: block; margin-top: 15px; font-weight: 600; color: #475569; font-size: 0.95rem; }
        select, input, textarea, button { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--color-borde); font-size: 1rem; font-family: inherit; margin-top: 5px; box-sizing: border-box; }
        textarea { resize: vertical; min-height: 100px; }
        select:focus, input:focus, textarea:focus { outline: none; border-color: var(--color-acento); box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2); }
        select[multiple] { height: 160px; }
        .helper-text { font-size: 0.85rem; color: #64748b; margin-top: 4px; display: block; }
        #btnGenerar { margin-top: 25px; background-color: var(--color-primario); color: white; border: none; font-weight: 700; cursor: pointer; transition: 0.3s; font-size: 1.1rem; }
        #btnGenerar:hover { background-color: #152c69; transform: translateY(-2px); }
        #btnGenerar:disabled { background-color: #94a3b8; cursor: not-allowed; transform: none; }
        .contenedor-botones { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }
        #btnCopiar { background-color: var(--color-exito); color: white; border: none; font-weight: 600; cursor: pointer; padding: 12px; border-radius: 8px; font-size: 1rem; transition: background-color 0.3s; }
        #btnDescargarPDF { background-color: var(--color-primario); color: white; margin-top: 10px; border: none; font-weight: 600; cursor: pointer; transition: background-color 0.3s; padding: 12px; border-radius: 8px; font-size: 1rem; }
        .alerta-estrategia { background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; border-radius: 0 8px 8px 0; }
        .dialogo-simulado { background-color: #f8fafc; border: 1px solid #cbd5e1; padding: 15px; border-radius: 8px; margin-top: 15px; }
        .dialogo-actor { font-weight: 700; color: var(--color-primario); }
        .dialogo-padre { font-weight: 700; color: #b91c1c; }
        .analisis-oculto { font-size: 0.9rem; color: #64748b; font-style: italic; margin-bottom: 10px; display: block; }
        .return-section { text-align: center; margin-top: 20px; }
        .btn-return { display: inline-flex; align-items: center; gap: 8px; background-color: var(--color-primario); color: white; padding: 12px 25px; border-radius: 50px; text-decoration: none; font-weight: 600; transition: background-color 0.3s, transform 0.2s; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🛡️ Simulador de Crisis con Padres</h1>
            <p>Anticípate a escenarios problemáticos y domina la comunicación asertiva.</p>
            <p style="font-size: 0.9rem;">Usos restantes: <strong id="contador-usos"><?php echo htmlspecialchars($usos_actuales, ENT_QUOTES, 'UTF-8'); ?></strong></p>
        </header>
        
        <section class="form-section">
            <form id="simuladorForm">
                <div class="form-group-title"><i class="fas fa-users"></i> 1. Identificación del Escenario</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label for="nombreDocente">Tu Nombre (Docente):</label>
                        <input type="text" id="nombreDocente" placeholder="Ej. Profr. Juan Pérez" required>
                    </div>
                    <div>
                        <label for="nivelEducativo">Nivel Educativo:</label>
                        <select id="nivelEducativo" required>
                            <option value="Preescolar">Preescolar</option>
                            <option value="Primaria">Primaria</option>
                            <option value="Secundaria">Secundaria</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
                    <div>
                        <label for="nombreAlumno">Nombre del Alumno(a):</label>
                        <input type="text" id="nombreAlumno" placeholder="Ej. Mateo Ramírez" required>
                    </div>
                    <div>
                        <label for="nombreTutor">Nombre del Padre/Tutor:</label>
                        <input type="text" id="nombreTutor" placeholder="Ej. Sr. Roberto Ramírez" required>
                    </div>
                </div>

                <div class="form-group-title"><i class="fas fa-exclamation-triangle"></i> 2. Naturaleza del Conflicto</div>

                <label for="motivoReunion">Motivo Principal de la Reunión:</label>
                <select id="motivoReunion" required>
                    <option value="">-- Selecciona el motivo --</option>
                    <option value="Bajo rendimiento académico">Bajo rendimiento académico o calificaciones</option>
                    <option value="Problemas severos de conducta en el aula">Problemas severos de conducta en el aula</option>
                    <option value="Acoso escolar (Bullying) - Agresor">Su hijo comete Acoso Escolar (Agresor)</option>
                    <option value="Acoso escolar (Bullying) - Víctima">Su hijo sufre Acoso Escolar (Víctima)</option>
                    <option value="Faltas de respeto a la autoridad">Faltas de respeto directas al docente</option>
                    <option value="Inasistencias y retardos continuos">Inasistencias y retardos continuos</option>
                    <option value="Falta de higiene o negligencia">Falta de higiene / Negligencia parental</option>
                </select>

                <label for="perfilPrincipal">Actitud Principal del Padre/Madre:</label>
                <select id="perfilPrincipal" required>
                    <option value="">-- Selecciona su actitud dominante --</option>
                    <option value="Agresivo y Confrontativo">Agresivo y Confrontativo (Busca pelear, levanta la voz)</option>
                    <option value="Permisivo y Sobreprotector">Permisivo y Sobreprotector ("Mi hijo es un ángel, usted miente")</option>
                    <option value="Sabelotodo y Cuestionador">Sabelotodo y Cuestionador (Cuestiona tu método y capacidad)</option>
                    <option value="Victimista">Victimista (Siente que la escuela está en su contra)</option>
                    <option value="Ausente y Desinteresado">Ausente y Desinteresado (Molesto por tener que ir a la escuela)</option>
                    <option value="Manipulador">Manipulador (Intenta ser tu amigo para obtener ventajas)</option>
                </select>

                <label for="rasgosEspecificos">Comportamientos Específicos (Ctrl+Clic para varios):</label>
                <select id="rasgosEspecificos" multiple required>
                    <option value="Grita y pierde los estribos rápido">Grita y pierde los estribos rápido</option>
                    <option value="Usa lenguaje altisonante o groserías">Usa lenguaje altisonante o groserías</option>
                    <option value="Desautoriza al docente frente al alumno">Desautoriza al docente frente al alumno</option>
                    <option value="No ejerce ninguna autoridad en casa">No ejerce ninguna autoridad en casa</option>
                    <option value="Amenaza con ir a supervisión o demandar">Amenaza con ir a supervisión o quejarse más arriba</option>
                    <option value="Interrumpe constantemente, no deja hablar">Interrumpe constantemente, no deja hablar</option>
                    <option value="Culpa a otros niños de lo que hace su hijo">Culpa a otros niños de lo que hace su hijo</option>
                    <option value="Exige trato preferencial">Exige trato preferencial</option>
                </select>
                <span class="helper-text">Selecciona todas las "banderas rojas" que caracterizan a esta persona.</span>

                <div class="form-group-title"><i class="fas fa-info-circle"></i> 3. Contexto Adicional</div>

                <label for="contextoAdicional">Detalles de la situación (Opcional pero clave para la IA):</label>
                <textarea id="contextoAdicional" placeholder="Ej. Los padres se acaban de divorciar..."></textarea>

                <button type="button" id="btnGenerar" <?php if (!$puede_generar) echo 'disabled'; ?>><i class="fas fa-shield-alt"></i> <?php echo $puede_generar ? 'Generar Perfil y Estrategia de Contención' : '🚫 Usos Agotados'; ?></button>
            </form>
        </section>

        <section class="resultado-section" id="seccionResultadoContenedor" style="display:none;">
            <h2>📊 Reporte Táctico: Manejo de Entrevista</h2>
            <div id="resultadoSimulador"></div>
            <div id="contenedorBotones" class="contenedor-botones" style="display:none;">
                <button type="button" id="btnCopiar"><i class="fas fa-copy"></i> Copiar Reporte Estratégico</button>
                <button type="button" id="btnDescargarPDF"><i class="fas fa-file-pdf"></i> Descargar como PDF</button>
            </div>
        </section>

        <div class="return-section">
            <a href="index.php" class="btn-return"><span>⬅️</span><span>Regresar a Inicio</span></a>
        </div>
    </div>

    <script>
        let usosRestantes = parseInt(<?php echo (int)$usos_actuales; ?>, 10);
        const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";

        const btnGenerar = document.getElementById('btnGenerar');
        const formSection = document.querySelector('.form-section');
        const seccionResultadoContenedor = document.getElementById('seccionResultadoContenedor');
        const resultadoDiv = document.getElementById('resultadoSimulador');
        const btnCopiar = document.getElementById('btnCopiar');
        const btnDescargarPDF = document.getElementById('btnDescargarPDF');
        const contenedorBotones = document.getElementById('contenedorBotones');

        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('docente_nombre')) {
                document.getElementById('nombreDocente').value = localStorage.getItem('docente_nombre');
            }
            if (usosRestantes <= 0) {
                btnGenerar.disabled = true;
                btnGenerar.innerHTML = '<i class="fas fa-ban"></i> Usos Agotados';
            }
        });

        btnGenerar.addEventListener('click', async () => {
            // Recolección de datos
            const payload = {
                docente: document.getElementById('nombreDocente').value.trim(),
                alumno: document.getElementById('nombreAlumno').value.trim(),
                tutor: document.getElementById('nombreTutor').value.trim(),
                nivel: document.getElementById('nivelEducativo').value,
                motivo: document.getElementById('motivoReunion').value,
                perfil: document.getElementById('perfilPrincipal').value,
                rasgos: Array.from(document.getElementById('rasgosEspecificos').selectedOptions).map(opt => opt.value),
                contexto: document.getElementById('contextoAdicional').value.trim()
            };

            // Validación frontend
            if (!payload.docente || !payload.alumno || !payload.tutor || !payload.nivel || !payload.motivo || !payload.perfil || payload.rasgos.length === 0) {
                alert('Por favor, completa todos los campos obligatorios.');
                return;
            }

            localStorage.setItem('docente_nombre', payload.docente);

            formSection.style.display = 'none';
            seccionResultadoContenedor.style.display = 'block';
            contenedorBotones.style.display = 'none'; 
            
            resultadoDiv.innerHTML = '<p style="text-align: center; font-size: 1.2rem; color: var(--color-acento);"><i class="fas fa-brain fa-spin"></i> Analizando psicología del perfil y estructurando defensas verbales...<br><br><span style="font-size:0.9rem; color:#64748b;">Preparando simulador. Por favor espera unos segundos.</span></p>';

            try {
                // [SEGURIDAD] Enviamos el objeto estructurado
                const response = await fetch('procesar_simulador_padres_problematicos.php', {
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
                    throw new Error(`Error en el servidor al generar la respuesta.<br><br><span style="font-size:12px;color:red;font-family:monospace;">${textoCrudo.replace(/</g, "&lt;").replace(/>/g, "&gt;")}</span>`);
                }

                if (!response.ok || data.success === false) {
                    throw new Error(data.error || 'Error desconocido del servidor.');
                }

                usosRestantes = data.usos_restantes;
                document.getElementById('contador-usos').innerText = usosRestantes;

                const reporte = data.reporte;
                const escapeHTML = str => str.replace(/[&<>'"]/g, tag => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'}[tag] || tag));

                let html = `<div class="plan-resultado" id="area-impresion-pdf">`;
                
                // Cabecera
                html += `
                <div style="border-bottom: 2px solid var(--color-primario); padding-bottom: 15px; margin-bottom: 20px;">
                    <h3 style="margin:0; color:var(--color-primario);"><i class="fas fa-file-contract"></i> Ficha Táctica de Entrevista</h3>
                    <p style="margin:5px 0 0 0;"><strong>Caso:</strong> Alumno ${escapeHTML(payload.alumno)} (${escapeHTML(payload.nivel)}) <br> <strong>Atiende:</strong> ${escapeHTML(payload.docente)}</p>
                </div>`;

                // Análisis Psicológico
                html += `<h4><i class="fas fa-user-ninja"></i> Perfil del Tutor: ${escapeHTML(payload.tutor)}</h4>`;
                html += `<p><strong>Arquetipo:</strong> <span style="color:var(--color-acento); font-weight:bold;">${escapeHTML(reporte.analisis_perfil.arquetipo)}</span></p>`;
                html += `<p>${escapeHTML(reporte.analisis_perfil.descripcion)}</p>`;

                // Errores Fatales
                html += `<div class="alerta-estrategia">
                            <h4 style="margin-top:0; color:#b91c1c;"><i class="fas fa-skull-crossbones"></i> Lo que NUNCA debes hacer/decir</h4>
                            <ul style="margin-bottom:0;">`;
                reporte.errores_fatales.forEach(err => html += `<li>${escapeHTML(err)}</li>`);
                html += `   </ul>
                         </div>`;

                // Estrategia de Comunicación
                html += `<h4><i class="fas fa-chess-knight"></i> Estrategia Asertiva Paso a Paso</h4><ul>`;
                reporte.estrategia_comunicacion.forEach(est => html += `<li>${escapeHTML(est)}</li>`);
                html += `</ul>`;

                // Frases Salvavidas
                html += `<h4><i class="fas fa-life-ring"></i> Frases Salvavidas (Memorízalas)</h4><ul>`;
                reporte.frases_salvavidas.forEach(frase => html += `<li><strong>"${escapeHTML(frase)}"</strong></li>`);
                html += `</ul>`;

                // Simulador de Diálogo
                html += `<h4><i class="fas fa-comments"></i> Simulador de Apertura (Juego de Roles)</h4>`;
                html += `<p style="font-size:0.9rem; color:#64748b;">Así es como probablemente inicie la reunión y cómo debes responder:</p>`;
                
                reporte.simulacion_dialogo.forEach(linea => {
                    if(linea.actor === "Tutor") {
                        html += `<div class="dialogo-simulado" style="border-left: 4px solid var(--color-acento);">
                                    <span class="dialogo-padre">${escapeHTML(payload.tutor)} (Tutor):</span> "${escapeHTML(linea.dialogo)}"
                                    <span class="analisis-oculto">Intención oculta: ${escapeHTML(linea.analisis_oculto)}</span>
                                 </div>`;
                    } else {
                        html += `<div class="dialogo-simulado" style="border-left: 4px solid var(--color-primario); background-color: #f0fdf4;">
                                    <span class="dialogo-actor">${escapeHTML(payload.docente)} (Tú):</span> "${escapeHTML(linea.dialogo)}"
                                    <span class="analisis-oculto" style="color:#15803d;">Técnica aplicada: ${escapeHTML(linea.tecnica_aplicada)}</span>
                                 </div>`;
                    }
                });

                html += `<div class="aviso" style="background-color: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 25px; font-size: 0.9rem;"><strong>Nota:</strong> ${escapeHTML(reporte.aviso)}</div>`;
                html += `</div>`;

                resultadoDiv.innerHTML = html;
                contenedorBotones.style.display = 'flex';

            } catch (error) {
                resultadoDiv.innerHTML = `<div class="alerta-estrategia">
                    <h3 style="margin-top:0;"><i class="fas fa-exclamation-triangle"></i> Ocurrió un error</h3>
                    <p>${error.message}<br><br>Inténtalo de nuevo, no se han descontado créditos.</p>
                </div>`;
            } finally {
                formSection.style.display = 'block';
                if (usosRestantes > 0) {
                    btnGenerar.disabled = false;
                    btnGenerar.innerHTML = '<i class="fas fa-shield-alt"></i> Generar Perfil y Estrategia de Contención';
                } else {
                    btnGenerar.disabled = true;
                    btnGenerar.innerHTML = '<i class="fas fa-ban"></i> Usos Agotados';
                }
            }
        });

        btnDescargarPDF.addEventListener('click', () => {
            const elemento = document.getElementById('area-impresion-pdf');
            
            // Configuración robusta para evitar PDFs en blanco o cortados
            const opciones = {
                margin:       [15, 15, 15, 15], 
                filename:     'Reporte_Estrategico_Tutor.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { 
                    scale: 2, 
                    useCORS: true, /* ¡CRÍTICO! Permite que los íconos externos se dibujen sin bloquear el PDF */
                    backgroundColor: '#ffffff', 
                    scrollY: 0, 
                    windowHeight: elemento.scrollHeight + 200 /* Calcula el alto total dinámicamente */
                },
                jsPDF:        { unit: 'mm', format: 'letter', orientation: 'portrait' },
                pagebreak:    { mode: ['css', 'legacy'] } /* Permite saltos de página naturales */
            };

            const btn = btnDescargarPDF;
            const original = btn.innerHTML;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Estructurando PDF...';
            btn.disabled = true;

            html2pdf().set(opciones).from(elemento).save().then(() => {
                btn.innerHTML = original;
                btn.disabled = false;
            }).catch(error => {
                console.error("Error al generar PDF: ", error);
                alert("Hubo un problema al generar el documento. Intenta de nuevo.");
                btn.innerHTML = original;
                btn.disabled = false;
            });
        });
        
        btnCopiar.addEventListener('click', () => {
            const texto = document.getElementById('area-impresion-pdf').innerText;
            navigator.clipboard.writeText(texto).then(() => {
                const btn = btnCopiar;
                const original = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
                setTimeout(() => btn.innerHTML = original, 2000);
            });
        });
    </script>
</body>
</html>