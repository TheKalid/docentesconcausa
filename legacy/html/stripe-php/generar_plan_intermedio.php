<?php
// ===== BLOQUE PHP INICIAL COMPLETO CON CONTADOR Y SEGURIDAD =====

session_start();

// --- 1. VERIFICACIÓN DE LOGIN (usando el estándar 'usuario_id') ---
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// --- 2. VERIFICACIÓN DE NIVEL DE PLAN ---
$nivel_requerido = 2; // Nivel 2 = Mentor/Intermedio
$plan_activo = $_SESSION['plan_activo'] ?? 0;

if ($plan_activo < $nivel_requerido) {
    header('Location: catalogo_de_pagos.php');
    exit();
}

// --- 3. OBTENCIÓN DE USOS Y CONTEXTO (CON LA VARIABLE DE SESIÓN CORRECTA) ---
include 'conexion.php';
$usuario_id = $_SESSION['usuario_id'];
$usos_actuales = $_SESSION['usos_plan_intermedio'] ?? 0; 
$puede_generar = $usos_actuales > 0; 

$contexto = [];
$stmt = $conexion->prepare("SELECT * FROM grupos WHERE usuario_id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();
if ($resultado->num_rows > 0) {
    $contexto = $resultado->fetch_assoc();
} else {
    // Si no tiene contexto, lo mandamos a crearlo.
    header("Location: preguntas.php");
    exit();
}
$stmt->close();
$conexion->close();

$contexto_json = json_encode($contexto);

// --- 4. PREPARAMOS EL MENSAJE DE "SIN USOS" SI APLICA ---
$mensaje_sin_usos = '';
if (!$puede_generar) {
    $mensaje_sin_usos = '<div class="aviso-usos-agotados">Has agotado tus generaciones de este mes. <br>Si necesitas más, por favor, <a href="servicio_cliente.html">contacta a servicio al cliente</a> o espera a tu renovación mensual.</div>';
}

// ===== FIN DEL BLOQUE PHP =====
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Planeación Avanzada - Docentes con Causa</title>
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
        header { text-align: center; margin-bottom: 20px; }
        header h1 { font-size: 2.5rem; color: var(--color-primario); margin: 0; }
        
        .contador-usos {
            background-color: #eef2ff;
            color: var(--color-primario);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            margin-bottom: 25px;
            border: 1px solid #c7d2fe;
        }

        .form-section, .resultado-section {
            background-color: var(--color-tarjeta);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 40px;
        }
        label { display: block; margin-top: 20px; margin-bottom: 8px; font-weight: 600; color: var(--color-primario); }
        select, input, button {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--color-borde);
            font-size: 1rem;
            font-family: var(--fuente-principal);
            background-color: #f8f9fa;
        }
        select:focus, input:focus {
            outline: none;
            border-color: var(--color-acento);
            box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.2);
        }
        select[multiple] { height: 150px; }
        small { font-size: 0.85rem; color: #64748b; display: block; margin-top: 5px; }
        
        #btnGenerar:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }
        
        #btnGenerar {
            margin-top: 30px;
            background-color: var(--color-primario);
            color: white;
            border: none;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }
        #btnGenerar:not(:disabled):hover { background-color: #1c3178; transform: translateY(-2px); }

        .resultado-section h2 { margin-top: 0; color: var(--color-primario); border-bottom: 2px solid var(--color-borde); padding-bottom: 10px; }
        #btnCopiar {
            background-color: var(--color-exito);
            color: white;
            margin-top: 20px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        #btnCopiar:hover { background-color: #15803d; }
        .btn-contexto {
            display: block;
            text-align: center;
            background-color: var(--color-acento);
            color: white;
            padding: 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 30px;
            transition: background-color 0.3s, transform 0.2s;
        }
        .btn-contexto:hover { background-color: #d35400; transform: translateY(-2px); }
        .return-button-container { text-align: center; margin-top: 20px; }
        .btn-return {
            display: inline-flex; align-items: center; gap: 8px;
            background-color: var(--color-primario); color: white;
            padding: 12px 25px; border-radius: 50px;
            text-decoration: none; font-weight: 600;
            transition: background-color 0.3s, transform 0.2s;
        }
        .btn-return:hover { background-color: #0b213a; transform: translateY(-2px); }
        footer { text-align: center; padding: 20px; margin-top: 40px; color: #64748b; }
        #resultadoPlaneacion h3 { border-bottom: 2px solid var(--color-acento); padding-bottom: 5px; margin-top: 25px; }
        #resultadoPlaneacion ul { list-style-type: '✔️ '; padding-left: 20px; }
        .aviso { background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; border: 1px solid #ffeeba; margin-top: 20px; }
        
        /* ===== NUEVO ESTILO PARA EL AVISO DE USOS AGOTADOS ===== */
        .aviso-usos-agotados {
            background-color: #fffbe6; /* Un amarillo muy pálido */
            color: #854d0e; /* Un color ámbar oscuro */
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #fde68a; /* Borde ámbar claro */
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
            <h1>📘 Generador de Planeación Avanzada 2.0</h1>
        </header>
        
        <section class="form-section">
            <form id="planeacionForm">
                <a href="preguntas.php" class="btn-contexto">✍️ Revisar/Editar Contexto de Grupo</a>

                <div class="contador-usos">
                    Usos restantes para este plan: <strong id="contador-display"><?php echo htmlspecialchars($usos_actuales); ?></strong>
                </div>

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
                    <select id="asignatura" name="asignatura" onchange="actualizarCamposFormativos()" required></select>
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
                
                <button type="button" id="btnGenerar" <?php if (!$puede_generar) echo 'disabled'; ?>>
                    <?php echo $puede_generar ? '📄 Generar Planeación' : '🚫 Usos Agotados'; ?>
                </button>
            </form>
        </section>
        <section class="resultado-section">
            <h2>📝 Tu Planeación Generada</h2>
            <div id="resultadoPlaneacion">
                <?php
                    // ===== CAMBIO FINAL: MOSTRAMOS EL MENSAJE SI NO HAY USOS =====
                    if (!empty($mensaje_sin_usos)) {
                        echo $mensaje_sin_usos;
                    } else {
                        echo '<p>Aquí aparecerá tu planeación...</p>';
                    }
                ?>
            </div>
            <button type="button" id="btnCopiar" style="display:none;">📋 Copiar Planeación</button>
        </section>
        <div class="return-button-container">
            <a href="index.php" class="btn-return">
                <span>⬅️</span>
                <span>Regresar a la Página Principal</span>
            </a>
        </div>
    </div>
    <footer>
        <p>&copy; 2025 Planeando con Causa | Todos los derechos reservados</p>
    </footer>

    <script>
        const contextoGrupo = <?php echo $contexto_json; ?>;
    </script>
    
    <script>
        // --- INICIO DEL CÓDIGO JAVASCRIPT COMPLETO Y ORIGINAL ---

        let datosGradoActual = {};
        
        document.getElementById('btnGenerar').addEventListener('click', generarPlaneacion);
        document.getElementById('btnCopiar').addEventListener('click', copiarContenido);

        async function generarPlaneacion() {
            const resultadoDiv = document.getElementById('resultadoPlaneacion');
            const btnCopiar = document.getElementById('btnCopiar');
            const btnGenerar = document.getElementById('btnGenerar');

            resultadoDiv.innerHTML = '<p>Generando planeación, por favor espera... 🧠</p>';
            btnCopiar.style.display = 'none';
            btnGenerar.disabled = true;

            const datosFormulario = {
                grado: document.getElementById('grado').value,
                asignatura: document.getElementById('grado').value.includes('Secundaria') ? document.getElementById('asignatura').value : 'No aplica',
                campoFormativo: document.getElementById('campoFormativo').value,
                contenido: document.getElementById('contenido').value,
                pda: document.getElementById('pda').value,
                ejesArticuladores: [...document.getElementById('ejeArticulador').options].filter(o => o.selected).map(o => o.value),
                tiempo: document.getElementById('tiempo').value 
            };
            
            const datosParaEnviar = { ...datosFormulario, contexto: contextoGrupo };

            try {
                const response = await fetch('procesar_planeacion_intermedio.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(datosParaEnviar)
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.details || `Error en la respuesta del servidor: ${response.statusText}`);
                }
                
                if (data.error) {
                    throw new Error(`Error del servidor: ${data.details || data.error}`);
                }

                let htmlResultado = '';
                if (data.planeacion_completa) {
                    htmlResultado += marked.parse(data.planeacion_completa);
                }
                if (data.lista_materiales && data.lista_materiales.length > 0) {
                    htmlResultado += '<h3>📦 Lista de Materiales</h3><ul>';
                    data.lista_materiales.forEach(material => { htmlResultado += `<li>${material}</li>`; });
                    htmlResultado += '</ul>';
                }
                if (data.sugerencias_didacticas && data.sugerencias_didacticas.length > 0) {
                    htmlResultado += '<h3>💡 Sugerencias Didácticas</h3><ul>';
                    data.sugerencias_didacticas.forEach(sugerencia => { htmlResultado += `<li>${sugerencia}</li>`; });
                    htmlResultado += '</ul>';
                }
                if (data.aviso) {
                    htmlResultado += `<div class="aviso"><strong>Aviso:</strong> ${data.aviso}</div>`;
                }

                resultadoDiv.innerHTML = htmlResultado;

                if (htmlResultado) { 
                    btnCopiar.style.display = 'block'; 
                    
                    const contadorDisplay = document.getElementById('contador-display');
                    if (contadorDisplay) {
                        let usosActuales = parseInt(contadorDisplay.innerText, 10);
                        if (!isNaN(usosActuales)) {
                            usosActuales--;
                            contadorDisplay.innerText = usosActuales;
                            if (usosActuales <= 0) {
                                btnGenerar.disabled = true;
                                btnGenerar.innerText = '🚫 Usos Agotados';
                            }
                        }
                    }
                }

            } catch (error) {
                console.error('Error:', error);
                resultadoDiv.innerHTML = `<p style="color:red;"><strong>Ocurrió un error.</strong><br>${error.message}</p>`;
            } finally {
                const contadorDisplay = document.getElementById('contador-display');
                if (contadorDisplay && parseInt(contadorDisplay.innerText, 10) > 0) {
                    btnGenerar.disabled = false;
                }
            }
        }
        
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
        // --- FIN DEL CÓDIGO JAVASCRIPT ---
    </script>
</body>
</html>