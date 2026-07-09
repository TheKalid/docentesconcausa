<?php
// ===== INICIO DEL BLOQUE DE SEGURIDAD Y CONEXIÓN =====
session_start();

// Incluimos nuestro archivo de conexión centralizado.
require_once 'conexion.php';

$nivel_requerido = 1; 
$plan_activo = $_SESSION['plan_activo'] ?? 0;
if ($plan_activo < $nivel_requerido) {
    header('Location: catalogo_de_pagos.html');
    exit();
}

// CORRECCIÓN: Ahora usamos la variable de sesión estandarizada 'usuario_id'
$usos_plan_basico = 0; // Valor por defecto.
if (isset($_SESSION['usuario_id'])) {
    $userId = $_SESSION['usuario_id'];

    $stmt = $conexion->prepare("SELECT usos_plan_basico FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        $usos_plan_basico = (int)$user['usos_plan_basico'];
        // Guardamos también el uso en la sesión para consistencia
        $_SESSION['usos_plan_basico'] = $usos_plan_basico;
    }
    $stmt->close();
}
// ===== FIN DEL BLOQUE =====
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
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

        body {
            font-family: var(--fuente-principal);
            background-color: var(--color-fondo);
            margin: 0;
            color: var(--color-texto);
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        header {
            text-align: center;
            margin-bottom: 40px;
        }
        header h1 {
            font-size: 2.5rem;
            color: var(--color-primario);
            margin: 0;
        }
        label { display: block; margin-top: 20px; margin-bottom: 8px; font-weight: 600; color: var(--color-primario); }
        select, input, button {
            width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--color-borde);
            font-size: 1rem; font-family: var(--fuente-principal); background-color: #f8f9fa;
        }
        select:focus, input:focus {
            outline: none; border-color: var(--color-acento); box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.2);
        }
        select[multiple] { height: 150px; }
        small { font-size: 0.85rem; color: #64748b; display: block; margin-top: 5px; }
        #btnGenerar {
            margin-top: 30px; background-color: var(--color-primario); color: white; border: none;
            font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: background-color 0.3s, transform 0.2s;
        }
        #btnGenerar:hover { background-color: #1c3178; transform: translateY(-2px); }
        #btnGenerar:disabled { background-color: #6c757d; cursor: not-allowed; transform: none; }
        .resultado-section, .form-section {
            background-color: var(--color-tarjeta);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 40px;
        }
        .resultado-section h2 { margin-top: 0; color: var(--color-primario); border-bottom: 2px solid var(--color-borde); padding-bottom: 10px; }
        #btnCopiar { background-color: var(--color-exito); color: white; margin-top: 20px; border: none; font-weight: 600; cursor: pointer; transition: background-color 0.3s; }
        #btnCopiar:hover { background-color: #15803d; }
        .aviso { background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; border: 1px solid #ffeeba; margin-top: 20px; }
        #resultadoPlaneacion h3 { border-bottom: 2px solid var(--color-acento); padding-bottom: 5px; margin-top: 25px; }
        #resultadoPlaneacion ul { list-style-type: '✔️ '; padding-left: 20px; }
        .return-button-container { text-align: center; margin-top: 20px; }
        .btn-return {
            display: inline-flex; align-items: center; gap: 8px; background-color: var(--color-primario); color: white;
            padding: 12px 25px; border-radius: 50px; text-decoration: none; font-weight: 600;
            transition: background-color 0.3s, transform 0.2s;
        }
        .btn-return:hover { background-color: #0b213a; transform: translateY(-2px); }
        footer { text-align: center; padding: 20px; margin-top: 40px; color: #64748b; }
        
        /* Estilo para el aviso de usos agotados */
        .aviso-usos-agotados {
            background-color: #fffbe6;
            color: #854d0e;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #fde68a;
            margin-top: 20px;
            text-align: center;
            font-weight: 600;
        }
        .aviso-usos-agotados a {
            color: var(--color-primario);
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📘 Generador de Planeación Básica</h1>
            <p>Usos restantes para este plan: <strong id="contador-usos"><?php echo $usos_plan_basico; ?></strong></p>
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
                <div id="contenedorAsignatura" style="display:none;"><label for="asignatura">2. Selecciona la Asignatura:</label><select id="asignatura" name="asignatura" onchange="actualizarCamposFormativos()" required></select></div>
                <label for="campoFormativo">3. Selecciona el Campo Formativo:</label><select id="campoFormativo" name="campoFormativo" onchange="actualizarContenidos()" required><option value="">-- Selecciona grado (y asignatura si aplica) --</option></select>
                <label for="contenido">4. Selecciona el Contenido:</label><select id="contenido" name="contenido" onchange="actualizarPDAs()" required><option value="">-- Selecciona primero un Campo Formativo --</option></select>
                <label for="pda">5. Selecciona el PDA:</label><select id="pda" name="pda" required><option value="">-- Selecciona primero un Contenido --</option></select>
                <label for="ejeArticulador">6. Selecciona Ejes Articuladores:</label><select id="ejeArticulador" name="ejesArticuladores[]" multiple required><option value="Inclusión">Inclusión</option><option value="Interculturalidad crítica">Interculturalidad crítica</option><option value="Pensamiento crítico">Pensamiento crítico</option><option value="Igualdad de género">Igualdad de género</option><option value="Vida saludable">Vida saludable</option><option value="Apropiación de las culturas a través de la lectura y la escritura">Apropiación de las culturas a través de la lectura y la escritura</option><option value="Artes y experiencias estéticas">Artes y experiencias estéticas</option></select>
                <small>Puedes seleccionar varios manteniendo presionada la tecla Ctrl (o Cmd en Mac) y haciendo clic.</small>
                <label for="tiempo">7. Selecciona la duración de la planeación:</label><select id="tiempo" name="tiempo" required><option value="">-- Selecciona el tiempo --</option><option value="5 días">5 días</option><option value="10 días">10 días</option></select>
                <button type="button" id="btnGenerar">📄 Generar Planeación</button>
            </form>
        </section>

        <section class="resultado-section">
            <h2>📝 Tu Planeación Generada</h2>
            <div id="resultadoPlaneacion">
                <p>Aquí aparecerá tu planeación...</p>
            </div>
            <button type="button" id="btnCopiar" style="display:none;">📋 Copiar Planeación</button>
        </section>

        <div class="return-button-container">
            <a href="index.php" class="btn-return"><span>⬅️</span><span>Regresar a la Página Principal</span></a>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Planeando con Causa | Todos los derechos reservados</p>
    </footer>

    <script>
        // ========= SECCIÓN DE JAVASCRIPT COMPLETA Y CORREGIDA =========
        
        let usosRestantes = <?php echo $usos_plan_basico; ?>;
        const btnGenerar = document.getElementById('btnGenerar');
        const contadorUsosSpan = document.getElementById('contador-usos');
        const resultadoDiv = document.getElementById('resultadoPlaneacion');

        function mostrarAvisoUsosAgotados() {
            resultadoDiv.innerHTML = `
                <div class="aviso-usos-agotados">
                    Has agotado tus generaciones para el plan básico. <br>
                    Si necesitas más, por favor, <a href="servicio_cliente.html">contacta a servicio al cliente</a> o mejora tu plan.
                </div>
            `;
            document.getElementById('btnCopiar').style.display = 'none';
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
                console.error('Error:', error);
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

        document.getElementById('btnCopiar').addEventListener('click', copiarContenido);

        function copiarContenido() {
            const resultadoDiv = document.getElementById('resultadoPlaneacion');
            const btnCopiar = document.getElementById('btnCopiar');
            const textoParaCopiar = resultadoDiv.innerText;

            navigator.clipboard.writeText(textoParaCopiar).then(() => {
                const originalText = btnCopiar.innerText;
                btnCopiar.innerText = '¡Copiado!';
                setTimeout(() => {
                    btnCopiar.innerText = originalText;
                }, 2000);
            }).catch(err => {
                console.error('Error al intentar copiar al portapapeles: ', err);
                alert('No se pudo copiar el texto.');
            });
        }
        
        document.getElementById('btnGenerar').addEventListener('click', generarPlaneacion);

        async function generarPlaneacion() {
            const btnCopiar = document.getElementById('btnCopiar');
            
            if (usosRestantes <= 0) {
                mostrarAvisoUsosAgotados();
                return;
            }

            resultadoDiv.innerHTML = '<p>Generando planeación, por favor espera... 🧠</p>';
            btnCopiar.style.display = 'none';
            btnGenerar.disabled = true;

            const grado = document.getElementById('grado').value;
            const asignatura = document.getElementById('asignatura').value;
            const campoFormativo = document.getElementById('campoFormativo').value;
            const contenido = document.getElementById('contenido').value;
            const pda = document.getElementById('pda').value;
            const ejesSelect = document.getElementById('ejeArticulador');
            const ejes = [...ejesSelect.options].filter(o => o.selected).map(o => o.value);
            const tiempo = document.getElementById('tiempo').value;

            const datosParaEnviar = {
                grado,
                asignatura: grado.includes('Secundaria') ? asignatura : 'No aplica',
                campoFormativo,
                contenido,
                pda,
                ejesArticuladores: ejes,
                tiempo: tiempo 
            };

            try {
                const response = await fetch('procesar_planeacion.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(datosParaEnviar)
                });
                
                const data = await response.json();

                if (typeof data.usos_restantes !== 'undefined') {
                    usosRestantes = data.usos_restantes;
                    contadorUsosSpan.textContent = usosRestantes;
                }
                
                if (data.success === false) {
                    throw new Error(data.error || 'Ocurrió un error desconocido en el servidor.');
                }

                const planeacion = data.plan;
                let htmlResultado = '';
                
                if (planeacion.planeacion_completa) {
                    htmlResultado += marked.parse(planeacion.planeacion_completa);
                }
                if (planeacion.lista_materiales && planeacion.lista_materiales.length > 0) {
                    htmlResultado += '<h3>📦 Lista de Materiales</h3><ul>';
                    planeacion.lista_materiales.forEach(material => { htmlResultado += `<li>${material}</li>`; });
                    htmlResultado += '</ul>';
                }
                if (planeacion.sugerencias_didacticas && planeacion.sugerencias_didacticas.length > 0) {
                    htmlResultado += '<h3>💡 Sugerencias Didácticas</h3><ul>';
                    planeacion.sugerencias_didacticas.forEach(sugerencia => { htmlResultado += `<li>${sugerencia}</li>`; });
                    htmlResultado += '</ul>';
                }
                if (planeacion.aviso) {
                    htmlResultado += `<div class="aviso"><strong>Aviso:</strong> ${planeacion.aviso}</div>`;
                }

                resultadoDiv.innerHTML = htmlResultado;
                
                if (htmlResultado) {
                    btnCopiar.style.display = 'block';
                }

            } catch (error) {
                console.error('Error:', error);
                resultadoDiv.innerHTML = `<p style="color:red;"><strong>Ocurrió un error.</strong><br>${error.message}</p>`;
            } finally {
                if (usosRestantes > 0) {
                    btnGenerar.disabled = false;
                } else {
                    btnGenerar.disabled = true;
                    btnGenerar.textContent = '🚫 Usos Agotados';
                }
            }
        }
    </script>
</body>
</html>