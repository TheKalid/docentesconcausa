<?php
// ===== BLOQUE DE SEGURIDAD Y CONTADOR =====
session_start();

// 1. Verificación de Nivel
$nivel_requerido = 2; 
$plan_activo = $_SESSION['plan_activo'] ?? 0;
if ($plan_activo < $nivel_requerido) {
    header('Location: catalogo_de_pagos.html');
    exit();
}

// 2. OBTENCIÓN DE USOS DESDE LA BD
require_once 'conexion.php';
$usos_protocolos = 0; // Valor por defecto
if (isset($_SESSION['usuario_id'])) {
    $userId = $_SESSION['usuario_id'];
    $stmt = $conexion->prepare("SELECT usos_protocolos FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        $usos_protocolos = (int)$user['usos_protocolos'];
        $_SESSION['usos_protocolos'] = $usos_protocolos;
    }
    $stmt->close();
    $conexion->close();
}
$puede_consultar = $usos_protocolos > 0;

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
        .contador-usos { background-color: #eef2ff; color: var(--color-primario); padding: 15px; border-radius: 8px; text-align: center; font-weight: 600; margin-bottom: 25px; border: 1px solid #c7d2fe; }
        .aviso-usos-agotados { background-color: #fffbe6; color: #854d0e; padding: 15px; border-radius: 8px; border: 1px solid #fde68a; text-align: center; font-weight: 600; }
        .aviso-usos-agotados a { color: var(--color-primario); font-weight: 700; }
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
            <button id="btn-copiar" style="display: none;">
                <i class="far fa-copy"></i>
                <span>Copiar recomendación</span>
            </button>
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
        
        // La función para mostrar el aviso de "sin usos" (ahora solo se usa al aceptar el disclaimer)
        function mostrarAvisoUsosAgotados() {
            respuestaProtocolo.innerHTML = `
                <div class="aviso-usos-agotados">
                    Has agotado tus consultas de protocolos. <br>
                    Si necesitas más, por favor, <a href="servicio_cliente.html">contacta a servicio al cliente</a> o espera a tu renovación mensual.
                </div>
            `;
            btnCopiar.style.display = 'none';
            finalDisclaimer.style.display = 'none';
        }
        
        async function consultarProtocolo(pregunta, nivel) {
            try {
                const response = await fetch('consultar_protocolos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ consulta: pregunta, nivel: nivel })
                });
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.error || 'Error en la consulta');
                }
                return await response.json();
            } catch (error) {
                console.error('Error al consultar:', error);
                return { success: false, error: "No se pudo conectar con el servidor", detalle: error.message };
            }
        }
        
        function formatMarkdown(text) {
            if (!text) return '';
            return text
                .replace(/^# (.*$)/gm, '<h3>$1</h3>')
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/^-\s(.*$)/gm, '<li>$1</li>')
                .replace(/(<li>.*<\/li>\s*)+/s, "<ul>$0</ul>")
                .replace(/\n/g, '<br>');
        }
        
        formConsulta.addEventListener('submit', async (e) => {
            e.preventDefault();
            const pregunta = promptDocente.value.trim();
            const nivel = nivelEducativo.value;
            
            if (pregunta.length < 15) {
                respuestaProtocolo.innerHTML = `<div class="error-message"><i class="fas fa-exclamation-triangle"></i><div><strong>Por favor, describe tu situación con más detalle.</strong></div></div>`;
                return;
            }
            if (!nivel) {
                respuestaProtocolo.innerHTML = `<div class="error-message"><i class="fas fa-exclamation-triangle"></i><div><strong>Selecciona un nivel educativo.</strong></div></div>`;
                return;
            }
            
            btnConsultar.disabled = true;
            btnConsultar.innerHTML = `<div class="spinner-small"></div><span>Procesando...</span>`;
            respuestaProtocolo.innerHTML = `<div class="spinner"></div><p class="loading-text">Analizando tu consulta...</p>`;
            btnCopiar.style.display = 'none';
            finalDisclaimer.style.display = 'none';
            
            try {
                const resultado = await consultarProtocolo(pregunta, nivel);
                if (!resultado.success) {
                    throw new Error(resultado.error || 'Error desconocido');
                }
                
                const { protocolo, fuentes, sugerencias } = resultado.data;
                let htmlResult = `<div class="protocolo-respuesta">${formatMarkdown(protocolo)}</div>`;
                if (fuentes && fuentes.length > 0) {
                    htmlResult += `<h4><i class="fas fa-book"></i> Fuentes:</h4><ul>${fuentes.map(f => `<li>${f}</li>`).join('')}</ul>`;
                }
                if (sugerencias && sugerencias.length > 0) {
                    htmlResult += `<h4><i class="fas fa-lightbulb"></i> Sugerencias:</h4><ul>${sugerencias.map(s => `<li>${s}</li>`).join('')}</ul>`;
                }
                
                respuestaProtocolo.innerHTML = htmlResult;
                btnCopiar.style.display = 'flex';
                finalDisclaimer.style.display = 'flex';
                
                const contadorDisplay = document.getElementById('contador-display');
                if (contadorDisplay) {
                    let usosActuales = parseInt(contadorDisplay.innerText, 10) - 1;
                    contadorDisplay.innerText = usosActuales;
                    
                    if (usosActuales <= 0) {
                        btnConsultar.disabled = true;
                        btnConsultar.innerHTML = `<span><i class="fas fa-times-circle"></i> Consultas Agotadas</span>`;
                        
                        // ===== LÍNEA DEL ERROR ELIMINADA =====
                        // setTimeout(mostrarAvisoUsosAgotados, 4000); // Esta línea se ha borrado.
                    }
                }
                
            } catch (error) {
                console.error('Error:', error);
                respuestaProtocolo.innerHTML = `<div class="error-message"><i class="fas fa-exclamation-triangle"></i><div><strong>Error al obtener recomendaciones.</strong><p>${error.message}</p></div></div>`;
            } finally {
                const contadorDisplay = document.getElementById('contador-display');
                if (contadorDisplay && parseInt(contadorDisplay.innerText, 10) > 0) {
                     btnConsultar.disabled = false;
                     btnConsultar.innerHTML = `<i class="fas fa-search"></i><span>Consultar Protocolo</span>`;
                }
            }
        });
        
        btnCopiar.addEventListener('click', () => {
            const texto = respuestaProtocolo.textContent;
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
            } else {
                // El aviso de "sin usos" ya está visible gracias al PHP, por lo que no hacemos nada
                // y el formulario simplemente no se muestra.
            }
        });
    </script>
</body>
</html>