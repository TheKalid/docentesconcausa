<?php
// ==============================================================================
// SECCIÓN 1: CONFIGURACIÓN Y SEGURIDAD (BACKEND)
// ==============================================================================
session_start();
require_once 'conexion.php'; 

// [SEGURIDAD] Cabeceras HTTP Anti-Ataques
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

// 1. SEGURIDAD: Verificar si el usuario inició sesión
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_nombre'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nombre = htmlspecialchars($_SESSION['usuario_nombre'], ENT_QUOTES, 'UTF-8'); 

// ==============================================================================
// AUDITORÍA FANTASMA Y LÍMITE DE USO (5 AL DÍA)
// ==============================================================================
$limite_diario = 5;
$hoy = date('Y-m-d');

// Reiniciamos el contador si es un nuevo día
if (!isset($_SESSION['fecha_simuladores']) || $_SESSION['fecha_simuladores'] !== $hoy) {
    $_SESSION['usos_simuladores_hoy'] = 0;
    $_SESSION['fecha_simuladores'] = $hoy;
    unset($_SESSION['ultimo_acceso_catalogo']);
}

// Ventana de 5 minutos de gracia (300 segundos) para no quemar usos por un simple F5
$tiempo_actual = time();
$tiempo_gracia = 300; 

if (!isset($_SESSION['ultimo_acceso_catalogo']) || ($tiempo_actual - $_SESSION['ultimo_acceso_catalogo']) > $tiempo_gracia) {
    
    // Si NO han superado el límite, sumamos 1 uso y registramos en la base de datos
    if ($_SESSION['usos_simuladores_hoy'] < $limite_diario) {
        $_SESSION['usos_simuladores_hoy']++;
        $_SESSION['ultimo_acceso_catalogo'] = $tiempo_actual;
        
        $herramienta_usada = "Simuladores USICAMM"; 
        $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        
        try {
            $stmt_log = $conexion->prepare("INSERT INTO historial_uso (usuario_id, herramienta, ip_usuario) VALUES (?, ?, ?)");
            if ($stmt_log) {
                $stmt_log->bind_param("iss", $usuario_id, $herramienta_usada, $ip_cliente);
                $stmt_log->execute();
                $stmt_log->close();
            }
        } catch(Exception $e) {}
    }
}

// Variable matemática para mostrar visualmente a los docentes cuántos usos les quedan
$usos_restantes_hoy = max(0, $limite_diario - $_SESSION['usos_simuladores_hoy']);

// Validamos si ya alcanzó el límite absoluto de hoy
if ($_SESSION['usos_simuladores_hoy'] >= $limite_diario && $usos_restantes_hoy == 0) {
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Límite Alcanzado</title><script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></head><body class="bg-gray-50 flex items-center justify-center min-h-screen p-4"><div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 text-center"><div class="w-16 h-16 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl"><i class="fa-solid fa-hand-paper"></i></div><h2 class="text-2xl font-bold text-slate-800 mb-2">Límite Diario Alcanzado</h2><p class="text-gray-600 mb-6">Para garantizar la estabilidad del servidor para todos los maestros, el acceso a los simuladores está limitado a <strong>5 veces por día</strong>.<br><br>Tu límite de hoy se ha agotado. ¡Regresa mañana para seguir practicando!</p><a href="index.php" class="block w-full bg-blue-900 text-white font-bold py-3 rounded-lg hover:bg-blue-800 transition">Volver al Inicio</a></div></body></html>';
    exit();
}

// [RENDIMIENTO] Liberamos la base de datos y la sesión INMEDIATAMENTE
@$conexion->close();
session_write_close();
// ==============================================================================


// 2. SISTEMA DE PERMISOS (Base de Datos simulada)
$suscripciones_activas = [
    'ingreso_preescolar' => true, 'ingreso_primaria' => true, 'ingreso_secundaria' => true,
    'horizontal_preescolar' => true, 'horizontal_primaria' => true, 'horizontal_secundaria' => true,
    'vertical_preescolar' => true, 'vertical_primaria' => true, 'vertical_secundaria' => true,
    'directiva_preescolar' => true, 'directiva_primaria' => true, 'directiva_secundaria' => true,
    'horizontal_supervisor' => true, // Se agregó el permiso para el nuevo simulador
];

