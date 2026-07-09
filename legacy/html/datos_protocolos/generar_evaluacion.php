<?php
// ===== INICIO DEL BLOQUE DE SEGURIDAD Y DATOS DE USUARIO =====
session_start();
// Se incluye la conexión a la BD para poder leer los datos del usuario.
require_once 'conexion.php';

// Verificación de Nivel de Plan
$nivel_requerido = 1; 
$plan_activo = $_SESSION['plan_activo'] ?? 0;
if ($plan_activo < $nivel_requerido) {
    header('Location: catalogo_de_pagos.php');//JOJOJOJOJ
    exit();
}

// === NUEVO: OBTENER LOS USOS RESTANTES DE LA EVALUACIÓN DIAGNÓSTICA ===
$usos_restantes = 0; // Se establece un valor por defecto.
if (isset($_SESSION['usuario_id'])) {
    // Se prepara una consulta para obtener el valor de la columna específica 'usos_evaluacion_diagnostica'.
    $stmt = $conexion->prepare("SELECT usos_evaluacion_diagnostica FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['usuario_id']);
    $stmt->execute();
    $resultado = $stmt->get_result();
    if ($fila = $resultado->fetch_assoc()) {
        $usos_restantes = (int)$fila['usos_evaluacion_diagnostica'];
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
    <title>Generador de Evaluación Diagnóstica - Planeando con Causa</title>
    
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
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--color-fondo);
            margin: 0;
            color: var(--color-texto);
            line-height: 1.7;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        header {
            background-color: var(--color-tarjeta); padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 1000;
        }
        .nav-container { display: flex; justify-content: space-between; align-items: center; }
        .logo { display: flex; align-items: center; text-decoration: none; color: var(--color-primario); font-weight: 700; font-size: 1.5rem; }
        .logo img { width: 50px; margin-right: 10px; }
        nav ul { list-style: none; margin: 0; padding: 0; display: flex; align-items: center; gap: 25px; }
        nav a { text-decoration: none; color: var(--color-texto); font-weight: 600; padding: 8px 12px; border-radius: 6px; transition: background-color 0.3s, color 0.3s; }
        nav a:hover { background-color: var(--color-primario); color: white; }
        footer { background-color: var(--color-primario); color: #e0e7ff; text-align: center; padding: 25px 0; margin-top: 60px; }
        
        .main-content { padding: 60px 0; }
        .page-title { text-align: center; color: var(--color-primario); }
        .page-title h1 { font-size: 2.8rem; margin-bottom: 10px; }
        .page-title p { font-size: 1.2rem; max-width: 700px; margin: 0 auto 40px auto; color: #475569; }

        /* === NUEVOS ESTILOS PARA EL CONTADOR === */
        .counter-section {
            text-align: center;
            margin-top: -20px;
            margin-bottom: 30px;
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        .counter-section p {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--color-primario);
        }
        .counter-section i {
            margin-right: 8px;
            color: var(--color-acento);
        }
        .counter-section span {
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
        /* === FIN DE NUEVOS ESTILOS === */

        .form-container {
            background-color: var(--color-tarjeta); padding: 40px; border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08); max-width: 900px; margin: 0 auto;
        }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; font-weight: 600; color: var(--color-primario); margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem; font-family: 'Poppins', sans-serif; }
        textarea.form-control { min-height: 100px; resize: vertical; }
        .checkbox-group { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
        .checkbox-item label { display: flex; align-items: center; gap: 8px; font-weight: normal; }
        .submit-button {
            display: block; width: 100%; background-color: var(--color-acento); color: white; text-decoration: none;
            padding: 15px 35px; border-radius: 8px; font-weight: 700; font-size: 1.2rem;
            border: none; cursor: pointer; transition: background-color 0.3s, transform 0.3s;
        }
        .submit-button:hover { background-color: #d35400; transform: translateY(-2px); }
        .submit-button:disabled { background-color: #ccc; cursor: not-allowed; transform: none; }
        #resultado-evaluacion {
            margin-top: 40px; padding: 40px; background-color: #fff;
            border: 1px solid #e0e0e0; border-radius: 12px; display: none;
        }
        .action-buttons { float: right; margin-bottom: 15px; display: flex; gap: 10px; }
        .btn-action { padding: 8px 15px; cursor: pointer; border-radius: 6px; border: 1px solid #ccc; background-color: #f0f0f0; }
        #resultado-evaluacion .contenido-ia h2 { color: var(--color-primario); border-bottom: 2px solid var(--color-acento); padding-bottom: 10px; margin-top: 15px; margin-bottom: 20px; font-size: 1.6rem; }
        #resultado-evaluacion .contenido-ia h3 { color: var(--color-primario); margin-top: 25px; margin-bottom: 10px; font-size: 1.3rem; }
        #resultado-evaluacion .contenido-ia ul { list-style-type: disc; padding-left: 25px; margin-block-start: 1em; margin-block-end: 1em; }
        #resultado-evaluacion .contenido-ia li { margin-bottom: 10px; line-height: 1.6; }
        #resultado-evaluacion .contenido-ia strong { font-weight: 600; color: var(--color-texto); }
        #resultado-evaluacion .contenido-ia br { content: ""; display: block; margin-bottom: 0.5em; }
        .return-section { text-align: center; margin-top: 40px; }
        .btn-return { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; background-color: var(--color-primario); color: white; padding: 12px 25px; border-radius: 50px; font-weight: 600; transition: transform 0.3s; }
        .btn-return:hover { transform: translateY(-3px); }

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
                        <label for="num_estudiantes">3. ¿Cuántos estudiantes tienes en tu grupo?</label>
                        <input type="number" id="num_estudiantes" name="num_estudiantes" class="form-control" placeholder="Ej: 25" min="1">
                    </div>
                    <div class="form-group">
                        <label for="necesidad">4. ¿Tienes alguna necesidad o contexto específico a considerar?</label>
                        <textarea id="necesidad" name="necesidad" class="form-control" placeholder="Ej: Detectar rezago en lectura, grupo multigrado, alumnos con TDAH, etc."></textarea>
                    </div>
                    <button type="submit" class="submit-button" id="submitBtn">Generar Evaluación</button>
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

    <footer style="background-color: #1e3a8a; color: #e0e7ff; text-align: center; padding: 25px 0; margin-top: 40px;">
        <p style="margin: 0 0 10px 0;">&copy; 2025 Planeando con Causa | Todos los derechos reservados</p>
    </footer>

    <script>
        // === NUEVA LÓGICA DE JAVASCRIPT PARA EL CONTADOR ===
        const submitBtn = document.getElementById('submitBtn');
        const intentosSpan = document.getElementById('intentos-restantes');
        let usosRestantes = <?php echo $usos_restantes; ?>;

        // Al cargar la página, verificamos si el usuario tiene usos.
        document.addEventListener('DOMContentLoaded', function() {
            if (usosRestantes <= 0) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'No tienes intentos disponibles';
            }
        });
        
        document.getElementById('check-todas').addEventListener('change', function(e) {
            const checkboxes = document.querySelectorAll('input[name="area"]:not(#check-todas)');
            checkboxes.forEach(checkbox => { checkbox.checked = e.target.checked; });
        });

        document.getElementById('evaluacionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const resultadoDiv = document.getElementById('resultado-evaluacion');
            const grado = document.getElementById('grado').value;
            if (!grado) { alert('Por favor, selecciona un grado escolar.'); return; }
            const areasSeleccionadas = Array.from(document.querySelectorAll('input[name="area"]:checked:not(#check-todas)')).map(cb => cb.value);
            if (areasSeleccionadas.length === 0) { alert('Por favor, selecciona al menos un área a evaluar.'); return; }
            const numEstudiantes = document.getElementById('num_estudiantes').value;
            const necesidad = document.getElementById('necesidad').value;

            submitBtn.textContent = 'Generando, por favor espera...';
            submitBtn.disabled = true;
            resultadoDiv.style.display = 'block';
            resultadoDiv.innerHTML = '<p>Contactando al asistente de IA, esto puede tardar hasta un minuto...</p>';
            resultadoDiv.scrollIntoView({ behavior: 'smooth' });

            fetch('procesar_diagnostico.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ grado: grado, areas: areasSeleccionadas, num_estudiantes: numEstudiantes, necesidad: necesidad })
            })
            .then(response => response.json())
            .then(result => {
                // Si la solicitud fue exitosa, actualizamos el contador.
                if (result.success) {
                    const contenidoFinal = `
                        <div class="action-buttons">
                            <button class="btn-action btn-copy">📋 Copiar texto</button>
                            <button class="btn-action" onclick="window.print()">🖨️ Imprimir</button>
                        </div>
                        <div class="contenido-ia" id="texto-a-copiar">${result.data}</div>`;
                    resultadoDiv.innerHTML = contenidoFinal;

                    // El backend nos devuelve los usos restantes.
                    if (result.usos_restantes !== undefined) {
                        usosRestantes = result.usos_restantes;
                        intentosSpan.textContent = usosRestantes; // Actualizamos el número en pantalla.
                        // Si ya no quedan usos, deshabilitamos el botón permanentemente.
                        if (usosRestantes <= 0) {
                            submitBtn.textContent = 'No tienes intentos disponibles';
                            document.querySelector('.btn-contact').style.display = 'inline-block'; // Mostramos el botón de contacto
                        } else {
                             submitBtn.disabled = false; // Se vuelve a habilitar si aún quedan usos
                        }
                    }
                } else {
                    resultadoDiv.innerHTML = `<p style="color: red;"><strong>Error:</strong> ${result.error}</p>`;
                     // Si el error fue por falta de usos, el botón se queda deshabilitado.
                    if (!result.error.includes("agotado")) {
                       submitBtn.disabled = false;
                    }
                }
            })
            .catch(error => {
                console.error('Error en la solicitud fetch:', error);
                resultadoDiv.innerHTML = `<p style="color: red;"><strong>Error de conexión:</strong> No se pudo contactar al servidor.</p>`;
                submitBtn.disabled = false; // Habilitamos de nuevo si fue un error de red
            })
            .finally(() => {
                // Solo reactivamos el texto del botón si no se han agotado los usos.
                if (usosRestantes > 0) {
                   submitBtn.textContent = 'Generar Evaluación';
                }
            });
        });

        document.getElementById('resultado-evaluacion').addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-copy')) {
                const textoParaCopiar = document.getElementById('texto-a-copiar').innerText;
                navigator.clipboard.writeText(textoParaCopiar).then(() => {
                    e.target.textContent = '¡Copiado!';
                    setTimeout(() => { e.target.textContent = '📋 Copiar texto'; }, 2000);
                }).catch(err => {
                    console.error('Error al copiar: ', err);
                    alert('No se pudo copiar el texto.');
                });
            }
        });
    </script>
</body>
</html>