<?php
session_start();
// Verificamos si hay un usuario logueado. Si no, no podemos asignarle el pago.
if (!isset($_SESSION['usuario_id'])) {
    // Si no hay sesión, lo mandamos a que inicie una antes de pagar.
    // Guardamos la URL actual para redirigirlo aquí después del login.
    $redirect_url = urlencode('catalogo_de_pagos.php');
    header('Location: login.php?redirect_url=' . $redirect_url);
    exit;
}
// Guardamos el ID del usuario para usarlo en los enlaces.
$usuario_id = $_SESSION['usuario_id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Pagos - Docentes con Causa</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        /* Tu CSS aquí (sin cambios) */
        :root {
            --color-primario: #2c3e50;
            --color-secundario: #3498db;
            --color-acento: #f39c12;
            --color-fondo: #f4f7f6;
            --color-texto: #34495e;
            --fuente-principal: 'Poppins', sans-serif;
        }
        body {
            font-family: var(--fuente-principal);
            background-color: var(--color-fondo);
            margin: 0;
            padding: 40px 20px;
            color: var(--color-texto);
            text-align: center;
        }
        .main-container { max-width: 1100px; margin: 0 auto; }
        header { margin-bottom: 50px; }
        .header-logo { width: 150px; height: auto; margin-bottom: 25px; }
        header h1 { font-size: 2.8rem; color: var(--color-primario); font-weight: 700; margin-bottom: 10px; }
        header p { font-size: 1.1rem; color: #7f8c8d; max-width: 600px; margin: 0 auto; }
        .pricing-container { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; }
        .pricing-card { background-color: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.07); transition: transform 0.3s, box-shadow 0.3s; display: flex; flex-direction: column; }
        .pricing-card:not(.disabled-card):hover { transform: translateY(-10px); box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1); }
        .card-icon { font-size: 3rem; margin-bottom: 20px; }
        .card-title { font-size: 1.5rem; font-weight: 600; color: var(--color-primario); margin: 0 0 10px 0; }
        .card-level { font-size: 0.9rem; font-weight: 600; color: #7f8c8d; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 25px; }
        .card-price { margin-bottom: 30px; display: flex; justify-content: center; align-items: baseline; color: var(--color-primario); }
        .card-price .currency { font-size: 1.5rem; font-weight: 600; margin-right: 5px; }
        .card-price .amount { font-size: 3.8rem; font-weight: 700; line-height: 1; }
        .card-price .period { font-size: 1rem; color: #7f8c8d; margin-left: 5px; }
        .card-benefits { list-style: none; padding: 0; margin: 0 0 40px 0; text-align: left; flex-grow: 1; }
        .card-benefits li { margin-bottom: 15px; display: flex; align-items: center; }
        .card-benefits li::before { content: '✅'; margin-right: 10px; font-size: 1.1rem; }
        .cta-button { display: inline-block; background-color: var(--color-secundario); color: white; padding: 15px 20px; border-radius: 8px; font-weight: 600; transition: background-color 0.3s; border: none; font-family: var(--fuente-principal); font-size: 1rem; cursor: pointer; text-decoration: none; }
        .cta-button:hover { background-color: #2980b9; }
        .pricing-card.recommended { border: 3px solid var(--color-acento); position: relative; transform: scale(1.05); }
        .pricing-card.recommended .cta-button { background-color: var(--color-acento); }
        .pricing-card.recommended:not(.disabled-card) .cta-button:hover { background-color: #d35400; }
        .pricing-card.recommended .card-price { color: var(--color-acento); }
        .recommended-badge { position: absolute; top: -15px; left: 50%; transform: translateX(-50%); background-color: var(--color-acento); color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; }
        .pricing-card.disabled-card { opacity: 0.7; }
        
        /* Regla CSS corregida y añadida para el botón deshabilitado */
        .cta-button.disabled { 
            background-color: #bdc3c7; 
            cursor: not-allowed; 
            pointer-events: none; 
        }
        .cta-button.disabled:hover { 
            background-color: #bdc3c7; 
        }

        .back-to-home { margin-top: 60px; }
        .invoice-button {
            display: inline-block;
            margin-top: 15px;
            padding: 8px 16px;
            font-size: 0.9rem;
            color: white;
            background-color: #7f8c8d;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        .invoice-button:hover {
            background-color: #6c7a89;
        }
        @media (max-width: 900px) {
            .pricing-container { grid-template-columns: 1fr; }
            .pricing-card.recommended { transform: scale(1); }
        }
    </style>
</head>
<body>

    <div class="main-container">
        <header>
            <img src="logo.png" alt="Logo de Planeando con Causa" class="header-logo">
            <h1>Elige tu Plan de Apoyo</h1>
            <p>Únete a nuestra comunidad y transforma tu práctica docente con herramientas y apoyo diseñados para ti.</p>
        </header>

        <main class="pricing-container">
            <article class="pricing-card">
                <div class="card-icon">👩‍🏫</div>
                <h3 class="card-title">Docente con Causa</h3>
                <p class="card-level">Nivel Básico - IVA Incluido</p>
                <div class="card-price"><span class="currency">$</span><span class="amount">125</span><span class="period">/mes</span></div>
                <ul class="card-benefits">
                    <li><b>5 planeaciones por mes.</b></li>
                    <li>Acceso a la IA educativa entrenada en eduacion y NEM.</li>
                    <li>Planeaciones automáticas </li>
                    <li>Acceso a Evaluación Diagnósica.</li>
                    <li>Acceso a la IA para clases de Educación Física</li>
                    <li>Ideal para iniciar con IA educativa.</li>
                    <li>Acceso a nuestro catalogo de Biblioteca.</li>
                </ul>
                <a href="https://buy.stripe.com/00w3cveQQarW6P1d7H4AU02?client_reference_id=<?php echo $usuario_id; ?>" class="cta-button">Elegir este plan</a>
            </article>

            <article class="pricing-card recommended">
                <div class="recommended-badge">Más Popular</div>
                <div class="card-icon">🧑‍🏫</div>
                <h3 class="card-title">Mentor con Causa</h3>
                <p class="card-level">Nivel Intermedio - IVA Incluido</p>
                <div class="card-price"><span class="currency">$</span><span class="amount">179</span><span class="period">/mes</span></div>
                <ul class="card-benefits">
                    <li><b>7 planeaciones por mes.</b></li>
                    <li><b>Todo lo del plan Básico +</b></li>
                    <li>IA más personalizable y poderosa.</li>
                    <li>Acceso a cursos de formación docente.</li>
                    <li>Botón de protocolos escolares.</li>
                    <li>Acceso a IA para analizar la Bitácora Docente.</li>
                </ul>
                <a href="https://buy.stripe.com/eVqcN59wwfMg8X98Rr4AU03?client_reference_id=<?php echo $usuario_id; ?>" class="cta-button">Elegir este plan</a>
            </article>

            <article class="pricing-card disabled-card">
                <div class="card-icon">👨‍🏫</div>
                <h3 class="card-title">Líder con Causa PRÓXIMAMENTE</h3>
                <p class="card-level">Nivel Avanzado - IVA Incluido</p>
                <div class="card-price"><span class="currency">$</span><span class="amount">347</span><span class="period">/mes</span></div>
                <ul class="card-benefits">
                    <li><b>10 planeaciones por mes.</b></li>
                    <li><b>Todo lo del plan Intermedio +</b></li>
                    <li>1 sesión psicológica mensual.</li>
                    <li>Apoyo emocional y profesional continuo de parte de un especialista.</li>
                    <li>Te estaremos enviando a tu celular preguntas sobre la Nem, e informacion para que dediques unos minutos diarios y estes actualizad@.</li>
                </ul>
                <a href="AQUI EL ENLACE?client_reference_id=<?php echo $usuario_id; ?>" class="cta-button disabled">Elegir este plan</a>
            </article>
        </main>

        <div class="back-to-home">
            <a href="index.php" class="cta-button">Página Principal</a>
            <br>
            <a href="servicio_cliente.html" class="invoice-button">¿Necesitas algo?</a>
        </div>
    </div>

    </body>
</html>