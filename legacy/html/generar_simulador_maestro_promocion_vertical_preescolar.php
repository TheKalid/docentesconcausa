<?php
/************************************************************
 * CONFIGURACIÓN Y SEGURIDAD
 ************************************************************/
// [SEGURIDAD] Cabeceras HTTP Anti-Ataques
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);

// Validar nombre de usuario para personalización 
if (!isset($_SESSION['usuario_nombre'])) {
    $_SESSION['usuario_nombre'] = "Docente Invitado"; 
}
// [SEGURIDAD] Sanitizamos el nombre
$usuario_nombre = htmlspecialchars($_SESSION['usuario_nombre'], ENT_QUOTES, 'UTF-8');

// [RENDIMIENTO] Liberamos la sesión para no bloquear la concurrencia
session_write_close();

$archivo_json = __DIR__ . '/preguntas_promocion_vertical_preescolar.json';

/************************************************************
 * CARGA OPTIMIZADA Y CACHÉ CORREGIDO
 ************************************************************/
function cargarBancoPreguntas($archivo_json) {
    // [CRÍTICO] Llave de caché única para VERTICAL PREESCOLAR
    $cache_key = 'banco_vertical_preescolar';

    if (function_exists('apcu_fetch')) {
        $banco = apcu_fetch($cache_key);
        if ($banco !== false) {
            return ['status' => 'success', 'data' => $banco];
        }
    }

    if (!file_exists($archivo_json)) {
        return ['status' => 'error', 'msg' => 'No se encontró el archivo de preguntas.'];
    }

    $contenido = file_get_contents($archivo_json);
    if ($contenido === false) {
        return ['status' => 'error', 'msg' => 'Error de lectura del archivo.'];
    }

    $banco = json_decode($contenido, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['status' => 'error', 'msg' => 'Error de formato JSON: ' . json_last_error_msg()];
    }

    if (function_exists('apcu_store')) {
        apcu_store($cache_key, $banco, 3600);
    }

    return ['status' => 'success', 'data' => $banco];
}

$resultado_carga = cargarBancoPreguntas($archivo_json);
$banco_preguntas = [];
$error_carga = null;

if ($resultado_carga['status'] === 'success') {
    $banco_preguntas = $resultado_carga['data'];
    // [RENDIMIENTO] Eliminamos shuffle() del servidor. Se ejecuta en JS.
} else {
    $error_carga = $resultado_carga['msg'];
}

