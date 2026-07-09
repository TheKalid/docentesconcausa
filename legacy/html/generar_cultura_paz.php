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
$nivel_requerido = 2;
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
    <title>Cultura de Paz - Docentes con Causa</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

    <style>
        :root {
            --color-primario: #1e3a8a;
            --color-secundario: #3b82f6;
            --color-acento: #10b981; 
            --color-fondo: #f8fafc;
            --color-texto: #334155;
            --color-tarjeta: #ffffff;
            --color-borde: #e2e8f0;
            --fuente-principal: 'Poppins', sans-serif;
        }

        body { font-family: var(--fuente-principal); background-color: var(--color-fondo); color: var(--color-texto); margin: 0; line-height: 1.6; }
        
        header { background-color: var(--color-tarjeta); padding: 15px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 1000; }
        .nav-container { display: flex; justify-content: space-between; align-items: center; max-width: 1000px; margin: 0 auto; padding: 0 20px;}
        .logo { display: flex; align-items: center; text-decoration: none; color: var(--color-primario); font-weight: 700; font-size: 1.5rem; }
        .logo i { margin-right: 10px; font-size: 1.8rem; color: var(--color-acento); }
        .container { max-width: 900px; margin: 0 auto; padding: 40px 20px; }
        
        .page-header { text-align: center; margin-bottom: 30px; }
        .page-header h1 { color: var(--color-primario); font-size: 2.2rem; margin-bottom: 10px; }
        .page-header p { color: #64748b; font-size: 1.1rem; }

        .contador-usos { background-color: #ecfdf5; color: #065f46; padding: 15px; border-radius: 8px; text-align: center; font-weight: 600; margin-bottom: 30px; border: 1px solid #a7f3d0; }

        .card-section { background-color: var(--color-tarjeta); border-radius: 12px; padding: 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.06); margin-bottom: 25px; border: 1px solid var(--color-borde); }
        .card-section h2 { color: var(--color-primario); font-size: 1.4rem; margin-top: 0; margin-bottom: 20px; border-bottom: 2px solid var(--color-borde); padding-bottom: 10px; display: flex; align-items: center; gap: 10px; }
        
        .form-row { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 250px; margin-bottom: 15px; }
        label { display: block; font-weight: 600; color: var(--color-primario); margin-bottom: 8px; font-size: 0.95rem; }
        select, input[type="text"], textarea { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--color-borde); font-size: 1rem; font-family: var(--fuente-principal); background-color: #fdfdff; box-sizing: border-box; transition: border-color 0.3s; }
        select:focus, input:focus, textarea:focus { outline: none; border-color: var(--color-secundario); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        textarea { resize: vertical; min-height: 80px; }

        .btn-magia { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 16px; background: linear-gradient(135deg, var(--color-acento), #059669); color: white; border: none; border-radius: 10px; font-size: 1.2rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); margin-top: 10px; }
        .btn-magia:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4); }
        .btn-magia:disabled { background: #94a3b8; cursor: not-allowed; box-shadow: none; transform: none; }

        #loading-container { text-align: center; padding: 40px; display: none; }
        .spinner { border: 4px solid rgba(0, 0, 0, 0.1); border-radius: 50%; border-top: 4px solid var(--color-acento); width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto 20px auto; }
        .spinner-small { border: 2px solid rgba(255,255,255,0.3); border-top: 2px solid #fff; border-radius: 50%; width: 18px; height: 18px; animation: spin 1s linear infinite; display: inline-block; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        #error-container { display: none; background-color: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 20px; border-radius: 10px; margin-bottom: 25px; }

        #resultado-container { display: none; margin-top: 20px; }
        .resultado-header { background-color: var(--color-primario); color: white; padding: 20px; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;}
        .resultado-header h3 { margin: 0; font-size: 1.5rem; }
        .botones-accion { display: flex; gap: 10px; }
        .resultado-body { background-color: white; padding: 40px 30px; border: 1px solid var(--color-borde); border-top: none; border-radius: 0 0 12px 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.06); }
        
        .btn-accion { background-color: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.4); padding: 8px 15px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.3s; display: flex; align-items: center; gap: 6px; }
        .btn-accion:hover { background-color: rgba(255,255,255,0.3); }

        .encabezado-oficial { text-align: center; border-bottom: 2px solid var(--color-primario); padding-bottom: 15px; margin-bottom: 30px; }
        .encabezado-oficial h2 { margin: 0; color: var(--color-primario); text-transform: uppercase; font-size: 1.5rem; }
        .encabezado-oficial p { margin: 5px 0 0 0; font-size: 1.1rem; color: var(--color-texto); }

        .markdown-content h1, .markdown-content h2, .markdown-content h3 { color: var(--color-primario); margin-top: 25px; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;}
        .markdown-content h4 { color: var(--color-secundario); margin-top: 20px;}
        .markdown-content ul, .markdown-content ol { padding-left: 20px; margin-bottom: 15px;}
        .markdown-content li { margin-bottom: 8px;}
        .markdown-content p { margin-bottom: 15px;}
        .markdown-content blockquote { border-left: 4px solid var(--color-acento); margin: 0; padding-left: 15px; color: #475569; background-color: #f8fafc; padding: 10px; border-radius: 4px;}

        .return-section { text-align: center; margin-top: 40px; }
        .btn-return { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: var(--color-primario); font-weight: 600; padding: 10px 20px; border: 2px solid var(--color-primario); border-radius: 50px; transition: all 0.3s; }
        .btn-return:hover { background-color: var(--color-primario); color: white; }

        @media (max-width: 600px) {
            .resultado-header { flex-direction: column; align-items: flex-start; }
            .botones-accion { width: 100%; justify-content: space-between; }
        }
    </style>
</head>
<body>
    <header>
         <div class="nav-container">
            <a href="index.php" class="logo">
                <i class="fas fa-dove"></i> Planeando con Causa
            </a>
        </div>
    </header>

    <div class="container">
        <div class="page-header">
            <h1>Cultura de Paz y Convivencia</h1>
            <p>Genera estrategias, proyectos y actividades para promover la resolución pacífica de conflictos y el bienestar escolar.</p>
        </div>

        <div class="contador-usos">
            <i class="fas fa-heart"></i> Generaciones restantes: <strong id="contador-display"><?php echo htmlspecialchars($usos_actuales); ?></strong>
        </div>

        <div id="loading-container">
            <div class="spinner"></div>
            <h3 style="color: var(--color-primario);">Diseñando estrategia de paz...</h3>
            <p style="color: #64748b;">Integrando enfoques de la Nueva Escuela Mexicana y resolución de conflictos. Esto tomará unos segundos.</p>
        </div>

        <div id="error-container">
            <h4><i class="fas fa-exclamation-triangle"></i> ¡Ups! Algo salió mal.</h4>
            <p id="error-message">No se pudo completar la solicitud.</p>
        </div>

        <form id="paz-form">
            <div class="card-section">
                <h2><i class="fas fa-bullseye"></i> 1. Enfoque de la Actividad</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="tema_principal">Tema Principal:</label>
                        <select id="tema_principal" name="tema_principal" required>
                            <option value="" disabled selected>Selecciona un tema...</option>
                            <option value="resolucion_conflictos">Resolución Pacífica de Conflictos (Mediación)</option>
                            <option value="gestion_emociones">Gestión de Emociones y Autocontrol</option>
                            <option value="prevencion_acoso">Prevención del Acoso Escolar (Bullying)</option>
                            <option value="empatia_inclusion">Empatía, Inclusión y Respeto a la Diversidad</option>
                            <option value="reglas_convivencia">Construcción de Acuerdos de Convivencia</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="subtema">Situación Específica a Abordar:</label>
                        <input type="text" id="subtema" name="subtema" placeholder="Ej: Peleas constantes en el recreo, apodos..." required>
                    </div>
                </div>
            </div>

            <div class="card-section">
                <h2><i class="fas fa-tools"></i> 2. Detalles Pedagógicos</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="estrategia_didactica">Estrategia Preferida:</label>
                        <select id="estrategia_didactica" name="estrategia_didactica">
                            <option value="juego_roles">Juego de Roles / Dramatización</option>
                            <option value="circulo_dialogo">Círculo de Diálogo / Asamblea</option>
                            <option value="arte_expresion">Arte y Expresión (Dibujo, música, cuentos)</option>
                            <option value="proyecto_comunitario">Pequeño Proyecto Comunitario Escolar</option>
                            <option value="dinamica_juego">Dinámica o Juego Cooperativo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="duracion">Duración Estimada:</label>
                        <select id="duracion" name="duracion">
                            <option value="1_sesion">1 Sesión (45-50 min)</option>
                            <option value="2_sesiones">2 Sesiones (Corto plazo)</option>
                            <option value="proyecto_semanal">Proyecto de una semana</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="contexto_grupo">Contexto del Grupo (Opcional):</label>
                    <textarea id="contexto_grupo" name="contexto_grupo" placeholder="Ej: Es un grupo de 5to de primaria muy inquieto, se distraen rápido, hay dos líderes que suelen chocar..."></textarea>
                </div>
            </div>

            <div class="card-section" style="background-color: #f0fdf4; border-color: #a7f3d0;">
                <h2 style="border-bottom: none; margin-bottom: 10px; color: #065f46;"><i class="fas fa-file-signature"></i> 3. Datos para el Documento</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombreDocente" style="color: #065f46;">Nombre del Docente:</label>
                        <input type="text" id="nombreDocente" placeholder="Ej. Profr. Juan Pérez" style="border-color: #a7f3d0;">
                    </div>
                    <div class="form-group">
                        <label for="nombreEscuela" style="color: #065f46;">Nombre de la Escuela:</label>
                        <input type="text" id="nombreEscuela" placeholder="Ej. Esc. Primaria Benito Juárez" style="border-color: #a7f3d0;">
                    </div>
                </div>
            </div>

            <button type="submit" id="btn-generar" class="btn-magia" <?php if (!$puede_generar) echo 'disabled'; ?>>
                <?php echo $puede_generar ? '<i class="fas fa-dove"></i> Crear Estrategia de Paz' : '<i class="fas fa-ban"></i> Usos Agotados'; ?>
            </button>
        </form>

        <div id="resultado-container">
            <div class="resultado-header">
                <h3 id="titulo-resultado">Estrategia Generada</h3>
                <div class="botones-accion">
                    <button class="btn-accion" id="btnCopiar" onclick="copiarResultado(this)"><i class="fas fa-copy"></i> Copiar</button>
                    <button class="btn-accion" id="btnDescargarPDF" onclick="descargarPDF()"><i class="fas fa-file-pdf"></i> PDF</button>
                </div>
            </div>
            <div class="resultado-body" id="contenido-resultado">
                </div>
        </div>

        <div class="return-section">
             <a href="index.php" class="btn-return"><i class="fas fa-arrow-left"></i> Regresar al menú principal</a>
        </div>
    </div>

    <script>
        // [SEGURIDAD] Capturamos el Token CSRF en JS
        const csrfToken = "<?php echo $csrf_token; ?>";

        const formulario = document.getElementById('paz-form');
        const btnGenerar = document.getElementById('btn-generar');
        const loadingContainer = document.getElementById('loading-container');
        const errorContainer = document.getElementById('error-container');
        const resultadoContainer = document.getElementById('resultado-container');
        const contadorDisplay = document.getElementById('contador-display');
        
        document.addEventListener('DOMContentLoaded', () => {
            const inputNombreDocente = document.getElementById('nombreDocente');
            const inputNombreEscuela = document.getElementById('nombreEscuela');
            
            if (inputNombreDocente && localStorage.getItem('docente_nombre')) {
                inputNombreDocente.value = localStorage.getItem('docente_nombre');
            }
            if (inputNombreEscuela && localStorage.getItem('docente_escuela')) {
                inputNombreEscuela.value = localStorage.getItem('docente_escuela');
            }
        });

        async function iniciarGeneracionCulturaPaz(e) {
            e.preventDefault();
            
            const inputNombreDocente = document.getElementById('nombreDocente');
            const inputNombreEscuela = document.getElementById('nombreEscuela');
            if (inputNombreDocente) localStorage.setItem('docente_nombre', inputNombreDocente.value);
            if (inputNombreEscuela) localStorage.setItem('docente_escuela', inputNombreEscuela.value);

            btnGenerar.disabled = true;
            formulario.style.display = 'none';
            errorContainer.style.display = 'none';
            resultadoContainer.style.display = 'none';
            loadingContainer.style.display = 'block';

            const formData = {
                tema_principal: document.getElementById('tema_principal').value,
                subtema: document.getElementById('subtema').value,
                estrategia_didactica: document.getElementById('estrategia_didactica').options[document.getElementById('estrategia_didactica').selectedIndex].text,
                duracion: document.getElementById('duracion').options[document.getElementById('duracion').selectedIndex].text,
                contexto_grupo: document.getElementById('contexto_grupo').value
            };

            try {
                // [NUEVO] Enviamos la firma Anti-Bots en los headers
                const response = await fetch('procesar_cultura_paz.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(formData)
                });

                const textoCrudo = await response.text();
                let data;
                try {
                    data = JSON.parse(textoCrudo);
                } catch(parseError) {
                    throw new Error(`El servidor devolvió una respuesta no válida (Revisa los logs de PHP).`);
                }

                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Ocurrió un error al procesar la solicitud.');
                }

                if (data.usos_restantes !== undefined) {
                    contadorDisplay.innerText = data.usos_restantes;
                }

                mostrarResultadoExitoso(data.data);

            } catch (error) {
                mostrarError(error.message);
            } finally {
                loadingContainer.style.display = 'none';
                if (parseInt(contadorDisplay.innerText, 10) > 0) {
                    btnGenerar.disabled = false;
                } else {
                    btnGenerar.disabled = true;
                    btnGenerar.innerHTML = '<i class="fas fa-ban"></i> Usos Agotados';
                }
            }
        }

        function mostrarResultadoExitoso(datosIA) {
            const nomDoc = document.getElementById('nombreDocente').value || 'Docente Titular';
            const nomEsc = document.getElementById('nombreEscuela').value || 'Institución Educativa';
            
            document.getElementById('titulo-resultado').innerText = datosIA.titulo_actividad || 'Estrategia de Cultura de Paz';

            let markdownFormateado = marked.parse(datosIA.contenido_markdown || '');
            
            let htmlCompleto = `
                <div class="encabezado-oficial">
                    <h2>${nomEsc}</h2>
                    <p style="color: var(--color-acento); font-weight:bold; text-transform:uppercase;">PROYECTO DE CULTURA DE PAZ Y CONVIVENCIA</p>
                    <p><strong>Docente:</strong> ${nomDoc}</p>
                </div>
                <div class="markdown-content">
                    ${markdownFormateado}
                </div>
            `;

            document.getElementById('contenido-resultado').innerHTML = htmlCompleto;
            resultadoContainer.style.display = 'block';
            resultadoContainer.scrollIntoView({ behavior: 'smooth' });
            formulario.style.display = 'block';
        }

        function mostrarError(mensaje) {
            document.getElementById('error-message').innerText = mensaje;
            errorContainer.style.display = 'block';
            formulario.style.display = 'block';
            errorContainer.scrollIntoView({ behavior: 'smooth' });
        }

        window.copiarResultado = function(btn) {
            const contenido = document.getElementById('contenido-resultado');
            if (!contenido) return;

            navigator.clipboard.writeText(contenido.innerText).then(() => {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copiado';
                setTimeout(() => { btn.innerHTML = originalHtml; }, 2000);
            }).catch(err => {
                alert('No se pudo copiar el texto. Intenta seleccionarlo manualmente.');
            });
        };

        // ==========================================================
        // FUNCIÓN PARA GENERAR PDF DE ADECUACIÓN CURRICULAR
        // ==========================================================
        window.descargarPDF = function() {
            const btnPDF = document.getElementById('btnDescargarPDF');
            const elemento = document.getElementById('contenido-resultado');
            if (!elemento) return;

            const opciones = {
                margin:       [15, 15, 15, 15], 
                filename:     'Estrategia_Cultura_Paz.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, backgroundColor: '#ffffff', scrollY: 0, windowHeight: elemento.scrollHeight + 200 },
                jsPDF:        { unit: 'mm', format: 'letter', orientation: 'portrait' },
                pagebreak:    { mode: ['css', 'legacy'] } 
            };

            const textoOriginal = btnPDF.innerHTML;
            btnPDF.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
            btnPDF.disabled = true;

            html2pdf().set(opciones).from(elemento).save().then(() => {
                btnPDF.innerHTML = textoOriginal;
                btnPDF.disabled = false;
            });
        };
        
        if (formulario) {
            formulario.addEventListener('submit', iniciarGeneracionCulturaPaz);
        }
    </script>
</body>
</html>