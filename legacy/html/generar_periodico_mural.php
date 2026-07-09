<?php
// ===== INICIO DEL BLOQUE DE SEGURIDAD Y DATOS DE USUARIO =====
session_start();
require_once 'conexion.php';

// [SEGURIDAD] Cabeceras HTTP Anti-Ataques y Anti-Caché
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// Generar Token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$nivel_requerido = 2;
$plan_activo = $_SESSION['plan_activo'] ?? 0;
if ($plan_activo < $nivel_requerido) {
    header('Location: catalogo_de_pagos.php');
    exit();
}

// Obtener usos restantes
$usos_restantes = 0;
if (isset($_SESSION['usuario_id'])) {
    $stmt = $conexion->prepare("SELECT usos_plan_intermedio FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['usuario_id']);
    $stmt->execute();
    $resultado = $stmt->get_result();
    if ($fila = $resultado->fetch_assoc()) {
        $usos_restantes = (int)$fila['usos_plan_intermedio'];
        $_SESSION['usos_plan_intermedio'] = $usos_restantes;
    }
    $stmt->close();
}

// [RENDIMIENTO] Cerramos BD y liberamos la sesión INMEDIATAMENTE
if (isset($conexion)) { $conexion->close(); }
session_write_close();
// ===== FIN DEL BLOQUE =====
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title>Eventos Cívicos y Periódicos Murales - Planeando con Causa</title>
    
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --color-primario: #1e3a8a; --color-acento: #f39c12; --color-fondo: #f8f9fa; --color-texto: #334155; --color-tarjeta: #ffffff; --color-exito: #16a34a; --color-borde: #dee2e6; }
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
        .page-title { text-align: center; color: var(--color-primario); margin-bottom: 30px;}
        .page-title h1 { font-size: 2.5rem; margin-bottom: 10px; }
        .page-title p { font-size: 1.1rem; max-width: 700px; margin: 0 auto 30px auto; color: #475569; }
        .counter-section { text-align: center; margin-bottom: 30px; padding: 15px; background-color: #eef2ff; border-radius: 8px; border: 1px solid #c7d2fe; max-width: 900px; margin: 0 auto; }
        .counter-section p { margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--color-primario); }
        .counter-section span { font-weight: 700; font-size: 1.3rem; }
        .form-container { background-color: var(--color-tarjeta); padding: 30px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); margin: 0 auto 40px auto; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: var(--color-primario); margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid var(--color-borde); border-radius: 6px; font-size: 1rem; font-family: 'Poppins', sans-serif; box-sizing: border-box;}
        textarea.form-control { min-height: 100px; resize: vertical; }
        .submit-button { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; background-color: var(--color-acento); color: white; padding: 15px; border-radius: 8px; font-weight: 700; font-size: 1.1rem; border: none; cursor: pointer; transition: background-color 0.3s, transform 0.3s; }
        .submit-button:hover { background-color: #d35400; transform: translateY(-2px); }
        .submit-button:disabled { background-color: #ccc; cursor: not-allowed; transform: none; }
        #resultado-evento { margin-top: 0; padding: 30px; background-color: #fff; border: 1px solid var(--color-borde); border-radius: 12px; display: none; box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        #action-buttons-container { text-align: right; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--color-borde); }
        .btn-action { padding: 8px 15px; cursor: pointer; border-radius: 6px; border: 1px solid var(--color-borde); background-color: #f8f9fa; color: var(--color-texto); font-weight: 600; margin-left: 10px; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 5px; }
        .btn-action:hover { background-color: var(--color-primario); color: white; border-color: var(--color-primario); }
        .btn-copy { background-color: var(--color-exito); color: white; border-color: var(--color-exito); }
        .btn-pdf { background-color: #dc2626; color: white !important; border-color: #dc2626; }
        .markdown-content h1, .markdown-content h2, .markdown-content h3 { color: var(--color-primario); border-bottom: 2px solid var(--color-borde); padding-bottom: 8px; margin-top: 25px; }
        .markdown-content ul { padding-left: 20px; }
        .markdown-content a { color: var(--color-acento); text-decoration: underline; font-weight: bold; }
        .spinner { border: 4px solid rgba(0, 0, 0, 0.1); border-radius: 50%; border-top: 4px solid var(--color-acento); width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .error-message { color: #dc3545; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 8px; font-weight: bold; text-align: center; }
    </style>
</head>
<body>
    <header>
         <div class="container nav-container">
            <a href="index.php" class="logo">
                <i class="fas fa-calendar-check" style="margin-right: 10px; color: var(--color-acento);"></i>
                <span>Planeando con Causa</span>
            </a>
            <nav>
                <ul>
                    <li><a href="index.php">🏠 Inicio</a></li>
                    <li><a href="biblioteca.php">📚 Biblioteca</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="page-title">
            <h1>Organizador de Eventos y Proyectos</h1>
            <p>Genera propuestas logísticas profesionales para efemérides, periódicos murales y festividades adaptables a tus proyectos.</p>
        </div>

        <div class="counter-section">
            <p><i class="fas fa-bolt"></i> Créditos Restantes: <span id="intentos-restantes"><?php echo $usos_restantes; ?></span></p>
        </div>

        <div class="form-container">
            <form id="eventoForm">
                <div class="form-group">
                    <label for="tipo_proyecto">1. ¿Qué tipo de organización necesitas?</label>
                    <select id="tipo_proyecto" name="tipo_proyecto" class="form-control" required>
                        <option value="" disabled selected>-- Selecciona una opción --</option>
                        <option value="periodico_mural">Diseño de Periódico Mural Interactivo</option>
                        <option value="honores_bandera">Organización de Acto Cívico / Honores a la Bandera</option>
                        <option value="kermes_festival">Organización de Festival o Kermés</option>
                        <option value="representacion_teatral">Guion para Representación Escolar / Bailable</option>
                        <option value="altar_exhibicion">Montaje de Altar o Exhibición Temática</option>
                        <option value="otra_actividad">Otra actividad / Evento escolar</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="efemeride">2. ¿Qué fecha o efeméride se celebra?</label>
                    <select id="efemeride" name="efemeride" class="form-control" required>
                        <option value="" disabled selected>-- Selecciona la efeméride --</option>
                        <option value="24_febrero_bandera">24 de Febrero - Día de la Bandera</option>
                        <option value="21_marzo_primavera">21 de Marzo - Natalicio de Benito Juárez / Primavera</option>
                        <option value="30_abril_nino">30 de Abril - Día del Niño y la Niña</option>
                        <option value="10_mayo_madres">10 de Mayo - Día de las Madres</option>
                        <option value="15_mayo_maestro">15 de Mayo - Día del Maestro</option>
                        <option value="16_septiembre_independencia">16 de Septiembre - Independencia de México</option>
                        <option value="12_octubre_raza">12 de Octubre - Encuentro de Dos Mundos (Día de la Raza)</option>
                        <option value="2_noviembre_muertos">1 y 2 de Noviembre - Día de Muertos</option>
                        <option value="20_noviembre_revolucion">20 de Noviembre - Revolución Mexicana</option>
                        <option value="diciembre_posadas">Diciembre - Navidad, Posadas y Fin de Año</option>
                        <option value="fin_ciclo_escolar">Clausura - Fin de Ciclo Escolar</option>
                        <option value="otro">Otra fecha importante (Especificar en contexto)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="nivel_grado">3. Nivel y Grado dirigido:</label>
                    <input type="text" id="nivel_grado" name="nivel_grado" class="form-control" placeholder="Ej: Preescolar 3er grado, Toda la Escuela Primaria..." required>
                </div>

                <div class="form-group">
                    <label for="contexto_extra">4. Contexto y recursos disponibles (Opcional):</label>
                    <textarea id="contexto_extra" name="contexto_extra" class="form-control" placeholder="Ej: No tenemos presupuesto, usaremos material reciclado. Queremos que el mural sirva como cierre de un proyecto de lectura."></textarea>
                </div>

                <button type="submit" class="submit-button" id="submitBtn" <?php if ($usos_restantes <= 0) echo 'disabled'; ?>>
                    <i class="fas fa-calendar-star"></i> <?php echo ($usos_restantes > 0) ? 'Generar Proyecto' : 'Sin créditos suficientes'; ?>
                </button>
            </form>
        </div>

        <div id="resultado-evento"></div>
    </main>

    <footer>
        <p style="margin: 0;">&copy; 2025 Planeando con Causa | Todos los derechos reservados</p>
    </footer>

    <script>
        const form = document.getElementById('eventoForm');
        const submitBtn = document.getElementById('submitBtn');
        const resultadoDiv = document.getElementById('resultado-evento');
        const intentosSpan = document.getElementById('intentos-restantes');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let usosRestantes = <?php echo $usos_restantes; ?>;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const payload = {
                tipo_proyecto: document.getElementById('tipo_proyecto').value,
                efemeride: document.getElementById('efemeride').options[document.getElementById('efemeride').selectedIndex].text,
                nivel_grado: document.getElementById('nivel_grado').value,
                contexto_extra: document.getElementById('contexto_extra').value
            };

            submitBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Procesando...`;
            submitBtn.disabled = true;
            resultadoDiv.style.display = 'block';
            resultadoDiv.innerHTML = `<div class="spinner"></div><p style="text-align:center; color: var(--color-primario);"><strong>Diseñando estructura profesional del evento...</strong></p>`;
            resultadoDiv.scrollIntoView({ behavior: 'smooth' });

            try {
                const response = await fetch('procesar_periodico_mural.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken 
                    },
                    body: JSON.stringify(payload)
                });

                const rawText = await response.text();
                let data;
                try {
                    data = JSON.parse(rawText);
                } catch(err) {
                    throw new Error("Error interno del servidor al procesar la respuesta.");
                }

                if (!response.ok || data.status === 'error') throw new Error(data.error);

                usosRestantes = data.usos_restantes;
                intentosSpan.textContent = usosRestantes;

                const parseado = marked.parse(data.plan.desarrollo_proyecto);
                let materialesHtml = data.plan.lista_materiales.map(m => `<li>${m}</li>`).join('');
                let consejosHtml = data.plan.consejos_practicos.map(c => `<li>${c}</li>`).join('');

                resultadoDiv.innerHTML = `
                    <div id="action-buttons-container">
                        <button class="btn-action btn-copy"><i class="fas fa-copy"></i> Copiar</button>
                        <button class="btn-action btn-pdf" id="btnDescargarPDF"><i class="fas fa-file-pdf"></i> Descargar PDF</button>
                    </div>
                    <div id="area-impresion-pdf">
                        <div style="text-align: center; border-bottom: 2px solid var(--color-primario); padding-bottom: 10px; margin-bottom: 20px;">
                            <h2 style="color:#1e3a8a; margin-bottom: 5px; text-transform: uppercase;">${data.plan.titulo_propuesta}</h2>
                            <p style="color: #64748b; margin-top: 0; font-weight: bold;">PROPUESTA DE LOGÍSTICA Y MONTAJE</p>
                        </div>
                        <div class="markdown-content" id="texto-a-copiar">
                            ${parseado}
                            <h3>🛠️ Materiales Sugeridos (Bajo Costo / Reciclados)</h3>
                            <ul>${materialesHtml}</ul>
                            <h3>💡 Consejos Prácticos y Control de Grupo</h3>
                            <ul>${consejosHtml}</ul>
                        </div>
                    </div>
                `;

            } catch (error) {
                resultadoDiv.innerHTML = `<div class="error-message">Error: ${error.message}</div>`;
            } finally {
                if (usosRestantes > 0) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-calendar-star"></i> Generar Proyecto';
                } else {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-ban"></i> Sin créditos suficientes';
                }
            }
        });

        // Event Delegation para botones generados dinámicamente
        resultadoDiv.addEventListener('click', function(e) {
            const copyBtn = e.target.closest('.btn-copy');
            if (copyBtn) {
                const content = document.getElementById('texto-a-copiar').innerText;
                navigator.clipboard.writeText(content);
                copyBtn.innerHTML = '<i class="fas fa-check"></i> Copiado';
                setTimeout(() => copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copiar', 2000);
            }

            const pdfBtn = e.target.closest('#btnDescargarPDF');
            if (pdfBtn) {
                const element = document.getElementById('area-impresion-pdf');
                
                const opciones = {
                    margin: [15, 15, 15, 15],
                    filename: 'Proyecto_Escolar_Evento.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', scrollY: 0, windowHeight: element.scrollHeight + 200 },
                    jsPDF: { unit: 'mm', format: 'letter', orientation: 'portrait' },
                    pagebreak: { mode: ['css', 'legacy'] }
                };

                const textoOriginal = pdfBtn.innerHTML;
                pdfBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
                pdfBtn.disabled = true;

                html2pdf().set(opciones).from(element).save().then(() => {
                    pdfBtn.innerHTML = textoOriginal;
                    pdfBtn.disabled = false;
                });
            }
        });
    </script>
</body>
</html>