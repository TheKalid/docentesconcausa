<?php
// 1. INICIA LA SESIÓN Y VERIFICA AL USUARIO
session_start();

// Si el usuario no ha iniciado sesión, se le redirige al login.
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.html");
    exit();
}

// 2. CONECTA A LA BASE DE DATOS Y OBTÉN LOS DATOS ACTUALES
include 'conexion.php'; // Asegúrate de que este archivo exista y funcione.
$usuario_id = $_SESSION['usuario_id'];

// Prepara la consulta para obtener los datos del usuario.
$stmt = $conexion->prepare("SELECT nombre, email, telefono, estado_pago, fecha_proximo_pago FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 1) {
    // Guarda los datos en variables para usarlos en el formulario.
    $usuario = $resultado->fetch_assoc();
    $nombre_usuario = $usuario['nombre'];
    $email_usuario = $usuario['email'];
    $telefono_usuario = $usuario['telefono'] ?? '';
    $estado_pago = $usuario['estado_pago'];

    // Formatea la fecha para mostrarla.
    if (!empty($usuario['fecha_proximo_pago'])) {
        $fecha = new DateTime($usuario['fecha_proximo_pago']);
        $fecha_proximo_pago = $fecha->format('d / m / Y');
    } else {
        $fecha_proximo_pago = 'N/A';
    }
} else {
    // Si no se encuentra al usuario, muestra un error.
    die("Error: No se pudieron cargar los datos del perfil para editar.");
}
$stmt->close();
$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Mi Perfil - Planeando con Causa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primario: #1e3a8a; --color-acento: #f39c12; --color-fondo: #f8f9fa;
            --color-texto: #334155; --color-tarjeta: #ffffff; --color-exito: #22c55e;
            --fuente-principal: 'Poppins', sans-serif;
        }
        body { font-family: var(--fuente-principal); background-color: var(--color-fondo); margin: 0; color: var(--color-texto); }
        .header { background-color: var(--color-primario); color: white; padding: 15px 20px; display: flex; justify-content: center; align-items: center; }
        .header-logo { height: 120px; width: auto; margin-right: 15px; }
        .header h1 { margin: 0; font-size: 1.8rem; }
        .container { max-width: 800px; margin: 40px auto; padding: 20px; background-color: var(--color-tarjeta); border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .profile-section { margin-bottom: 30px; }
        .profile-section h2 { color: var(--color-primario); border-bottom: 2px solid #eef2ff; padding-bottom: 10px; margin-bottom: 20px; }
        .info-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .info-item:last-child { border-bottom: none; }
        .info-item strong { color: #475569; flex-basis: 30%; }
        .info-item input[type="text"], .info-item input[type="email"], .info-item input[type="password"] {
            border: 1px solid #ddd; background-color: #f8f9fa; text-align: left;
            font-family: var(--fuente-principal); font-size: 1rem; color: var(--color-texto);
            width: 70%; padding: 8px; border-radius: 6px;
        }
        .status { padding: 5px 12px; border-radius: 20px; font-weight: 600; text-transform: capitalize; }
        .status.pagado { background-color: #dcfce7; color: #166534; }
        .status.pendiente { background-color: #fee2e2; color: #991b1b; }
        .action-buttons { margin-top: 20px; display: flex; flex-wrap: wrap; gap: 10px; }
        .btn, .action-buttons a {
            display: inline-block; text-align: center; color: white; text-decoration: none;
            padding: 12px 25px; border-radius: 8px; font-weight: 600; transition: background-color 0.3s;
            border: none; font-family: var(--fuente-principal); cursor: pointer;
        }
        .btn-primary { background-color: var(--color-primario); }
        .btn-danger { background-color: #dc2626; }
        .btn-success { background-color: var(--color-exito); }
        .btn-accent { background-color: var(--color-acento); }
        .btn-secondary { background-color: #64748b; }
    </style>
</head>
<body>
    <header class="header">
        <img src="logo.png" alt="Logo Planeando con Causa" class="header-logo">
        <h1>Editar Mi Perfil</h1>
    </header>

    <main class="container">
        <form action="actualizar_perfil.php" method="POST">
            <section class="profile-section">
                <h2>👤 Datos Personales (Editables)</h2>
                <div class="info-item">
                    <strong>Nombre:</strong>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($nombre_usuario); ?>" required>
                </div>
                <div class="info-item">
                    <strong>Correo Electrónico:</strong>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email_usuario); ?>" required>
                </div>
                <div class="info-item">
                    <strong>Teléfono:</strong>
                    <input type="text" name="telefono" value="<?php echo htmlspecialchars($telefono_usuario); ?>">
                </div>
            </section>

            <section class="profile-section">
                <h2>🔒 Cambiar Contraseña (Opcional)</h2>
                <div class="info-item">
                    <strong>Contraseña Actual:</strong>
                    <input type="password" name="password_actual" placeholder="Ingresa tu contraseña actual">
                </div>
                <div class="info-item">
                    <strong>Nueva Contraseña:</strong>
                    <input type="password" name="nueva_password" placeholder="Mínimo 8 caracteres">
                </div>
            </section>
            
            <div class="action-buttons">
                <button type="submit" class="btn btn-accent">Guardar Cambios</button>
                <a href="perfil.php" class="btn btn-secondary">Cancelar y Volver</a>
            </div>
        </form>
    </main>

    <footer style="background-color: #1e3a8a; color: #e0e7ff; text-align: center; padding: 25px 0; margin-top: 40px;">
        <p style="margin: 0 0 10px 0;">&copy; <?php echo date("Y"); ?> Planeando con Causa | Todos los derechos reservados</p>
    </footer>
</body>
</html>