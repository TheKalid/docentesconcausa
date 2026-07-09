<?php
// ===== INICIO DEL BLOQUE DE SEGURIDAD (NIVEL BÁSICO) =====
session_start();
$nivel_requerido = 1; 
$plan_activo = $_SESSION['plan_activo'] ?? 0;
if ($plan_activo < $nivel_requerido) {
    header('Location: catalogo_de_pagos.html');
    exit();
}
// ===== FIN DEL BLOQUE DE SEGURIDAD =====
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material de Estudio - Docentes con Causa</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            --color-primario: #1e3a8a;
            --color-acento: #16a34a;
            --color-acento-hover: #15803d;
            --color-fondo: #f8f9fa;
            --color-texto: #334155;
            --color-tarjeta: #ffffff;
            --color-borde: #e2e8f0;
            --fuente-titulos: 'Montserrat', sans-serif;
            --fuente-cuerpo: 'Roboto', sans-serif;
        }
        body {
            font-family: var(--fuente-cuerpo);
            background-color: var(--color-fondo);
            margin: 0;
            color: var(--color-texto);
            line-height: 1.6;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .page-header {
            text-align: center;
            margin-bottom: 50px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--color-borde);
        }
        .page-header h1 {
            font-family: var(--fuente-titulos);
            font-size: 2.8rem;
            color: var(--color-primario);
            margin: 0 0 10px 0;
        }
        .page-header p {
            font-size: 1.15rem;
            color: #64748b;
            max-width: 700px;
            margin: 0 auto;
        }
        .resource-category {
            margin-bottom: 60px;
        }
        .resource-category h2 {
            font-family: var(--fuente-titulos);
            font-size: 1.8rem;
            color: var(--color-primario);
            margin-bottom: 25px;
            border-left: 5px solid var(--color-acento);
            padding-left: 15px;
        }
        .resource-list {
            display: grid;
            gap: 20px;
        }
        .resource-item {
            background-color: var(--color-tarjeta);
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: box-shadow 0.3s, transform 0.3s;
        }
        .resource-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .resource-icon {
            font-size: 2.5rem;
            color: var(--color-primario);
        }
        .resource-info {
            flex-grow: 1;
        }
        .resource-info h3 {
            font-family: var(--fuente-cuerpo);
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0 0 5px 0;
            color: var(--color-texto);
        }
        .resource-info p {
            margin: 0;
            font-size: 0.95rem;
            color: #64748b;
        }
        .action-buttons-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-width: 180px;
        }
        .btn-primary-action, .btn-secondary-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 500;
            white-space: nowrap;
            transition: background-color 0.3s, color 0.3s, transform 0.2s;
        }
        .btn-primary-action {
            background-color: var(--color-primario);
            color: white;
        }
        .btn-primary-action:hover {
            background-color: #0b213a;
            transform: translateY(-2px);
        }
        .btn-secondary-action {
            background-color: #e2e8f0;
            color: var(--color-primario);
        }
        .btn-secondary-action:hover {
            background-color: #cbd5e1;
        }
        .return-button-container {
            text-align: center;
            margin-top: 50px;
        }
        .btn-return {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: var(--color-primario);
            color: white;
            padding: 12px 25px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s, transform 0.2s;
        }
        .btn-return:hover {
            background-color: #0b213a;
            transform: translateY(-2px);
        }
        footer {
            background-color: var(--color-primario);
            color: #e0e7ff;
            text-align: center;
            padding: 25px 20px;
            margin-top: 60px;
        }
        @media (max-width: 600px) {
            .resource-item {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            .btn-download, .action-buttons-container {
                margin-top: 15px;
                width: 100%;
            }
            .page-header h1 {
                font-size: 2.2rem;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <header class="page-header">
            <h1>Material de Estudio para Docentes</h1>
            <p>Aquí encontrarás guías, resúmenes y exámenes de práctica para tu desarrollo profesional.</p>
        </header>

        <main>
            <section class="resource-category">
                <h2>Exámenes de Práctica</h2>
                <div class="resource-list">
                    
                    <article class="resource-item">
                        <div class="resource-icon">✍️</div>
                        <div class="resource-info">
                            <h3>Estudiemos el acuerdo 14/12/23 por el que se emiten los Lineamientos para el protocolo de erradicación del acoso escolar</h3>
                            <p>Evalúa tus conocimientos con nuestro examen interactivo y descarga la guía de estudio.</p>
                        </div>
                        <div class="action-buttons-container">
                            <a href="https://docs.google.com/forms/d/e/1FAIpQLSfE9V7-lJ8HnOuG8up8Rnbg9tOMtEYddtzsXVpldacWsoe8nA/viewform?usp=header" target="_blank" class="btn-primary-action"> !Quiero estudiar!</a>
                            <a href="https://drive.google.com/uc?export=download&id=1KKYT0UTDUbgk1w-Nq6-t3GPnzYZf_tCD" class="btn-secondary-action">Descargar Guía "pdf"</a>
                        </div>
                    </article>

                    <article class="resource-item">
                        <div class="resource-icon">✍️</div>
                        <div class="resource-info">
                            <h3>Examen Diagnóstico para Secundaria - Matemáticas</h3>
                            <p>Realiza el examen interactivo y descarga la versión para imprimir.</p>
                        </div>
                        <div class="action-buttons-container">
                            <a href="https://docs.google.com/forms/d/e/1FAIpQLScvfpvtFuQ_-b32tlZKtqGTkE2kHFzKW5gDBOQ-tTfmBALz6w/viewform?usp=header" target="_blank" class="btn-primary-action">Presentar Examen</a>
                            <a href="URL_DE_TU_PDF_AQUI" class="btn-secondary-action">Descargar Guía</a>
                        </div>
                    </article>

                </div>
            </section>

            <section class="resource-category">
                <h2>Resúmenes y Guías de Estudio</h2>
                <div class="resource-list">
                    
                    <article class="resource-item">
                        <div class="resource-icon">📄</div>
                        <div class="resource-info">
                            <h3>Resumen de los Principios Clave de la NEM</h3>
                            <p>Una guía concisa para entender los fundamentos de la Nueva Escuela Mexicana.</p>
                        </div>
                        <a href="#" download class="btn-download">Descargar</a>
                    </article>

                    <article class="resource-item">
                        <div class="resource-icon">📄</div>
                        <div class="resource-info">
                            <h3>Guía Práctica sobre Evaluación Formativa</h3>
                            <p>Conceptos y ejemplos para aplicar la evaluación formativa en tu aula.</p>
                        </div>
                        <a href="#" download class="btn-download">Descargar</a>
                    </article>

                    <article class="resource-item">
                        <div class="resource-icon">📄</div>
                        <div class="resource-info">
                            <h3>Infografías de las Metodologías Activas</h3>
                            <p>Material visual sobre ABP, STEAM, Proyectos Comunitarios y Aprendizaje Servicio.</p>
                        </div>
                        <a href="#" download class="btn-download">Descargar</a>
                    </article>

                </div>
            </section>
        </main>

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

</body>
</html>