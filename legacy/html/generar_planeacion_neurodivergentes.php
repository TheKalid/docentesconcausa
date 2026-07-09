<?php
session_start();

// --- [NUEVO] Cabeceras HTTP Anti-Ataques y Anti-Caché ---
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// --- 1. VERIFICACIÓN DE LOGIN Y PLAN ---
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}
$nivel_requerido = 2; // Nivel intermedio requerido
$plan_activo = $_SESSION['plan_activo'] ?? 0;
if ($plan_activo < $nivel_requerido) {
    header('Location: catalogo_de_pagos.php');
    exit();
}

// --- [NUEVO] Generación de Token CSRF (Anti-Bots) ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// --- 2. OBTENEMOS LOS USOS RESTANTES DESDE BD ---
require_once 'conexion.php'; 
$usos_actuales = 0;
if(isset($_SESSION['usuario_id'])) {
    $stmt_usos = $conexion->prepare("SELECT usos_plan_intermedio FROM usuarios WHERE id = ?");
    $stmt_usos->bind_param("i", $_SESSION['usuario_id']);
    $stmt_usos->execute();
    $usos_result = $stmt_usos->get_result()->fetch_assoc();
    $usos_actuales = $usos_result['usos_plan_intermedio'] ?? 0;
    $_SESSION['usos_plan_intermedio'] = $usos_actuales; 
    $stmt_usos->close();
}

// --- [NUEVO] Cerramos la BD y liberamos la sesión INMEDIATAMENTE ---
if (isset($conexion) && $conexion instanceof mysqli) {
    $conexion->close();
}
session_write_close();