// Codificación segura para JS
$json_preguntas = json_encode($banco_preguntas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulador Vertical Preescolar | Vanguardia Magisterial</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'vm-dark': '#002366',
                        'vm-light': '#00A8E8',
                    },
                    fontFamily: {
                        sans: ['Montserrat', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        .opcion-correcta { background-color: #d1fae5 !important; border-color: #10B981 !important; color: #065f46 !important; }
        .opcion-incorrecta { background-color: #fee2e2 !important; border-color: #EF4444 !important; color: #991b1b !important; }
        .bloqueado { pointer-events: none; opacity: 0.9; }
        html { scroll-behavior: smooth; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col">

    <nav class="bg-white shadow-md border-b border-gray-200 h-16 flex items-center justify-between px-4 lg:px-8 fixed w-full top-0 z-50">
        <div class="flex items-center gap-3">
            <a href="catalogo_de_servicios.php" class="text-gray-500 hover:text-vm-dark transition font-medium text-sm flex items-center gap-1">
                <i class="fa-solid fa-arrow-left"></i> Salir
            </a>
            <div class="h-6 w-px bg-gray-300 mx-2"></div>
            <span class="font-bold text-vm-dark hidden sm:block">Simulador <span class="text-vm-light">Directivo Preescolar</span></span>
        </div>
        
        <div class="flex items-center gap-4">
            <div class="bg-gray-100 px-3 py-1 rounded-lg flex items-center gap-2 border border-gray-200">
                <i class="fa-regular fa-clock text-gray-500"></i>
                <span id="cronometro" class="font-mono font-bold text-gray-700">00:00</span>
            </div>
            <div class="text-xs font-bold bg-blue-100 text-vm-dark px-3 py-1 rounded-full border border-blue-200">
                <span id="progreso-texto">Cargando...</span>
            </div>
        </div>
    </nav>

    <main class="flex-grow container mx-auto px-4 py-8 mt-20 max-w-3xl">

        <?php if ($error_carga): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-6 rounded shadow-md animate__animated animate__fadeIn">
                <h3 class="font-bold text-lg mb-2"><i class="fa-solid fa-triangle-exclamation"></i> Error de Sistema</h3>
                <p><?php echo htmlspecialchars($error_carga, ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="text-sm mt-2 text-red-500">Por favor verifica que el archivo JSON exista en el directorio.</p>
            </div>
        <?php else: ?>

            <div id="contenedor-pregunta" class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 md:p-8 relative overflow-hidden transition-all hidden">
                
                <div class="absolute top-0 left-0 w-full h-1.5 bg-gray-100">
                    <div id="barra-progreso" class="h-full bg-vm-light transition-all duration-500" style="width: 0%"></div>
                </div>

                <div class="mb-6 mt-2">
                    <div class="flex justify-between items-start">
                        <span id="categoria-pregunta" class="inline-block bg-blue-50 text-vm-dark text-[10px] font-extrabold px-3 py-1 rounded-full uppercase tracking-wider mb-3 border border-blue-100">
                            CATEGORÍA
                        </span>
                        <span class="text-xs text-gray-400 font-mono" id="id-pregunta">ID: 0</span>
                    </div>
                    <div id="texto-pregunta"></div>
                </div>

                <div id="lista-opciones" class="space-y-3 mb-6"></div>

                <div id="feedback-box" class="hidden rounded-xl p-5 mb-6 border-l-4 text-sm animate__animated animate__fadeIn bg-gray-50">
                    <h4 id="feedback-titulo" class="font-bold text-base mb-2 flex items-center gap-2"></h4>
                    <p id="feedback-texto" class="text-gray-700 leading-relaxed"></p>
                </div>

                <div class="flex justify-between items-center pt-4 border-t border-gray-100">
                    <button id="btn-terminar" class="text-red-500 hover:text-red-700 font-bold text-sm px-4 py-2 hover:bg-red-50 rounded-lg transition">
                        <i class="fa-solid fa-stop-circle mr-1"></i> Terminar ahora
                    </button>

                    <button id="btn-siguiente" class="bg-gray-300 text-white font-bold px-8 py-3 rounded-xl shadow-sm transition transform flex items-center gap-2 cursor-not-allowed" disabled>
                        Siguiente <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <div id="contenedor-resultados" class="hidden bg-white rounded-2xl shadow-xl border border-gray-100 p-8 text-center animate__animated animate__fadeInUp">
                <div class="w-24 h-24 bg-gradient-to-br from-vm-light to-blue-600 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-4xl shadow-lg ring-4 ring-blue-50">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                
                <h2 class="text-3xl font-bold text-vm-dark mb-2">Evaluación Completada</h2>
                <p class="text-gray-500 mb-8">Resumen de desempeño para <?php echo $usuario_nombre; ?>.</p>

                <div class="grid grid-cols-2 gap-6 mb-8 max-w-sm mx-auto">
                    <div class="bg-gray-50 p-4 rounded-2xl border border-gray-200">
                        <span class="block text-4xl font-extrabold text-vm-dark" id="puntaje-final">0</span>
                        <span class="text-xs text-gray-500 uppercase font-bold tracking-widest">Aciertos</span>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-2xl border border-gray-200">
                        <span class="block text-4xl font-extrabold text-vm-light" id="calificacion-final">0%</span>
                        <span class="text-xs text-gray-500 uppercase font-bold tracking-widest">Efectividad</span>
                    </div>
                </div>

                <div class="bg-blue-50 text-blue-800 p-4 rounded-lg mb-8 text-sm text-left border border-blue-100">
                    <i class="fa-solid fa-circle-info mr-2"></i> <strong>Nota:</strong>
                    La efectividad se calcula sobre las <strong id="total-contestadas-display">0</strong> preguntas que respondiste.
                </div>

                <div class="flex flex-col md:flex-row gap-4 justify-center">
                    <button onclick="reiniciarExamen()" class="px-6 py-3 border-2 border-vm-dark text-vm-dark font-bold rounded-lg hover:bg-gray-50 transition">
                        <i class="fa-solid fa-rotate-right mr-2"></i> Repetir (Random)
                    </button>
                    <a href="catalogo_de_servicios.php" class="px-6 py-3 bg-vm-dark text-white font-bold rounded-lg hover:bg-blue-900 transition shadow-lg">
                        Finalizar y Salir
                    </a>
                </div>
            </div>

        <?php endif; ?>

    </main>

    <script>
        let preguntas = <?php echo $json_preguntas; ?>;
        let indiceActual = 0;
        let aciertos = 0;
        let preguntasRespondidas = 0; 
        let respondido = false;
        let segundos = 0;

        const ui = {
            preguntaBox: document.getElementById('contenedor-pregunta'),
            resultadoBox: document.getElementById('contenedor-resultados'),
            categoria: document.getElementById('categoria-pregunta'),
            idPregunta: document.getElementById('id-pregunta'),
            texto: document.getElementById('texto-pregunta'),
            opciones: document.getElementById('lista-opciones'),
            feedback: document.getElementById('feedback-box'),
            feedbackTitulo: document.getElementById('feedback-titulo'),
            feedbackTexto: document.getElementById('feedback-texto'),
            btnSiguiente: document.getElementById('btn-siguiente'),
            btnTerminar: document.getElementById('btn-terminar'),
            progresoBar: document.getElementById('barra-progreso'),
            progresoTexto: document.getElementById('progreso-texto'),
            puntaje: document.getElementById('puntaje-final'),
            calificacion: document.getElementById('calificacion-final'),
            totalContestadasDisplay: document.getElementById('total-contestadas-display')
        };

        setInterval(() => {
            segundos++;
            const m = Math.floor(segundos / 60).toString().padStart(2, '0');
            const s = (segundos % 60).toString().padStart(2, '0');
            if(document.getElementById('cronometro')) 
                document.getElementById('cronometro').textContent = `${m}:${s}`;
        }, 1000);

        function mezclarArray(array) {
            for (let i = array.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [array[i], array[j]] = [array[j], array[i]];
            }
        }

        function iniciar() {
            if (!preguntas || preguntas.length === 0) return;
            mezclarArray(preguntas);
            ui.preguntaBox.classList.remove('hidden');
            mostrarPregunta(0);
        }

        function reiniciarExamen() {
            indiceActual = 0;
            aciertos = 0;
            preguntasRespondidas = 0;
            respondido = false;
            segundos = 0;
            mezclarArray(preguntas);
            ui.resultadoBox.classList.add('hidden');
            ui.preguntaBox.classList.remove('hidden');
            ui.feedback.classList.add('hidden'); 
            mostrarPregunta(0);
            window.scrollTo(0,0);
        }

        function mostrarPregunta(index) {
            respondido = false;
            const p = preguntas[index];

            ui.categoria.textContent = p.tema || p.category || "General";
            ui.idPregunta.textContent = `Reactivo ${index + 1}`; 

            let htmlContenido = "";
            if (p.caso) {
                htmlContenido += `
                    <div class="mb-5 text-lg md:text-xl font-bold text-gray-900 bg-gray-50 p-5 rounded-lg border-l-4 border-vm-light text-justify leading-relaxed">
                        ${p.caso}
                    </div>
                `;
            }
            const textoPreguntaReal = p.pregunta || p.question || "Pregunta sin texto";
            htmlContenido += `<h2 class="text-lg md:text-xl font-semibold text-gray-800 leading-relaxed">${textoPreguntaReal}</h2>`;
            ui.texto.innerHTML = htmlContenido;
            
            ui.progresoTexto.textContent = `${index + 1} / ${preguntas.length}`;
            ui.progresoBar.style.width = `${((index + 1) / preguntas.length) * 100}%`;

            ui.feedback.classList.add('hidden');
            ui.opciones.innerHTML = '';
            ui.btnSiguiente.disabled = true;
            ui.btnSiguiente.className = "bg-gray-300 text-white font-bold px-8 py-3 rounded-xl shadow-sm transition transform flex items-center gap-2 cursor-not-allowed";
            ui.btnSiguiente.innerHTML = 'Siguiente <i class="fa-solid fa-arrow-right"></i>';

            let opcionesParaRenderizar = [];
            const rawOpciones = p.opciones || p.options;
            
            if (Array.isArray(rawOpciones)) {
                rawOpciones.forEach((opt, idx) => {
                    if (typeof opt === 'string') {
                        const match = opt.match(/^([A-D])\)\s*(.*)/i);
                        if (match) {
                            opcionesParaRenderizar.push({ id: match[1].toLowerCase(), text: match[2] });
                        } else {
                            const letras = ['a', 'b', 'c', 'd', 'e'];
                            opcionesParaRenderizar.push({ id: letras[idx] || '?', text: opt });
                        }
                    } else {
                        opcionesParaRenderizar.push({ id: opt.id || '?', text: opt.text || opt.contenido });
                    }
                });
            } else if (typeof rawOpciones === 'object' && rawOpciones !== null) {
                for (const [key, value] of Object.entries(rawOpciones)) {
                    opcionesParaRenderizar.push({ id: key, text: value });
                }
            }

            opcionesParaRenderizar.forEach(opt => {
                const idOpcion = opt.id; 
                const contenido = opt.text;
                const correcta = p.respuesta_correcta || p.correctAnswer; 

                const btn = document.createElement('div');
                btn.className = "p-4 rounded-xl border border-gray-200 bg-white cursor-pointer hover:bg-gray-50 transition-all flex gap-4 items-center group relative overflow-hidden select-none";
                
                btn.onclick = () => procesarRespuesta(idOpcion, correcta, p.explicacion || p.rationale);

                btn.innerHTML = `
                    <div class="w-8 h-8 rounded-full border-2 border-gray-300 flex-shrink-0 flex items-center justify-center text-sm font-bold text-gray-400 group-hover:border-vm-light transition-colors z-10 bg-white uppercase" id="circulo-${idOpcion}">
                        ${idOpcion}
                    </div>
                    <span class="text-sm md:text-base text-gray-700 z-10 leading-snug text-left flex-1">${contenido}</span>
                `;
                btn.setAttribute('data-id', idOpcion);
                ui.opciones.appendChild(btn);
            });
        }

        function procesarRespuesta(seleccionId, respuestaCorrecta, explicacion) {
            if (respondido) return;
            respondido = true;
            preguntasRespondidas++;

            const sel = String(seleccionId).toLowerCase();
            const cor = String(respuestaCorrecta).toLowerCase();
            const esCorrecta = (sel === cor);

            const botones = ui.opciones.children;
            for (let btn of botones) { btn.classList.add('bloqueado'); }

            const btnSel = document.querySelector(`div[data-id="${seleccionId}"]`);
            const circuloSel = document.getElementById(`circulo-${seleccionId}`);
            
            let btnCor = null;
            let circuloCor = null;
            
            Array.from(botones).forEach(b => {
                if(b.getAttribute('data-id').toLowerCase() === cor) {
                    btnCor = b;
                    circuloCor = b.querySelector('div[id^="circulo-"]');
                }
            });

            if (esCorrecta) {
                aciertos++;
                if(btnSel) {
                    btnSel.classList.add('opcion-correcta');
                    circuloSel.classList.replace('border-gray-300', 'border-green-500');
                    circuloSel.classList.replace('text-gray-400', 'text-green-700');
                    circuloSel.innerHTML = '<i class="fa-solid fa-check"></i>';
                }
                mostrarFeedback(true, "¡Respuesta Correcta!", explicacion);
            } else {
                if(btnSel) {
                    btnSel.classList.add('opcion-incorrecta');
                    circuloSel.classList.replace('border-gray-300', 'border-red-500');
                    circuloSel.classList.replace('text-gray-400', 'text-red-700');
                    circuloSel.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                }
                if (btnCor) {
                    btnCor.classList.add('opcion-correcta');
                    circuloCor.classList.replace('border-gray-300', 'border-green-500');
                    circuloCor.classList.replace('text-gray-400', 'text-green-700');
                    circuloCor.innerHTML = '<i class="fa-solid fa-check"></i>';
                }
                mostrarFeedback(false, "Respuesta Incorrecta", explicacion);
            }

            ui.btnSiguiente.disabled = false;
            ui.btnSiguiente.className = "bg-vm-dark text-white font-bold px-8 py-3 rounded-xl shadow-lg hover:bg-blue-900 transition transform hover:-translate-y-1 flex items-center gap-2 cursor-pointer animate__animated animate__pulse";
            
            if (indiceActual === preguntas.length - 1) {
                ui.btnSiguiente.innerHTML = 'Ver Resultados <i class="fa-solid fa-flag-checkered"></i>';
            }
        }

        function mostrarFeedback(esCorrecto, titulo, texto) {
            ui.feedback.classList.remove('hidden');
            ui.feedback.className = `rounded-xl p-5 mb-6 border-l-4 text-sm animate__animated animate__fadeIn ${esCorrecto ? 'bg-green-50 border-green-500' : 'bg-red-50 border-red-500'}`;
            ui.feedbackTitulo.innerHTML = esCorrecto 
                ? `<i class="fa-solid fa-circle-check text-green-600"></i> <span class="text-green-800">${titulo}</span>`
                : `<i class="fa-solid fa-circle-xmark text-red-600"></i> <span class="text-red-800">${titulo}</span>`;
            ui.feedbackTexto.textContent = texto || "Sin explicación disponible.";
            ui.feedback.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        ui.btnSiguiente.addEventListener('click', () => {
            if (indiceActual < preguntas.length - 1) {
                indiceActual++;
                mostrarPregunta(indiceActual);
                ui.preguntaBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                finalizar();
            }
        });

        ui.btnTerminar.addEventListener('click', () => {
            if (confirm("¿Seguro que deseas terminar el examen ahora?")) {
                finalizar();
            }
        });

        function finalizar() {
            ui.preguntaBox.classList.add('hidden');
            ui.resultadoBox.classList.remove('hidden');
            const baseCalculo = Math.max(1, preguntasRespondidas);
            const porcentaje = Math.round((aciertos / baseCalculo) * 100);
            ui.puntaje.textContent = `${aciertos} / ${preguntasRespondidas}`;
            ui.calificacion.textContent = `${porcentaje}%`;
            ui.totalContestadasDisplay.textContent = preguntasRespondidas;
            window.scrollTo(0,0);
        }

        iniciar();
    </script>
</body>
</html>