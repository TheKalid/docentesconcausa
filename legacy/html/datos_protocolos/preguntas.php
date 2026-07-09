<?php
// --- ▼▼ CÓDIGO PHP PARA CARGAR DATOS GUARDADOS ▼▼ ---
session_start();
// Seguridad: Redirigir si no hay sesión iniciada
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.html");
    exit();
}

// Incluir la conexión a la BD
include 'conexion.php';
$usuario_id = $_SESSION['usuario_id'];

// Cargar los datos del grupo guardados previamente por el usuario
$grupo = [];
$stmt = $conexion->prepare("SELECT * FROM grupos WHERE usuario_id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();
if ($resultado->num_rows > 0) {
    $grupo = $resultado->fetch_assoc();
}
$stmt->close();
$conexion->close();

// Función auxiliar para marcar como 'selected' las opciones de un campo múltiple
function is_selected($valor, $valores_guardados) {
    if (is_string($valores_guardados)) {
        $array_guardado = explode(', ', $valores_guardados);
        return in_array($valor, $array_guardado) ? 'selected' : '';
    }
    return '';
}
// --- ▲▲ FIN DEL CÓDIGO PHP AÑADIDO ▲▲ ---
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contexto del Grupo - Planeando con Causa</title>
    <link rel="stylesheet" href="styles.css">
    <script>
        // Funciones de JavaScript para mostrar y ocultar secciones
        function mostrarOcultarDiv(triggerId, targetId) {
            const el = document.getElementById(triggerId);
            document.getElementById(targetId).style.display = (el.value === 'Sí') ? 'block' : 'none';
        }
        function mostrarSeccionAdecuacionLecto() {
            const el = document.getElementById('tiene_no_lectoescritura');
            document.getElementById('seccion_adecuacion_lecto').style.display = (el.value === 'Sí') ? 'block' : 'none';
        }
    </script>
</head>
<body>

    <header>
        <h1>¡Cuéntanos tu realidad!</h1>
    </header>

    <form id="registroDocente" action="guardar_grupo.php" method="POST">

        <div style="text-align: center;">
            <label style="font-size: 24px; color: green;">Estructura de la planeación</label>
        </div>
        
        <label>¿Para qué grado es la planeación?</label>
        <select name="grado_planeacion" id="grado_planeacion" required>
            <option value="">-- Selecciona una opción --</option>
            <option value="1° Grado de Preescolar" <?php echo (($grupo['grado_planeacion'] ?? '') == '1° Grado de Preescolar') ? 'selected' : ''; ?>>1° Grado de Preescolar</option>
            <option value="2° Grado de Preescolar" <?php echo (($grupo['grado_planeacion'] ?? '') == '2° Grado de Preescolar') ? 'selected' : ''; ?>>2° Grado de Preescolar</option>
            <option value="3° Grado de Preescolar" <?php echo (($grupo['grado_planeacion'] ?? '') == '3° Grado de Preescolar') ? 'selected' : ''; ?>>3° Grado de Preescolar</option>
            <option value="1° Grado de Primaria" <?php echo (($grupo['grado_planeacion'] ?? '') == '1° Grado de Primaria') ? 'selected' : ''; ?>>1° Grado de Primaria</option>
            <option value="2° Grado de Primaria" <?php echo (($grupo['grado_planeacion'] ?? '') == '2° Grado de Primaria') ? 'selected' : ''; ?>>2° Grado de Primaria</option>
            <option value="3° Grado de Primaria" <?php echo (($grupo['grado_planeacion'] ?? '') == '3° Grado de Primaria') ? 'selected' : ''; ?>>3° Grado de Primaria</option>
            <option value="4° Grado de Primaria" <?php echo (($grupo['grado_planeacion'] ?? '') == '4° Grado de Primaria') ? 'selected' : ''; ?>>4° Grado de Primaria</option>
            <option value="5° Grado de Primaria" <?php echo (($grupo['grado_planeacion'] ?? '') == '5° Grado de Primaria') ? 'selected' : ''; ?>>5° Grado de Primaria</option>
            <option value="6° Grado de Primaria" <?php echo (($grupo['grado_planeacion'] ?? '') == '6° Grado de Primaria') ? 'selected' : ''; ?>>6° Grado de Primaria</option>
            <option value="1° Grado de Secundaria" <?php echo (($grupo['grado_planeacion'] ?? '') == '1° Grado de Secundaria') ? 'selected' : ''; ?>>1° Grado de Secundaria</option>
            <option value="2° Grado de Secundaria" <?php echo (($grupo['grado_planeacion'] ?? '') == '2° Grado de Secundaria') ? 'selected' : ''; ?>>2° Grado de Secundaria</option>
            <option value="3° Grado de Secundaria" <?php echo (($grupo['grado_planeacion'] ?? '') == '3° Grado de Secundaria') ? 'selected' : ''; ?>>3° Grado de Secundaria</option>
        </select>

        <label>Tiempo de duración de la planeación:</label>
        <select name="duracion_planeacion" required>
            <option value="">-- Selecciona una opción --</option>
            <option value="Semanal" <?php echo (($grupo['duracion_planeacion'] ?? '') == 'Semanal') ? 'selected' : ''; ?>>Semanal (5 sesiones)</option>
            <option value="Quincenal" <?php echo (($grupo['duracion_planeacion'] ?? '') == 'Quincenal') ? 'selected' : ''; ?>>Quincenal (10 sesiones)</option>
        </select>

        <div style="text-align: center;">
            <label style="font-size: 24px; color: green;">Definición de mi grupo</label>
        </div>

        <label>Número Total de estudiantes:</label>
        <input type="number" name="numero_total_estudiantes" min="1" value="<?php echo htmlspecialchars($grupo['numero_total_estudiantes'] ?? ''); ?>" required>

        <label>Selecciona los canales de aprendizaje y número de estudiantes:</label>
        <input type="number" name="auditivos" placeholder="Auditivos" min="0" value="<?php echo htmlspecialchars($grupo['auditivos'] ?? ''); ?>" required>
        <input type="number" name="visuales" placeholder="Visuales" min="0" value="<?php echo htmlspecialchars($grupo['visuales'] ?? ''); ?>" required>
        <input type="number" name="kinestesicos" placeholder="Kinestésicos" min="0" value="<?php echo htmlspecialchars($grupo['kinestesicos'] ?? ''); ?>" required>
        
        <label>¿Maestr@ Desea implementar estrategias generales para el apoyo de lectoescritura?</label>
        <select name="refuerzo_lecto_general" id="refuerzo_lecto_general" onchange="mostrarOcultarDiv('refuerzo_lecto_general', 'estrategias_lecto_general_div')">
            <option value="">-- Selecciona una opción --</option>
            <option value="Sí" <?php echo (($grupo['refuerzo_lecto_general'] ?? '') == 'Sí') ? 'selected' : ''; ?>>Sí</option>
            <option value="No" <?php echo (($grupo['refuerzo_lecto_general'] ?? '') == 'No') ? 'selected' : ''; ?>>No</option>
        </select>
        
        <div id="estrategias_lecto_general_div" style="display: <?php echo (($grupo['refuerzo_lecto_general'] ?? '') == 'Sí') ? 'block' : 'none'; ?>">
            <label>Selecciona estrategias para mejorar la lectoescritura:</label>
            <select name="estrategias_lecto_general[]" multiple>
                <option value="Juegos de palabras" <?php echo is_selected('Juegos de palabras', $grupo['estrategias_lecto_general'] ?? ''); ?>>Juegos de palabras</option>
                <option value="Lectura guiada" <?php echo is_selected('Lectura guiada', $grupo['estrategias_lecto_general'] ?? ''); ?>>Lectura guiada</option>
                <option value="Uso de imágenes y pictogramas" <?php echo is_selected('Uso de imágenes y pictogramas', $grupo['estrategias_lecto_general'] ?? ''); ?>>Uso de imágenes y pictogramas</option>
                <option value="Tarjetas de vocabulario" <?php echo is_selected('Tarjetas de vocabulario', $grupo['estrategias_lecto_general'] ?? ''); ?>>Tarjetas de vocabulario</option>
                <option value="Dictado con apoyo visual" <?php echo is_selected('Dictado con apoyo visual', $grupo['estrategias_lecto_general'] ?? ''); ?>>Dictado con apoyo visual</option>
            </select>
        </div>
        
        <label>¿Desea implementar estrategias para mejorar el cálculo mental?</label>
        <select name="refuerzo_calculo" id="refuerzo_calculo" onchange="mostrarOcultarDiv('refuerzo_calculo', 'estrategias_calculo_div')">
            <option value="">-- Selecciona una opción --</option>
            <option value="Sí" <?php echo (($grupo['refuerzo_calculo'] ?? '') == 'Sí') ? 'selected' : ''; ?>>Sí</option>
            <option value="No" <?php echo (($grupo['refuerzo_calculo'] ?? '') == 'No') ? 'selected' : ''; ?>>No</option>
        </select>

        <div id="estrategias_calculo_div" style="display: <?php echo (($grupo['refuerzo_calculo'] ?? '') == 'Sí') ? 'block' : 'none'; ?>">
            <label>Selecciona estrategias para mejorar el cálculo mental:</label>
            <select name="estrategias_calculo[]" multiple>
                <option value="Juegos de agilidad numérica" <?php echo is_selected('Juegos de agilidad numérica', $grupo['estrategias_calculo'] ?? ''); ?>>Juegos de agilidad numérica</option>
                <option value="Ejercicios de cálculo mental diario" <?php echo is_selected('Ejercicios de cálculo mental diario', $grupo['estrategias_calculo'] ?? ''); ?>>Ejercicios de cálculo mental diario</option>
                <option value="Uso de material concreto para visualización" <?php echo is_selected('Uso de material concreto para visualización', $grupo['estrategias_calculo'] ?? ''); ?>>Uso de material concreto para visualización</option>
                <option value="Retos matemáticos en equipo" <?php echo is_selected('Retos matemáticos en equipo', $grupo['estrategias_calculo'] ?? ''); ?>>Retos matemáticos en equipo</option>
            </select>
        </div>

        <label>¿Desea reforzar las operaciones básicas?</label>
        <select name="refuerzo_operaciones" id="refuerzo_operaciones" onchange="mostrarOcultarDiv('refuerzo_operaciones', 'estrategias_operaciones_div')">
            <option value="">-- Selecciona una opción --</option>
            <option value="Sí" <?php echo (($grupo['refuerzo_operaciones'] ?? '') == 'Sí') ? 'selected' : ''; ?>>Sí</option>
            <option value="No" <?php echo (($grupo['refuerzo_operaciones'] ?? '') == 'No') ? 'selected' : ''; ?>>No</option>
        </select>

        <div id="estrategias_operaciones_div" style="display: <?php echo (($grupo['refuerzo_operaciones'] ?? '') == 'Sí') ? 'block' : 'none'; ?>">
            <label>Selecciona estrategias para mejorar operaciones básicas:</label>
            <select name="estrategias_operaciones[]" multiple>
                <option value="Uso de regletas y material manipulativo" <?php echo is_selected('Uso de regletas y material manipulativo', $grupo['estrategias_operaciones'] ?? ''); ?>>Uso de regletas y material manipulativo</option>
                <option value="Juegos matemáticos interactivos" <?php echo is_selected('Juegos matemáticos interactivos', $grupo['estrategias_operaciones'] ?? ''); ?>>Juegos matemáticos interactivos</option>
                <option value="Resolución de problemas en contexto" <?php echo is_selected('Resolución de problemas en contexto', $grupo['estrategias_operaciones'] ?? ''); ?>>Resolución de problemas en contexto</option>
                <option value="Memorama de operaciones" <?php echo is_selected('Memorama de operaciones', $grupo['estrategias_operaciones'] ?? ''); ?>>Memorama de operaciones</option>
            </select>
        </div>

        <div style="text-align: center;">
            <label style="font-size: 24px; color: green;">Adecuaciones Curriculares</label>
        </div>
        
        <p>Estimado Docente: con excepción de los grados de 1° y 2° de Primaria, en los demás grados los estudiantes deben "Saber" leer y escribir. Si tienes grados superiores a estos y cuentas con alumnos que aún no lo hacen, se recomienda tomarlo como adecuación curricular de Lectoescritura.</p>

        <label>¿Deseas aplicar Adecuaciones en Lectoescritura?</label>
        <select name="tiene_no_lectoescritura" id="tiene_no_lectoescritura" onchange="mostrarSeccionAdecuacionLecto()" required>
            <option value="">-- Selecciona una opción --</option>
            <option value="Sí" <?php echo (($grupo['tiene_no_lectoescritura'] ?? '') == 'Sí') ? 'selected' : ''; ?>>Sí</option>
            <option value="No" <?php echo (($grupo['tiene_no_lectoescritura'] ?? '') == 'No') ? 'selected' : ''; ?>>No</option>
        </select>

        <div id="seccion_adecuacion_lecto" style="display: <?php echo (($grupo['tiene_no_lectoescritura'] ?? '') == 'Sí') ? 'block' : 'none'; ?>">
            <label>¿Cuántos estudiantes requieren esta adecuación?</label>
            <input type="number" name="numero_alumnos_lectoescritura" placeholder="Número de estudiantes" min="0" value="<?php echo htmlspecialchars($grupo['numero_alumnos_lectoescritura'] ?? ''); ?>">

            <label>¿Desea que implementemos estrategias para la lectoescritura en esta adecuación?</label>
            <select name="refuerzo_lecto_adecuacion" id="refuerzo_lecto_adecuacion" onchange="mostrarOcultarDiv('refuerzo_lecto_adecuacion', 'estrategias_lecto_adecuacion_div')">
                <option value="">-- Selecciona una opción --</option>
                <option value="Sí" <?php echo (($grupo['refuerzo_lecto_adecuacion'] ?? '') == 'Sí') ? 'selected' : ''; ?>>Sí</option>
                <option value="No" <?php echo (($grupo['refuerzo_lecto_adecuacion'] ?? '') == 'No') ? 'selected' : ''; ?>>No</option>
            </select>
            
            <div id="estrategias_lecto_adecuacion_div" style="display: <?php echo (($grupo['refuerzo_lecto_adecuacion'] ?? '') == 'Sí') ? 'block' : 'none'; ?>">
                <label>Selecciona estrategias para mejorar la lectoescritura:</label>
                <select name="estrategias_lecto_adecuacion[]" multiple>
                    <option value="Juegos de palabras" <?php echo is_selected('Juegos de palabras', $grupo['estrategias_lecto_adecuacion'] ?? ''); ?>>Juegos de palabras</option>
                    <option value="Lectura guiada" <?php echo is_selected('Lectura guiada', $grupo['estrategias_lecto_adecuacion'] ?? ''); ?>>Lectura guiada</option>
                    <option value="Uso de imágenes y pictogramas" <?php echo is_selected('Uso de imágenes y pictogramas', $grupo['estrategias_lecto_adecuacion'] ?? ''); ?>>Uso de imágenes y pictogramas</option>
                    <option value="Tarjetas de vocabulario" <?php echo is_selected('Tarjetas de vocabulario', $grupo['estrategias_lecto_adecuacion'] ?? ''); ?>>Tarjetas de vocabulario</option>
                    <option value="Dictado con apoyo visual" <?php echo is_selected('Dictado con apoyo visual', $grupo['estrategias_lecto_adecuacion'] ?? ''); ?>>Dictado con apoyo visual</option>
                </select>
            </div>
        </div>
        <div class="botones">
            <button type="submit">💾 Guardar y Continuar al Generador</button>
        </div>
        
    </form>
</body>
</html>