$puede_generar = $usos_actuales > 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planificador Inclusivo - Docentes con Causa</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        :root {
            --color-primario: #1e3a8a;
            --color-secundario: #3b82f6;
            --color-acento: #f39c12;
            --color-fondo: #f8f9fa;
            --color-texto: #334155;
            --color-tarjeta: #ffffff;
            --color-borde: #e2e8f0;
            --color-exito: #16a34a;
            --color-error: #ef4444;
            --fuente-principal: 'Poppins', sans-serif;
        }

        body { font-family: var(--fuente-principal); background-color: var(--color-fondo); color: var(--color-texto); margin: 0; line-height: 1.6; }
        
        /* Header y Layout */
        header { background-color: var(--color-tarjeta); padding: 15px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 1000; }
        .nav-container { display: flex; justify-content: space-between; align-items: center; max-width: 1000px; margin: 0 auto; padding: 0 20px;}
        .logo { display: flex; align-items: center; text-decoration: none; color: var(--color-primario); font-weight: 700; font-size: 1.5rem; }
        .logo i { margin-right: 10px; font-size: 1.8rem; color: var(--color-acento); }
        .container { max-width: 900px; margin: 0 auto; padding: 40px 20px; }
        
        .page-header { text-align: center; margin-bottom: 30px; }
        .page-header h1 { color: var(--color-primario); font-size: 2.2rem; margin-bottom: 10px; }
        .page-header p { color: #64748b; font-size: 1.1rem; }

        .contador-usos { background-color: #eef2ff; color: var(--color-primario); padding: 15px; border-radius: 8px; text-align: center; font-weight: 600; margin-bottom: 30px; border: 1px solid #c7d2fe; }

        /* Formularios y Tarjetas */
        .card-section { background-color: var(--color-tarjeta); border-radius: 12px; padding: 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.06); margin-bottom: 25px; border: 1px solid var(--color-borde); }
        .card-section h2 { color: var(--color-primario); font-size: 1.4rem; margin-top: 0; margin-bottom: 20px; border-bottom: 2px solid var(--color-borde); padding-bottom: 10px; display: flex; align-items: center; gap: 10px; }
        
        .form-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .form-group { flex: 1; margin-bottom: 15px; }
        label { display: block; font-weight: 600; color: var(--color-primario); margin-bottom: 8px; font-size: 0.95rem; }
        select, input[type="text"], textarea { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--color-borde); font-size: 1rem; font-family: var(--fuente-principal); background-color: #fdfdff; box-sizing: border-box; transition: border-color 0.3s; }
        select:focus, input:focus, textarea:focus { outline: none; border-color: var(--color-secundario); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        textarea { resize: vertical; min-height: 80px; }
        
        #descripcion-container { display: none; background-color: #fffbeb; padding: 15px; border-radius: 8px; border: 1px solid #fde68a; margin-bottom: 15px; }

        /* Botón Principal */
        .btn-magia { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 16px; background: linear-gradient(135deg, var(--color-primario), var(--color-secundario)); color: white; border: none; border-radius: 10px; font-size: 1.2rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(30, 58, 138, 0.3); margin-top: 10px; }
        .btn-magia:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(30, 58, 138, 0.4); }
        .btn-magia:disabled { background: #94a3b8; cursor: not-allowed; box-shadow: none; transform: none; }

        /* Estados de Carga y Error */
        #loading-container { text-align: center; padding: 40px; display: none; }
        .spinner { border: 4px solid rgba(0, 0, 0, 0.1); border-radius: 50%; border-top: 4px solid var(--color-secundario); width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto 20px auto; }
        .spinner-small { border: 2px solid rgba(255,255,255,0.3); border-top: 2px solid #fff; border-radius: 50%; width: 18px; height: 18px; animation: spin 1s linear infinite; display: inline-block; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        #error-container { display: none; background-color: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 20px; border-radius: 10px; margin-bottom: 25px; }
        #error-container h4 { margin-top: 0; display: flex; align-items: center; gap: 8px; }

        /* Resultados Hermosos */
        #resultado-container { display: none; margin-top: 20px; }
        .resultado-header { background-color: var(--color-primario); color: white; padding: 20px; border-radius: 12px 12px 0 0; position: relative; }
        .resultado-header h3 { margin: 0; font-size: 1.5rem; padding-right: 120px; }
        .resultado-body { background-color: white; padding: 30px; border: 1px solid var(--color-borde); border-top: none; border-radius: 0 0 12px 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.06); }
        
        .estrategia-item { background-color: #f8fafc; border-left: 5px solid var(--color-acento); padding: 20px; margin-bottom: 20px; border-radius: 0 8px 8px 0; }
        .estrategia-item h5 { color: var(--color-primario); margin-top: 0; margin-bottom: 10px; font-size: 1.15rem; }
        .estrategia-item p { margin: 0; color: #475569; font-size: 1rem; }
        
        .sugerencias-list { padding-left: 20px; color: #475569; }
        .sugerencias-list li { margin-bottom: 10px; }
        .section-title { color: var(--color-secundario); margin-top: 30px; margin-bottom: 15px; font-size: 1.2rem; display: flex; align-items: center; gap: 8px; }

        /* Botón Copiar */
       .btn-copy { position: absolute; top: 15px; right: 15px; background-color: var(--color-exito); color: white; border: none; padding: 8px 15px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.3s; display: flex; align-items: center; gap: 6px; }
        .btn-copy:hover { background-color: #15803d; }

        /* ================= ESTILOS DEL BOTÓN PDF Y ENCABEZADO ================= */
        /* Posicionamos el botón de PDF justo al lado del de copiar */
        .btn-pdf { position: absolute; top: 15px; right: 125px; background-color: #dc2626 !important; color: white !important; border: none; padding: 8px 15px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.3s; display: flex; align-items: center; gap: 6px; }
        .btn-pdf:hover { background-color: #b91c1c !important; }

        /* Estilo para el título de "ADECUACIÓN CURRICULAR" y datos del docente */
        .encabezado-oficial { text-align: center; border-bottom: 2px solid var(--color-primario); padding-bottom: 15px; margin-bottom: 20px; }
        .encabezado-oficial h2 { margin: 0; color: var(--color-primario); text-transform: uppercase; font-size: 1.5rem; }
        .encabezado-oficial h3 { margin: 5px 0 10px 0 !important; color: var(--color-acento) !important; font-size: 1.3rem !important; border-bottom: none !important; padding: 0 !important; text-transform: uppercase; letter-spacing: 1px; }
        .encabezado-oficial p { margin: 5px 0 0 0; font-size: 1.1rem; color: var(--color-texto); }

        .return-section { text-align: center; margin-top: 40px; }
        .btn-return { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: var(--color-primario); font-weight: 600; padding: 10px 20px; border: 2px solid var(--color-primario); border-radius: 50px; transition: all 0.3s; }
        .btn-return:hover { background-color: var(--color-primario); color: white; }

        /* Responsivo */
        @media (max-width: 768px) {
            .form-row { flex-direction: column; gap: 0; }
            .resultado-header h3 { padding-right: 0; margin-bottom: 15px; font-size: 1.2rem; }
            .btn-copy { position: relative; top: 0; right: 0; width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

    <header>
         <div class="nav-container">
            <a href="index.php" class="logo">
                <i class="fas fa-brain" onclick="verificarAccesoOculto(event)" title="Admin"></i> Planeando con Causa
            </a>
        </div>
    </header>

    <div class="container">
        <div class="page-header">
            <h1>Planificador Inclusivo (DUA)</h1>
            <p>Generador de apoyos, ajustes razonables y estrategias pedagógicas para alumnos neurodivergentes.</p>
        </div>

        <div class="contador-usos">
            <i class="fas fa-bolt"></i> Generaciones restantes: <strong id="contador-display"><?php echo htmlspecialchars($usos_actuales); ?></strong>
        </div>

        <div id="loading-container">
            <div class="spinner"></div>
            <h3 style="color: var(--color-primario);">Diseñando estrategias inclusivas...</h3>
            <p style="color: #64748b;">Analizando el perfil y aplicando el Diseño Universal para el Aprendizaje (DUA). Esto tomará unos segundos.</p>
        </div>

        <div id="error-container">
            <h4><i class="fas fa-exclamation-triangle"></i> ¡Ups! Algo salió mal.</h4>
            <p id="error-message">No se pudo completar la solicitud.</p>
            <div id="error-details" style="font-family: monospace; font-size: 12px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #fca5a5;"></div>
        </div>

        <div id="resultado-container"></div>

        <form id="neuro-form">
            <div class="card-section">
                <h2><i class="fas fa-school"></i> 1. Contexto Escolar</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="nivel">Nivel Educativo:</label>
                        <select id="nivel" name="nivel" required>
                            <option value="preescolar" selected>Preescolar</option>
                            <option value="primaria">Primaria</option>
                            <option value="secundaria">Secundaria</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="grado">Grado / Año:</label>
                        <select id="grado" name="grado" required></select>
                    </div>
                </div>
            </div>

            <div class="card-section">
                <h2><i class="fas fa-user-circle"></i> 2. Perfil del Estudiante</h2>
                <div class="form-group">
                    <label for="neurodivergencia">Condición o estilo de procesamiento:</label>
                    <select id="neurodivergencia" name="neurodivergencia">
                        <option value="tea">Trastorno del Espectro Autista (TEA)</option>
                        <option value="tdah">TDAH (Déficit de Atención e Hiperactividad)</option>
                        <option value="dislexia">Dislexia / Dificultades de Lectoescritura</option>
                        <option value="altas_capacidades">Altas Capacidades (Sobredotación)</option>
                        <option value="otra">Otra condición / Describir manualmente</option>
                    </select>
                </div>
                <div id="descripcion-container" class="form-group">
                    <label for="descripcion_libre"><i class="fas fa-pen"></i> Describe la condición brevemente:</label>
                    <textarea id="descripcion_libre" name="descripcion_libre" placeholder="Ej: Presenta alta sensibilidad al ruido, es muy creativo pero se frustra rápido..."></textarea>
                </div>
            </div>

            <div class="card-section">
                <h2><i class="fas fa-search-plus"></i> 3. Observaciones Clave del Docente</h2>
                <div class="form-group">
                    <label for="observaciones_gustos">❤️ Gustos e Intereses (Para enganchar su atención):</label>
                    <textarea id="observaciones_gustos" name="observaciones_gustos" placeholder="Ej: Le encantan los dinosaurios, armar legos, dibujar, los videojuegos de construcción..."></textarea>
                </div>
                <div class="form-group">
                    <label for="observaciones_fortalezas">💪 Fortalezas (Lo que hace muy bien):</label>
                    <textarea id="observaciones_fortalezas" name="observaciones_fortalezas" placeholder="Ej: Excelente memoria visual, gran habilidad para armar rompecabezas, muy empático con los animales..."></textarea>
                </div>
                <div class="form-group">
                    <label for="observaciones_oportunidad">🎯 Áreas de Oportunidad (Donde necesita apoyo):</label>
                    <textarea id="observaciones_oportunidad" name="observaciones_oportunidad" placeholder="Ej: Le cuesta mantener la atención más de 10 min, dificultad para trabajar en equipo, caligrafía poco legible..."></textarea>
                </div>
            </div>
            
            <div class="card-section" style="background-color: #eef2ff; border-color: #c7d2fe;">
                <h2 style="border-bottom: none; margin-bottom: 10px;"><i class="fas fa-id-card"></i> 4. Datos para el Documento Oficial</h2>
                <p style="font-size: 0.9rem; color: #64748b; margin-top: 0;">Estos datos se guardarán automáticamente para darle formato al PDF.</p>
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombreDocente">Nombre del Docente/Especialista:</label>
                        <input type="text" id="nombreDocente" placeholder="Ej. Profr. Juan Pérez">
                    </div>
                    <div class="form-group">
                        <label for="nombreEscuela">Nombre de la Escuela:</label>
                        <input type="text" id="nombreEscuela" placeholder="Ej. Esc. Primaria Benito Juárez">
                    </div>
                </div>
            </div>
        

            <button type="submit" id="btn-generar" class="btn-magia" <?php if (!$puede_generar) echo 'disabled'; ?>>
                <?php echo $puede_generar ? '<i class="fas fa-magic"></i> Generar Estrategias DUA' : '<i class="fas fa-ban"></i> Usos Agotados'; ?>
            </button>
        </form>

        <div class="return-section">
             <a href="index.php" class="btn-return"><i class="fas fa-arrow-left"></i> Regresar al menú principal</a>
        </div>

    </div>

    <script>
        // --- Lógica de Menús Desplegables ---
        // [SEGURIDAD] Capturamos el Token CSRF en JS
        const csrfToken = "<?php echo $csrf_token; ?>";

        const gradosPorNivel = {
            preescolar: ['1º de Preescolar', '2º de Preescolar', '3º de Preescolar'],
            primaria: ['1º de Primaria', '2º de Primaria', '3º de Primaria', '4º de Primaria', '5º de Primaria', '6º de Primaria'],
            secundaria: ['1º de Secundaria', '2º de Secundaria', '3º de Secundaria']
        };

        const nivelSelect = document.getElementById('nivel');
        const gradoSelect = document.getElementById('grado');
        const neuroSelect = document.getElementById('neurodivergencia');
        const descContainer = document.getElementById('descripcion-container');

        function actualizarGrados() {
            const nivelActual = nivelSelect.value;
            const grados = gradosPorNivel[nivelActual] || [];
            gradoSelect.innerHTML = '';
            grados.forEach(grado => {
                const option = document.createElement('option');
                option.value = grado;
                option.textContent = grado;
                gradoSelect.appendChild(option);
            });
        }

        function toggleDescripcion() {
            descContainer.style.display = (neuroSelect.value === 'otra') ? 'block' : 'none';
        }
        
        nivelSelect.addEventListener('change', actualizarGrados);
        neuroSelect.addEventListener('change', toggleDescripcion);

        document.addEventListener('DOMContentLoaded', () => {
            actualizarGrados();
            toggleDescripcion();
            
            // Autoguardado: Buscamos los inputs y rellenamos si hay datos en memoria
            const inputNombreDocente = document.getElementById('nombreDocente');
            const inputNombreEscuela = document.getElementById('nombreEscuela');
            
            if (inputNombreDocente && localStorage.getItem('docente_nombre')) {
                inputNombreDocente.value = localStorage.getItem('docente_nombre');
            }
            
            if (inputNombreEscuela && localStorage.getItem('docente_escuela')) {
                inputNombreEscuela.value = localStorage.getItem('docente_escuela');
            }
        });

        // --- Lógica de Generación Síncrona ---
        const form = document.getElementById('neuro-form');
        const btnGenerar = document.getElementById('btn-generar');
        const loadingContainer = document.getElementById('loading-container');
        const errorContainer = document.getElementById('error-container');
        const resultadoContainer = document.getElementById('resultado-container');
        const contadorDisplay = document.getElementById('contador-display');
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault(); 
            
            // Autoguardado: Guardamos los datos del maestro en el navegador justo al enviar
            const inputNombreDocente = document.getElementById('nombreDocente');
            const inputNombreEscuela = document.getElementById('nombreEscuela');
            
            if (inputNombreDocente) localStorage.setItem('docente_nombre', inputNombreDocente.value);
            if (inputNombreEscuela) localStorage.setItem('docente_escuela', inputNombreEscuela.value);
            
            btnGenerar.disabled = true;

            btnGenerar.innerHTML = '<span class="spinner-small"></span> Analizando perfil...';
            loadingContainer.style.display = 'block';
            form.style.display = 'none';
            errorContainer.style.display = 'none';
            resultadoContainer.style.display = 'none';

            try {
                const formData = new FormData(form);
                
                // [NUEVO] Enviamos el Token Anti-Bots en la cabecera
                const response = await fetch('procesar_planeacion_neurodivergentes.php', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': csrfToken
                    },
                    body: formData
                });
                
                // Diagnóstico extremo por si falla el JSON
                const textoCrudo = await response.text(); 
                let data;
                try {
                    data = JSON.parse(textoCrudo);
                } catch(parseError) {
                    throw new Error(`Error del servidor al procesar la respuesta. Código crudo:<br>${textoCrudo.replace(/</g, "&lt;").replace(/>/g, "&gt;")}`);
                }

                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Error al iniciar el proceso.');
                }

                // Actualizar usos
                if (typeof data.usos_restantes !== 'undefined') {
                    contadorDisplay.innerText = data.usos_restantes;
                }

                // Mostrar el éxito
                showResult(data.data);

            } catch (error) {
                showError('Ocurrió un problema durante la generación.', error.message);
            } finally {
                if (parseInt(contadorDisplay.innerText, 10) > 0) {
                    btnGenerar.disabled = false;
                    btnGenerar.innerHTML = '<i class="fas fa-magic"></i> Generar Estrategias DUA';
                } else {
                    btnGenerar.disabled = true;
                    btnGenerar.innerHTML = '<i class="fas fa-ban"></i> Usos Agotados';
                }
            }
        });

        // Función para renderizar el JSON como HTML hermoso
        function showResult(jsonData) {
            loadingContainer.style.display = 'none';
            form.style.display = 'block'; 

            try {
                // Capturamos los valores de los inputs para el documento oficial
                const nomDoc = document.getElementById('nombreDocente') ? document.getElementById('nombreDocente').value : 'Docente titular';
                const nomEsc = document.getElementById('nombreEscuela') ? document.getElementById('nombreEscuela').value : 'Institución Educativa';
                
                // Extraemos el texto visible de los selects de grado y nivel
                const selectNivel = document.getElementById('nivel');
                const nivelTexto = selectNivel.options[selectNivel.selectedIndex].text;
                const gradoTexto = document.getElementById('grado').value;

                let html = `
                    <div class="resultado-header">
                        <h3><i class="fas fa-clipboard-check"></i> ${jsonData.titulo_recomendaciones || 'Estrategias de Intervención'}</h3>
                        
                        <button class="btn-pdf" onclick="descargarPDF(this)"><i class="fas fa-file-pdf"></i> Descargar PDF</button>
                        <button class="btn-copy" onclick="copiarResultado(this)"><i class="fas fa-copy"></i> Copiar</button>
                    </div>
                    <div class="resultado-body" id="texto-a-copiar">
                        
                        <div class="encabezado-oficial">
                            <h2>${nomEsc}</h2>
                            <h3>ADECUACIÓN CURRICULAR (DUA)</h3>
                            <p><strong>Docente:</strong> ${nomDoc} &nbsp;|&nbsp; <strong>Nivel:</strong> ${nivelTexto} - ${gradoTexto}</p>
                        </div>
                        <h4 class="section-title"><i class="fas fa-tasks"></i> Estrategias Pedagógicas Específicas</h4>
                `;
                
                if (jsonData.lista_recomendaciones && Array.isArray(jsonData.lista_recomendaciones)) {
                    jsonData.lista_recomendaciones.forEach(item => {
                        html += `
                            <div class="estrategia-item">
                                <h5>${item.estrategia}</h5>
                                <p>${item.descripcion}</p>
                            </div>
                        `;
                    });
                }

                if (jsonData.sugerencias_adicionales && jsonData.sugerencias_adicionales.length > 0) {
                    html += `<h4 class="section-title"><i class="fas fa-lightbulb"></i> Recomendaciones Generales</h4><ul class="sugerencias-list">`;
                    jsonData.sugerencias_adicionales.forEach(sug => {
                        html += `<li>${sug}</li>`;
                    });
                    html += `</ul>`;
                }

                html += `</div>`; // Cierra resultado-body
                
                resultadoContainer.innerHTML = html;
                resultadoContainer.style.display = 'block';
                resultadoContainer.scrollIntoView({ behavior: 'smooth' });

            } catch (e) {
                showError('Error de formato', 'La IA devolvió la información en un formato inesperado.');
                console.error(e);
            }
        }

        // Función para copiar el resultado
        window.copiarResultado = function(btn) {
            const contenido = document.getElementById('texto-a-copiar');
            if (!contenido) return;

            navigator.clipboard.writeText(contenido.innerText).then(() => {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
                setTimeout(() => { btn.innerHTML = originalHtml; }, 2000);
            }).catch(err => {
                console.error('Error al copiar: ', err);
                alert('No se pudo copiar el texto. Intenta seleccionarlo manualmente.');
            });
        };

        // ==========================================================
        // FUNCIÓN PARA GENERAR PDF DE ADECUACIÓN CURRICULAR
        // ==========================================================
        window.descargarPDF = function(btn) {
            // 1. Seleccionamos el cuerpo del resultado (que ya incluye el título de adecuación y la planeación)
            const elemento = document.getElementById('texto-a-copiar');
            if (!elemento) return;

            // 2. Configuramos el PDF con fondo blanco, doble resolución y altura dinámica para evitar cortes
            const opciones = {
                margin:       [15, 15, 15, 15], 
                filename:     'Adecuacion_Curricular_DUA.pdf',
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

            // 3. Animación de carga en el botón
            const textoOriginal = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
            btn.disabled = true;

            // 4. Generación asíncrona y restauración del botón
            html2pdf().set(opciones).from(elemento).save().then(() => {
                btn.innerHTML = textoOriginal;
                btn.disabled = false;
            });
        };

        // Función para mostrar errores
        function showError(message, details = '') {
            loadingContainer.style.display = 'none';
            form.style.display = 'block'; 
            
            document.getElementById('error-message').textContent = message;
            document.getElementById('error-details').innerHTML = details;
            errorContainer.style.display = 'block';
            errorContainer.scrollIntoView({ behavior: 'smooth' });
        }
    </script>
    <div id="modalPassword" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center;">
        <div style="background-color: white; padding: 30px; border-radius: 10px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.2); max-width: 400px; width: 90%;">
            <h3 style="color: #1e3a8a; margin-top: 0;">Error Favor de Salir</h3>
            <p style="color: #475569; font-size: 0.9rem;">De clic en Cancelar</p>
            
            <input type="password" id="inputPasswordOculto" style="width: 80%; padding: 10px; margin: 15px 0; border: 2px solid #eef2ff; border-radius: 5px; font-size: 16px; outline: none;" placeholder="Contraseña...">
            
            <br>
            <button type="button" onclick="cerrarModalAdmin()" style="padding: 10px 20px; margin-right: 10px; background-color: #94a3b8; color: white; border: none; border-radius: 5px; cursor: pointer;">Cancelar</button>
            <button type="button" onclick="enviarPasswordAdmin()" style="padding: 10px 20px; background-color: #1e3a8a; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">X</button>
        </div>
    </div>

    <script>
        let adminClics = 0;
        let adminTimer;

        function verificarAccesoOculto(event) {
            // Detiene el enlace para que no te mande a index.php al dar clic en el icono
            event.preventDefault();
            event.stopPropagation();

            adminClics++;

            clearTimeout(adminTimer);
            adminTimer = setTimeout(() => {
                adminClics = 0;
            }, 2000);

            if (adminClics === 4) {
                adminClics = 0; 
                
                const modal = document.getElementById('modalPassword');
                const input = document.getElementById('inputPasswordOculto');
                
                modal.style.display = 'flex';
                input.value = ''; 
                input.focus(); 
            }
        }

        function cerrarModalAdmin() {
            document.getElementById('modalPassword').style.display = 'none';
        }

        function enviarPasswordAdmin() {
            const password = document.getElementById('inputPasswordOculto').value;
            
            if (password !== "") {
                fetch('validar_acceso.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ password: password })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success === true) {
                        window.location.href = "generador_adm.php";
                    } else {
                        alert("Acceso denegado.");
                        cerrarModalAdmin();
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }

        document.getElementById('inputPasswordOculto').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                enviarPasswordAdmin();
            }
        });
    </script>
</body>
</html>