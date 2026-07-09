<?php
// generar_planeacion_fisica.php (VERSIÓN ORIGINAL SIN POLLING)

session_start();
require_once 'conexion.php'; 

// --- Verificación de sesión USANDO 'usuario_id' ---
if (!isset($_SESSION['usuario_id'])) { 
    header("Location: login.php"); 
    exit();
}
$usuario_id = $_SESSION['usuario_id'];

// --- Verificación del Nivel del Plan ---
$nivel_requerido = 1;
$plan_activo = $_SESSION['plan_activo'] ?? 0;
if ($plan_activo < $nivel_requerido) {
    header('Location: catalogo_de_pagos.html');
    exit();
}

// --- Obtenemos los usos restantes de 'usos_fisica' ---
$tipo_uso_actual = 'usos_fisica';
$usos_restantes = 0;
$stmt = $conexion->prepare("SELECT {$tipo_uso_actual} FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();
if ($user_data = $resultado->fetch_assoc()) {
    $usos_restantes = (int)$user_data[$tipo_uso_actual];
    $_SESSION['usos_fisica'] = $usos_restantes; // Sincronizar sesión
}
$stmt->close();
$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Planeación de Educación Física</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root { --color-primario: #005f73; --color-secundario: #0a9396; --color-acento: #ee9b00; --color-fondo: #f8f9fa; --color-texto: #334155; --color-tarjeta: #ffffff; --color-borde: #e2e8f0; --fuente-principal: 'Poppins', sans-serif; }
        body { font-family: var(--fuente-principal); background-color: var(--color-fondo); margin: 0; color: var(--color-texto); line-height: 1.6; }
        .container { max-width: 900px; margin: 0 auto; padding: 40px 20px; }
        header { text-align: center; margin-bottom: 40px; }
        header h1 { font-size: 2.5rem; color: var(--color-primario); margin: 0; }
        header p { font-size: 1.1rem; color: var(--color-texto); }
        .card { background-color: var(--color-tarjeta); border-radius: 12px; padding: 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); margin-bottom: 40px; }
        label { display: block; margin-top: 20px; margin-bottom: 8px; font-weight: 600; color: var(--color-primario); }
        select { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--color-borde); font-size: 1rem; font-family: var(--fuente-principal); background-color: #f8f9fa; box-sizing: border-box; }
        .card h2 { margin-top: 0; color: var(--color-primario); border-bottom: 2px solid var(--color-borde); padding-bottom: 10px; }
        .placeholder { color: #64748b; font-style: italic; text-align: center; }
        .actions-container { display: flex; flex-direction: column; gap: 15px; margin-top: 20px; }
        .btn { padding: 15px; border-radius: 8px; border: none; font-size: 1rem; font-weight: 700; font-family: var(--fuente-principal); color: white; cursor: pointer; text-align: center; text-decoration: none; transition: background-color 0.3s, transform 0.2s; }
        .btn:disabled { background-color: #94a3b8; cursor: not-allowed; transform: none; }
        .btn:hover:not(:disabled) { transform: translateY(-2px); }
        .btn-generar { background-color: var(--color-secundario); }
        .btn-copiar { background-color: #0d9488; }
        /* ================= ESTILOS NUEVOS (PDF Y ENCABEZADO) ================= */
        /* Estilo rojo para diferenciar el botón de descarga PDF */
        .btn-descargar-pdf { background-color: #dc2626; }
        
        /* Estilos del encabezado profesional que solo se verá en el documento generado */
        .encabezado-oficial { text-align: center; border-bottom: 2px solid var(--color-primario); padding-bottom: 15px; margin-bottom: 20px; }
        .encabezado-oficial h2 { margin: 0; color: var(--color-primario); text-transform: uppercase; font-size: 1.5rem; }
        .encabezado-oficial p { margin: 5px 0 0 0; font-size: 1.1rem; color: var(--color-texto); }

        .btn-return { background-color: var(--color-primario); }
        .no-usos-mensaje { text-align: center; font-weight: 600; color: var(--color-primario); background-color: #eef2ff; padding: 15px; border-radius: 8px; }
        .btn-contacto { background-color: var(--color-acento); }
        .btn-contacto:hover { background-color: #d35400; }
        footer { text-align: center; padding: 20px; margin-top: 40px; color: #64748b; }
        
        .markdown-content h1, .markdown-content h2, .markdown-content h3 { color: var(--color-primario); border-bottom: 2px solid var(--color-secundario); padding-bottom: 5px; margin-top: 20px; }
        .markdown-content ul { padding-left: 20px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🤸‍♀️ Generador de Planeación de Educación Física</h1>
            <p>Usos restantes: <strong id="contador-usos"><?php echo $usos_restantes; ?></strong></p>
        </header>
        
        <section class="card">
            <label for="grado">1. Selecciona el Grado:</label>
            <select id="grado" name="grado" required>
                <option value="">-- Selecciona un grado --</option>
                <optgroup label="Preescolar">
                    <option value="Primero de Preescolar">1º de Preescolar</option>
                    <option value="Segundo de Preescolar">2º de Preescolar</option>
                    <option value="Tercero de Preescolar">3º de Preescolar</option>
                </optgroup>
                <optgroup label="Primaria">
                    <option value="Primero de Primaria">1º de Primaria</option>
                    <option value="Segundo de Primaria">2º de Primaria</option>
                    <option value="Tercero de Primaria">3º de Primaria</option>
                    <option value="Cuarto de Primaria">4º de Primaria</option>
                    <option value="Quinto de Primaria">5º de Primaria</option>
                    <option value="Sexto de Primaria">6º de Primaria</option>
                </optgroup>
                <optgroup label="Secundaria">
                    <option value="Primero de Secundaria">1º de Secundaria</option>
                    <option value="Segundo de Secundaria">2º de Secundaria</option>
                    <option value="Tercero de Secundaria">3º de Secundaria</option>
                </optgroup>
            </select>
            <label for="contenido">2. Selecciona el Contenido:</label>
            <select id="contenido" name="contenido" required>
                <option value="">-- Primero selecciona un grado --</option>
            </select>
            <label for="pda">3. Selecciona el PDA:</label>
            <select id="pda" name="pda" required>
                <option value="">-- Primero selecciona un contenido --</option>
            </select>

            <div style="background-color: #e2e8f0; padding: 15px; border-radius: 8px; margin-top: 25px;">
                <h4 style="margin-top: 0; color: var(--color-primario);"><i class="fas fa-id-card"></i> Datos para el documento</h4>
                
                <label for="nombreDocente" style="margin-top: 10px;">Nombre del Docente:</label>
                <input type="text" id="nombreDocente" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--color-borde);" placeholder="Ej. Profr. Juan Pérez">
                
                <label for="nombreEscuela" style="margin-top: 10px;">Nombre de la Escuela:</label>
                <input type="text" id="nombreEscuela" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--color-borde);" placeholder="Ej. Esc. Primaria Benito Juárez">
            </div>
            </section>

        <section class="card">
            <h2>🤖 Planeación Generada</h2>
            <div id="resultado-ia">
                <p class="placeholder">Aquí aparecerá la planeación generada por la IA.</p>
            </div>
            
            <div class="actions-container" id="actions-container">
            </div>
        </section>
    </div>

    <footer>
        <p>&copy; 2026 Recursos para Docentes | Educación Física | La herramienta puede generar errores verifica la información importante</p>
    </footer>

    <script>
        let usosRestantes = <?php echo $usos_restantes; ?>;
        let datosEducacionFisica = {};
        
        const gradoSelect = document.getElementById('grado');
        const contenidoSelect = document.getElementById('contenido');
        const pdaSelect = document.getElementById('pda');
        const resultadoIADiv = document.getElementById('resultado-ia');
        const contadorUsosSpan = document.getElementById('contador-usos');
        const actionsContainer = document.getElementById('actions-container');
        // ================= VARIABLES DEL DOCENTE Y MEMORIA LOCAL =================
        // Capturamos los inputs recién creados
        const inputNombreDocente = document.getElementById('nombreDocente');
        const inputNombreEscuela = document.getElementById('nombreEscuela');

        // Al cargar la página, verificamos si el navegador recuerda los datos del maestro
        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('docente_nombre')) inputNombreDocente.value = localStorage.getItem('docente_nombre');
            if (localStorage.getItem('docente_escuela')) inputNombreEscuela.value = localStorage.getItem('docente_escuela');
        });


        // Función original para actualizar botones
        function actualizarControles(mostrarBotonCopiar = false) {
            let htmlBotones = '';
            if (usosRestantes > 0) {
                htmlBotones += `<button type="button" id="btnGenerarIA" class="btn btn-generar"><i class="fas fa-magic"></i> Generar Planeación</button>`;
            } else {
                htmlBotones += `<div class="no-usos-mensaje">Si necesita más planeaciones, que se contacte con el soporte o con la ayuda del cliente.</div>`;
                htmlBotones += `<a href="servicio_cliente.html" class="btn btn-contacto">📞 Contactar a Servicio al Cliente</a>`;
            }
            
            htmlBotones += `<a href="index.php" class="btn btn-return">⬅️ Regresar</a>`;
            
            // Controlamos la visibilidad compartida para el botón de Copiar y el de PDF
            const displayStyle = mostrarBotonCopiar ? 'block' : 'none';
            
            // Inyectamos el botón de Copiar
            htmlBotones += `<button type="button" id="btnCopiar" class="btn btn-copiar" style="display:${displayStyle};"><i class="fas fa-copy"></i> Copiar Planeación</button>`;
            
            // Inyectamos nuestro nuevo botón de Descargar PDF justo debajo
            htmlBotones += `<button type="button" id="btnDescargarPDF" class="btn btn-descargar-pdf" style="display:${displayStyle};"><i class="fas fa-file-pdf"></i> Descargar como PDF</button>`;
            
            // Plasmamos los botones en el HTML
            actionsContainer.innerHTML = htmlBotones;
            
            // Reactivamos los "escuchadores de clics" (EventListeners) para cada botón
            const btnGenerarIA = document.getElementById('btnGenerarIA');
            if (btnGenerarIA) {
                btnGenerarIA.addEventListener('click', generarPlaneacionIA);
            }
            document.getElementById('btnCopiar').addEventListener('click', copiarPlaneacion);
            
            // Si los botones de resultados están visibles, activamos la función del PDF
            const btnDescargarPDF = document.getElementById('btnDescargarPDF');
            if (btnDescargarPDF && mostrarBotonCopiar) {
                btnDescargarPDF.addEventListener('click', descargarPlaneacionPDF);
            }
        }

        // Carga de JSON ORIGINAL
        document.addEventListener('DOMContentLoaded', async () => {
            actualizarControles(false);
            try {
                const response = await fetch('datos_plan_basico/contenidos_educacion_fisica.json');
                if (!response.ok) throw new Error('No se pudo cargar el archivo "contenidos_educacion_fisica.json".');
                datosEducacionFisica = await response.json();
            } catch (error) {
                console.error('Error al cargar datos:', error);
                alert(`Error al cargar los datos base: ${error.message}`);
            }
        });

        // Funciones originales de actualización de selects
        function actualizarContenidos() {
            const gradoSeleccionado = gradoSelect.value;
            contenidoSelect.innerHTML = '<option value="">-- Selecciona un contenido --</option>';
            pdaSelect.innerHTML = '<option value="">-- Primero selecciona un contenido --</option>'; 
            if (gradoSeleccionado && datosEducacionFisica[gradoSeleccionado]) {
                const contenidos = datosEducacionFisica[gradoSeleccionado];
                for (const nombreContenido in contenidos) {
                    const option = new Option(nombreContenido, nombreContenido);
                    contenidoSelect.add(option);
                }
            }
        }
        function actualizarPDAs() {
            const gradoSeleccionado = gradoSelect.value;
            const contenidoSeleccionado = contenidoSelect.value;
            pdaSelect.innerHTML = '<option value="">-- Selecciona un PDA --</option>';
            if (gradoSeleccionado && contenidoSeleccionado && datosEducacionFisica[gradoSeleccionado][contenidoSeleccionado]) {
                const pdas = datosEducacionFisica[gradoSeleccionado][contenidoSeleccionado];
                pdas.forEach(pda => {
                    const option = new Option(pda, pda);
                    pdaSelect.add(option);
                });
            }
        }

        /**
         * LÓGICA DE GENERACIÓN MODIFICADA (SIN POLLING, SÍNCRONA)
         */
        async function generarPlaneacionIA() {
            const grado = gradoSelect.value;
            const contenido = contenidoSelect.value;
            const pda = pdaSelect.value;
            if (!grado || !contenido || !pda) { return alert('Por favor, completa todos los pasos.'); }

            resultadoIADiv.innerHTML = '<p class="placeholder"><i class="fas fa-spinner fa-spin"></i> Iniciando generación, por favor espera de 30 a 90 segundos... 🧠</p>';
            actualizarControles(false); // Oculta los botones de resultados
            
            // Guardamos los nombres escritos por el docente en el navegador
            localStorage.setItem('docente_nombre', inputNombreDocente.value);
            localStorage.setItem('docente_escuela', inputNombreEscuela.value);
            
            if(document.getElementById('btnGenerarIA')) {
                document.getElementById('btnGenerarIA').disabled = true;
                document.getElementById('btnGenerarIA').textContent = 'Generando...';
            }
            
            try {
                // LLAMADA DIRECTA AL NUEVO procesar_planeacion_fisica.php
                const response = await fetch('procesar_planeacion_fisica.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        tipo_uso: 'usos_fisica',
                        prompt: `Grado: ${grado}\nContenido: ${contenido}\nPDA: ${pda}`
                    })
                });

                const data = await response.json();

                if (!response.ok || data.status === 'error' || data.success === false) { 
                    throw new Error(data.details || data.error || 'Error al procesar la solicitud en el servidor.'); 
                }
                
                // Si todo salió bien, mostramos el resultado
                if (data.status === 'completo') {
                    if (typeof data.usos_restantes !== 'undefined') {
                        usosRestantes = data.usos_restantes;
                        contadorUsosSpan.textContent = usosRestantes;
                    }

                    // Parseamos el markdown y preparamos variables
                    const textoPlaneacion = data.plan;
                    const nomDoc = inputNombreDocente.value || 'Docente de Educación Física';
                    const nomEsc = inputNombreEscuela.value || 'Institución Educativa';
            
                    if (textoPlaneacion) {
                        // Construimos el bloque HTML del encabezado oficial usando las variables
                        let encabezadoOficial = `
                        <div class="encabezado-oficial" id="pdf-header">
                            <h2>${nomEsc}</h2>
                            <p><strong>Docente:</strong> ${nomDoc} &nbsp;|&nbsp; <strong>Grado:</strong> ${grado}</p>
                        </div>`;

                        // Juntamos el encabezado con la planeación convertida desde Markdown
                        if (typeof marked !== 'undefined') {
                            resultadoIADiv.innerHTML = encabezadoOficial + `<div class="markdown-content" id="contenido-plan">${marked.parse(textoPlaneacion)}</div>`;
                        } else {
                            resultadoIADiv.innerHTML = encabezadoOficial + `<div style="white-space: pre-wrap;" id="contenido-plan">${textoPlaneacion}</div>`;
                        }
                        actualizarControles(true); // Mostramos los botones de Copiar y PDF
                    } else {
                         resultadoIADiv.innerHTML = '<p class="placeholder">No se pudo obtener la planeación.</p>';
                         actualizarControles(false);
                    }
                }

            } catch (error) {
                console.error('Error al generar:', error);
                resultadoIADiv.innerHTML = `<p style="color:red; background-color: #fee2e2; padding: 15px; border-radius: 8px;"><strong>Ocurrió un error al generar:</strong><br>${error.message}<br><br>No se descontó tu crédito. Por favor, inténtalo de nuevo.</p>`;
                actualizarControles(false); // Re-habilita el botón de generar si falló
            }
        }

        // ==========================================================
        // FUNCIÓN PARA GENERAR EL DOCUMENTO PDF
        // ==========================================================
        function descargarPlaneacionPDF() {
            // Seleccionamos el div completo que tiene el resultado de la IA
            const elemento = document.getElementById('resultado-ia');
            const btnPDF = document.getElementById('btnDescargarPDF');
            
            // Configuramos las reglas de exportación a PDF
            const opciones = {
                margin:       [15, 15, 15, 15], 
                filename:     'Planeacion_Educacion_Fisica.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                
                // html2canvas incluye el fix 'windowHeight' para evitar que se coma los textos largos
                html2canvas:  { 
                    scale: 2, 
                    useCORS: true, 
                    backgroundColor: '#ffffff', 
                    scrollY: 0,
                    windowHeight: elemento.scrollHeight + 200 
                },
                jsPDF:        { unit: 'mm', format: 'letter', orientation: 'portrait' },
                
                // Eliminamos 'avoid-all' para que fluya naturalmente entre páginas
                pagebreak:    { mode: ['css', 'legacy'] } 
            };

            // Efecto de carga en el botón
            const textoOriginal = btnPDF.innerHTML;
            btnPDF.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
            btnPDF.disabled = true;

            // Ejecutamos html2pdf
            html2pdf().set(opciones).from(elemento).save().then(() => {
                // Restauramos el botón a la normalidad
                btnPDF.innerHTML = textoOriginal;
                btnPDF.disabled = false;
            });
        }
        // Función original de copiado
        function copiarPlaneacion() {
            const textoParaCopiar = document.getElementById('resultado-ia').innerText;
            navigator.clipboard.writeText(textoParaCopiar).then(() => {
                const btnCopiar = document.getElementById('btnCopiar');
                const originalText = btnCopiar.textContent;
                btnCopiar.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
                setTimeout(() => { btnCopiar.textContent = originalText; }, 2000);
            }).catch(err => {
                console.error('Error al copiar:', err);
                alert('No se pudo copiar el texto.');
            });
        }

        // Asignación de eventos originales
        gradoSelect.addEventListener('change', actualizarContenidos);
        contenidoSelect.addEventListener('change', actualizarPDAs);
        
    </script>
</body>
</html>