<?php
session_start(); // Inicia o reanuda la sesión para recordar al usuario

// ===== INICIO DEL NUEVO BLOQUE DE PERMISOS =====
$nombre_usuario = $_SESSION['usuario_nombre'] ?? null;

// 1. Obtenemos el plan del usuario desde la sesión. Si no existe o no ha pagado, será 0.
$plan_activo = $_SESSION['plan_activo'] ?? 0;

// 2. Creamos variables claras para saber a qué tiene acceso el usuario.
$puede_usar_basico     = ($plan_activo >= 1); // Verdadero si el plan es 1, 2 o 3.
$puede_usar_intermedio = ($plan_activo >= 2); // Verdadero si el plan es 2 o 3.
// $puede_usar_avanzado   = ($plan_activo >= 3); // Para cuando implementes el plan Líder.

// 3. Definimos el mensaje y la URL para los botones bloqueados.
$url_no_pagado = 'catalogo_de_pagos.html';
$mensaje_no_pagado = 'Función exclusiva para suscriptores. ¡Mejora tu plan!';
// ===== FIN DEL NUEVO BLOQUE DE PERMISOS =====
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Docentes con causa - Herramientas para Docentes</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

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
            box-shadow: 0 2px 10px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 100;
        }
        .nav-container { display: flex; justify-content: space-between; align-items: center; }
        .logo { display: flex; align-items: center; text-decoration: none; color: var(--color-primario); font-weight: 700; font-size: 1.5rem; }
        .logo img { width: 50px; margin-right: 10px; }
        nav ul { list-style: none; margin: 0; padding: 0; display: flex; align-items: center; gap: 25px; }
        nav a { text-decoration: none; color: var(--color-texto); font-weight: 600; padding: 8px 12px; border-radius: 6px; transition: background-color 0.3s, color 0.3s; }
        nav a:hover { background-color: var(--color-primario); color: white; }
        
        .user-welcome a {
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: #eef2ff;
            border: 1px solid #c7d2fe;
            color: var(--color-primario);
        }
        .user-welcome a:hover {
            background-color: var(--color-primario);
            color: white;
        }
        .user-welcome .user-name {
            font-weight: 700;
        }

        .hero-section { background-color: #eef2ff; text-align: center; padding: 80px 20px; }
        .hero-section h1 { font-size: 3rem; color: var(--color-primario); font-weight: 700; margin-bottom: 20px; }
        .hero-section p { font-size: 1.2rem; max-width: 700px; margin: 0 auto 30px auto; color: #475569; }
        .hero-buttons { display: flex; flex-direction: column; align-items: center; gap: 15px; }
        .cta-button {
            display: inline-block; background-color: var(--color-acento); color: white; text-decoration: none;
            padding: 15px 35px; border-radius: 50px; font-weight: 700; font-size: 1.1rem;
            transition: background-color 0.3s, transform 0.3s; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .cta-button:hover { background-color: #d35400; transform: translateY(-3px); }
        .cta-button.secondary {
            background-color: transparent;
            border: 2px solid var(--color-primario);
            color: var(--color-primario);
        }
        .cta-button.secondary:hover {
            background-color: var(--color-primario);
            color: white;
        }
        .features-section { padding: 80px 0; }
        .section-title { text-align: center; font-size: 2.5rem; color: var(--color-primario); margin-bottom: 50px; }
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; }
        .feature-card {
            background-color: var(--color-tarjeta); border-radius: 12px; padding: 35px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08); text-align: center; display: flex; flex-direction: column;
        }
        .feature-icon { font-size: 3rem; margin-bottom: 20px; }
        .feature-card h3 { font-size: 1.5rem; color: var(--color-primario); margin-bottom: 15px; }
        .feature-card p { margin-bottom: 25px; flex-grow: 1; }
        .feature-card .card-button {
            display: inline-block; background-color: #eef2ff; color: var(--color-primario);
            text-decoration: none; padding: 10px 20px; border-radius: 50px;
            font-weight: 600; transition: background-color 0.3s; margin-top: auto;
        }
        .feature-card .card-button:hover { background-color: #dbeafe; }
        .card-button.disabled { background-color: #e0e0e0; color: #888; cursor: not-allowed; pointer-events: none; }
        footer { background-color: var(--color-primario); color: #e0e7ff; text-align: center; padding: 25px 0; margin-top: 40px; }
        
        .impact-section {
            background-color: #eef2ff;
            padding: 60px 20px;
            text-align: center;
            margin-top: 80px;
        }
        .impact-section h2 {
            font-size: 2.2rem;
            color: var(--color-primario);
            margin-bottom: 20px;
        }
        .impact-section p {
            max-width: 800px;
            margin: 0 auto;
            font-size: 1.1rem;
            color: #475569;
        }

        /* ===== INICIO CAMBIOS PARA MENÚ RESPONSIVE ===== */
        .menu-toggle {
            display: none; /* Oculto en pantallas grandes */
            font-size: 2rem;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--color-primario);
        }

        /* Media Query: Se aplica en pantallas de 992px o menos */
        @media (max-width: 992px) {
            .menu-toggle {
                display: block; /* Mostramos el botón de hamburguesa */
                z-index: 101; /* Nos aseguramos que esté por encima de otros elementos */
            }

            nav {
                position: relative; /* Contenedor para el menú desplegable */
            }

            nav ul {
                display: none; /* Ocultamos el menú por defecto */
                flex-direction: column;
                position: absolute;
                top: 50px; /* Posición justo debajo del header */
                right: 0;
                width: 250px;
                background-color: var(--color-tarjeta);
                box-shadow: 0 8px 16px rgba(0,0,0,0.15);
                border-radius: 8px;
                padding: 20px;
                gap: 15px;
            }

            nav.is-open ul {
                display: flex; /* Mostramos el menú cuando tenga la clase .is-open */
            }

            nav ul li {
                width: 100%;
            }

            nav ul a {
                display: block; /* Hacemos que los enlaces ocupen todo el ancho del li */
                text-align: center;
            }
        }
        /* ===== FIN CAMBIOS PARA MENÚ RESPONSIVE ===== */

    </style>
</head>
<body>

    <header>
        <div class="container nav-container">
            <a href="index.php" class="logo">
                <img src="logo.png" alt="Logo Planeando con Causa">
                <span>Docentes con Causa</span>
            </a>
            
            <button class="menu-toggle" id="menu-toggle-button" aria-label="Abrir menú">☰</button>

            <nav id="main-nav">
            <ul>
                    <li><a href="mision.html">📜 Misión</a></li>
                    <li><a href="vision.html">🔭 Visión</a></li>
                    <li><a href="evidencias.html">📸 Evidencias</a></li>
                    <li><a href="biblioteca.php">📚 Biblioteca</a></li>
                    <li><a href="tutorial_de_usos.html">🎓 Tutorial</a></li>
                    
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
                    <a href="registro.php" class="cta-button">Paso 1 Registrate aquí</a>
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
                        <h3>Material de Estudio</h3>
                        <p>Prepárate para tu crecimiento profesional con material, simuladores y guías actualizadas para tu éxito.</p>
                        <a href="<?php echo $puede_usar_intermedio ? 'crecimiento_profesional.php' : $url_no_pagado; ?>" 
                           class="card-button <?php echo !$puede_usar_intermedio ? 'disabled' : ''; ?>" 
                           title="<?php echo !$puede_usar_intermedio ? $mensaje_no_pagado : 'Acceso para plan Mentor y superior'; ?>">
                            Estudiar Ahora
                        </a>
                    </article>
                    
                </div>
            </div>
        </section>
        
        <section class="impact-section">
            <div class="container">
                <h2>💙 Impacto Social: Donaciones y Transparencia</h2>
                <p>Creemos en un mundo donde la generosidad es la norma, no la excepción. Nacimos de la convicción de que cada persona tiene el poder de generar un cambio tangible y duradero. No somos solo una plataforma de suscripción; somos un movimiento. Un movimiento de personas que se niegan a ser espectadores y eligen ser agentes de cambio. Con cada suscripción, no solo adquieres un producto o servicio, te unes a una promesa: la de sembrar vida y esperanza. Juntos, estamos reescribiendo la historia, un niño a la vez, un árbol a la vez. Esta no es nuestra misión, es nuestra razón de ser</p>
            </div>
        </section>
    </main>

    <footer style="background-color: #1e3a8a; color: #e0e7ff; text-align: center; padding: 25px 0; margin-top: 40px;">
        <p style="margin: 0 0 10px 0;">&copy; 2025 Planeando con Causa | Todos los derechos reservados</p>
        <div>
            <a href="servicio_cliente.html" style="color: #e0e7ff; text-decoration: none;">
                Ayuda y Servicio al Cliente
            </a>
        </div>
    </footer>
    
    <script>
        // Seleccionamos el botón y el menú de navegación por sus IDs
        const menuToggle = document.getElementById('menu-toggle-button');
        const mainNav = document.getElementById('main-nav');

        // Verificamos que ambos elementos existan
        if (menuToggle && mainNav) {
            // Añadimos un "escuchador de eventos" que se activa con un clic
            menuToggle.addEventListener('click', () => {
                // La función classList.toggle() añade la clase 'is-open' si no la tiene,
                // y la quita si ya la tiene. Esto es lo que muestra/oculta el menú.
                mainNav.classList.toggle('is-open');

                // Cambiamos el ícono de hamburguesa a una 'X' y viceversa para mejor UX
                if (mainNav.classList.contains('is-open')) {
                    menuToggle.innerHTML = '✕'; // Ícono de cierre
                    menuToggle.setAttribute('aria-label', 'Cerrar menú');
                } else {
                    menuToggle.innerHTML = '☰'; // Ícono de hamburguesa
                    menuToggle.setAttribute('aria-label', 'Abrir menú');
                }
            });
        }
    </script>
    </body>
</html>