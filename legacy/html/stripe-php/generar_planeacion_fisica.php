<?php
// generar_planeacion_fisica.php (VERSIÓN FINAL CON LÓGICA DE MENÚS RESTAURADA)

session_start();
require_once 'conexion.php'; 

// --- 1. Verificación de sesión USANDO 'usuario_id' ---
if (!isset($_SESSION['usuario_id'])) { 
    header("Location: login.php"); 
    exit();
}
$usuario_id = $_SESSION['usuario_id'];

// --- 2. Verificación del Nivel del Plan ---
$nivel_requerido = 1;
$plan_activo = $_SESSION['plan_activo'] ?? 0;
if ($plan_activo < $nivel_requerido) {
    header('Location: catalogo_de_pagos.html');
    exit();
}

// --- 3. Obtenemos los usos restantes de 'usos_fisica' ---
$tipo_uso_actual = 'usos_fisica';
$usos_restantes = 0;
$stmt = $conexion->prepare("SELECT {$tipo_uso_actual} FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();
if ($user_data = $resultado->fetch_assoc()) {
    $usos_restantes = (int)$user_data[$tipo_uso_actual];
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
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        /* Tu CSS original va aquí. Solo se añaden los estilos para el nuevo mensaje. */
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
        .placeholder { color: #64748b; font-style: italic; }
        .actions-container { display: flex; flex-direction: column; gap: 15px; margin-top: 20px; }
        .btn { padding: 15px; border-radius: 8px; border: none; font-size: 1rem; font-weight: 700; font-family: var(--fuente-principal); color: white; cursor: pointer; text-align: center; text-decoration: none; transition: background-color 0.3s, transform 0.2s; }
        .btn:disabled { background-color: #94a3b8; cursor: not-allowed; transform: none; }
        .btn:hover:not(:disabled) { transform: translateY(-2px); }
        .btn-generar { background-color: var(--color-secundario); }
        .btn-copiar { background-color: #0d9488; }
        .btn-return { background-color: var(--color-primario); }
        .no-usos-mensaje { text-align: center; font-weight: 600; color: var(--color-primario); background-color: #eef2ff; padding: 15px; border-radius: 8px; }
        .btn-contacto { background-color: var(--color-acento); }
        .btn-contacto:hover { background-color: #d35400; }
        footer { text-align: center; padding: 20px; margin-top: 40px; color: #64748b; }
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
        <p>&copy; 2025 Recursos para Docentes | Educación Física</p>
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

        // --- INICIO DE LA LÓGICA COMPLETA ---

        // Función para actualizar los botones según los usos
        function actualizarControles() {
            let htmlBotones = '';
            if (usosRestantes > 0) {
                htmlBotones += `<button type="button" id="btnGenerarIA" class="btn btn-generar">📄 Generar Planeación</button>`;
            } else {
                htmlBotones += `<div class="no-usos-mensaje">Si necesita más planeaciones, que se contacte con el soporte o con la ayuda del cliente.</div>`;
                htmlBotones += `<a href="servicio_cliente.html" class="btn btn-contacto">📞 Contactar a Servicio al Cliente</a>`;
            }
            htmlBotones += `<button type="button" id="btnCopiar" class="btn btn-copiar" style="display:none;">📋 Copiar Planeación</button>`;
            htmlBotones += `<a href="index.php" class="btn btn-return">⬅️ Regresar</a>`;
            
            actionsContainer.innerHTML = htmlBotones;
            
            const btnGenerarIA = document.getElementById('btnGenerarIA');
            if (btnGenerarIA) {
                btnGenerarIA.addEventListener('click', generarPlaneacionIA);
            }
            document.getElementById('btnCopiar').addEventListener('click', copiarPlaneacion);
        }

        // --- CÓDIGO RESTAURADO ---
        // Carga el JSON y actualiza los controles iniciales
        document.addEventListener('DOMContentLoaded', async () => {
            actualizarControles();
            try {
                const response = await fetch('datos_plan_basico/contenidos_educacion_fisica.json');
                if (!response.ok) throw new Error('No se pudo cargar el archivo "contenidos_educacion_fisica.json".');
                datosEducacionFisica = await response.json();
            } catch (error) {
                console.error('Error al cargar datos:', error);
                alert(`Error al cargar los datos base: ${error.message}`);
            }
        });

        // FUNCIÓN RESTAURADA: Carga los contenidos
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
        
        // FUNCIÓN RESTAURADA: Carga los PDA
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
        // --- FIN DEL CÓDIGO RESTAURADO ---

        // Función principal para generar la planeación
        async function generarPlaneacionIA() {
            const grado = gradoSelect.value;
            const contenido = contenidoSelect.value;
            const pda = pdaSelect.value;
            if (!grado || !contenido || !pda) { return alert('Por favor, completa todos los pasos.'); }

            resultadoIADiv.innerHTML = '<p class="placeholder">Generando planeación, por favor espera... 🧠</p>';
            document.getElementById('btnGenerarIA').disabled = true;
            document.getElementById('btnGenerarIA').textContent = 'Generando...';
            
            try {
                const response = await fetch('procesar_planeacion_fisica.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        tipo_uso: 'usos_fisica',
                        prompt: `Grado: ${grado}\nContenido: ${contenido}\nPDA: ${pda}`
                    })
                });

                const data = await response.json();

                if (data.success === false) { throw new Error(data.details || data.error); }
                
                if (typeof data.usos_restantes !== 'undefined') {
                    usosRestantes = data.usos_restantes;
                    contadorUsosSpan.textContent = usosRestantes;
                }
                
                const textoPlaneacion = data.plan.choices[0].text.trim();
                const btnCopiar = document.getElementById('btnCopiar');
                if (textoPlaneacion) {
                    resultadoIADiv.innerHTML = marked.parse(textoPlaneacion);
                    if(btnCopiar) btnCopiar.style.display = 'block';
                } else {
                     resultadoIADiv.innerHTML = '<p class="placeholder">No se pudo obtener la planeación.</p>';
                }

            } catch (error) {
                console.error('Error al generar la planeación:', error);
                resultadoIADiv.innerHTML = `<p style="color:red;"><strong>Ocurrió un error:</strong> ${error.message}</p>`;
            } finally {
                actualizarControles();
            }
        }
        
        function copiarPlaneacion() {
            const textoParaCopiar = document.getElementById('resultado-ia').innerText;
            navigator.clipboard.writeText(textoParaCopiar).then(() => {
                const btnCopiar = document.getElementById('btnCopiar');
                const originalText = btnCopiar.textContent;
                btnCopiar.textContent = '¡Copiado!';
                setTimeout(() => { btnCopiar.textContent = originalText; }, 2000);
            });
        }

        // Asignación de eventos
        gradoSelect.addEventListener('change', actualizarContenidos);
        contenidoSelect.addEventListener('change', actualizarPDAs);
        
    </script>
</body>
</html>