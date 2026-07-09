<?php
session_start();

// 1. SEGURIDAD: VERIFICAR QUE EL USUARIO ESTÉ LOGUEADO
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.html");
    exit();
}

// 2. CONEXIÓN A LA BASE DE DATOS
include 'conexion.php'; // Incluye tu archivo de conexión a la BD
$usuario_id = $_SESSION['usuario_id'];

// 3. RECOLECTAR TODOS LOS DATOS DEL FORMULARIO
$grado_planeacion = $_POST['grado_planeacion'] ?? null;
$duracion_planeacion = $_POST['duracion_planeacion'] ?? null;
$numero_total_estudiantes = $_POST['numero_total_estudiantes'] ?? null;
$auditivos = $_POST['auditivos'] ?? null;
$visuales = $_POST['visuales'] ?? null;
$kinestesicos = $_POST['kinestesicos'] ?? null;
$refuerzo_lecto_general = $_POST['refuerzo_lecto_general'] ?? null;
$estrategias_lecto_general = isset($_POST['estrategias_lecto_general']) ? implode(', ', $_POST['estrategias_lecto_general']) : null;
$refuerzo_calculo = $_POST['refuerzo_calculo'] ?? null;
$estrategias_calculo = isset($_POST['estrategias_calculo']) ? implode(', ', $_POST['estrategias_calculo']) : null;
$refuerzo_operaciones = $_POST['refuerzo_operaciones'] ?? null;
$estrategias_operaciones = isset($_POST['estrategias_operaciones']) ? implode(', ', $_POST['estrategias_operaciones']) : null;
$tiene_no_lectoescritura = $_POST['tiene_no_lectoescritura'] ?? null;
$numero_alumnos_lectoescritura = $_POST['numero_alumnos_lectoescritura'] ?? null;
$refuerzo_lecto_adecuacion = $_POST['refuerzo_lecto_adecuacion'] ?? null;
$estrategias_lecto_adecuacion = isset($_POST['estrategias_lecto_adecuacion']) ? implode(', ', $_POST['estrategias_lecto_adecuacion']) : null;

// 4. PREPARAR LA CONSULTA SQL (INSERTAR O ACTUALIZAR)
$sql = "INSERT INTO grupos (usuario_id, grado_planeacion, duracion_planeacion, numero_total_estudiantes, auditivos, visuales, kinestesicos, refuerzo_lecto_general, estrategias_lecto_general, refuerzo_calculo, estrategias_calculo, refuerzo_operaciones, estrategias_operaciones, tiene_no_lectoescritura, numero_alumnos_lectoescritura, refuerzo_lecto_adecuacion, estrategias_lecto_adecuacion) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        grado_planeacion=VALUES(grado_planeacion), duracion_planeacion=VALUES(duracion_planeacion), numero_total_estudiantes=VALUES(numero_total_estudiantes), auditivos=VALUES(auditivos), visuales=VALUES(visuales), kinestesicos=VALUES(kinestesicos), refuerzo_lecto_general=VALUES(refuerzo_lecto_general), estrategias_lecto_general=VALUES(estrategias_lecto_general), refuerzo_calculo=VALUES(refuerzo_calculo), estrategias_calculo=VALUES(estrategias_calculo), refuerzo_operaciones=VALUES(refuerzo_operaciones), estrategias_operaciones=VALUES(estrategias_operaciones), tiene_no_lectoescritura=VALUES(tiene_no_lectoescritura), numero_alumnos_lectoescritura=VALUES(numero_alumnos_lectoescritura), refuerzo_lecto_adecuacion=VALUES(refuerzo_lecto_adecuacion), estrategias_lecto_adecuacion=VALUES(estrategias_lecto_adecuacion)";

$stmt = $conexion->prepare($sql);

// 5. VINCULAR LOS PARÁMETROS DE FORMA SEGURA
$stmt->bind_param("issiiiisssssssiss", $usuario_id, $grado_planeacion, $duracion_planeacion, $numero_total_estudiantes, $auditivos, $visuales, $kinestesicos, $refuerzo_lecto_general, $estrategias_lecto_general, $refuerzo_calculo, $estrategias_calculo, $refuerzo_operaciones, $estrategias_operaciones, $tiene_no_lectoescritura, $numero_alumnos_lectoescritura, $refuerzo_lecto_adecuacion, $estrategias_lecto_adecuacion);

// 6. EJECUTAR, CERRAR Y REDIRIGIR
$stmt->execute();
$stmt->close();
$conexion->close();

// Una vez guardado, redirigimos al usuario al generador de planes.
header("Location: generar_plan_intermedio.php");
exit();
?>