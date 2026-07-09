<?php
// 1. Iniciar sesión y seguridad
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Lógica para mostrar mensajes de feedback (movida aquí)
$mensaje_feedback = "";
$clase_mensaje = "";
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'cancel_success') {
        $mensaje_feedback = "<strong>Éxito:</strong> Tu suscripción ha sido programada para cancelarse. Seguirás teniendo acceso hasta el final de tu periodo de facturación.";
        $clase_mensaje = "mensaje-exito";
    } elseif ($_GET['status'] === 'cancel_error') {
        $mensaje_feedback = "<strong>Error:</strong> No se pudo procesar la cancelación. Por favor, contacta a soporte.";
        $clase_mensaje = "mensaje-error";
    }
}

// 3. Incluir conexión a la base de datos
include 'conexion.php';

// 4. Obtener todos los datos del usuario logueado
$usuario_id = $_SESSION['usuario_id'];
$stmt = $conexion->prepare("SELECT nombre, email, telefono, plan_activo, fecha_proximo_pago FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 1) {
    $usuario = $resultado->fetch_assoc();
    
    // Asignar datos a variables para la vista
    $nombre_usuario = $usuario['nombre'];
    $email_usuario = $usuario['email'];
    $telefono_usuario = $usuario['telefono'] ?? 'No proporcionado';

    // Interpretar el plan y el estado de la suscripción
    $plan_numero = $usuario['plan_activo'];
    $nombre_del_plan = "Sin Suscripción";
    $estado_suscripcion = "Inactiva";
    $clase_estado = "pendiente"; // Clase CSS para el estado

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