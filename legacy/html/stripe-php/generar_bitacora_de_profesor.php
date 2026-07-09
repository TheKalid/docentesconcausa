<?php
// ===== INICIO DEL BLOQUE DE SEGURIDAD Y DATOS DE USUARIO =====
session_start();
require_once 'conexion.php'; // Incluimos la conexión para leer los usos.

// Verificación de Nivel (como ya lo tenías)
$nivel_requerido = 2;
$plan_activo = $_SESSION['plan_activo'] ?? 0;
if ($plan_activo < $nivel_requerido) {
    header('Location: catalogo_de_pagos.html');
    exit();
}

// NUEVO: Obtener los usos restantes del usuario desde la BD
$usos_restantes = 0; // Valor por defecto
if (isset($_SESSION['usuario_id'])) {
    $stmt = $conexion->prepare("SELECT usos_bitacora FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['usuario_id']);
    $stmt->execute();
    $resultado = $stmt->get_result();
    if ($fila = $resultado->fetch_assoc()) {
        $usos_restantes = (int)$fila['usos_bitacora'];
    }
    $stmt->close();
}
// ===== FIN DEL BLOQUE DE SEGURIDAD Y DATOS DE USUARIO =====
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recomendaciones en la Bitácora - Docentes con Causa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --color-primario: #0d2c4b;
            --color-acento: #007bff;
            --color-acento-hover: #0056b3;
            --color-secundario: #6c757d;
            --color-secundario-hover: #5a6268;
            --color-exito: #28a745;
            --color-exito-hover: #218838;
            --color-fondo: #f8f9fa;
            --color-texto: #212529;
            --color-tarjeta: #ffffff;
            --color-borde: #dee2e6;
            --color-advertencia: #ffc107;
        }
        body { font-family: 'Source Sans Pro', sans-serif; background-color: var(--color-fondo); color: var(--color-texto); line-height: 1.6; }
        .container { max-width: 800px; margin: 20px auto; padding: 20px; }
        .page-header { text-align: center; margin-bottom: 30px; }
        .page-header .logo { max-width: 150px; margin-bottom: 20px; }
        .page-header h1 { font-size: 2rem; color: var(--color-primario); }
        .page-header p { color: #6c757d; }
        .counter-section {
            text-align: center;
            margin-top: -15px;
            margin-bottom: 25px;
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 8px;
            border: 1px solid var(--color-borde);
        }
        .counter-section p {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--color-primario);
        }
        .counter-section p i {
            margin-right: 8px;
            color: var(--color-acento);
        }
        .counter-section p span {
            font-weight: 700;
            font-size: 1.5rem;
        }
        .btn-contact {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 15px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--color-acento);
            background-color: transparent;
            border: 2px solid var(--color-acento);
            border-radius: 50px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .btn-contact:hover {
            background-color: var(--color-acento);
            color: white;
        }
        .card { background-color: var(--color-tarjeta); border-radius: 12px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); border: 1px solid var(--color-borde); }
        fieldset { border: 1px solid var(--color-borde); border-radius: 8px; padding: 20px; margin-bottom: 25px; }
        legend { font-weight: 700; font-size: 1.2rem; color: var(--color-primario); padding: 0 10px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; }
        input, textarea, select { width: 100%; padding: 10px 12px; border: 1px solid var(--color-borde); border-radius: 8px; font-family: 'Source Sans Pro', sans-serif; font-size: 1rem; box-sizing: border-box; }
        textarea { min-height: 150px; resize: vertical; }
        .button-group { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
        button { display: inline-flex; align-items: center; justify-content: center; gap: 10px; padding: 12px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.3s; color: white; }
        button:disabled { background-color: #ccc; cursor: not-allowed; }
        #btn-analizar { background-color: var(--color-secundario); }
        #btn-analizar:hover { background-color: var(--color-secundario-hover); }
        #btn-protocolo { background-color: var(--color-acento); }
        #btn-protocolo:hover { background-color: var(--color-acento-hover); }
        #btn-previsualizar { background-color: var(--color-exito); }
        #btn-previsualizar:hover { background-color: var(--color-exito-hover); }
        .resultado-seccion { margin-top: 25px; padding: 20px; border-radius: 8px; border: 1px solid var(--color-borde); background-color: #f8f9fa; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: #fff; margin: auto; padding: 30px; border-radius: 8px; max-width: 800px; width: 90%; box-shadow: 0 5px 25px rgba(0,0,0,0.2); position: relative; }
        .close-button { color: #aaa; float: right; font-size: 28px; font-weight: bold; position: absolute; top: 10px; right: 20px; cursor: pointer; }
        .spinner { border: 4px solid rgba(0,0,0,0.1); border-radius: 50%; border-top: 4px solid var(--color-acento); width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
        .return-section { display: flex; justify-content: center; margin-top: 40px; }
        .btn-return { display: inline-flex; align-items: center; gap: 8px; background-color: var(--color-primario); color: white; padding: 12px 25px; border-radius: 50px; text-decoration: none; font-weight: 600; }
        .footer-disclaimer { margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--color-borde); text-align: center; font-size: 0.85rem; color: var(--color-secundario); }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <div class="container">
        <header class="page-header">
            <img src="logo.png" alt="Logo de Docentes con Causa" class="logo">
            <h1><i class="fas fa-shield-alt"></i> Recomendaciones en la Bitácora Docente</h1>
            <p>Estimado docente revise que todos los campos esten llenos antes de oprimir algun botón para poder obtener resultados de nuestra herramienta de apoyo.</p>
        </header>

        <div class="counter-section">
            <p><i class="fas fa-bolt"></i>Intentos Restantes: <span id="intentos-restantes"><?php echo $usos_restantes; ?></span></p>

            <a href="servicio_cliente.html"
               class="btn-contact"
               style="<?php if ($usos_restantes > 0) echo 'display: none;'; ?>">
               ¿Necesitas más intentos? Contacta a servicio al cliente
            </a>
        </div>

        <div class="card">
            <form id="bitacora-form">
                 <fieldset>
                    <legend>Datos Clave</legend>
                    <div class="form-group">
                        <label for="nivel-educativo">Nivel educativo del incidente:</label>
                        <select id="nivel-educativo" required>
                            <option value="" disabled selected>Selecciona un nivel</option>
                            <option value="preescolar">Preescolar</option>
                            <option value="primaria">Primaria</option>
                            <option value="secundaria">Secundaria</option>
                        </select>
                    </div>
                 </fieldset>
                 <fieldset>
                    <legend>1. Datos Generales del Incidente</legend>
                    <div class="form-group"><label for="fecha-incidente">Fecha:</label><input type="date" id="fecha-incidente" required></div>
                    <div class="form-group"><label for="hora-incidente">Hora:</label><input type="time" id="hora-incidente" required></div>
                    <div class="form-group"><label for="lugar-incidente">Lugar:</label><input type="text" id="lugar-incidente" placeholder="Ej: Patio, salón 3°B..." required></div>
                    <div class="form-group"><label for="docente-reporta">Docente que Registra:</label><input type="text" id="docente-reporta" placeholder="Su nombre completo" required></div>
                </fieldset>
                <fieldset>
                    <legend>2. Personas Involucradas</legend>
                    <div class="form-group"><label for="alumnos-involucrados">Alumno(s):</label><textarea id="alumnos-involucrados" placeholder="Nombre completo, grado y rol..." required></textarea></div>
                    <div class="form-group"><label for="adultos-presentes">Testigo(s):</label><input type="text" id="adultos-presentes" placeholder="Otro personal de la escuela..."></div>
                </fieldset>
                <fieldset>
                    <legend>3. Descripción de los Hechos</legend>
                    <div class="form-group"><label for="descripcion-hechos">Narración Objetiva:</label><textarea id="descripcion-hechos" placeholder="Describa paso a paso lo que sucedió..." required></textarea></div>
                </fieldset>
                <fieldset>
                    <legend>4. Acciones y Seguimiento</legend>
                    <div class="form-group"><label for="acciones-tomadas">Acciones Inmediatas:</label><textarea id="acciones-tomadas" placeholder="Ej: Se separó a los alumnos..." required></textarea></div>
                    <div class="form-group"><label for="notificaciones">Notificaciones:</label><input type="text" id="notificaciones" placeholder="Ej: Se notificó al Director(a)..."></div>
                    <div class="form-group"><label for="acuerdos-compromisos">Acuerdos:</label><textarea id="acuerdos-compromisos" placeholder="Ej: El alumno se compromete a..."></textarea></div>
                </fieldset>
            </form>

            <div class="button-group">
                <button id="btn-analizar" onclick="analizarBitacora()"><i class="fas fa-clipboard-check"></i> Analizar Registro</button>
                <button id="btn-protocolo" onclick="obtenerProtocolo()"><i class="fas fa-directions"></i> Obtener Protocolo</button>
                <button id="btn-previsualizar" onclick="previsualizarBitacora()"><i class="fas fa-print"></i> Previsualizar Bitácora</button>
            </div>

            <div id="seccion-resultados" class="resultado-seccion" style="display:none;"></div>
        </div>
        
        <section class="return-section">
            <a href="index.php" class="btn-return"><span>⬅️</span><span>Regresar al Inicio</span></a>
        </section>

        <footer class="footer-disclaimer">
            <p>El contenido registrado en esta bitácora es responsabilidad exclusiva del usuario. Docentes con Causa no asume responsabilidad por las acciones o decisiones derivadas del uso de esta información. La bitácora tiene fines de registro y apoyo, y no sustituye las obligaciones legales o normativas del usuario.</p>
        </footer>
    </div>

    <div id="modal-previsualizacion" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="document.getElementById('modal-previsualizacion').style.display='none'">&times;</span>
            <div id="bitacora-preview"></div>
        </div>
    </div>

    <script>
        const resultadosDiv = document.getElementById('seccion-resultados');
        const allButtons = document.querySelectorAll('.button-group button');
        const intentosSpan = document.getElementById('intentos-restantes');
        let usosRestantes = <?php echo $usos_restantes; ?>;

        document.addEventListener('DOMContentLoaded', () => {
            if (usosRestantes <= 0) {
                allButtons.forEach(btn => btn.disabled = true);
                showError("Has agotado tus intentos. Contacta a servicio al cliente para obtener más.");
            }
        });

        async function callBitacoraAPI(action, data) {
            setLoadingState(true);
            try {
                const response = await fetch('procesar_bitacora_de_profesor.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ action, data })
                });

                const result = await response.json();
                
                if (!response.ok) {
                    throw new Error(result.error || `Error del servidor: ${response.statusText}`);
                }

                if (!result.success) {
                    throw new Error(result.error || 'Ocurrió un error en el backend.');
                }
                
                if (result.usos_restantes !== undefined) {
                    usosRestantes = result.usos_restantes;
                    intentosSpan.textContent = usosRestantes;
                    if (usosRestantes <= 0) {
                        allButtons.forEach(btn => btn.disabled = true);
                        const contactBtn = document.querySelector('.btn-contact');
                        if (contactBtn) {
                            contactBtn.style.display = 'inline-block';
                        }
                    }
                }
                
                return result.data;

            } catch (error) {
                console.error('Error en la API:', error);
                showError(error.message);
                if (error.message.includes("agotado")) {
                    allButtons.forEach(btn => btn.disabled = true);
                }
                return null;
            } finally {
                setLoadingState(false);
            }
        }

        function setLoadingState(isLoading) {
            if (usosRestantes > 0) {
                allButtons.forEach(btn => btn.disabled = isLoading);
            }
            if (isLoading) {
                resultadosDiv.innerHTML = '<div class="spinner"></div>';
                resultadosDiv.style.display = 'block';
            }
        }

        function showError(message) {
            resultadosDiv.innerHTML = `<p style="color:red; font-weight:bold;"><i class="fas fa-exclamation-triangle"></i> Error: ${message}</p>`;
            resultadosDiv.style.display = 'block';
        }

        function getFormData() {
            return {
                nivel_educativo: document.getElementById('nivel-educativo').value,
                fecha_incidente: document.getElementById('fecha-incidente').value,
                hora_incidente: document.getElementById('hora-incidente').value,
                lugar_incidente: document.getElementById('lugar-incidente').value,
                docente_reporta: document.getElementById('docente-reporta').value,
                alumnos_involucrados: document.getElementById('alumnos-involucrados').value,
                adultos_presentes: document.getElementById('adultos-presentes').value,
                descripcion_hechos: document.getElementById('descripcion-hechos').value,
                acciones_tomadas: document.getElementById('acciones-tomadas').value,
                notificaciones: document.getElementById('notificaciones').value,
                acuerdos_compromisos: document.getElementById('acuerdos-compromisos').value
            };
        }

        async function analizarBitacora() {
            const formData = getFormData();
            const result = await callBitacoraAPI('analizar', { datos_bitacora: formData });
            if (result && result.feedback) {
                let feedbackHtml = '<h3><i class="fas fa-clipboard-check"></i> Análisis del Registro</h3><ul>';
                result.feedback.forEach(item => {
                    const icon = item.status === 'OK' ? 'fa-check-circle' : 'fa-exclamation-triangle';
                    const cssClass = item.status === 'OK' ? 'feedback-ok' : 'feedback-warn';
                    feedbackHtml += `<li class="feedback-item ${cssClass}"><i class="fas ${icon}"></i> ${item.mensaje}</li>`;
                });
                feedbackHtml += '</ul>';
                resultadosDiv.innerHTML = feedbackHtml;
            }
        }

        async function obtenerProtocolo() {
            const formData = getFormData();
            if (!formData.nivel_educativo || !formData.descripcion_hechos) {
                showError("Por favor, seleccione el nivel educativo y describa los hechos para obtener un protocolo.");
                return;
            }
            const result = await callBitacoraAPI('obtener_protocolo', {
                nivel_educativo: formData.nivel_educativo,
                descripcion_hechos: formData.descripcion_hechos
            });             
            if (result && result.recomendacion_html) {
                resultadosDiv.innerHTML = result.recomendacion_html;
            }
        }

        async function previsualizarBitacora() {
            const formData = getFormData();
            const result = await callBitacoraAPI('generar_vista_previa', { datos_bitacora: formData });
            if (result && result.html_preview) {
                document.getElementById('bitacora-preview').innerHTML = result.html_preview;
                document.getElementById('modal-previsualizacion').style.display = 'flex';
                resultadosDiv.style.display = 'none';
            }
        }
    </script>
</body>
</html>