<?php
// ===== INICIO DEL BLOQUE DE SEGURIDAD Y DATOS DE USUARIO =====
session_start();

// [SEGURIDAD] Cabeceras HTTP Anti-Ataques y Anti-Caché
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once 'conexion.php';

// [SEGURIDAD] Generación de Token CSRF (Anti-Bots)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$nivel_requerido = 1;
$plan_activo = $_SESSION['plan_activo'] ?? 0;
if ($plan_activo < $nivel_requerido) {
    header('Location: catalogo_de_pagos.php');
    exit();
}

$usos_restantes = 0;
if (isset($_SESSION['usuario_id'])) {
    $stmt = $conexion->prepare("SELECT usos_evaluacion_diagnostica FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['usuario_id']);
    $stmt->execute();
    $resultado = $stmt->get_result();
    if ($fila = $resultado->fetch_assoc()) {
        $usos_restantes = (int)$fila['usos_evaluacion_diagnostica'];
        $_SESSION['usos_evaluacion_diagnostica'] = $usos_restantes;
    }
    $stmt->close();
}

// [RENDIMIENTO] Cerramos BD y liberamos la sesión inmediatamente
if (isset($conexion)) { $conexion->close(); }
session_write_close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Evaluación Diagnóstica - Planeando con Causa</title>

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --color-primario: #1e3a8a;
            --color-acento: #f39c12;
            --color-fondo: #f8f9fa;
            --color-texto: #334155;
            --color-tarjeta: #ffffff;
            --color-exito: #16a34a; 
            --color-borde: #dee2e6;
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--color-fondo); margin: 0; color: var(--color-texto); line-height: 1.7; }
        .container { max-width: 900px; margin: 0 auto; padding: 40px 20px; }
        header { background-color: var(--color-tarjeta); padding: 15px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 1000; }
        .nav-container { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; padding: 0 20px;}
        .logo { display: flex; align-items: center; text-decoration: none; color: var(--color-primario); font-weight: 700; font-size: 1.5rem; }
        .logo img { width: 50px; margin-right: 10px; }
        nav ul { list-style: none; margin: 0; padding: 0; display: flex; align-items: center; gap: 25px; }
        nav a { text-decoration: none; color: var(--color-texto); font-weight: 600; padding: 8px 12px; border-radius: 6px; transition: background-color 0.3s, color 0.3s; }
        nav a:hover { background-color: var(--color-primario); color: white; }
        footer { background-color: var(--color-primario); color: #e0e7ff; text-align: center; padding: 25px 0; margin-top: 60px; }

        .main-content { padding: 40px 0; }
        .page-title { text-align: center; color: var(--color-primario); margin-bottom: 30px;}
        .page-title h1 { font-size: 2.5rem; margin-bottom: 10px; }
        .page-title p { font-size: 1.1rem; max-width: 700px; margin: 0 auto 30px auto; color: #475569; }

        .counter-section { text-align: center; margin-bottom: 30px; padding: 15px; background-color: #eef2ff; border-radius: 8px; border: 1px solid #c7d2fe; max-width: 900px; margin-left: auto; margin-right: auto; }
        .counter-section p { margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--color-primario); }
        .counter-section i { margin-right: 8px; color: var(--color-primario); }
        .counter-section span { font-weight: 700; font-size: 1.3rem; }
        .btn-contact { display: inline-block; margin-top: 10px; padding: 8px 15px; font-size: 0.9rem; font-weight: 600; color: var(--color-acento); background-color: transparent; border: 2px solid var(--color-acento); border-radius: 50px; text-decoration: none; transition: all 0.3s ease; }
        .btn-contact:hover { background-color: var(--color-acento); color: white; }

        .form-container { background-color: var(--color-tarjeta); padding: 30px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); max-width: 900px; margin: 0 auto 40px auto; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: var(--color-primario); margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid var(--color-borde); border-radius: 6px; font-size: 1rem; font-family: 'Poppins', sans-serif; box-sizing: border-box;}
        textarea.form-control { min-height: 100px; resize: vertical; }
        .checkbox-group { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
        .checkbox-item label { display: flex; align-items: center; gap: 8px; font-weight: normal; }
        .submit-button { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; background-color: var(--color-acento); color: white; text-decoration: none; padding: 15px; border-radius: 8px; font-weight: 700; font-size: 1.1rem; border: none; cursor: pointer; transition: background-color 0.3s, transform 0.3s; }
        .submit-button:hover { background-color: #d35400; transform: translateY(-2px); }
        .submit-button:disabled { background-color: #ccc; cursor: not-allowed; transform: none; }

        #resultado-evaluacion { margin-top: 0; padding: 30px; background-color: #fff; border: 1px solid var(--color-borde); border-radius: 12px; display: none; box-shadow: 0 8px 25px rgba(0,0,0,0.08); max-width: 900px; margin: 0 auto 40px auto; }
        #action-buttons-container { text-align: right; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--color-borde); }
        .btn-action { padding: 8px 15px; cursor: pointer; border-radius: 6px; border: 1px solid var(--color-borde); background-color: #f8f9fa; color: var(--color-texto); font-weight: 600; margin-left: 10px; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 5px; }
        .btn-action:hover { background-color: var(--color-primario); color: white; border-color: var(--color-primario); }
        .btn-copy { background-color: var(--color-exito); color: white; border-color: var(--color-exito); }
        .btn-copy:hover { background-color: #14833a; border-color: #14833a; }
        /* ================= ESTILOS DEL BOTÓN PDF Y ENCABEZADO ================= */
        /* Le asignamos el color rojo exacto en formato hexadecimal (#dc2626) para que siempre sea visible */
        .btn-pdf { background-color: #dc2626; color: white !important; border-color: #dc2626; }
        .btn-pdf:hover { background-color: #b91c1c; border-color: #b91c1c; color: white;}
        
        .encabezado-oficial { text-align: center; border-bottom: 2px solid var(--color-primario); padding-bottom: 15px; margin-bottom: 20px; }
        .encabezado-oficial h2 { margin: 0; color: var(--color-primario); text-transform: uppercase; font-size: 1.5rem; }
        .encabezado-oficial p { margin: 5px 0 0 0; font-size: 1.1rem; color: var(--color-texto); }

        .markdown-content h1, .markdown-content h2, .markdown-content h3 { color: var(--color-primario); border-bottom: 2px solid var(--color-borde); padding-bottom: 8px; margin-top: 25px; }
        .markdown-content ul { padding-left: 20px; }

        .return-section { text-align: center; margin-top: 40px; }
        .btn-return { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; background-color: var(--color-primario); color: white; padding: 12px 25px; border-radius: 50px; font-weight: 600; transition: transform 0.3s; }
        .btn-return:hover { transform: translateY(-3px); }

        .spinner { border: 4px solid rgba(0, 0, 0, 0.1); border-radius: 50%; border-top: 4px solid var(--color-acento); width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
        .spinner-small { display: inline-block; border: 2px solid rgba(255, 255, 255, 0.3); border-radius: 50%; border-top: 2px solid white; width: 16px; height: 16px; animation: spin 1s linear infinite; margin-right: 10px; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .error-message { color: #dc3545; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 8px; font-weight: bold; margin-top: 15px; text-align: center; }
    </style>
</head>
<body>
    <header>
         <div class="container nav-container">
            <a href="index.php" class="logo">
                <img src="logo.png" alt="Logo Planeando con Causa">
                <span>Planeando con Causa</span>
            </a>
            <nav>
                <ul>
                    <li><a href="index.php">🏠 Inicio</a></li>
                    <li><a href="biblioteca.php">📚 Biblioteca</a></li>
                    <li><a href="tutorial_de_usos.html">🎓 Tutorial</a></li>
                </ul>
            </nav>
        </div>
    </header>

     <main class="main-content">
        <div class="container">
            <div class="page-title">
                <h1>Generador de Evaluación Diagnóstica</h1>
                <p>Completa los siguientes campos para crear una evaluación precisa y contextualizada.</p>
            </div>

            <div class="counter-section">
                <p><i class="fas fa-tasks"></i>Intentos Restantes: <span id="intentos-restantes"><?php echo $usos_restantes; ?></span></p>
                <a href="servicio_cliente.html" class="btn-contact" style="<?php if ($usos_restantes > 0) echo 'display: none;'; ?>">
                    ¿Necesitas más intentos? Contacta a servicio al cliente
                </a>
            </div>

            <div class="form-container">
                <form id="evaluacionForm">
                     <div class="form-group">
                        <label for="grado">1. ¿Qué grado deseas diagnosticar?</label>
                        <select id="grado" name="grado" class="form-control" required>
                            <option value="" disabled selected>-- Selecciona un grado escolar --</option>
                            <optgroup label="Preescolar">
                                <option value="preescolar_1">1º de Preescolar</option>
                                <option value="preescolar_2">2º de Preescolar</option>
                                <option value="preescolar_3">3º de Preescolar</option>
                            </optgroup>
                            <optgroup label="Primaria">
                                <option value="primaria_1">1º de Primaria</option>
                                <option value="primaria_2">2º de Primaria</option>
                                <option value="primaria_3">3º de Primaria</option>
                                <option value="primaria_4">4º de Primaria</option>
                                <option value="primaria_5">5º de Primaria</option>
                                <option value="primaria_6">6º de Primaria</option>
                            </optgroup>
                            <optgroup label="Secundaria">
                                <option value="secundaria_1">1º de Secundaria</option>
                                <option value="secundaria_2">2º de Secundaria</option>
                                <option value="secundaria_3">3º de Secundaria</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>2. ¿Qué áreas deseas evaluar?</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item"><label><input type="checkbox" name="area" value="todas" id="check-todas"> <strong>Seleccionar Todas</strong></label></div>
                            <div class="checkbox-item"><label><input type="checkbox" name="area" value="lenguaje"> Lenguaje y comunicación</label></div>
                            <div class="checkbox-item"><label><input type="checkbox" name="area" value="matematicas"> Pensamiento lógico-matemático</label></div>
                            <div class="checkbox-item"><label><input type="checkbox" name="area" value="socioemocional"> Desarrollo socioemocional</label></div>
                            <div class="checkbox-item"><label><input type="checkbox" name="area" value="artistica"> Expresión artística y motricidad</label></div>
                            <div class="checkbox-item"><label><input type="checkbox" name="area" value="estilos_aprendizaje"> Estilos y canales de aprendizaje</label></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="num_estudiantes">3. ¿Cuántos estudiantes tienes en tu grupo? (Opcional)</label>
                        <input type="number" id="num_estudiantes" name="num_estudiantes" class="form-control" placeholder="Ej: 25" min="1">
                    </div>
                    <div class="form-group">
                        <label for="necesidad">4. ¿Tienes alguna necesidad o contexto específico a considerar? (Opcional)</label>
                        <textarea id="necesidad" name="necesidad" class="form-control" placeholder="Ej: Detectar rezago en lectura, grupo multigrado, alumnos con TDAH, etc."></textarea>
                    </div>

                    <div style="background-color: #eef2ff; padding: 15px; border-radius: 8px; border: 1px solid #c7d2fe; margin-bottom: 25px;">
                        <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--color-primario);"><i class="fas fa-id-card"></i> Datos para el documento</h4>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <label for="nombreDocente" style="font-size: 0.9rem; margin-bottom: 5px;">Nombre del Docente:</label>
                                <input type="text" id="nombreDocente" class="form-control" placeholder="Ej. Profr. Juan Pérez">
                            </div>
                            <div>
                                <label for="nombreEscuela" style="font-size: 0.9rem; margin-bottom: 5px;">Nombre de la Escuela:</label>
                                <input type="text" id="nombreEscuela" class="form-control" placeholder="Ej. Esc. Primaria Benito Juárez">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="submit-button" id="submitBtn" <?php if ($usos_restantes <= 0) echo 'disabled'; ?>>
                        
                         <i class="fas fa-magic"></i> <?php echo ($usos_restantes > 0) ? 'Generar Evaluación' : 'No tienes intentos disponibles'; ?>
                    </button>
                </form>
            </div>

             <div id="resultado-evaluacion"></div>

            <section class="return-section">
                <a href="index.php" class="btn-return">
                    <span>⬅️</span>
                    <span>Regresar a la Página Principal</span>
                </a>
            </section>
        </div>
    </main>
    <footer>
        <p style="margin: 0 0 10px 0;">&copy; 2025 Planeando con Causa | Todos los derechos reservados</p>
    </footer>

    <script>
        // [SEGURIDAD] Capturamos el Token CSRF en JS
        const csrfToken = "<?php echo $csrf_token; ?>";

        const submitBtn = document.getElementById('submitBtn');
        const intentosSpan = document.getElementById('intentos-restantes');
        const resultadoDiv = document.getElementById('resultado-evaluacion');
        let usosRestantes = <?php echo $usos_restantes; ?>;

        const inputNombreDocente = document.getElementById('nombreDocente');
        const inputNombreEscuela = document.getElementById('nombreEscuela');

        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('docente_nombre')) inputNombreDocente.value = localStorage.getItem('docente_nombre');
            if (localStorage.getItem('docente_escuela')) inputNombreEscuela.value = localStorage.getItem('docente_escuela');
        });

        document.getElementById('check-todas').addEventListener('change', function(e) {
             const checkboxes = document.querySelectorAll('input[name="area"]:not(#check-todas)');
             checkboxes.forEach(checkbox => { checkbox.checked = e.target.checked; });
        });

        // LÓGICA DE GENERACIÓN SÍNCRONA
        document.getElementById('evaluacionForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const grado = document.getElementById('grado').value;
            if (!grado) { alert('Por favor, selecciona un grado escolar.'); return; }
            const areasSeleccionadas = Array.from(document.querySelectorAll('input[name="area"]:checked:not(#check-todas)')).map(cb => cb.value);
            if (areasSeleccionadas.length === 0) { alert('Por favor, selecciona al menos un área a evaluar.'); return; }
            const numEstudiantes = document.getElementById('num_estudiantes').value;
            const necesidad = document.getElementById('necesidad').value;

            localStorage.setItem('docente_nombre', inputNombreDocente.value);
            localStorage.setItem('docente_escuela', inputNombreEscuela.value);

            submitBtn.innerHTML = `<span class="spinner-small"></span> Elaborando Diagnóstico...`;
            submitBtn.disabled = true;
            resultadoDiv.style.display = 'block';
            resultadoDiv.innerHTML = `<div class="spinner"></div><p style="text-align:center; color:#1e3a8a;"><strong>Redactando rúbricas y actividades...</strong><br><span style="font-size:0.9rem; color:#64748b;">Este proceso toma de 15 a 30 segundos.</span></p>`;
            resultadoDiv.scrollIntoView({ behavior: 'smooth' });

            try {
                const response = await fetch('procesar_diagnostico.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken // [SEGURIDAD] Enviamos firma digital
                    },
                    body: JSON.stringify({ grado: grado, areas: areasSeleccionadas, num_estudiantes: numEstudiantes, necesidad: necesidad })
                });

                const textoCrudo = await response.text(); 
                let result;
                try {
                    result = JSON.parse(textoCrudo);
                } catch(parseError) {
                    throw new Error("<strong>Error del servidor:</strong><br><div style='background:#fff; color:#dc3545; padding:10px; border:1px solid #dc3545; margin-top:10px; font-family:monospace; font-size:12px;'>" + textoCrudo.replace(/</g, "&lt;").replace(/>/g, "&gt;") + "</div>");
                }

                if (!response.ok || result.status === 'error') {
                    throw new Error(result.error || 'Error desconocido al generar la evaluación.');
                }

                if (result.status === 'completo') {
                    usosRestantes = result.usos_restantes;
                    intentosSpan.textContent = usosRestantes;

                    const gradoSelectText = document.getElementById('grado').options[document.getElementById('grado').selectedIndex].text;
                    const nomDoc = inputNombreDocente.value || 'Docente Evaluador';
                    const nomEsc = inputNombreEscuela.value || 'Institución Educativa';

                    const htmlParseado = typeof marked !== 'undefined' ? marked.parse(result.data) : `<div style="white-space: pre-wrap;">${result.data}</div>`;

                    const contenidoFinal = `
                        <div id="action-buttons-container">
                            <button class="btn-action btn-copy"><i class="fas fa-copy"></i> Copiar texto</button>
                            <button class="btn-action" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
                            <button class="btn-action btn-pdf" id="btnDescargarPDF"><i class="fas fa-file-pdf"></i> Descargar PDF</button>
                        </div>
                        <div id="area-impresion-pdf">
                            <div class="encabezado-oficial">
                                <h2>${nomEsc}</h2>
                                <p><strong>Docente:</strong> ${nomDoc} &nbsp;|&nbsp; <strong>Evaluación:</strong> ${gradoSelectText}</p>
                            </div>
                            <div class="markdown-content" id="texto-a-copiar">${htmlParseado}</div>
                        </div>`; 
                    
                    resultadoDiv.innerHTML = contenidoFinal;

                    if (usosRestantes <= 0) {
                        const contactBtn = document.querySelector('.btn-contact');
                        if (contactBtn) contactBtn.style.display = 'inline-block';
                    }
                }

            } catch (error) {
                console.error(error);
                resultadoDiv.innerHTML = `<div class="error-message"><strong>Error durante la generación:</strong><br>${error.message}<br><br><span style="color:#333; font-weight:normal;">No se descontó tu crédito.</span></div>`;
            } finally {
                if (usosRestantes > 0) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-magic"></i> Generar Evaluación';
                } else {
                    submitBtn.innerHTML = '<i class="fas fa-times-circle"></i> No tienes intentos disponibles';
                    submitBtn.disabled = true;
                }
            }
        });

        // ================= LÓGICA DE BOTONES DINÁMICOS =================
        resultadoDiv.addEventListener('click', function(e) {
            
            const copyButton = e.target.closest('.btn-copy');
            if (copyButton) {
                const contentDiv = document.getElementById('texto-a-copiar');
                if (!contentDiv) return;

                navigator.clipboard.writeText(contentDiv.innerText).then(() => {
                    const originalHtml = copyButton.innerHTML;
                    copyButton.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
                    setTimeout(() => { copyButton.innerHTML = originalHtml; }, 2000);
                }).catch(err => {
                    console.error('Error al copiar: ', err);
                });
                return;
            }

            const pdfButton = e.target.closest('#btnDescargarPDF');
            if (pdfButton) {
                const elemento = document.getElementById('area-impresion-pdf');
                
                const opciones = {
                    margin:       [15, 15, 15, 15], 
                    filename:     'Evaluacion_Diagnostica.pdf',
                    image:        { type: 'jpeg', quality: 0.98 },
                    html2canvas:  { scale: 2, useCORS: true, backgroundColor: '#ffffff', scrollY: 0, windowHeight: elemento.scrollHeight + 200 },
                    jsPDF:        { unit: 'mm', format: 'letter', orientation: 'portrait' },
                    pagebreak:    { mode: ['css', 'legacy'] } 
                };

                const textoOriginal = pdfButton.innerHTML;
                pdfButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
                pdfButton.disabled = true;

                html2pdf().set(opciones).from(elemento).save().then(() => {
                    pdfButton.innerHTML = textoOriginal;
                    pdfButton.disabled = false;
                });
            }
        });
    </script>
</body>
</html>