<?php
// ===== INICIO DEL BLOQUE DE SEGURIDAD Y CONEXIÓN =====
session_start();

// [SEGURIDAD] Cabeceras HTTP Anti-Ataques y Anti-Caché
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once 'conexion.php'; // Incluimos la conexión a la base de datos

// Esta es una función avanzada, requiere el plan Mentor o superior.
$nivel_requerido = 2; 
$plan_activo = $_SESSION['plan_activo'] ?? 0;
if ($plan_activo < $nivel_requerido) {
    // Si no cumple con el plan, lo redirigimos a la página de pagos.
    header('Location: catalogo_de_pagos.html');
    exit();
}

// [SEGURIDAD] Generación de Token CSRF (Anti-Bots)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtenemos los créditos específicos para generar exámenes.
$creditos_examenes = 0; // Valor por defecto
if (isset($_SESSION['usuario_id'])) {
    $userId = $_SESSION['usuario_id'];

    $stmt = $conexion->prepare("SELECT usos_examenes FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        $creditos_examenes = (int)$user['usos_examenes'];
    }
    $stmt->close();
}

// [RENDIMIENTO] Cerramos BD y liberamos la pestaña inmediatamente
if (isset($conexion)) { $conexion->close(); }
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
    <title>Generar Examen NEM - Planeando con Causa</title>
    
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
        select, input, button { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--color-borde); font-size: 1rem; font-family: var(--fuente-principal); background-color: #f8f9fa; }
        select:focus, input:focus { outline: none; border-color: var(--color-acento); box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.2); }
        #btnGenerar { margin-top: 30px; background-color: var(--color-primario); color: white; border: none; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: background-color 0.3s, transform 0.2s; }
        #btnGenerar:hover { background-color: #1c3178; transform: translateY(-2px); }
        #btnGenerar:disabled { background-color: #6c757d; cursor: not-allowed; transform: none; }
        .resultado-section, .form-section { background-color: var(--color-tarjeta); border-radius: 12px; padding: 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); margin-bottom: 40px; }
        .resultado-section h2 { margin-top: 0; color: var(--color-primario); border-bottom: 2px solid var(--color-borde); padding-bottom: 10px; }
        /* ================= ESTILOS DE BOTONES Y ENCABEZADO ================= */
        #btnCopiar { background-color: var(--color-exito); color: white; border: none; font-weight: 600; cursor: pointer; transition: background-color 0.3s; padding: 12px; border-radius: 8px; font-size: 1rem; }
        #btnCopiar:hover { background-color: #15803d; }
        
        .contenedor-botones { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }
        
        #btnDescargarPDF { background-color: #dc2626 !important; color: white !important; border: none !important; font-weight: 600; cursor: pointer; transition: background-color 0.3s; padding: 12px; border-radius: 8px; font-size: 1rem; }
        #btnDescargarPDF:hover { background-color: #b91c1c !important; }

        .encabezado-oficial { text-align: center; border-bottom: 2px solid var(--color-primario); padding-bottom: 15px; margin-bottom: 20px; }
        .encabezado-oficial h2 { margin: 0; color: var(--color-primario); text-transform: uppercase; font-size: 1.5rem; }
        .encabezado-oficial p { margin: 5px 0 0 0; font-size: 1.1rem; color: var(--color-texto); }
        .return-button-container { text-align: center; margin-top: 20px; }
        .btn-return { display: inline-flex; align-items: center; gap: 8px; background-color: var(--color-primario); color: white; padding: 12px 25px; border-radius: 50px; text-decoration: none; font-weight: 600; transition: background-color 0.3s, transform 0.2s; }
        .aviso-usos-agotados { background-color: #fffbe6; color: #854d0e; padding: 15px; border-radius: 8px; border: 1px solid #fde68a; margin-top: 20px; text-align: center; font-weight: 600; }
        .aviso-usos-agotados a { color: var(--color-primario); font-weight: 700; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📝 Generador de Exámenes NEM</h1>
            <p>Créditos restantes para generar exámenes: <strong id="contador-creditos"><?php echo $creditos_examenes; ?></strong></p>
        </header>
        
        <section class="form-section">
            <form id="examenForm">
                <label for="grado">1. Selecciona el grado:</label>
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
                <div id="contenedorAsignatura" style="display:none;"><label for="asignatura">2. Selecciona la Asignatura:</label><select id="asignatura" name="asignatura" onchange="actualizarCamposFormativos()" required></select></div>
                <label for="campoFormativo">3. Selecciona el Campo Formativo:</label><select id="campoFormativo" name="campoFormativo" onchange="actualizarContenidos()" required><option value="">-- Selecciona grado --</option></select>
                <label for="contenido">4. Selecciona el Contenido:</label><select id="contenido" name="contenido" onchange="actualizarPDAs()" required><option value="">-- Selecciona Campo Formativo --</option></select>
                <label for="pda">5. Selecciona el PDA:</label><select id="pda" name="pda" required><option value="">-- Selecciona Contenido --</option></select>
                
                <label for="complejidad">6. Selecciona el nivel de complejidad del examen:</label>
                <select id="complejidad" name="complejidad" required>
                    <option value="">-- Selecciona un nivel --</option>
                    <option value="Básico">Básico (Opción múltiple)</option>
                    <option value="Intermedio">Intermedio (Opción múltiple, respuestas cortas)</option>
                    <option value="Avanzado">Avanzado (Opción múltiple, respuestas abiertas)</option>
                </select>

                <div style="background-color: #eef2ff; padding: 15px; border-radius: 8px; border: 1px solid #c7d2fe; margin-top: 25px; margin-bottom: 25px;">
                    <h4 style="margin-top: 0; color: var(--color-primario); margin-bottom: 15px;">Datos para el documento (Se guardan automáticamente)</h4>
                    
                    <label for="nombreDocente" style="margin-top: 0; font-size: 0.95rem;">Nombre del Docente:</label>
                    <input type="text" id="nombreDocente" placeholder="Ej. Profr. Juan Pérez">
                    
                    <label for="nombreEscuela" style="font-size: 0.95rem;">Nombre de la Escuela:</label>
                    <input type="text" id="nombreEscuela" placeholder="Ej. Esc. Primaria Benito Juárez">
                </div>
                <button type="button" id="btnGenerar">🧠 Generar Examen</button>
            </form>
        </section>

        <section class="resultado-section">
            <h2>📄 Tu Examen Generado "Recuerde revisar para adaptarlo a su grupo"</h2>
            <div id="resultadoExamen">
                <p>Aquí aparecerá tu examen...</p>
            </div>
            
            <div id="contenedorBotones" class="contenedor-botones" style="display:none;">
                <button type="button" id="btnCopiar">📋 Copiar Examen</button>
                <button type="button" id="btnDescargarPDF">📥 Descargar como PDF</button>
            </div>
        </section>

        <div class="return-button-container">
            <a href="index.php" class="btn-return"><span>⬅️</span><span>Regresar a la Página Principal</span></a>
        </div>
    </div>

    <script>
    let creditosRestantes = <?php echo $creditos_examenes; ?>;
    // [SEGURIDAD] Capturamos el Token CSRF en JS
    const csrfToken = "<?php echo $_SESSION['csrf_token'] ?? ''; ?>";

    const btnGenerar = document.getElementById('btnGenerar');
    const contadorCreditosSpan = document.getElementById('contador-creditos');
    const resultadoDiv = document.getElementById('resultadoExamen');
    let datosGradoActual = {};

    // Variables para el PDF y datos del docente
    const contenedorBotones = document.getElementById('contenedorBotones');
    const btnDescargarPDF = document.getElementById('btnDescargarPDF');
    const inputNombreDocente = document.getElementById('nombreDocente');
    const inputNombreEscuela = document.getElementById('nombreEscuela');

    // Leer datos guardados en memoria al cargar
    document.addEventListener('DOMContentLoaded', () => {
        if (localStorage.getItem('docente_nombre')) inputNombreDocente.value = localStorage.getItem('docente_nombre');
        if (localStorage.getItem('docente_escuela')) inputNombreEscuela.value = localStorage.getItem('docente_escuela');
    });

    function mostrarAvisoCreditosAgotados() {
        resultadoDiv.innerHTML = `
            <div class="aviso-usos-agotados">
                Has agotado tus créditos para generar exámenes. <br>
                Si necesitas más, por favor, <a href="servicio_cliente.html">contacta a servicio al cliente</a> o mejora tu plan.
            </div>
        `;
        document.getElementById('btnCopiar').style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (creditosRestantes <= 0) {
            btnGenerar.disabled = true;
            btnGenerar.textContent = '🚫 Créditos Agotados';
            mostrarAvisoCreditosAgotados();
        }
    });

    // ========= INICIO DEL CÓDIGO CON LA CORRECCIÓN DE VALIDACIÓN =========

    async function actualizarOpciones() {
        const gradoSeleccionado = document.getElementById("grado").value;
        const contenedorAsignatura = document.getElementById('contenedorAsignatura');
        const asignaturaSelect = document.getElementById('asignatura');

        document.getElementById('campoFormativo').innerHTML = '<option value="">-- Selecciona --</option>';
        document.getElementById('contenido').innerHTML = '<option value="">-- Selecciona --</option>';
        document.getElementById('pda').innerHTML = '<option value="">-- Selecciona --</option>';
        
        if (!gradoSeleccionado) {
            contenedorAsignatura.style.display = 'none';
            asignaturaSelect.required = false; 
            return;
        }

        const nombreArchivo = gradoSeleccionado.toLowerCase().replace(/º de /g, '_').replace(/ /g, '_') + '.json';

        try {
            const respuesta = await fetch(`datos_plan_basico/${nombreArchivo}`);
            if (!respuesta.ok) throw new Error('Archivo no encontrado para el grado seleccionado.');
            datosGradoActual = await respuesta.json();

            if (gradoSeleccionado.includes('Secundaria')) {
                contenedorAsignatura.style.display = 'block';
                asignaturaSelect.required = true; 
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
                asignaturaSelect.required = false; 
                actualizarCamposFormativos();
            }
        } catch (error) {
            console.error('Error al cargar datos:', error);
            document.getElementById('campoFormativo').innerHTML = '<option value="">-- Error al cargar datos --</option>';
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

    // ========= FIN DEL CÓDIGO CON LA CORRECCIÓN DE VALIDACIÓN =========

    document.getElementById('btnCopiar').addEventListener('click', () => {
        navigator.clipboard.writeText(resultadoDiv.innerText).then(() => {
            const btn = document.getElementById('btnCopiar');
            btn.innerText = '¡Copiado!';
            setTimeout(() => { btn.innerText = '📋 Copiar Examen'; }, 2000);
        });
    });

    // ==========================================================
    // FUNCIÓN PARA GENERAR PDF
    // ==========================================================
    btnDescargarPDF.addEventListener('click', () => {
        const elemento = document.getElementById('resultadoExamen');
        
        const opciones = {
            margin:       [15, 15, 15, 15], 
            filename:     'Examen_NEM.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, backgroundColor: '#ffffff', scrollY: 0, windowHeight: elemento.scrollHeight + 200 },
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
    
    document.getElementById('btnGenerar').addEventListener('click', generarExamen);

    async function generarExamen() {
        const btnCopiar = document.getElementById('btnCopiar');
        if (creditosRestantes <= 0) {
            mostrarAvisoCreditosAgotados();
            return;
        }

        const form = document.getElementById('examenForm');
        if (!form.checkValidity()) {
            alert('Por favor, completa todos los campos antes de generar el examen.');
            form.reportValidity();
            return;
        }

        resultadoDiv.innerHTML = '<p style="text-align:center; color:#1e3a8a;"><span class="spinner-small"></span> Construyendo y validando preguntas del examen... 🧠</p>';
        // Ocultamos el contenedor de botones temporalmente
        contenedorBotones.style.display = 'none';
        btnGenerar.disabled = true;

        // Guardamos los datos en el navegador
        localStorage.setItem('docente_nombre', inputNombreDocente.value);
        localStorage.setItem('docente_escuela', inputNombreEscuela.value);

        const grado = document.getElementById('grado').value;
        const asignatura = document.getElementById('asignatura').value;
        const campoFormativo = document.getElementById('campoFormativo').value;
        const contenido = document.getElementById('contenido').value;
        const pda = document.getElementById('pda').value;
        const complejidad = document.getElementById('complejidad').value;

        const datosParaEnviar = {
            grado,
            asignatura: grado.includes('Secundaria') ? asignatura : 'No aplica',
            campoFormativo,
            contenido,
            pda,
            complejidad
        };

        try {
            const response = await fetch('procesar_examen_nem.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken // [SEGURIDAD] Enviamos el token Anti-Bots
                },
                body: JSON.stringify(datosParaEnviar)
            });
            
            const data = await response.json();

            if (typeof data.creditos_restantes !== 'undefined') {
                creditosRestantes = data.creditos_restantes;
                contadorCreditosSpan.textContent = creditosRestantes;
            }
            
            if (data.success === false) {
                throw new Error(data.error || 'Ocurrió un error desconocido en el servidor.');
            }

            const examenMarkdown = data.examen.examen_texto || "No se pudo generar el contenido del examen.";
            
            // Obtenemos textos para el encabezado
            const gradoSeleccionadoText = document.getElementById('grado').options[document.getElementById('grado').selectedIndex].text;
            const nomDoc = inputNombreDocente.value || 'Docente titular';
            const nomEsc = inputNombreEscuela.value || 'Institución Educativa';

            // Armamos el encabezado y le pegamos el examen procesado por Marked
            const htmlCompleto = `
                <div class="encabezado-oficial">
                    <h2>${nomEsc}</h2>
                    <p><strong>Docente:</strong> ${nomDoc} &nbsp;|&nbsp; <strong>Examen de:</strong> ${gradoSeleccionadoText}</p>
                </div>
                <div class="markdown-content">${marked.parse(examenMarkdown)}</div>
            `;

            resultadoDiv.innerHTML = htmlCompleto;
            
            if (examenMarkdown) {
                // Mostramos el contenedor con los botones
                contenedorBotones.style.display = 'flex';
            }

        } catch (error) {
            console.error('Error:', error);
            resultadoDiv.innerHTML = `<p style="color:red;"><strong>Ocurrió un error.</strong><br>${error.message}</p>`;
        } finally {
            if (creditosRestantes > 0) {
                btnGenerar.disabled = false;
            } else {
                btnGenerar.disabled = true;
                btnGenerar.textContent = '🚫 Créditos Agotados';
                mostrarAvisoCreditosAgotados();
            }
        }
    }
</script>
</body>
</html>