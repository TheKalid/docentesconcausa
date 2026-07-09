<?php
session_start(); // Mantenemos la sesión del usuario activa
$nombre_usuario = $_SESSION['usuario_nombre'] ?? null; // Obtenemos el nombre del usuario si ha iniciado sesión
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expertos en Educación - Docentes con Causa</title>
    
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
            --sombra-suave: 0 8px 25px rgba(0,0,0,0.08);
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

        .page-header {
            background-color: #eef2ff;
            text-align: center;
            padding: 60px 20px;
        }
        .page-header h1 {
            font-size: 2.8rem;
            color: var(--color-primario);
            margin: 0 0 15px 0;
        }
        .page-header p {
            font-size: 1.1rem;
            max-width: 800px;
            margin: 0 auto;
            color: #475569;
        }

        .team-container {
            padding: 80px 0;
        }
        .profile-section {
            margin-bottom: 60px;
        }
        .profile-section:last-child {
            margin-bottom: 0;
        }
        .profile-content {
            display: flex;
            align-items: center;
            gap: 50px;
            background-color: var(--color-tarjeta);
            padding: 40px;
            border-radius: 15px;
            box-shadow: var(--sombra-suave);
        }
        
        .profile-section:nth-child(even) .profile-content {
            flex-direction: row-reverse;
        }
        
        .profile-image-container {
            flex-shrink: 0;
        }
        .profile-image {
            width: 160px;
            height: 160px;
            border-radius: 50%; /* Formato circular */
            object-fit: cover; /* Evita que la imagen se deforme */
            border: 4px solid var(--color-tarjeta);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .profile-image:hover {
            transform: scale(1.05); /* Efecto de zoom suave */
        }
        
        .profile-details {
            text-align: left;
            width: 100%;
        }
        .profile-details .name {
            font-size: 2rem;
            color: var(--color-primario);
            margin: 0 0 5px 0;
            font-weight: 700;
        }
        .profile-details .role {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--color-acento);
            margin-bottom: 20px;
        }
        
        .profile-details .expert-info {
            list-style: none;
            padding: 0;
            margin: 25px 0;
            text-align: left;
        }
        .profile-details .expert-info li {
            margin-bottom: 12px;
            font-size: 1rem;
            line-height: 1.6;
        }
        .profile-details .expert-info strong {
            color: var(--color-primario);
            display: block;
            font-size: 0.9rem;
            margin-bottom: 2px;
        }
         .profile-details .expert-info .contact-link {
            color: var(--color-acento);
            text-decoration: none;
            font-weight: 600;
        }
        .profile-details .expert-info .contact-link:hover {
            text-decoration: underline;
        }

        .menu-toggle { display: none; font-size: 2rem; background: none; border: none; cursor: pointer; color: var(--color-primario); }
        
        /* --- INICIO: ESTILOS PARA EL BOTÓN DE REGRESAR --- */
        .back-button-container {
            text-align: center;
            margin-top: 80px; /* Espacio superior para separar de la última tarjeta */
        }
        .back-button {
            display: inline-block;
            padding: 15px 35px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #ffffff;
            background-color: var(--color-primario);
            text-decoration: none;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
            transition: background-color 0.3s ease, transform 0.3s ease;
        }
        .back-button:hover {
            background-color: #1c3170; /* Tono más oscuro al pasar el cursor */
            transform: translateY(-3px); /* Ligero efecto de elevación */
        }
        /* --- FIN: ESTILOS PARA EL BOTÓN DE REGRESAR --- */

        @media (max-width: 992px) {
            .profile-content, .profile-section:nth-child(even) .profile-content {
                flex-direction: column;
                text-align: center;
                gap: 30px;
            }
            .profile-details {
                text-align: center;
            }
             .profile-details .expert-info {
                text-align: center;
            }
            .profile-image { /* Ajuste para móviles */
                width: 140px;
                height: 140px;
            }
            .menu-toggle { display: block; z-index: 101; }
            nav { position: relative; }
            nav ul { display: none; flex-direction: column; position: absolute; top: 50px; right: 0; width: 250px; background-color: var(--color-tarjeta); box-shadow: 0 8px 16px rgba(0,0,0,0.15); border-radius: 8px; padding: 20px; gap: 15px; }
            nav.is-open ul { display: flex; }
            nav ul li { width: 100%; }
            nav ul a { display: block; text-align: center; }
        }
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
                    <li><a href="creadores.html">🤝 Creadores</a></li>
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
        <section class="page-header">
            <div class="container">
                <h1>Tu Red de Expertos en Guadalajara</h1>
                <p>Hemos seleccionado a los mejores especialistas de la región para brindarte apoyo de primer nivel. Conecta con profesionales en áreas críticas como IA, ciberseguridad, psicología, talleres de ajedrez, juegos matemáticos, literatura e investigación para enriquecer tu práctica docente y llevar tu aula al siguiente nivel.</p>
            </div>
        </section>

        <section class="team-container">
            <div class="container">
                
                <article class="profile-section">
                    <div class="profile-content">
                        <div class="profile-image-container">
                            <img src="arturo.png" alt="Dra. Mónica Velasco" class="profile-image">
                        </div>
                        <div class="profile-details">
                            <h3 class="name">Dr. Arturo Moran</h3>
                            <p class="role">Especialista en Inteligencia Artificial Educativa y Seguridad Informática.</p>
                            <ul class="expert-info">
                                <li><strong>Nombre del experto:</strong> Dr. Arturo Morán.</li>
                                <li><strong>Experiencia:</strong> Doctorado en Educación. Ing. En sistemas Computacionales.Con más de 4 años de experiencia implementando tecnología en escuelas de Jalisco.</li>
                                <li><strong>Disponibilidad:</strong> Talleres y consultorías disponibles en formato digital (en línea) y presencial.</li>
                                <li><strong>Contacto:</strong> <a class="contact-link" href="mailto:IngArturoDaliMoran@gmail.com">IngArturoDaliMoran@gmail.com</a></li>
                            </ul>
                        </div>
                    </div>
                </article>

                <article class="profile-section">
                    <div class="profile-content">
                         <div class="profile-image-container">
                            <img src="javiero.png" alt="Ing. Javier Luján" class="profile-image">
                        </div>
                        <div class="profile-details">
                            <h3 class="name">Mtro. Javier Ornelas</h3>
                            <p class="role">Consultor en Ábaco Matemático. </p>
                             <ul class="expert-info">
                                <li><strong>Nombre del experto:</strong> Mtro. Javier Ornelas</li>
                                <li><strong>Experiencia:</strong> Maestro de apoyo de USAER y asesor en diferentes ámbitos, ha dedicado su carrera a mejorar la calidad de la educación. Fruto de su labor, ha desarrollado un curso-taller único sobre el uso del ábaco. Su principal objetivo es compartir con otros docentes un método efectivo para la comprensión del sistema decimal y su aplicación en la resolución de problemas matemáticos cotidianos y operaciones básicas. Es un profesional comprometido con la creación de espacios de reflexión para construir estrategias didácticas más productivas entre colegas  .</li>
                                <li><strong>Disponibilidad:</strong> Conferencias y talleres disponibles en formato digital (en línea) y presencial.</li>
                                <li><strong>Contacto:</strong> <a class="contact-link" href="mailto: profeornelas@hotmail.com"> profeornelas@hotmail.com Y 33-14-11-38-86 </a></li>
                            </ul>
                        </div>
                    </div>
                </article>

                <article class="profile-section">
                    <div class="profile-content">
                        <div class="profile-image-container">
                            <img src="mili.png" alt="Mtra. Sofía Ramírez" class="profile-image">
                        </div>
                        <div class="profile-details">
                            <h3 class="name">Dra. Milagros Montserrat</h3>
                            <p class="role">Asesoría y capacitación.</p>
                            <ul class="expert-info">
                                <li><strong>Nombre del experto:</strong> Dra. Milagros Montserrat.</li>
                                <li><strong>Experiencia:</strong> Dra en educación, especializada en Lenguajes, ayuda en capacitaciones para promocion horizontal, ingreso o vertical.</li>
                                <li><strong>Disponibilidad:</strong> Asesorías individuales y grupales disponibles en formato digital (en línea) y presencial.</li>
                                <li><strong>Contacto:</strong> <a class="contact-link" href="mailto:milagros.mariscal@jaliscoedu.mx">milagros.mariscal@jaliscoedu.mx</a></li>
                            </ul>
                        </div>
                    </div>
                </article>

                <article class="profile-section">
                    <div class="profile-content">
                        <div class="profile-image-container">
                            <img src="marios.png" alt="Fotografía del instructor Mario Alfredo S.P." class="profile-image">
                        </div>
                        <div class="profile-details">
                            <h3 class="name">Lic. Mario Alfredo S.P.</h3>
                            <p class="role">Instructor de Ajedrez Educativo</p>
                            <ul class="expert-info">
                                <li><strong>Nombre del experto:</strong> Lic. Mario Alfredo S.P.</li>
                                <li><strong>Experiencia:</strong> Maestro de ajedrez. Ha implementado con éxito el programa de ajedrez en más de 2 escuelas primarias.</li>
                                <li><strong>Disponibilidad:</strong> Clases y torneos disponibles en formato digital (en línea) y presencial.</li>
                                <li><strong>Contacto:</strong> <a class="contact-link" href="mailto:Mario.Salas@expertosgdl.com">Mario.Salas@expertosgdl.com</a></li>
                            </ul>
                        </div>
                    </div>
                </article>

                <article class="profile-section">
                    <div class="profile-content">
                        <div class="profile-image-container">
                            <img src="paor.png" alt="Psic. Paola Romero" class="profile-image">
                        </div>
                        <div class="profile-details">
                            <h3 class="name">Psic. Paola Romero</h3>
                            <p class="role">Especialista en Terapia de Lenguaje y Conductual</p>
                            <ul class="expert-info">
                                <li><strong>Nombre del experto:</strong> Psic. Paola Romero</li>
                                <li><strong>Experiencia:</strong> Psicóloga con maestría en educación, altamente capacitada en terapia de lenguaje y el enfoque conductivo-conductual para niños y adolescentes.</li>
                                <li><strong>Disponibilidad:</strong> Asesorías especializadas disponibles tanto en formato presencial como en línea.</li>
                                <li><strong>Contacto:</strong> <a class="contact-link" href="mailto:paola.romero@expertosgdl.com">paola.romero@expertosgdl.com</a></li>
                            </ul>
                        </div>
                    </div>
                </article>

                <div class="back-button-container">
                    <a href="index.php" class="back-button">Regresar al Inicio</a>
                </div>
                </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Docentes con Causa. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
        const menuToggleButton = document.getElementById('menu-toggle-button');
        const mainNav = document.getElementById('main-nav');

        if(menuToggleButton) {
            menuToggleButton.addEventListener('click', () => {
                mainNav.classList.toggle('is-open');
            });
        }
    </script>

</body>
</html>