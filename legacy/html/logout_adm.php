<?php
// Iniciar la sesión para poder acceder a las variables
session_start();

// Eliminar la variable específica del administrador
unset($_SESSION['admin_logeado']);

// Opcional pero recomendado: Destruir toda la sesión actual por seguridad
session_destroy();

// Redirigir al usuario de vuelta a la página principal (o al login)
header("Location: index.php");
exit;
?>