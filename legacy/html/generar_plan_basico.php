<?php
// ===== INICIO DEL BLOQUE DE SEGURIDAD Y CONEXIÓN =====

// 1. Iniciamos la sesión. Esto permite que el servidor recuerde quién está conectado.
session_start();

// 2. SEGURIDAD: Prevenimos el Clickjacking y XSS.
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// 3. Verificamos si el usuario está logueado.
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// 4. SEGURIDAD AVANZADA (CSRF): Token anti-falsificación.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
}

// 5. Incluimos la conexión a tu base de datos MySQL.
require_once 'conexion.php';

// 6. Verificamos que el usuario tenga el nivel adecuado.
$nivel_requerido = 1; 
$plan_activo = $_SESSION['plan_activo'] ?? 0;

if ($plan_activo < $nivel_requerido) {
    header('Location: catalogo_de_pagos.php'); // Ojo: lo cambié a .php por consistencia con tus otros archivos, verifícalo.
    exit(); 
}

// 7. Buscamos en la base de datos cuántos usos le quedan al usuario.
$usos_plan_basico = 0; 

if (isset($_SESSION['usuario_id'])) {
    $userId = $_SESSION['usuario_id'];
    $stmt = $conexion->prepare("SELECT usos_plan_basico FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute(); 
    $result = $stmt->get_result(); 
    
    if ($user = $result->fetch_assoc()) {
        $usos_plan_basico = (int)$user['usos_plan_basico'];
        $_SESSION['usos_plan_basico'] = $usos_plan_basico; 
    }
    $stmt->close(); 
}

// [RENDIMIENTO] Cerramos BD y liberamos sesión de inmediato
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
    <title>Generar Planeación Básica - Planeando con Causa</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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

        body { font-family: var(--fuente-principal); background-color: var(--color-fondo); margin: 0; color: var(--color-texto); line-height: 1.6; }
        .container { max-width: 900px; margin: 0 auto; padding: 40px 20px; }
        header { text-align: center; margin-bottom: 40px; }
        header h1 { font-size: 2.5rem; color: var(--color-primario); margin: 0; }
        label { display: block; margin-top: 20px; margin-bottom: 8px; font-weight: 600; color: var(--color-primario); }
        select, input, button { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--color-borde); font-size: 1rem; font-family: var(--fuente-principal); background-color: #f8f9fa; box-sizing: border-box; }
        select:focus, input:focus { outline: none; border-color: var(--color-acento); box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.2); }
        select[multiple] { height: 150px; }
        small { font-size: 0.85rem; color: #64748b; display: block; margin-top: 5px; }
        
        #btnGenerar { margin-top: 30px; background-color: var(--color-primario); color: white; border: none; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: background-color 0.3s, transform 0.2s; }
        #btnGenerar:hover { background-color: #1c3178; transform: translateY(-2px); }
        #btnGenerar:disabled { background-color: #6c757d; cursor: not-allowed; transform: none; }
        
        .resultado-section, .form-section { background-color: var(--color-tarjeta); border-radius: 12px; padding: 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); margin-bottom: 40px; }
        .resultado-section h2 { margin-top: 0; color: var(--color-primario); border-bottom: 2px solid var(--color-borde); padding-bottom: 10px; }
        
        #btnCopiar { background-color: var(--color-exito); color: white; margin-top: 20px; border: none; font-weight: 600; cursor: pointer; transition: background-color 0.3s; }
        #btnCopiar:hover { background-color: #15803d; }
        #btnDescargarPDF { background-color: #dc2626; color: white; margin-top: 10px; border: none; font-weight: 600; cursor: pointer; transition: background-color 0.3s; padding: 12px; border-radius: 8px; font-size: 1rem; }
        #btnDescargarPDF:hover { background-color: #b91c1c; }
        
        .contenedor-botones { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }
        .aviso { background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; border: 1px solid #ffeeba; margin-top: 20px; }
        #resultadoPlaneacion h3 { color: var(--color-primario); border-bottom: 2px solid var(--color-acento); padding-bottom: 5px; margin-top: 25px; }
        #resultadoPlaneacion h4 { color: #334155; margin-top: 20px; margin-bottom: 10px; }
        #resultadoPlaneacion ul { padding-left: 20px; }
        
        .return-button-container { text-align: center; margin-top: 20px; }
        .btn-return { display: inline-flex; align-items: center; gap: 8px; background-color: var(--color-primario); color: white; padding: 12px 25px; border-radius: 50px; text-decoration: none; font-weight: 600; transition: background-color 0.3s, transform 0.2s; }
        .btn-return:hover { background-color: #0b213a; transform: translateY(-2px); }
        footer { text-align: center; padding: 20px; margin-top: 40px; color: #64748b; }
        
        .aviso-usos-agotados { background-color: #fffbe6; color: #854d0e; padding: 15px; border-radius: 8px; border: 1px solid #fde68a; margin-top: 20px; text-align: center; font-weight: 600; }
        .aviso-usos-agotados a { color: var(--color-primario); font-weight: 700; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📘 Generador de Planeación Básica</h1>
            <p>Usos restantes para este plan: <strong id="contador-usos"><?php echo htmlspecialchars($usos_plan_basico, ENT_QUOTES, 'UTF-8'); ?></strong></p>
        </header>
        
        <section class="form-section">
            <form id="planeacionForm">
                <label for="grado">1. Selecciona el grado para tu planeación:</label>
                <select id="grado" name="grado" onchange="actualizarOpciones()" required>
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
                    <label for="asignatura">2. Selecciona la Asignatura:</label>
                    <select id="asignatura" name="asignatura" onchange="actualizarCamposFormativos()"></select>
                </div>

                <label for="campoFormativo">3. Selecciona el Campo Formativo:</label>
                <select id="campoFormativo" name="campoFormativo" onchange="actualizarContenidos()" required>
                    <option value="">-- Selecciona grado (y asignatura si aplica) --</option>
                </select>

                <label for="contenido">4. Selecciona el Contenido:</label>
                <select id="contenido" name="contenido" onchange="actualizarPDAs()" required>
                    <option value="">-- Selecciona primero un Campo Formativo --</option>
                </select>

                <label for="pda">5. Selecciona el PDA:</label>
                <select id="pda" name="pda" required>
                    <option value="">-- Selecciona primero un Contenido --</option>
                </select>

                <label for="ejeArticulador">6. Selecciona Ejes Articuladores:</label>
                <select id="ejeArticulador" name="ejesArticuladores[]" multiple required>
                    <option value="Inclusión">Inclusión</option>
                    <option value="Interculturalidad crítica">Interculturalidad crítica</option>
                    <option value="Pensamiento crítico">Pensamiento crítico</option>
                    <option value="Igualdad de género">Igualdad de género</option>
                    <option value="Vida saludable">Vida saludable</option>
                    <option value="Apropiación de las culturas a través de la lectura y la escritura">Apropiación de las culturas a través de la lectura y la escritura</option>
                    <option value="Artes y experiencias estéticas">Artes y experiencias estéticas</option>
                </select>
                <small>Puedes seleccionar varios manteniendo presionada la tecla Ctrl (o Cmd en Mac) y haciendo clic.</small>

                <label for="tiempo">7. Selecciona la duración de la planeación:</label>
                <select id="tiempo" name="tiempo" required>
                    <option value="">-- Selecciona el tiempo --</option>
                    <option value="5 días">5 días</option>
                    <option value="10 días">10 días</option>
                </select>

                <button type="button" id="btnGenerar">📄 Generar Planeación</button>
            </form>
        </section>

        <section class="resultado-section" style="display:none;" id="seccionResultadoContenedor">
            <h2>📝 Tu Planeación Generada</h2>
            <div id="resultadoPlaneacion"></div>
            <div id="contenedorBotones" class="contenedor-botones" style="display:none;">
                <button type="button" id="btnCopiar">📋 Copiar Planeación</button>
                <button type="button" id="btnDescargarPDF">📥 Descargar como PDF</button>
            </div>
        </section>

        <div class="return-button-container">
            <a href="index.php" class="btn-return"><span>⬅️</span><span>Regresar a la Página Principal</span></a>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Planeando con Causa | Todos los derechos reservados</p>
    </footer>

    <script>
        let usosRestantes = parseInt(<?php echo (int)$usos_plan_basico; ?>, 10);
        const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";

        const btnGenerar = document.getElementById('btnGenerar');
        const contadorUsosSpan = document.getElementById('contador-usos');
        const resultadoDiv = document.getElementById('resultadoPlaneacion');
        const seccionResultadoContenedor = document.getElementById('seccionResultadoContenedor');
        const btnCopiar = document.getElementById('btnCopiar');
        const contenedorBotones = document.getElementById('contenedorBotones');
        const btnDescargarPDF = document.getElementById('btnDescargarPDF');
        const formSection = document.querySelector('.form-section');

        function mostrarAvisoUsosAgotados() {
            seccionResultadoContenedor.style.display = 'block';
            resultadoDiv.innerHTML = `
                <div class="aviso-usos-agotados">
                    Has agotado tus generaciones para el plan básico. <br>
                    Si necesitas más, por favor, <a href="servicio_cliente.html">contacta a servicio al cliente</a> o mejora tu plan.
                </div>
            `;
            btnCopiar.style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (usosRestantes <= 0) {
                btnGenerar.disabled = true;
                btnGenerar.textContent = '🚫 Usos Agotados';
                mostrarAvisoUsosAgotados();
            }
        });
        
        let datosGradoActual = {};
        
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
                    Object.keys(datosGradoActual).forEach(campoFormativo => {
                        const asignaturasEnCampo = datosGradoActual[campoFormativo];
                        Object.keys(asignaturasEnCampo).forEach(asignatura => asignaturasUnicas.add(asignatura));
                    });
                    asignaturasUnicas.forEach(asignatura => {
                        let option = document.createElement("option");
                        option.value = asignatura;
                        option.textContent = asignatura;
                        asignaturaSelect.appendChild(option);
                    });
                } else {
                    contenedorAsignatura.style.display = 'none';
                    actualizarCamposFormativos();
                }
            } catch (error) {
                console.error('Error al cargar datos:', error);
                document.getElementById('campoFormativo').innerHTML = '<option value="">-- Datos no disponibles --</option>';
            }
        }

        function actualizarCamposFormativos() {
            const grado = document.getElementById('grado').value;
            const asignatura = document.getElementById('asignatura').value;
            const campoFormativoSelect = document.getElementById("campoFormativo");
            campoFormativoSelect.innerHTML = '<option value="">-- Selecciona Campo Formativo --</option>';
            
            if (!grado.includes('Secundaria')) {
                 Object.keys(datosGradoActual).forEach(campo => {
                    let option = document.createElement("option");
                    option.value = campo;
                    option.textContent = campo;
                    campoFormativoSelect.appendChild(option);
                });
            } else {
                Object.keys(datosGradoActual).forEach(campo => {
                    if(datosGradoActual[campo] && datosGradoActual[campo].hasOwnProperty(asignatura)) {
                        let option = document.createElement("option");
                        option.value = campo;
                        option.textContent = campo;
                        campoFormativoSelect.appendChild(option);
                    }
                });
            }
        }

        function actualizarContenidos() {
            const campoFormativo = document.getElementById('campoFormativo').value;
            const asignatura = document.getElementById('asignatura').value;
            const grado = document.getElementById('grado').value;
            const contenidoSelect = document.getElementById("contenido");

            contenidoSelect.innerHTML = '<option value="">-- Selecciona Contenido --</option>';
            document.getElementById("pda").innerHTML = '<option value="">-- Selecciona --</option>';
            
            let datosContenido;
            if (grado.includes('Secundaria')) {
                if(!asignatura || !campoFormativo || !datosGradoActual[campoFormativo] || !datosGradoActual[campoFormativo][asignatura]) return;
                datosContenido = datosGradoActual[campoFormativo][asignatura];
            } else {
                if(!campoFormativo || !datosGradoActual[campoFormativo]) return;
                datosContenido = datosGradoActual[campoFormativo];
            }

            Object.keys(datosContenido).forEach(contenido => {
                let option = document.createElement("option");
                option.value = contenido;
                option.textContent = contenido;
                option.title = contenido; 
                contenidoSelect.appendChild(option);
            });
        }

        function actualizarPDAs() {
            const campoFormativo = document.getElementById('campoFormativo').value;
            const asignatura = document.getElementById('asignatura').value;
            const grado = document.getElementById('grado').value;
            const contenido = document.getElementById("contenido").value;
            const pdaSelect = document.getElementById("pda");

            pdaSelect.innerHTML = '<option value="">-- Selecciona PDA --</option>';

            let pdas;
             if (grado.includes('Secundaria')) {
                if(!contenido || !datosGradoActual[campoFormativo][asignatura] || !datosGradoActual[campoFormativo][asignatura][contenido]) return;
                pdas = datosGradoActual[campoFormativo][asignatura][contenido];
            } else {
                if(!contenido || !datosGradoActual[campoFormativo] || !datosGradoActual[campoFormativo][contenido]) return;
                pdas = datosGradoActual[campoFormativo][contenido];
            }
            
            pdas.forEach(pda => {
                let option = document.createElement("option");
                option.value = pda;
                option.textContent = pda;
                option.title = pda;
                pdaSelect.appendChild(option);
            });
        }

        btnCopiar.addEventListener('click', () => {
            const textoParaCopiar = document.getElementById('resultadoPlaneacion').innerText;
            navigator.clipboard.writeText(textoParaCopiar).then(() => {
                btnCopiar.innerText = '¡Copiado!';
                setTimeout(() => { btnCopiar.innerText = '📋 Copiar Planeación'; }, 2000);
            });
        });

        btnDescargarPDF.addEventListener('click', () => {
            const elemento = document.getElementById('resultadoPlaneacion');
            const opciones = {
                margin:       [15, 15, 15, 15], 
                filename:     'Planeacion_Didactica.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, backgroundColor: '#ffffff', scrollY: 0 },
                jsPDF:        { unit: 'mm', format: 'letter', orientation: 'portrait' },
                pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
            };

            const textoOriginal = btnDescargarPDF.innerHTML;
            btnDescargarPDF.innerHTML = '⏳ Generando PDF...';
            btnDescargarPDF.disabled = true;

            html2pdf().set(opciones).from(elemento).save().then(() => {
                btnDescargarPDF.innerHTML = textoOriginal;
                btnDescargarPDF.disabled = false;
            });
        });
        
        btnGenerar.addEventListener('click', generarPlaneacionIA);

        async function generarPlaneacionIA() {
            const grado = document.getElementById('grado').value;
            const asignatura = document.getElementById('asignatura').value;
            const campoFormativo = document.getElementById('campoFormativo').value;
            const contenido = document.getElementById('contenido').value;
            const pda = document.getElementById('pda').value;
            const tiempo = document.getElementById('tiempo').value;
            
            const selectEjes = document.getElementById('ejeArticulador');
            const ejesSeleccionados = Array.from(selectEjes.selectedOptions).map(opt => opt.value).join(', ');

            if (!grado || !campoFormativo || !contenido || !pda || !tiempo || !ejesSeleccionados) {
                alert('Por favor, completa todos los campos requeridos antes de generar la planeación.');
                return;
            }

            formSection.style.display = 'none'; 
            seccionResultadoContenedor.style.display = 'block'; 
            contenedorBotones.style.display = 'none'; 
            
            resultadoDiv.innerHTML = '<p style="text-align: center; font-size: 1.2rem; color: #1e3a8a;">🧠 Analizando pedagogía y construyendo tu planeación...<br><br><span style="font-size:0.9rem; color:#64748b;">Este proceso toma alrededor de 15 a 30 segundos. Por favor, no recargues la página.</span></p>';
            
            btnGenerar.disabled = true;
            btnGenerar.innerHTML = '⏳ Generando...';

            let promptText = `Grado: ${grado}\nCampo Formativo: ${campoFormativo}\n`;
            if (asignatura && document.getElementById('contenedorAsignatura').style.display !== 'none') {
                promptText += `Asignatura: ${asignatura}\n`;
            }
            promptText += `Contenido: ${contenido}\nPDA: ${pda}\nEjes Articuladores: ${ejesSeleccionados}\nDuración/Sesiones: ${tiempo}`;

            try {
                const response = await fetch('procesar_planeacion.php', {
                    method: 'POST', 
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken 
                    },
                    body: JSON.stringify({ prompt: promptText }) 
                });

                const data = await response.json();

                if (!response.ok || data.status === 'error' || data.success === false) {
                    throw new Error(data.error || data.details || 'Error al procesar la solicitud en el servidor.');
                }

                if (data.status === 'completo') {
                    usosRestantes = data.usos_restantes;
                    contadorUsosSpan.innerText = usosRestantes;

                    const plan = data.plan;
                    let htmlRespuesta = `<div class="plan-resultado">`;
                    
                    const escapeHTML = str => str.replace(/[&<>'"]/g, 
                        tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
                    );

                    htmlRespuesta += `<h3>${escapeHTML(plan.datos_principales.proyecto || 'Proyecto de Planeación')}</h3>`;
                    htmlRespuesta += `<p><strong>Metodología NEM:</strong> ${escapeHTML(plan.datos_principales.metodologia)}</p>`;
                    htmlRespuesta += `<p><strong>PDA:</strong> ${escapeHTML(plan.datos_principales.pda)}</p>`;
                    
                    htmlRespuesta += `<h4><i class="fas fa-box-open"></i> Materiales Necesarios</h4><ul>`;
                    if(plan.lista_materiales && plan.lista_materiales.length > 0) {
                        plan.lista_materiales.forEach(mat => {
                            htmlRespuesta += `<li>${escapeHTML(mat)}</li>`;
                        });
                    } else {
                        htmlRespuesta += `<li>No se especificaron materiales particulares.</li>`;
                    }
                    htmlRespuesta += `</ul>`;
                    
                    htmlRespuesta += `<h4><i class="fas fa-chalkboard-teacher"></i> Planeación Didáctica</h4>`;
                    
                    if (typeof marked !== 'undefined') {
                        htmlRespuesta += `<div class="markdown-content">${marked.parse(plan.planeacion_completa)}</div>`;
                    } else {
                        htmlRespuesta += `<div class="markdown-content" style="white-space: pre-wrap;">${escapeHTML(plan.planeacion_completa)}</div>`;
                    }
                    
                    htmlRespuesta += `<h4><i class="fas fa-lightbulb"></i> Sugerencias Didácticas</h4><ul>`;
                    if(plan.sugerencias_didacticas && plan.sugerencias_didacticas.length > 0) {
                        plan.sugerencias_didacticas.forEach(sug => {
                            htmlRespuesta += `<li>${escapeHTML(sug)}</li>`;
                        });
                    }
                    htmlRespuesta += `</ul>`;
                    
                    htmlRespuesta += `<div class="aviso" style="margin-top:20px;"><strong>Aviso:</strong> ${escapeHTML(plan.aviso)}</div>`;
                    htmlRespuesta += `</div>`;

                    resultadoDiv.innerHTML = htmlRespuesta;
                    contenedorBotones.style.display = 'flex';
                }

            } catch (error) {
                console.error('Error del sistema:', error);
                resultadoDiv.innerHTML = `
                    <div class="aviso" style="background-color: #fee2e2; border-color: #ef4444; color: #b91c1c;">
                        <h3 style="margin-top:0; color: #b91c1c; border-bottom:none;">Aviso del Sistema</h3>
                        <p style="margin-bottom:0;">${error.message}<br><br><strong>Nota:</strong> Su solicitud no fue procesada. Sus créditos están a salvo.</p>
                    </div>`;
            } finally {
                formSection.style.display = 'block'; 
                
                if (usosRestantes > 0) {
                    btnGenerar.disabled = false;
                    btnGenerar.innerHTML = '📄 Generar Planeación';
                } else {
                    btnGenerar.disabled = true;
                    btnGenerar.innerHTML = '🚫 Usos Agotados';
                    mostrarAvisoUsosAgotados();
                }
            }
        }
    </script>
</body>
</html>