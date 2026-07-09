<?php
// =======================================================================
// 1. MEJORA DE SEGURIDAD: BLINDAJE DE LA SESIÓN (SESSION HIJACKING)
// =======================================================================
// Explicación: Antes de iniciar la sesión, configuramos el "boleto" (cookie)
// del usuario con estrictas medidas de seguridad para evitar que lo roben.
session_set_cookie_params([
    'lifetime' => 0, // El boleto caduca en cuanto el usuario cierra el navegador.
    'path' => '/', // El boleto es válido en cualquier parte de tu sitio web.
    'domain' => '', // Válido en tu dominio actual.
    'secure' => isset($_SERVER['HTTPS']), // Solo viaja por conexiones cifradas con candado verde (HTTPS).
    'httponly' => true, // Hace que el boleto sea invisible para programas maliciosos de JavaScript.
    'samesite' => 'Strict' // Evita que otras páginas maliciosas hagan peticiones falsas.
]);

// Iniciamos o reanudamos la sesión de manera segura.
session_start(); 

// ===== INICIO DEL BLOQUE DE PERMISOS =====
// Explicación: Aquí revisamos quién es el usuario y qué compró.
$nombre_usuario = $_SESSION['usuario_nombre'] ?? null; 

// 1. Obtenemos el plan del usuario desde la sesión. Si no existe o no ha pagado, será 0.
$plan_activo = $_SESSION['plan_activo'] ?? 0;

