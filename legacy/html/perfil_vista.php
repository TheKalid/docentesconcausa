<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Planeando con Causa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Tus estilos CSS van aquí (sin cambios) */
        :root {
            --color-primario: #1e3a8a; --color-acento: #f39c12; --color-fondo: #f8f9fa;
            --color-texto: #334155; --color-tarjeta: #ffffff; --color-exito: #16a34a;
            --color-peligro: #dc2626; --fuente-principal: 'Poppins', sans-serif;
        }
        body { font-family: var(--fuente-principal); background-color: var(--color-fondo); margin: 0; color: var(--color-texto); }
        .container { max-width: 800px; margin: 40px auto; padding: 30px; background-color: var(--color-tarjeta); border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .header { background-color: var(--color-primario); color: white; padding: 15px 20px; text-align: center; }
        .header-logo { height: 120px; }
        .profile-section { margin-bottom: 30px; }
        .profile-section h2 { color: var(--color-primario); border-bottom: 2px solid #eef2ff; padding-bottom: 10px; margin-bottom: 20px; }
        .info-item { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid #f1f5f9; }
        .info-item:last-child { border-bottom: none; }
        .info-item strong { color: #475569; }
        .status { padding: 5px 12px; border-radius: 20px; font-weight: 600; }
        .status.pagado { background-color: #dcfce7; color: #166534; }
        .status.pendiente { background-color: #fee2e2; color: #991b1b; }
        .btn-danger { background-color: var(--color-peligro); color: white; text-decoration: none; padding: 10px 20px; border-radius: 8px; border: none; font-family: var(--fuente-principal); font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: background-color 0.3s; }
        .btn-danger:hover { background-color: #b91c1c; }
        .danger-zone { margin-top: 40px; padding: 25px; border: 2px solid #fecaca; background-color: #fff7f7; border-radius: 12px; }
        .danger-zone h3 { color: var(--color-peligro); margin-top: 0; }
        .mensaje-exito { padding: 15px; border: 1px solid var(--color-exito); background-color: #dcfce7; color: #166534; border-radius: 8px; margin-bottom: 20px; }
        .mensaje-error { padding: 15px; border: 1px solid var(--color-peligro); background-color: #fee2e2; color: #991b1b; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <header class="header">
        <img src="logo.png" alt="Logo Planeando con Causa" class="header-logo">
        <h1>Mi Perfil de Usuario</h1>
    </header>

    <main class="container">
        <?php // Aquí se mostrará el mensaje de éxito o error
        if (!empty($mensaje_feedback)): ?>
            <div class="<?php echo $clase_mensaje; ?>">
                <?php echo $mensaje_feedback; ?>
            </div>
        <?php endif; ?>

        <section class="profile-section">
            <h2>👤 Datos Personales</h2>
            <div class="info-item"><strong>Nombre:</strong> <span><?php echo htmlspecialchars($nombre_usuario); ?></span></div>
            <div class="info-item"><strong>Correo Electrónico:</strong> <span><?php echo htmlspecialchars($email_usuario); ?></span></div>
            <div class="info-item"><strong>Teléfono:</strong> <span><?php echo htmlspecialchars($telefono_usuario); ?></span></div>
        </section>

        <section class="profile-section">
            <h2>💳 Estado de la Suscripción</h2>
            <div class="info-item"><strong>Plan actual:</strong> <span><?php echo htmlspecialchars($nombre_del_plan); ?></span></div>
            <div class="info-item">
                <strong>Estado:</strong>
                <span class="status <?php echo htmlspecialchars($clase_estado); ?>"><?php echo htmlspecialchars($estado_suscripcion); ?></span>
            </div>
            <div class="info-item"><strong>Tu plan está activo hasta:</strong> <span><?php echo htmlspecialchars($fecha_proximo_pago); ?></span></div>
        </section>
        
        <?php // Solo mostrar la zona de cancelación si el plan está activo
        if ($plan_numero > 0): ?>
            <section class="danger-zone">
                <h3>⚠️ Cancelar Suscripción</h3>
                <p>Esta acción programará la cancelación de tu plan. Seguirás teniendo acceso hasta la fecha indicada arriba. Esta acción no se puede deshacer.</p>
                <form action="cancelar_suscripcion.php" method="POST" onsubmit="return confirm('¿Estás seguro de que deseas cancelar tu suscripción?');">
                    <button type="submit" class="btn-danger">Confirmar Cancelación</button>
                </form>
            </section>
        <?php endif; ?>

        <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-top: 20px; border: 1px solid #ddd;">
    <?php if (empty($referido_por)): ?>
        <h3 style="margin-top:0; color: #2c3e50;">¿Te recomendó un asesor de CETL?</h3>
        <p style="font-size: 0.9rem; color: #555;">Selecciona a tu asesor en la lista para apoyarlo con su comisión antes de elegir tu plan.</p>
        
        <form action="perfil.php" method="POST" style="display: flex; gap: 10px; flex-wrap: wrap;">
            
            <select name="codigo_referido" required style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; flex-grow: 1; font-family: 'Poppins', sans-serif;">
                <option value="" disabled selected>Selecciona a tu asesor...</option>
                
                <option value="Mast0012026">Asesor Arturo Moran (Mast0012026)</option>
                <option value="Cetl0022026">Asesor Aristoteles Cervantes  (Cetl0022026)</option>
                <option value="Mast0032026">Asesor Milagros Montserrat (Mast0032026)</option>
                <option value="Reds0042026">Asesor Redes Sociales (Reds0042026)</option>
                <option value="Cetl0052026">Asesor Cesar Alfonso (Cetl0052026)</option>
                <option value="Cetl0062026">Asesor Laura Leticia (Cetl0062026)</option>
                <option value="Cetl0072026">Asesor Amado Yañez (Cetl0072026)</option>
                <option value="Cetl0082026">Asesor Daniel Espitia (Cetl0082026)</option>
                <option value="Cetl0092026">Asesor Hugo Fernando (Cetl0092026)</option>
                <option value="Cetl0102026">Asesor Jorge Alfaro (Cetl0102026)</option>
                <option value="Cetl0112026">Asesor Juan Rebollo (Cetl0112026)</option>
                <option value="Cetl0122026">Asesor Karla Elizabeth (Cetl0122026)</option>
                <option value="Cetl0132026">Asesor Luis Felipe (Cetl0132026)</option>
                <option value="Cetl0142026">Asesor Luis Manuel (Cetl0142026)</option>
                <option value="Cetl0152026">Asesor Miguel Angel (Cetl0152026)</option>
                <option value="Cetl0162026">Asesor Miriam Nuñez (Cetl0162026)</option>
                <option value="Cetl0172026">Asesor Omar Arturo (Cetl0172026)</option>
                <option value="Cetl0182026">Asesor Adrian Ortiz (Cetl0182026)</option>
                <option value="Cetl0192026">Asesor Ricardo Mendoza (Cetl0192026)</option>
                <option value="Cetl0202026">Asesor Gregorio Fernandez (Cetl0202026)</option>
                <option value="Cetl0212026">Asesor Alejandro Rodriguez (Cetl0212026)</option>
                <option value="Cetl0222026">Asesor Simon Vazquez (Cetl0222026)</option>
                <option value="Cetl0232026">Asesor Arturo Vazquez (Cetl0232026)</option>
                </select>
            
            <button type="submit" style="background-color: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; font-family: 'Poppins', sans-serif; transition: background-color 0.3s;">Guardar Asesor</button>
        </form>
        
    <?php else: ?>
        <h3 style="margin-top:0; color: #2c3e50;">✅ Asesor Guardado</h3>
        <p style="margin-bottom:0;">Estás apoyando al código: <strong><?php echo htmlspecialchars($referido_por); ?></strong></p>
    <?php endif; ?>
</div>
</div>
    </main>
    
</body>
</html>