function renderCard($id_simulador, $titulo, $desc, $icono, $tema, $link, $esMeta = false) {
    global $suscripciones_activas;
    $estaActivo = isset($suscripciones_activas[$id_simulador]) && $suscripciones_activas[$id_simulador] === true;
    $temas = [
        'verde'  => ['bg-emerald-100', 'text-emerald-600', 'border-emerald-500', 'hover:bg-emerald-50'],
        'azul'   => ['bg-cyan-100',    'text-cyan-600',    'border-vm-light',    'hover:bg-cyan-50'],
        'oscuro' => ['bg-indigo-100',  'text-indigo-700',  'border-vm-dark',     'hover:bg-blue-50'],
        'dorado' => ['bg-amber-100',   'text-amber-600',   'border-yellow-500',  'hover:bg-yellow-50'],
    ];
    $t = $temas[$tema];

    if ($estaActivo) {
        $opacity = "opacity-100"; $cursor = "cursor-pointer hover:-translate-y-1 hover:shadow-xl";
        $iconBg = $t[0]." ".$t[1]; $btnText = "INICIAR SIMULACIÓN"; $linkDestino = $link; $lockIcon = "";
        $btnClass = "bg-vm-dark text-white hover:bg-blue-800 shadow-md border-transparent";
        if ($esMeta) {
            $cardBorder = "border-t-4 border-yellow-400 ring-2 ring-yellow-400 ring-offset-2";
            $badge = '<span class="absolute -top-3 right-4 bg-yellow-400 text-vm-dark text-[10px] font-extrabold px-3 py-1 rounded-full shadow-sm uppercase tracking-wider z-20">Tu Objetivo</span>';
        } else {
            $cardBorder = "border-t-4 ".$t[2]; $badge = ''; 
        }
    } else {
        $opacity = "opacity-75 grayscale-[0.8]"; $cursor = "cursor-not-allowed"; $iconBg = "bg-gray-200 text-gray-400"; 
        $btnText = "<i class='fa-solid fa-lock mr-1'></i> CONTRATAR"; $linkDestino = "index.php#precios"; 
        $btnClass = "bg-white text-gray-400 border-gray-300 hover:bg-gray-50"; $cardBorder = "border-t-4 border-gray-300";
        $lockIcon = '<div class="absolute inset-0 z-10 bg-white/30 backdrop-blur-[1px] flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300"><i class="fa-solid fa-lock text-4xl text-gray-400"></i></div>';
        $badge = '';
    }

    echo '<div class="relative flex flex-col justify-between p-6 bg-white rounded-2xl border border-gray-100 transition-all duration-300 group '.$opacity.' '.$cursor.' '.$cardBorder.'">'.$badge.$lockIcon.'<div><div class="flex justify-between items-start mb-4"><div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl transition-transform group-hover:rotate-6 '.$iconBg.'"><i class="'.$icono.'"></i></div></div><h3 class="text-lg font-bold text-slate-800 mb-2 leading-tight">'.$titulo.'</h3><p class="text-sm text-slate-500 leading-relaxed mb-6">'.$desc.'</p></div><a href="'.$linkDestino.'" class="block w-full py-3 rounded-lg text-center font-bold text-xs uppercase tracking-wider border transition-all duration-300 '.$btnClass.'">'.$btnText.'</a></div>';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Catálogo | Vanguardia Magisterial</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { 'vm-dark': '#002366', 'vm-light': '#00A8E8', 'vm-accent': '#FFD700' },
                    fontFamily: { sans: ['Montserrat', 'sans-serif'] },
                    backgroundImage: { 'pattern': "url('https://www.transparenttextures.com/patterns/cubes.png')" }
                }
            }
        }
    </script>
    <style> body { background-color: #f8fafc; } .bg-pattern { opacity: 0.4; } </style>
</head>
<body class="text-slate-800 relative">

    <div class="fixed inset-0 z-0 pointer-events-none bg-pattern"></div>

    <nav class="fixed w-full z-50 bg-white/90 backdrop-blur-md border-b border-gray-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-2">
                    <div class="h-8 w-8 bg-vm-dark rounded-lg flex items-center justify-center text-white text-sm shadow-md">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <span class="font-bold text-vm-dark text-lg hidden sm:block">VANGUARDIA <span class="font-light text-gray-500">PANEL</span></span>
                </div>
                <div class="flex items-center gap-6">
                    <div class="hidden md:block text-right">
                        <p class="text-xs font-bold text-gray-400 uppercase">Bienvenido</p>
                        <p class="text-sm font-bold text-vm-dark"><?php echo $usuario_nombre; ?></p>
                    </div>
                    <a href="index.php" class="text-gray-400 hover:text-red-500 transition text-lg" title="Cerrar Sesión">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="pt-28 pb-10 bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="text-center md:text-left">
                <h1 class="text-3xl md:text-4xl font-extrabold text-vm-dark mb-3">Nuestros Simuladores</h1>
                <p class="text-gray-500 max-w-2xl">Selecciona el simulador de examen que deseas estudiar.</p>
            </div>
            
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-center shadow-sm w-full md:w-auto">
                <p class="text-xs text-blue-600 font-bold uppercase tracking-wider mb-1">
                    <i class="fa-solid fa-clock-rotate-left mr-1"></i> Prácticas Diarias
                </p>
                <p class="text-2xl font-black text-vm-dark">
                    <?php echo $usos_restantes_hoy; ?> <span class="text-sm font-medium text-gray-500">/ <?php echo $limite_diario; ?> disponibles</span>
                </p>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 relative z-10 space-y-16">
        
        <div class="animate__animated animate__fadeInUp">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2 flex items-center gap-2">
                    <i class="fa-solid fa-seedling text-emerald-500"></i> Simuladores para la Admisión Docente
                </h2>
                <h3 class="text-sm text-gray-600 flex items_center gap-1">
                    <i class="fa-solid fa-award text-amber-500"></i> Simuladores diseñados para aspirantes a la función docente, enfocados en fortalecer conocimientos pedagógicos, disciplinares y habilidades clave.
                </h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
                <?php 
                renderCard("ingreso_preescolar", "Ingreso a Preescolar", "Herramienta creada para educadoras aspirantes.", "fa-solid fa-shapes", "verde", "generar_simulador_ingreso_preescolar.php");
                renderCard("ingreso_primaria", "Ingreso a Primaria", "Simulador para futuras maestras y maestros.", "fa-solid fa-book-open", "verde", "generar_simulador_ingreso_primaria.php");
                renderCard("ingreso_secundaria", "Ingreso a Secundaria", "Herramienta dirigida a aspirantes en secundaria.", "fa-solid fa-flask", "verde", "generar_simulador_ingreso_secundaria.php");
                ?>
            </div>
        </div>

        <div class="animate__animated animate__fadeInUp">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2 flex items-center gap-2">
                    <i class="fa-solid fa-chalkboard-user text-cyan-500"></i> Simuladores para Docentes en Promoción Horizontal
                </h2>
                <h3 class="text-sm text-gray-600 flex items_center gap-1">
                <i class="fa-solid fa-award text-amber-500"></i> Simuladores diseñados para docentes en servicio que participan en el proceso de Promoción Horizontal.
                </h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
                <?php 
                renderCard("horizontal_preescolar", "Horizontal Preescolar", "Simulador para Promoción Horizontal en Preescolar.", "fa-solid fa-puzzle-piece", "azul", "generar_simulador_maestro_promocion_horizontal_preescolar.php");
                renderCard("horizontal_primaria", "Horizontal Primaria", "Simulador para Promoción Horizontal en Primaria.", "fa-solid fa-users-rectangle", "azul", "generar_simulador_maestro_promocion_horizontal_primaria.php");
                renderCard("horizontal_secundaria", "Horizontal Secundaria", "Simulador para Promoción Horizontal en Secundaria.", "fa-solid fa-microscope", "azul", "generar_simulador_maestro_promocion_horizontal_secundaria.php");
                ?>
            </div>
        </div>

        <div class="animate__animated animate__fadeInUp">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-vm-dark mb-2 flex items-center gap-2">
                    <i class="fa-solid fa-building-columns text-vm-dark"></i> Nuestros simuladores para DOCENTES en Promoción VERTICAL
                </h2>
                <h3 class="text-sm text-gray-600 flex items-center gap-1">
                    <i class="fa-solid fa-award text-amber-500"></i> Simuladores de alto nivel pedagógico para futuros Directivos. Casos de gestión escolar, protocolos y CTE. 
                </h3>
            </div>
            <div class="grid md:grid-cols-3 gap-6">
                <?php 
                renderCard("vertical_preescolar", "Futuro Director Preescolar", "Prepárate para ser un Lider Directivo en Preescolar", "fa-solid fa-cubes-stacked", "oscuro", "generar_simulador_maestro_promocion_vertical_preescolar.php");
                renderCard("vertical_primaria", "Futuro Director Primaria", "Prepárate para ser un Lider Directivo en Primaria", "fa-solid fa-school-flag", "oscuro", "generar_simulador_basico.php", true);
                renderCard("vertical_secundaria", "Futuro Director Secundaria", "Prepárate para ser un Lider Directivo en Secundaria.", "fa-solid fa-gavel", "oscuro", "generar_simulador_maestro_promocion_vertical_secundaria.php");
                ?>
            </div>
        </div>

        <div class="animate__animated animate__fadeInUp">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2 flex items-center gap-2">
                    <i class="fa-solid fa-award text-amber-500"></i> Simuladores para Directores en Promoción Horizontal
                </h2>
                <h3 class="text-sm text-gray-600 flex items_center gap-1">
                <i class="fa-solid fa-award text-amber-500"></i> Simuladores pedagógicos diseñados para directivos con enfoque práctico en gestión escolar.
                </h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
                <?php 
                renderCard("directiva_preescolar", "Incentivo Preescolar", "Ruta de mejora continua.", "fa-solid fa-star", "dorado", "generar_simulador_promocion_horizontal_para_director_de_preescolar.php");
                renderCard("directiva_primaria", "Incentivo Primaria", "Asesoría pedagógica.", "fa-solid fa-star", "dorado", "generar_simulador_promocion_horizontal_para_director_de_primaria.php");
                renderCard("directiva_secundaria", "Incentivo Secundaria", "Liderazgo distribuido.", "fa-solid fa-star", "dorado", "generar_simulador_promocion_horizontal_para_director_de_secundaria.php");
                ?>
            </div>
        </div>

        <!-- NUEVA SECCIÓN PARA SUPERVISORES -->
        <div class="animate__animated animate__fadeInUp">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2 flex items-center gap-2">
                    <i class="fa-solid fa-sitemap text-indigo-500"></i> Simuladores para Supervisores en Promoción Horizontal
                </h2>
                <h3 class="text-sm text-gray-600 flex items_center gap-1">
                <i class="fa-solid fa-award text-amber-500"></i> Simuladores estratégicos orientados a la función de supervisión y acompañamiento escolar.
                </h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
                <?php 
                renderCard("horizontal_supervisor", "Promoción Horizontal Supervisores", "Simulador para Supervisores de Primaria.", "fa-solid fa-layer-group", "oscuro", "generar_simulador_promocion_horizontal_para_supervisor_de_primaria.php");
                ?>
            </div>
        </div>

        <div class="animate__animated animate__fadeInUp">
            <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-3">
                <i class="fa-solid fa-award text-amber-500"></i> Todas nuestras herramientas son actualizadas con regularidad ya que buscamos brindar el mejor servicio posible.
            </h2>
        </div>
    </div>
</body>
</html>