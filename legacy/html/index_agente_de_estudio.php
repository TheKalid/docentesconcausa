<?php
// Puedes incluir aquí tu lógica de sesiones o validaciones de seguridad si las necesitas en el futuro.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agente de Estudio - En Desarrollo</title>
    <style>
        /* Estilos generales y centrado */
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #121212; /* Fondo oscuro moderno */
            color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }
        
        /* Contenedor principal estilo tarjeta */
        .container {
            max-width: 600px;
            padding: 40px;
            background-color: #1e1e1e;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            border: 1px solid #333;
            margin: 20px;
        }

        h1 {
            color: #4CAF50; /* Tono verde para dar sensación de tecnología/procesamiento */
            margin-bottom: 15px;
            font-size: 2em;
        }

        p {
            font-size: 1.1em;
            line-height: 1.6;
            color: #cccccc;
        }

        /* Animación de carga circular */
        .loader {
            border: 4px solid #333;
            border-top: 4px solid #4CAF50;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 30px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Botón para regresar al dashboard anterior */
        .btn-back {
            display: inline-block;
            margin-top: 25px;
            padding: 12px 28px;
            background-color: #333;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.1s ease;
        }

        .btn-back:hover {
            background-color: #4CAF50;
            color: #121212;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="loader"></div>
        
        <h1>Agente de Estudio en Entrenamiento</h1>
        
        <p>Estamos trabajando arduamente en el código de esta nueva sección. Por el momento, el <strong>Agente de Estudio</strong> no está disponible mientras ajustamos sus algoritmos y mejoramos la experiencia de usuario.</p>
        
        <p>Vuelve pronto para descubrir la herramienta que estamos preparando.</p>
        
        <a href="javascript:history.back()" class="btn-back">Regresar al panel anterior</a>
    </div>

</body>
</html>