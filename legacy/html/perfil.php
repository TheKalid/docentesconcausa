<?php
// 1. Iniciar sesión y seguridad
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Incluir conexión a la base de datos temprano para procesar datos por POST
include 'conexion.php';

$usuario_id = $_SESSION['usuario_id'];
$mensaje_feedback = "";
$clase_mensaje = "";

// --- NUEVO: Procesar el formulario del código de referido ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo_referido'])) {
    $codigo = trim($_POST['codigo_referido']);
    if (!empty($codigo)) {
        // Actualizamos solo si el usuario aún no tiene un código registrado
        $stmt_update = $conexion->prepare("UPDATE usuarios SET referido_por = ? WHERE id = ? AND (referido_por IS NULL OR referido_por = '')");
        $stmt_update->bind_param("si", $codigo, $usuario_id);
        
        if ($stmt_update->execute() && $stmt_update->affected_rows > 0) {
            $mensaje_feedback = "<strong>Éxito:</strong> Código de referido guardado correctamente.";
            $clase_mensaje = "mensaje-exito";
        } else {
            // Si affected_rows es 0, o el código es igual o ya tenía uno.
            $mensaje_feedback = "<strong>Aviso:</strong> No se guardó el código. Es posible que ya tengas uno registrado.";
            $clase_mensaje = "mensaje-error";
        }
        $stmt_update->close();
    }
}
// -----------------------------------------------------------

// 2. Lógica para mostrar mensajes de feedback por GET (Suscripciones)
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'cancel_success') {
        $mensaje_feedback = "<strong>Éxito:</strong> Tu suscripción ha sido programada para cancelarse. Seguirás teniendo acceso hasta el final de tu periodo de facturación.";
        $clase_mensaje = "mensaje-exito";
    } elseif ($_GET['status'] === 'cancel_error') {
        $mensaje_feedback = "<strong>Error:</strong> No se pudo procesar la cancelación. Por favor, contacta a soporte.";
        $clase_mensaje = "mensaje-error";
    }
}

// 4. Obtener todos los datos del usuario logueado (Agregamos referido_por)
$stmt = $conexion->prepare("SELECT nombre, email, telefono, plan_activo, fecha_proximo_pago, referido_por FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 1) {
    $usuario = $resultado->fetch_assoc();
    
    // Asignar datos a variables para la vista
    $nombre_usuario = $usuario['nombre'];
    $email_usuario = $usuario['email'];
    $telefono_usuario = $usuario['telefono'] ?? 'No proporcionado';
    $referido_por = $usuario['referido_por']; // <-- NUEVA VARIABLE PARA LA VISTA

    // Interpretar el plan y el estado de la suscripción
    $plan_numero = $usuario['plan_activo'];
    $nombre_del_plan = "Sin Suscripción";
    $estado_suscripcion = "Inactiva";
    $clase_estado = "pendiente";

    if ($plan_numero > 0) {
        $estado_suscripcion = "Activa";
        $clase_estado = "pagado";
        switch ($plan_numero) {
            case 1: $nombre_del_plan = "Docente con Causa (Básico)"; break;
            case 2: $nombre_del_plan = "Mentor con Causa (Intermedio)"; break;
            case 3: $nombre_del_plan = "Líder con Causa (Avanzado)"; break;
        }
    }

    // Formatear la fecha de próximo pago
    if (!empty($usuario['fecha_proximo_pago'])) {
        $fecha = new DateTime($usuario['fecha_proximo_pago']);
        $fecha_proximo_pago = $fecha->format('d / m / Y');
    } else {
        $fecha_proximo_pago = 'N/A';
    }

} else {
    die("Error crítico: No se pudieron cargar los datos del usuario.");
}

// 5. Incluir la vista para mostrar los datos
include 'perfil_vista.php';

// 6. Cerrar conexiones
$stmt->close();
$conexion->close();
?>