// 2. Creamos variables claras para saber a qué tiene acceso el usuario.
$puede_usar_basico     = ($plan_activo >= 1); // Verdadero si el plan es 1, 2 o 3.
$puede_usar_intermedio = ($plan_activo >= 2); // Verdadero si el plan es 2 o 3.
$puede_usar_avanzado   = ($plan_activo >= 3); // Verdadero si el plan es 3 (Líder).
// 3. Definimos el mensaje y la URL para los botones bloqueados.
$url_no_pagado = 'catalogo_de_pagos.php'; 
$mensaje_no_pagado = 'Función exclusiva para suscriptores. ¡Mejora tu plan!'; 
// ===== FIN DEL BLOQUE DE PERMISOS =====
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" sizes="512x512" href="logocel.png">
    <link rel="apple-touch-icon" href="logocel.png">
    <link rel="manifest" href="manifest.json"> 
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Herramientas para Docentes</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --color-primario: #1e3a8a;
            --color-primario-light: #3b82f6;
            --color-acento: #f39c12;
            --color-acento-hover: #d35400;
            --color-fondo: #f4f7f6;
            --color-texto: #334155;
            --color-tarjeta: #ffffff;
            --shadow-sm: 0 4px 10px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 30px rgba(30, 58, 138, 0.08);
            --shadow-lg: 0 20px 40px rgba(30, 58, 138, 0.15);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--color-fondo);
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 30px 30px;
            margin: 0;
            color: var(--color-texto);
            line-height: 1.7;
            overflow-x: hidden;
        }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        
        header {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 15px 0;
            box-shadow: var(--shadow-sm); 
            position: sticky; top: 0; z-index: 100;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .nav-container { display: flex; justify-content: space-between; align-items: center; }
        .logo { display: flex; align-items: center; text-decoration: none; color: var(--color-primario); font-weight: 800; font-size: 1.5rem; letter-spacing: -0.5px; }
        .logo img { width: 50px; margin-right: 12px; transition: var(--transition); }
        .logo:hover img { transform: scale(1.05); }
        
        nav ul { list-style: none; margin: 0; padding: 0; display: flex; align-items: center; gap: 25px; }
        nav a { text-decoration: none; color: var(--color-texto); font-weight: 600; padding: 8px 16px; border-radius: 8px; transition: var(--transition); }
        nav a:hover { background-color: #eef2ff; color: var(--color-primario); }
        
        .user-welcome a {
            display: flex; align-items: center; gap: 10px;
            background-color: #eef2ff; border: 1px solid #c7d2fe; color: var(--color-primario);
            border-radius: 50px; padding: 8px 20px;
        }
        .user-welcome a:hover { background-color: var(--color-primario); color: white; transform: translateY(-2px); box-shadow: var(--shadow-sm); }
        .user-welcome .user-name { font-weight: 700; }

        /* Banner Principal con soporte para Matrix */
        .hero-section { 
            background: linear-gradient(135deg, var(--color-primario) 0%, var(--color-primario-light) 100%);
            color: white;
            text-align: center; 
            padding: 100px 20px 120px 20px; 
            clip-path: polygon(0 0, 100% 0, 100% 90%, 0 100%);
            margin-bottom: 40px;
            box-shadow: inset 0 -10px 20px rgba(0,0,0,0.1);
            position: relative; 
            overflow: hidden; 
        }

        #matrix-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            opacity: 0.25; 
            pointer-events: none; 
        }

        .hero-section .container {
            position: relative;
            z-index: 1; 
        }

        .hero-section h1 { font-size: 3.5rem; color: white; font-weight: 800; margin-bottom: 20px; letter-spacing: -1px; text-shadow: 0 4px 10px rgba(0,0,0,0.4); }
        .hero-section p { font-size: 1.25rem; max-width: 700px; margin: 0 auto 40px auto; color: #e0e7ff; font-weight: 400; text-shadow: 0 2px 5px rgba(0,0,0,0.4); }
        
        .hero-buttons { display: flex; flex-direction: column; align-items: center; gap: 20px; }
        @media (min-width: 600px) { .hero-buttons { flex-direction: row; justify-content: center; } }
        
        .cta-button {
            display: inline-block; background-color: var(--color-acento); color: white; text-decoration: none;
            padding: 16px 40px; border-radius: 50px; font-weight: 700; font-size: 1.1rem;
            transition: var(--transition); box-shadow: 0 6px 20px rgba(243, 156, 18, 0.4);
        }
        .cta-button:hover:not(.disabled) { background-color: var(--color-acento-hover); transform: translateY(-4px); box-shadow: 0 10px 25px rgba(243, 156, 18, 0.5); }
        
        .hero-section .cta-button.secondary { background-color: rgba(255,255,255,0.1); border: 2px solid white; color: white; box-shadow: none; backdrop-filter: blur(5px);}
        .hero-section .cta-button.secondary:hover { background-color: white; color: var(--color-primario); transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        
        .cta-button.secondary { background-color: transparent; border: 2px solid var(--color-primario); color: var(--color-primario); box-shadow: none; }
        .cta-button.secondary:hover:not(.disabled) { background-color: var(--color-primario); color: white; }

        .cta-button.disabled { background-color: #cbd5e1; border-color: transparent; color: #64748b; cursor: not-allowed; pointer-events: none; box-shadow: none; }

        .features-section { padding: 40px 0 80px 0; }
        .section-title { text-align: center; font-size: 2.8rem; color: var(--color-primario); font-weight: 800; margin-bottom: 60px; letter-spacing: -0.5px; }
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 40px; padding: 10px; }
        
        .feature-card {
            background-color: var(--color-tarjeta); 
            border-radius: 24px; 
            padding: 40px 30px;
            box-shadow: var(--shadow-md); 
            text-align: center; 
            display: flex; 
            flex-direction: column;
            transition: var(--transition);
            border: 1px solid rgba(0,0,0,0.03);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .feature-card::before {
            content: '';
            position: absolute; top: 0; left: 0; width: 100%; height: 6px;
            background: linear-gradient(90deg, var(--color-primario-light), var(--color-acento));
            opacity: 0; transition: var(--transition); z-index: 0;
        }
        
        .feature-card:hover { transform: translateY(-12px); box-shadow: var(--shadow-lg); }
        .feature-card:hover::before { opacity: 1; }
        
        .feature-icon { 
            font-size: 3.5rem; margin-bottom: 25px; display: inline-block;
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative; z-index: 2;
        }
        .feature-card:hover .feature-icon { transform: scale(1.15) rotate(5deg); }
        
        .feature-card h3 { font-size: 1.4rem; color: var(--color-primario); margin-bottom: 15px; font-weight: 700; position: relative; z-index: 2; }
        .feature-card p { margin-bottom: 30px; flex-grow: 1; color: #64748b; font-size: 1rem; position: relative; z-index: 2; }
        
        .feature-card .card-button {
            display: inline-block; background-color: #eef2ff; color: var(--color-primario);
            text-decoration: none; padding: 12px 25px; border-radius: 50px;
            font-weight: 700; transition: var(--transition); margin-top: auto;
            position: relative; z-index: 2; border: 1px solid transparent;
        }
        .feature-card .card-button:hover:not(.disabled) { background-color: var(--color-primario); color: white; box-shadow: 0 4px 15px rgba(30, 58, 138, 0.2); }
        
        .card-button.disabled { background-color: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; cursor: not-allowed; pointer-events: none; }
        
        .impact-section { 
            background: linear-gradient(to right, #eef2ff, #f8fafc); 
            padding: 80px 20px; text-align: center; margin-top: 60px; 
            border-top: 1px solid #e2e8f0;
        }
        .impact-section h2 { font-size: 2.5rem; color: var(--color-primario); font-weight: 800; margin-bottom: 25px; letter-spacing: -0.5px; }
        .impact-section p { max-width: 850px; margin: 0 auto; font-size: 1.15rem; color: #475569; line-height: 1.8; }

        .menu-toggle {
            display: none; font-size: 2rem; background: none; border: none;
            cursor: pointer; color: var(--color-primario); transition: var(--transition);
        }
        .menu-toggle:hover { transform: scale(1.1); }

        @media (max-width: 992px) {
            .menu-toggle { display: block; z-index: 101; }
            nav { position: relative; }
            nav ul {
                display: none; flex-direction: column; position: absolute;
                top: 50px; right: 0; width: 260px;
                background-color: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(15px);
                box-shadow: var(--shadow-lg); border-radius: 16px;
                padding: 25px; gap: 15px; border: 1px solid rgba(0,0,0,0.05);
            }
            nav.is-open ul { display: flex; animation: fadeIn 0.3s ease; }
            nav ul li { width: 100%; }
            nav ul a { display: block; text-align: center; padding: 12px; }
            
            .hero-section { clip-path: none; padding: 80px 20px; }
            .hero-section h1 { font-size: 2.5rem; }
            .section-title { font-size: 2.2rem; }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        footer { background-color: var(--color-primario); color: #e0e7ff; text-align: center; padding: 30px 0; margin-top: 0; }
        footer a { transition: var(--transition); opacity: 0.8; }
        footer a:hover { opacity: 1; color: white; }
        
        .chat-widget { position: fixed; bottom: 30px; right: 30px; z-index: 1000; font-family: 'Poppins', sans-serif; }

        @keyframes pulse-animation {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(243, 156, 18, 0.7); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 15px rgba(243, 156, 18, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(243, 156, 18, 0); }
        }

        .chat-button {
            width: 70px; height: 70px; background-color: var(--color-acento);
            border-radius: 50%; border: 3px solid white; box-shadow: 0 6px 20px rgba(0,0,0,0.25);
            cursor: pointer; display: flex; justify-content: center; align-items: center;
            font-size: 35px; color: white; transition: var(--transition);
            animation: pulse-animation 2.5s infinite; 
        }

        .chat-button:hover { animation: none; transform: scale(1.1) rotate(10deg); background-color: var(--color-acento-hover); }

        .chat-window {
            display: none; width: 380px; height: 500px;
            background-color: white; border-radius: 20px;
            box-shadow: var(--shadow-lg);
            flex-direction: column; overflow: hidden;
            position: absolute; bottom: 90px; right: 0; border: 1px solid rgba(0,0,0,0.05);
            animation: scaleUp 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform-origin: bottom right;
        }
        
        @keyframes scaleUp {
            from { opacity: 0; transform: scale(0.5); }
            to { opacity: 1; transform: scale(1); }
        }

        .chat-header {
            background: linear-gradient(90deg, var(--color-primario), var(--color-primario-light));
            color: white; padding: 20px;
            display: flex; justify-content: space-between; align-items: center;
        }

        .chat-header h4 { margin: 0; font-size: 1.1rem; font-weight: 700; }
        .close-chat { background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; transition: var(--transition); opacity: 0.8; }
        .close-chat:hover { opacity: 1; transform: rotate(90deg); }

        .chat-messages {
            flex-grow: 1; padding: 20px; overflow-y: auto;
            background-color: #f8fafc; display: flex; flex-direction: column; gap: 15px;
        }

        .message { max-width: 85%; padding: 12px 18px; border-radius: 18px; font-size: 0.95rem; line-height: 1.5; box-shadow: var(--shadow-sm); }
        .bot-message { background-color: white; color: var(--color-texto); align-self: flex-start; border-bottom-left-radius: 4px; border: 1px solid #e2e8f0; }
        .user-message { background-color: var(--color-primario); color: white; align-self: flex-end; border-bottom-right-radius: 4px; }

        .chat-input-area { padding: 15px; border-top: 1px solid #f1f5f9; display: flex; gap: 10px; background-color: white; }
        .chat-input-area input { flex-grow: 1; padding: 12px 20px; border: 2px solid #e2e8f0; border-radius: 30px; outline: none; font-family: 'Poppins', sans-serif; transition: var(--transition); }
        .chat-input-area input:focus { border-color: var(--color-primario-light); }
        .chat-input-area button { background-color: var(--color-primario); color: white; border: none; width: 45px; height: 45px; border-radius: 50%; cursor: pointer; transition: var(--transition); display: flex; justify-content: center; align-items: center; font-size: 1.2rem; }
        .chat-input-area button:hover { background-color: var(--color-primario-light); transform: scale(1.05); }

        @media (max-width: 480px) {
            .chat-window { width: calc(100vw - 40px); right: -10px; height: 60vh; }
        }
    </style>
</head>
<body>

    <header>
        <div class="container nav-container">
            <a href="index.php" class="logo">
                <img src="logo.png" alt="Logo Planeando con Causa">
                <span>Herramientas para Docentes</span>
            </a>
            
            <button class="menu-toggle" id="menu-toggle-button" aria-label="Abrir menú">☰</button>

            <nav id="main-nav">
            <ul>
                    <li><a href="mision.html">📜 Misión</a></li>
                    <li><a href="creadores.html">🤝 Creadores</a></li>
                    <li><a href="evidencias.html">📸 Evidencias</a></li>
                    <li><a href="biblioteca.php">📚 Biblioteca</a></li>
                    <li><a href="tutorial_de_usos.html">🎓 ¿Dudas? Tutorial</a></li>
                    
                    <?php if ($nombre_usuario): ?>
                        <li class="user-welcome">
                            <a href="perfil.php">
                                <span>👤</span>
                                <span class="user-name"><?php echo htmlspecialchars(explode(' ', $nombre_usuario)[0]); ?></span>
                            </a>
                        </li>
                        <li><a href="cerrar_sesion.php">Cerrar Sesión</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Iniciar Sesión</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero-section">
            <canvas id="matrix-canvas"></canvas>
            <div class="container">
                <h1>
                    <?php if ($nombre_usuario): ?>
                        ¡Hola de nuevo, <?php echo htmlspecialchars(explode(' ', $nombre_usuario)[0]); ?>!
                    <?php else: ?>
                        Bienvenido, estimado maestr@
                    <?php endif; ?>
                </h1>
                <p>Tu herramienta aliada para una planeación didáctica inteligente, creativa y con propósito social.</p>
                <div class="hero-buttons">
                    <a href="registro.php" class="cta-button">Paso 1 Regístrate aquí</a>
                    <a href="catalogo_de_pagos.php" class="cta-button secondary">Ver Planes de Suscripción</a>
                </div>
            </div>
        </section>

        <section class="features-section">
            <div class="container">
                <h2 class="section-title">Nuestras Herramientas</h2>
                <div class="features-grid">
                    
                    <article class="feature-card">
                        <div class="feature-icon">📌</div>
                        <h3>Generador de Planeaciones</h3>
                        <p>Ahorra tiempo con planeaciones didácticas personalizadas, generadas por IA y alineadas a la NEM en minutos.</p>
                        <a href="<?php echo $puede_usar_basico ? 'generar_plan_basico.php' : $url_no_pagado; ?>" 
                           class="card-button <?php echo !$puede_usar_basico ? 'disabled' : ''; ?>" 
                           title="<?php echo !$puede_usar_basico ? $mensaje_no_pagado : 'Acceso para plan Docente y superior'; ?>">
                            Prueba el Plan Básico
                        </a>
                    </article>

                    <article class="feature-card">
                        <div class="feature-icon">✨</div>
                        <h3>Planeación Avanzada</h3>
                        <p>Añade el contexto de tu grupo y tus necesidades específicas para obtener una planeación profundamente adaptada y realista.</p>
                        <a href="<?php echo $puede_usar_intermedio ? 'generar_plan_intermedio.php' : $url_no_pagado; ?>" 
                           class="card-button <?php echo !$puede_usar_intermedio ? 'disabled' : ''; ?>" 
                           title="<?php echo !$puede_usar_intermedio ? $mensaje_no_pagado : 'Acceso para plan Mentor y superior'; ?>">
                            Crear Plan Avanzado
                        </a>
                    </article>

                    <article class="feature-card">
                        <div class="feature-icon">📶</div>
                        <h3>Planeación por Niveles (PALE)</h3>
                        <p>Crea planes para 1º y 2º de primaria, con actividades específicas para cada nivel de lectoescritura: presilábico, silábico, silábico-alfabético y alfabético.</p>
                         <a href="<?php echo $puede_usar_intermedio ? 'generar_planeacion_avanzada_pale.php' : $url_no_pagado; ?>" 
                           class="card-button <?php echo !$puede_usar_intermedio ? 'disabled' : ''; ?>" 
                           title="<?php echo !$puede_usar_intermedio ? $mensaje_no_pagado : 'Acceso para plan Mentor y superior'; ?>">
                            Diseñar Plan Diferenciado
                        </a>
                    </article>
                    
                    <article class="feature-card">
                        <div class="feature-icon">🧮</div>
                        <h3>Planeación por Niveles (PAM)</h3>
                        <p>Propuestas de aprendizaje para matemáticas, trabajando con el campo de saberes y pensamiento científico. Elige los PDA y te sugerimos las fichas PAM.</p>
                         <a href="<?php echo $puede_usar_intermedio ? 'generar_planeacion_avanzada_pam.php' : $url_no_pagado; ?>" 
                           class="card-button <?php echo !$puede_usar_intermedio ? 'disabled' : ''; ?>" 
                           title="<?php echo !$puede_usar_intermedio ? $mensaje_no_pagado : 'Acceso para plan Mentor y superior'; ?>">
                            Diseñar Plan PAM
                        </a>
                    </article>
                    <article class="feature-card">
                        <div class="feature-icon">🤸‍♀️</div>
                        <h3>Planeación de Educación Física</h3>
                        <p>Crea planeaciones para Educación Física, desde preescolar hasta secundaria, alineadas a los PDA y contenidos vigentes.</p>
                        <a href="<?php echo $puede_usar_basico ? 'generar_planeacion_fisica.php' : $url_no_pagado; ?>" 
                           class="card-button <?php echo !$puede_usar_basico ? 'disabled' : ''; ?>" 
                           title="<?php echo !$puede_usar_basico ? $mensaje_no_pagado : 'Acceso para plan Docente y superior'; ?>">
                            Crear Plan de E.F.
                        </a>
                    </article>

                    <article class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h3>Evaluación Diagnóstica</h3>
                        <p>Maestro, aquí podemos elaborar tu evaluación diagnóstica para conocer el punto de partida de tus alumnos.</p>
                        <a href="<?php echo $puede_usar_basico ? 'generar_evaluacion.php' : $url_no_pagado; ?>" 
                           class="card-button <?php echo !$puede_usar_basico ? 'disabled' : ''; ?>" 
                           title="<?php echo !$puede_usar_basico ? $mensaje_no_pagado : 'Acceso para plan Docente y superior'; ?>">
                            Elaborar Diagnóstico
                        </a>
                    </article>
                    
                    <article class="feature-card">
                        <div class="feature-icon">🧐</div>
                        <h3>Exámenes Personalizados</h3>
                        <p>Elige los contenidos, campos y PDA para generar exámenes con nivel de complejidad básico, intermedio o avanzado.</p>
                        <a href="<?php echo $puede_usar_intermedio ? 'generar_examen_nem.php' : $url_no_pagado; ?>" 
                           class="card-button <?php echo !$puede_usar_intermedio ? 'disabled' : ''; ?>" 
                           title="<?php echo !$puede_usar_intermedio ? $mensaje_no_pagado : 'Acceso para plan Mentor y superior'; ?>">
                            Crear Examen
                        </a>
                    </article>
                    <article class="feature-card">
                        <div class="feature-icon">📄</div>
                        <h3>Protocolos Educativos</h3>
                        <p>Accede a normativas y guías actualizadas para asegurar que tu práctica docente cumpla con los más altos estándares.</p>
                        <a href="<?php echo $puede_usar_intermedio ? 'protocolostest.php' : $url_no_pagado; ?>" 
                           class="card-button <?php echo !$puede_usar_intermedio ? 'disabled' : ''; ?>" 
                           title="<?php echo !$puede_usar_intermedio ? $mensaje_no_pagado : 'Acceso para plan Mentor y superior'; ?>">
                            Consultar Protocolos
                        </a>
                    </article>

                    <article class="feature-card">
                        <div class="feature-icon">📝</div>
                        <h3>Bitácora de Incidentes</h3>
                        <p>Registra, analiza y gestiona incidentes escolares con el apoyo de IA para garantizar el seguimiento y cumplimiento de protocolos.</p>
                        <a href="<?php echo $puede_usar_intermedio ? 'generar_bitacora_de_profesor.php' : $url_no_pagado; ?>" 
                           class="card-button <?php echo !$puede_usar_intermedio ? 'disabled' : ''; ?>" 
                           title="<?php echo !$puede_usar_intermedio ? $mensaje_no_pagado : 'Acceso para plan Mentor y superior'; ?>">
                            Gestionar Bitácora
                        </a>
                    </article>
                    <article class="feature-card">
                        <div class="feature-icon">📚</div>
                        <h3>Material de Estudio SIMULADORES</h3>
                        <p>Prepárate para tu crecimiento profesional con material, simuladores y guías actualizadas para tu éxito.</p>
                        <a href="<?php echo $puede_usar_intermedio ? 'catalogo_de_servicios.php' : $url_no_pagado; ?>" 
                           class="card-button <?php echo !$puede_usar_intermedio ? 'disabled' : ''; ?>" 
                           title="<?php echo !$puede_usar_intermedio ? $mensaje_no_pagado : 'Acceso para plan Mentor y superior'; ?>">
                            Estudiar Ahora
                        </a>
                    </article>
                    
                    <article class="feature-card">
                        <div class="feature-icon">🧠</div>
                        <h3>Planeación para NEURODIVERGENTES</h3>
                        <p>Genera planes de clase inclusivos y adaptados para estudiantes con TDAH, autismo, dislexia y otras neurodivergencias.</p>
                        <a href="<?php echo $puede_usar_intermedio ? 'generar_planeacion_neurodivergentes.php' : $url_no_pagado; ?>" 
                           class="card-button <?php echo !$puede_usar_intermedio ? 'disabled' : ''; ?>" 
                           title="<?php echo !$puede_usar_intermedio ? $mensaje_no_pagado : 'Acceso para plan Mentor y superior'; ?>">
                            Crear Plan Inclusivo
                        </a>
                    </article>

                    <article class="feature-card">
                        <div class="feature-icon">🕊️</div>
                        <h3>Cultura de Paz y Convivencia</h3>
                        <p>Genera estrategias y actividades para promover un ambiente escolar positivo, respetuoso y de convivencia pacífica.</p>
                        <a href="<?php echo $puede_usar_intermedio ? 'generar_cultura_paz.php' : $url_no_pagado; ?>" 
                           class="card-button <?php echo !$puede_usar_intermedio ? 'disabled' : ''; ?>" 
                           title="<?php echo !$puede_usar_intermedio ? $mensaje_no_pagado : 'Acceso para plan Mentor y superior'; ?>">
                            Fomentar la Paz
                        </a>
                    </article>

                    <article class="feature-card">
                    <div class="feature-icon">🧩</div>
                    <h3>Planeación Transversal</h3>
                    <p>Diseña proyectos integrales seleccionando múltiples campos formativos, ejes articuladores y Procesos de Desarrollo de Aprendizaje (PDA) en una sola secuencia didáctica.</p>
                    <a href="<?php echo $puede_usar_intermedio ? 'generar_planeacion_transversal.php' : $url_no_pagado; ?>" 
                    class="card-button <?php echo !$puede_usar_intermedio ? 'disabled' : ''; ?>" 
                    title="<?php echo !$puede_usar_intermedio ? $mensaje_no_pagado : 'Acceso para plan Mentor y superior'; ?>">
                    Crear Planeación Transversal
                    </a>
                    </article>

                    <article class="feature-card">
                    <div class="feature-icon">📂</div>
                    <h3>Adecuación Inteligente</h3>
                    <p>Sube tu planeación y mejora su impacto. Describe las necesidades o problemáticas de tus estudiantes y recibe adecuaciones personalizadas utilizando todo el poder de la inteligencia artificial.</p>
                    
                    <a href="<?php echo $puede_usar_intermedio ? 'generar_planeacion_potenciada.php' : $url_no_pagado; ?>" 
                    class="card-button <?php echo !$puede_usar_intermedio ? 'disabled' : ''; ?>" 
                    title="<?php echo !$puede_usar_intermedio ? $mensaje_no_pagado : 'Acceso para plan Mentor y superior'; ?>">
                    Subir y Adecuar Planeación
                    </a>
                    </article>

                    <article class="feature-card">
                    <div class="feature-icon">🛡️</div>
                    <h3>Simulador de Crisis con Padres</h3>
                    <p>¿Reuniones que terminan en conflicto? Entrena con nuestro simulador de IA. Enfréntate a escenarios problemáticos virtuales y domina el arte de la comunicación asertiva antes de la reunión real.</p>
                    
                    <a href="<?php echo $puede_usar_intermedio ? 'generar_simulador_padres_problematicos.php' : $url_no_pagado; ?>" 
                    class="card-button <?php echo !$puede_usar_intermedio ? 'disabled' : ''; ?>" 
                    title="<?php echo !$puede_usar_intermedio ? $mensaje_no_pagado : 'Acceso para plan Mentor y superior'; ?>">
                    Iniciar Simulador
                    </a>
                    </article>

                    <article class="feature-card">
                            <div class="feature-icon">📆</div>
                            <h3>Eventos Cívicos y Periódicos (Beta)</h3>
                            <p>Organiza fácilmente efemérides, bailables, kermeses, representaciones y altares adaptados a las fechas cívicas del ciclo escolar.</p>
                            <a href="<?php echo $puede_usar_intermedio ? 'generar_periodico_mural.php' : $url_no_pagado; ?>" 
                            class="card-button <?php echo !$puede_usar_intermedio ? 'disabled' : ''; ?>" 
                             title="<?php echo !$puede_usar_intermedio ? $mensaje_no_pagado : 'Acceso para plan Mentor y superior'; ?>">
                            Organizar Evento
                        </a>
                    </article>


                    <article class="feature-card">
                    <div class="feature-icon">✏️</div>
                    <h3>Planeación por Asignaturas (Beta)</h3>
                    <p>Diseña secuencias didácticas enfocadas en el desarrollo de contenidos específicos, integrando los PDA vigentes con una evaluación formativa estructurada.</p>
                    <a href="<?php echo $puede_usar_intermedio ? 'generar_planeacion_competencias.php' : $url_no_pagado; ?>" 
                    class="card-button <?php echo !$puede_usar_intermedio ? 'disabled' : ''; ?>" 
                    title="<?php echo !$puede_usar_intermedio ? $mensaje_no_pagado : 'Acceso para plan Mentor y superior'; ?>">
                    Crear Planeación
                    </a>
                    </article>

                    <article class="feature-card">
                        <div class="feature-icon">🛋️</div>
                        <h3>Sesiones Psicológicas</h3>
                        <p>Accede a tu sesión de apoyo psicológico mensual. Un espacio seguro, confidencial y profesional dedicado a cuidar tu bienestar emocional y mental.</p>
                        <a href="<?php echo $puede_usar_avanzado ? 'index3.php' : $url_no_pagado; ?>" 
                           class="card-button <?php echo !$puede_usar_avanzado ? 'disabled' : ''; ?>" 
                           title="<?php echo !$puede_usar_avanzado ? $mensaje_no_pagado : 'Acceso exclusivo para plan Líder con Causa'; ?>">
                            Ingresar a Sesión
                        </a>
                    </article>


                                    <article class="feature-card">
                    <div class="feature-icon">📺</div>
                    <h3>Planeación Telesecundarias</h3>
                    <p>Diseña tus clases articulando hasta dos campos formativos. Selecciona múltiples asignaturas con sus respectivos contenidos y PDA en una herramienta exclusiva para el modelo de Telesecundaria.</p>
                    
                    <!-- Aquí cambiamos $puede_usar_avanzado por $puede_usar_intermedio -->
                    <a href="<?php echo $puede_usar_intermedio ? 'generar_telesecundarias.php' : $url_no_pagado; ?>" 
                    class="card-button <?php echo !$puede_usar_intermedio ? 'disabled' : ''; ?>" 
                    title="<?php echo !$puede_usar_intermedio ? $mensaje_no_pagado : 'Acceso para plan Docente con Causa (Intermedio)'; ?>">
                        Ingresar a Herramienta
                    </a>
                </article>


                <article class="feature-card">
                    <div class="feature-icon">🤖</div>
                    <h3>Simulador y Tutor IA para USICAMM</h3>
                    <p>Nuestro algoritmo de IA optimizado para estudiar y aprobar los exámenes de USICAMM. Un agente interactivo que evalúa lo positivo y negativo de tus proyectos, ayudándote a mejorar mediante la recomendación autónoma de talleres, libros, capacitaciones y diplomados.</p>
                    
                    <a href="<?php echo $puede_usar_intermedio ? 'index_agente_de_estudio.php' : $url_no_pagado; ?>" 
                    class="card-button <?php echo !$puede_usar_intermedio ? 'disabled' : ''; ?>" 
                    title="<?php echo !$puede_usar_intermedio ? $mensaje_no_pagado : 'Acceso para plan Docente con Causa (Intermedio)'; ?>">
                        Ingresar a Herramienta
                    </a>
                </article>

                </div>

                <div class="hero-buttons" style="margin-top: 50px; flex-direction: row; justify-content: center; flex-wrap: wrap; gap: 20px;">
                    <a href="generar_expertos_externos.php" class="cta-button">
                       Consulta Expertos en educación
                    </a>
                    <a href="generar_herramientas_de_padres_de_familia.php" class="cta-button secondary">
                       Herramientas para Padres de familia
                    </a>
                </div>
                </div>
        </section>
        
        <section class="impact-section">
            <div class="container">
                <h2>💙 Impacto Social: Donaciones y Transparencia</h2>
                <p>Creemos en un mundo donde la generosidad es la norma, no la excepción. Nacimos de la convicción de que cada persona tiene el poder de generar un cambio tangible y duradero. No somos solo una plataforma de suscripción; somos un movimiento. Un movimiento de personas que se niegan a ser espectadores y eligen ser agentes de cambio. Con cada suscripción, no solo adquieres un producto o servicio, te unes a una promesa: la de sembrar vida y esperanza. Juntos, estamos reescribiendo la historia, un niño a la vez, un árbol a la vez. Esta no es nuestra misión, es nuestra razón de ser.</p>
            </div>
        </section>
    </main>

    <footer style="background-color: #1e3a8a; color: #e0e7ff; text-align: center; padding: 25px 0; border-top: 1px solid rgba(255,255,255,0.1);">
        <p style="margin: 0 0 10px 0; font-weight: 600;">&copy; 2025 Planeando con Causa | Todos los derechos reservados</p>
        <div>
            <a href="servicio_cliente.html" style="color: #cbd5e1; text-decoration: none; font-size: 0.9rem;">
                Ayuda y Servicio al Cliente
            </a>
        </div>
    </footer>

    <div class="chat-widget">
        <div class="chat-window" id="chat-window">
            <div class="chat-header">
                <h4>🤖 Pregunta tus Dudas</h4>
                <button class="close-chat" id="close-chat">✕</button>
            </div>
            <div class="chat-messages" id="chat-messages">
                <div class="message bot-message">
                    <?php if ($nombre_usuario): ?>
                        Hola <b><?php echo htmlspecialchars(explode(' ', $nombre_usuario)[0]); ?></b>, soy tu asistente virtual. ¿Puedo ayudarte con algo?
                    <?php else: ?>
                        ¡Hola! Soy el asistente de Docentes con Causa. ¿Tienes alguna duda? Pregúntanos.
                    <?php endif; ?>
                </div>
            </div>
            <div class="chat-input-area">
                <input type="text" id="chat-input" placeholder="Escribe tu duda aquí...">
                <button id="send-btn">➤</button>
            </div>
        </div>

        <button class="chat-button" id="chat-toggle" aria-label="Abrir chat">
            💬
        </button>
    </div>

    <script>
        const canvas = document.getElementById('matrix-canvas');
        const ctx = canvas.getContext('2d');

        function resizeCanvas() {
            canvas.width = canvas.offsetWidth;
            canvas.height = canvas.offsetHeight;
        }
        
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        const letters = 'SOCRATESYARISTOTELES';
        const fontSize = 14;
        let columns = canvas.width / fontSize;
        let drops = [];

        for(let x = 0; x < columns; x++) {
            drops[x] = 1;
        }

        function drawMatrix() {
            // Fondo oscuro semitransparente para la "estela"
            ctx.fillStyle = 'rgba(30, 58, 138, 0.1)'; 
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // Color del texto (azul claro/celeste)
            ctx.fillStyle = '#60a5fa'; 
            ctx.font = fontSize + 'px monospace';

            for(let i = 0; i < drops.length; i++) {
                const text = letters.charAt(Math.floor(Math.random() * letters.length));
                ctx.fillText(text, i * fontSize, drops[i] * fontSize);

                if(drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
                    drops[i] = 0;
                }
                
                drops[i]++;
            }
        }

        setInterval(drawMatrix, 50);

        window.addEventListener('resize', () => {
            columns = canvas.width / fontSize;
            drops = [];
            for(let x = 0; x < columns; x++) {
                drops[x] = 1;
            }
        });
    </script>

    <script>
        const menuToggle = document.getElementById('menu-toggle-button');
        const mainNav = document.getElementById('main-nav');

        if (menuToggle && mainNav) {
            menuToggle.addEventListener('click', () => {
                mainNav.classList.toggle('is-open');
                if (mainNav.classList.contains('is-open')) {
                    menuToggle.innerHTML = '✕';
                    menuToggle.setAttribute('aria-label', 'Cerrar menú');
                } else {
                    menuToggle.innerHTML = '☰';
                    menuToggle.setAttribute('aria-label', 'Abrir menú');
                }
            });
        }
    </script>

    <script>
        const chatToggle = document.getElementById('chat-toggle');
        const chatWindow = document.getElementById('chat-window');
        const closeChat = document.getElementById('close-chat');
        const sendBtn = document.getElementById('send-btn');
        const chatInput = document.getElementById('chat-input');
        const chatMessages = document.getElementById('chat-messages');

        function toggleChat() {
            if (chatWindow.style.display === 'flex') {
                chatWindow.style.display = 'none';
                chatToggle.innerHTML = '💬';
                chatToggle.style.animation = 'pulse-animation 2.5s infinite';
            } else {
                chatWindow.style.display = 'flex';
                chatToggle.innerHTML = '✕';
                chatToggle.style.animation = 'none';
                chatInput.focus();
            }
        }

        chatToggle.addEventListener('click', toggleChat);
        closeChat.addEventListener('click', toggleChat);

        async function sendMessage() {
            const text = chatInput.value.trim();
            if (text === '') return;

            addMessage(text, 'user-message');
            chatInput.value = '';

            showTypingIndicator();

            try {
                const response = await fetch('chat_backend.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ mensaje: text })
                });

                if (!response.ok) {
                    throw new Error('Error en la red o el archivo PHP no existe');
                }

                const data = await response.json();
                removeTypingIndicator();
                
                const respuestaFinal = data.respuesta || "Lo siento, no pude procesar la respuesta.";
                addMessage(respuestaFinal, 'bot-message');

            } catch (error) {
                console.error('Error:', error);
                removeTypingIndicator();
                addMessage("⚠️ Lo siento, hubo un error de conexión. Verifica que 'chat_backend.php' esté en la carpeta correcta.", 'bot-message');
            }
        }

        function addMessage(text, className) {
            const div = document.createElement('div');
            div.classList.add('message', className);

            if (className === 'user-message') {
                div.textContent = text; 
            } else {
                div.innerHTML = text; 
            }

            chatMessages.appendChild(div);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function showTypingIndicator() {
            const div = document.createElement('div');
            div.classList.add('message', 'bot-message');
            div.id = 'typing-indicator';
            div.innerHTML = 'Escribiendo...';
            chatMessages.appendChild(div);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function removeTypingIndicator() {
            const typing = document.getElementById('typing-indicator');
            if(typing) typing.remove();
        }

        sendBtn.addEventListener('click', sendMessage);
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });
    </script>
    
    
</body>
</html>