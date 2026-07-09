<?php
// ===================================================================================
// ARCHIVO: panel_de_administracion.php (VERSIÓN SOC - TERMINAL INTERACTIVA BLINDADA)
// ===================================================================================

session_start();

// --- [SEGURIDAD 1] CABECERAS HTTP ESTRICTAS ---
header("X-Frame-Options: DENY"); 
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// --- [SEGURIDAD 2] TOKEN CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ===================================================================================
// BARRERA 1: VERIFICACIÓN DE LLAVE TÁCTICA (ANULACIÓN MAESTRA)
// ===================================================================================
if (isset($_POST['llave_tactica'])) {
    
    $post_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $post_csrf)) {
        die("<div style='background:#0d1117; color:#f85149; text-align:center; padding:50px; font-family:monospace;'><h2>[!] ALERTA DE INTRUSIÓN</h2><p>Firma CSRF inválida. Bloqueando conexión.</p></div>");
    }

    if ($_POST['llave_tactica'] === 'DragonNegro') {
        $_SESSION['acceso_dragon'] = true;
        session_regenerate_id(true); 
    } else {
        sleep(2); 
        header("Location: generador_adm.php");
        exit();
    }
}

// ===================================================================================
// BARRERA 2: VERIFICACIÓN POR IDENTIDAD (LISTA BLANCA)
// ===================================================================================
if (!isset($_SESSION['acceso_dragon']) || $_SESSION['acceso_dragon'] !== true) {

    if (!isset($_SESSION['usuario_id']) || empty($_SESSION['usuario_id'])) {
        header("Location: login.php");
        exit();
    }

    require_once 'conexion.php';
    $id_visitante = $_SESSION['usuario_id'];

    $stmt = $conexion->prepare("SELECT email FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id_visitante);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $correo_visitante = $resultado->fetch_assoc()['email'];
    } else {
        header("Location: login.php");
        exit();
    }

    $administradores_autorizados = [
        'ingarturodalimoran@gmail.com',
        'arturo.moran@gmail.com'
    ];

    if (!in_array($correo_visitante, $administradores_autorizados)) {
        header("Location: index.php");
        exit(); 
    }
    
    $stmt->close();
    $conexion->close();
}

session_write_close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMC | Tactical Dashboard</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-base: #060913;
            --bg-panel: #0d1326;
            --bg-card: #141c36;
            --neon-blue: #00f0ff;
            --neon-purple: #b535f6;
            --neon-green: #00ff9d;
            --neon-red: #ff3366;
            --neon-orange: #ffb000;
            --text-main: #e2e8f0;
            --text-muted: #94a3b8;
            --font-ui: 'Poppins', sans-serif;
            --font-code: 'Courier New', Courier, monospace;
        }

        body { background-color: var(--bg-base); color: var(--text-main); font-family: var(--font-ui); margin: 0; padding: 20px; background-image: radial-gradient(circle at 50% 0%, #101935 0%, transparent 50%); min-height: 100vh; }
        
        /* HEADER */
        .header-container { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(0, 240, 255, 0.2); padding-bottom: 15px; margin-bottom: 30px; }
        h1 { font-family: var(--font-code); color: var(--neon-blue); margin: 0; font-size: 1.8rem; text-shadow: 0 0 15px rgba(0, 240, 255, 0.4); letter-spacing: 2px; }
        .btn-close { background: rgba(255, 51, 102, 0.1); color: var(--neon-red); border: 1px solid var(--neon-red); padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; font-family: var(--font-code); transition: all 0.3s; text-transform: uppercase; }
        .btn-close:hover { background: var(--neon-red); color: white; box-shadow: 0 0 15px var(--neon-red); }

        /* GRID PRINCIPAL */
        .main-layout { display: flex; gap: 25px; flex-wrap: wrap; }
        .metrics-section { flex: 3; min-width: 300px; }
        .ip-section { flex: 1.2; min-width: 320px; display: flex; flex-direction: column; gap: 20px; }

        /* TARJETAS DE MÉTRICAS */
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background-color: var(--bg-card); border-radius: 12px; padding: 20px; text-align: center; position: relative; overflow: hidden; backdrop-filter: blur(10px); }
        .card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 2px; }
        .blue-glow { border: 1px solid rgba(0, 240, 255, 0.2); box-shadow: inset 0 0 20px rgba(0, 240, 255, 0.05); }
        .blue-glow::before { background: var(--neon-blue); box-shadow: 0 0 10px var(--neon-blue); }
        .purple-glow { border: 1px solid rgba(181, 53, 246, 0.2); box-shadow: inset 0 0 20px rgba(181, 53, 246, 0.05); }
        .purple-glow::before { background: var(--neon-purple); box-shadow: 0 0 10px var(--neon-purple); }
        .green-glow { border: 1px solid rgba(0, 255, 157, 0.2); box-shadow: inset 0 0 20px rgba(0, 255, 157, 0.05); }
        .green-glow::before { background: var(--neon-green); box-shadow: 0 0 10px var(--neon-green); }
        .card h3 { margin: 0 0 10px 0; font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }
        .metric-value { font-family: var(--font-code); font-size: 2.2rem; font-weight: bold; color: #ffffff; margin: 10px 0; text-shadow: 0 0 10px rgba(255,255,255,0.2);}

        /* BARRAS DE PROGRESO */
        .gauge-bg { background-color: rgba(255,255,255,0.05); border-radius: 10px; height: 8px; width: 100%; margin-top: 15px; overflow: hidden; }
        .gauge-fill { height: 100%; width: 0%; background-color: var(--neon-green); transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.5s ease; box-shadow: 0 0 10px currentColor; }

        /* TERMINALES */
        .terminal-panel { background-color: #05070e; border-radius: 12px; padding: 20px; overflow-x: auto; font-family: var(--font-code); position: relative; }
        .terminal-panel h3 { margin-top: 0; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 10px; font-size: 1rem; display: flex; align-items: center; gap: 10px; }
        .terminal-text { white-space: pre; font-size: 0.85rem; line-height: 1.6; padding-top: 10px; }
        .panel-ips { border: 1px solid rgba(255, 51, 102, 0.3); }
        .panel-ips h3 { color: var(--neon-red); }
        .text-ips { color: #ff8a9f; } 
        .panel-procesos { border: 1px solid rgba(255, 176, 0, 0.3); }
        .panel-procesos h3 { color: var(--neon-orange); }
        .text-procesos { color: #ffdb80; } 

        /* ACCIONES */
        .actions-panel { background-color: var(--bg-panel); border: 1px solid rgba(0, 240, 255, 0.2); border-radius: 12px; padding: 25px; margin-top: 30px; }
        .actions-panel h2 { font-family: var(--font-code); color: var(--neon-blue); margin-top: 0; text-align: center; text-transform: uppercase; font-size: 1.3rem; margin-bottom: 25px; }
        .action-row { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
        input[type="text"] { background-color: #05070e; color: var(--neon-blue); border: 1px solid rgba(0, 240, 255, 0.3); padding: 12px 15px; border-radius: 6px; flex-grow: 1; font-family: var(--font-code); font-size: 1rem; transition: 0.3s; }
        input[type="text"]:focus { outline: none; border-color: var(--neon-blue); box-shadow: 0 0 15px rgba(0, 240, 255, 0.2); }
        .btn-action { font-family: var(--font-code); color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; display: flex; align-items: center; gap: 8px; justify-content: center; }
        .btn-safe { background-color: rgba(0, 255, 157, 0.1); color: var(--neon-green); border: 1px solid var(--neon-green); }
        .btn-safe:hover { background-color: var(--neon-green); color: #000; box-shadow: 0 0 15px var(--neon-green); }
        .btn-danger { background-color: rgba(255, 51, 102, 0.1); color: var(--neon-red); border: 1px solid var(--neon-red); }
        .btn-danger:hover { background-color: var(--neon-red); color: white; box-shadow: 0 0 15px var(--neon-red); }
        .btn-critical { background-color: #8b0000; color: white; border: 1px solid #ff0000; width: 100%; padding: 15px; font-size: 1.1rem; }
        .btn-critical:hover { background-color: #ff0000; box-shadow: 0 0 25px #ff0000; }

        /* MANUAL */
        .manual-panel { background-color: var(--bg-panel); border: 1px dashed rgba(0, 240, 255, 0.3); border-radius: 12px; padding: 25px; margin-top: 30px; }
        .manual-panel h2 { color: var(--neon-blue); font-family: var(--font-code); font-size: 1.2rem; margin-top: 0; border-bottom: 1px solid rgba(0, 240, 255, 0.2); padding-bottom: 10px; text-transform: uppercase;}
        .manual-item { margin-bottom: 15px; border-left: 4px solid; padding: 15px; background: rgba(255,255,255,0.02); border-radius: 0 8px 8px 0; }
        .manual-item h4 { margin: 0 0 8px 0; color: var(--text-main); font-size: 1.05rem; display: flex; align-items: center; gap: 10px;}
        .manual-item p { margin: 0; color: var(--text-muted); font-size: 0.95rem; line-height: 1.5;}
        .highlight-red { color: var(--neon-red); font-weight: bold; }
        .highlight-orange { color: var(--neon-orange); font-weight: bold; }

        /* ========================================================= */
        /* NUEVO: ESTILOS DE LA VENTANA EMERGENTE (TERMINAL VIRTUAL) */
        /* ========================================================= */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 9999; justify-content: center; align-items: center; backdrop-filter: blur(5px); }
        .modal-content { background: var(--bg-panel); border: 1px solid var(--neon-blue); border-radius: 12px; width: 90%; max-width: 800px; max-height: 85vh; display: flex; flex-direction: column; box-shadow: 0 0 40px rgba(0, 240, 255, 0.15); overflow: hidden;}
        .modal-header { padding: 20px; border-bottom: 1px solid rgba(0, 240, 255, 0.2); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.5); }
        .modal-header h3 { margin: 0; color: var(--neon-blue); font-family: var(--font-code); font-size: 1.2rem; }
        .modal-body { padding: 20px; overflow-y: auto; color: var(--neon-green); font-family: var(--font-code); font-size: 0.95rem; white-space: pre-wrap; background: #02040a; flex-grow: 1; line-height: 1.5; }
        .modal-body .comando-lanzado { color: var(--neon-purple); margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px dashed rgba(181, 53, 246, 0.3); }
        .modal-footer { padding: 15px 20px; border-top: 1px solid rgba(0, 240, 255, 0.2); display: flex; justify-content: flex-end; gap: 15px; background: rgba(0,0,0,0.5); }
        .btn-icon { background: none; border: none; color: var(--neon-red); font-size: 1.5rem; cursor: pointer; transition: 0.3s; }
        .btn-icon:hover { transform: scale(1.1); text-shadow: 0 0 10px var(--neon-red); }

        @media (max-width: 768px) {
            .header-container { flex-direction: column; gap: 15px; text-align: center; }
            .action-row { flex-direction: column; align-items: stretch; }
            .btn-action { width: 100%; }
            .modal-footer { flex-direction: column; }
        }
    </style>
</head>
<body>

    <div class="header-container">
        <h1><i class="fas fa-shield-alt"></i> S.M.C | Centro de Monitoreo Central</h1>
        <button class="btn-close" onclick="window.location.href='logout_adm.php'"><i class="fas fa-power-off"></i> Desconectar</button>
    </div>

    <div class="main-layout">
        <div class="metrics-section">
            <div class="dashboard-grid">
                <div class="card blue-glow"><h3>Carga CPU</h3><div id="val-cpu" class="metric-value">--</div><div class="gauge-bg"><div id="gauge-cpu" class="gauge-fill"></div></div></div>
                <div class="card blue-glow"><h3>Uso de RAM</h3><div id="val-ram" class="metric-value">--</div><div class="gauge-bg"><div id="gauge-ram" class="gauge-fill"></div></div></div>
                <div class="card blue-glow"><h3>Espacio Disco</h3><div id="val-disk" class="metric-value">--</div><div class="gauge-bg"><div id="gauge-disk" class="gauge-fill"></div></div></div>
                <div class="card green-glow"><h3>Conexiones TCP (Ataques posibles de DDS)</h3><div id="val-conn" class="metric-value">--</div></div>
                <div class="card green-glow"><h3>Latencia (Si sube mucho la red esta saturada)</h3><div id="val-lat" class="metric-value">--</div></div>
                <div class="card green-glow"><h3>Procesos Activos</h3><div id="val-procs" class="metric-value">--</div></div>
                <div class="card purple-glow"><h3>Uptime (Tiempo Activo)</h3><div id="val-uptime" class="metric-value" style="font-size: 1.3rem;">--</div></div>
                <div class="card purple-glow"><h3>Temp. CPU</h3><div id="val-temp" class="metric-value">--</div></div>
            </div>
        </div>

        <div class="ip-section">
            <div class="terminal-panel panel-ips">
                <h3><i class="fas fa-network-wired"></i> Conexiones Externas (Top IPs)</h3>
                <div id="val-ips" class="terminal-text text-ips">Escaneando red externa...</div>
            </div>
            <div class="terminal-panel panel-procesos">
                <h3><i class="fas fa-microchip"></i> Top Procesos (Rastreador PID)</h3>
                <div id="val-procesos" class="terminal-text text-procesos">Cargando mapa de procesos...</div>
            </div>
        </div>
    </div>

    <div class="actions-panel">
        <h2><i class="fas fa-crosshairs"></i> Panel de Mitigación de Amenazas</h2>
        
        <div class="action-row">
            <input type="text" id="input-ip" placeholder="Copie aquí la IP Sospechosa del panel de red...">
            <button class="btn-action btn-danger" onclick="ejecutarAccion('bloquear_ip')"><i class="fas fa-ban"></i> Bloquear IP</button>
            <button class="btn-action btn-safe" onclick="ejecutarAccion('desbloquear_ip')"><i class="fas fa-check-circle"></i> Permitir IP</button>
        </div>
        
        <div class="action-row">
            <input type="text" id="input-pid" placeholder="Ej. 1045 (Ingrese el PID del panel de procesos)">
            <button class="btn-action btn-danger" onclick="ejecutarAccion('matar_proceso')"><i class="fas fa-skull-crossbones"></i> Matar Proceso (Kill)</button>
        </div>
        
        <div class="action-row">
            <button class="btn-action btn-danger" style="flex: 1;" onclick="ejecutarAccion('reiniciar_apache')"><i class="fas fa-server"></i> Reiniciar Web Server</button>
            <button class="btn-action btn-danger" style="flex: 1;" onclick="ejecutarAccion('reiniciar_mysql')"><i class="fas fa-database"></i> Reiniciar Base de Datos</button>
        </div>

        <div class="action-row" style="gap: 15px;">
            <button class="btn-action" style="background-color: rgba(255, 85, 85, 0.1); color: #ff5555; border: 1px solid #ff5555; flex: 1;" onclick="ejecutarAccion('auditoria_forense')">
                <i class="fas fa-user-secret"></i> 🔍 AUDITORÍA FORENSE COMPLETA
            </button>
            <button class="btn-action" style="background-color: rgba(255, 170, 0, 0.1); color: #ffaa00; border: 1px solid #ffaa00; flex: 1;" onclick="ejecutarAccion('verificar_integridad')">
                <i class="fas fa-shield-alt"></i> 🛡️ VERIFICAR INTEGRIDAD (SHA256)
            </button>
        </div>
        <div class="action-row" style="margin-bottom: 0; margin-top: 20px;">
            <button class="btn-action btn-critical" onclick="ejecutarAccion('reboot_server')"><i class="fas fa-biohazard"></i> REINICIAR SERVIDOR COMPLETO (REBOOT HARDWARE)</button>
        </div>
    </div>

    <div class="manual-panel">
        <h2><i class="fas fa-book-medical"></i> Protocolos de Emergencia (Manual Rápido)</h2>
        
        <div class="manual-item" style="border-left-color: var(--neon-orange);">
            <h4><i class="fas fa-microchip"></i> 1. Carga de CPU o RAM en Rojo (>85%)</h4>
            <p>Mira la terminal de <strong>Top Procesos</strong>. Si ves un número (PID) consumiendo demasiada memoria o atascado, cópialo, pégalo arriba en "Matar Proceso" y ejecútalo. Esto forzará su cierre y liberará la memoria.</p>
        </div>

        <div class="manual-item" style="border-left-color: var(--neon-red);">
            <h4><i class="fas fa-network-wired"></i> 2. Conexiones TCP Altas (Posible Ataque DDoS)</h4>
            <p>Mira la terminal de <strong>Top IPs</strong>. Si ves que una IP extraña tiene muchísimas conexiones simultáneas (ej. +50), cópiala, pégala en "Bloquear IP" y ejecútalo. El atacante perderá acceso a tu página al instante.</p>
        </div>

        <div class="manual-item" style="border-left-color: var(--neon-blue);">
            <h4><i class="fas fa-globe"></i> 3. Página en blanco o cargando infinitamente</h4>
            <p>La red no marca error pero la página no se visualiza. Da clic en <span class="highlight-orange">Reiniciar Web Server</span>. Esto toma 3 segundos y destraba los procesos de carga web.</p>
        </div>

        <div class="manual-item" style="border-left-color: var(--neon-green);">
            <h4><i class="fas fa-database"></i> 4. Errores al guardar o "Too many connections"</h4>
            <p>Si los maestros reportan errores al intentar generar planeaciones o registrarse, da clic en <span class="highlight-orange">Reiniciar Base de Datos</span>. Esto limpia las conexiones fantasma y reinicia el servicio MySQL en segundos.</p>
        </div>

        <div class="manual-item" style="border-left-color: #ff5555; background: rgba(255, 85, 85, 0.05);">
            <h4><i class="fas fa-user-secret"></i> 5. Sospecha de Intrusión (Auditoría Forense)</h4>
            <p>Si notas un comportamiento extraño en la interfaz o la base de datos, ejecuta la <span style="color: #ff5555; font-weight: bold;">Auditoría Forense Completa</span>. Esto escaneará el código ofuscado, iframes inyectados, redirecciones maliciosas y archivos subidos de forma anómala (Webshells).</p>
        </div>

        <div class="manual-item" style="border-left-color: #ffaa00; background: rgba(255, 170, 0, 0.05);">
            <h4><i class="fas fa-shield-alt"></i> 6. Alerta de Modificación (Verificar Integridad)</h4>
            <p>Usa <span style="color: #ffaa00; font-weight: bold;">Verificar Integridad (SHA256)</span> para comparar todos los archivos actuales del servidor contra tu firma criptográfica original. Detectará instantáneamente si un archivo crítico (como index.php o conexion.php) fue alterado.</p>
        </div>
        <div class="manual-item" style="border-left-color: #8b0000; background: rgba(255,0,0,0.05);">
            <h4><i class="fas fa-biohazard"></i> 7. Colapso Total (Nada responde)</h4>
            <p>Si todas las métricas están al 100%, nada carga y los botones anteriores fallan, usa el botón rojo gigante <span class="highlight-red">REINICIAR SERVIDOR COMPLETO</span>. Tu página desaparecerá por ~60 segundos mientras la máquina virtual en Hostinger se apaga y vuelve a encender desde cero. Úsalo solo como último recurso.</p>
        </div>
    </div>

    <div id="modal-reporte" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-titulo"><i class="fas fa-terminal"></i> Terminal de Salida</h3>
                <button class="btn-icon" onclick="cerrarModal()"><i class="fas fa-times-circle"></i></button>
            </div>
            <div class="modal-body" id="modal-cuerpo">
                </div>
            <div class="modal-footer">
                <button class="btn-action" style="background: rgba(0, 240, 255, 0.1); color: var(--neon-blue); border: 1px solid var(--neon-blue);" onclick="copiarReporte()">
                    <i class="fas fa-copy"></i> Copiar al Portapapeles
                </button>
                <button class="btn-action" style="background: rgba(0, 255, 157, 0.1); color: var(--neon-green); border: 1px solid var(--neon-green);" onclick="descargarReporte()">
                    <i class="fas fa-download"></i> Descargar Log (.txt)
                </button>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = "<?php echo htmlspecialchars($csrf_token); ?>";

        function actualizarMedidor(idMedidor, valor, limiteAmarillo, limiteRojo) {
            let barra = document.getElementById(idMedidor);
            let numero = parseFloat(valor); 
            if (isNaN(numero)) numero = 0;
            let porcentaje = numero > 100 ? 100 : numero;
            barra.style.width = porcentaje + "%";

            if (numero >= limiteRojo) { 
                barra.style.backgroundColor = "var(--neon-red)"; 
                barra.style.boxShadow = "0 0 15px var(--neon-red)";
            } 
            else if (numero >= limiteAmarillo) { 
                barra.style.backgroundColor = "var(--neon-orange)"; 
                barra.style.boxShadow = "0 0 15px var(--neon-orange)";
            } 
            else { 
                barra.style.backgroundColor = "var(--neon-green)"; 
                barra.style.boxShadow = "0 0 10px var(--neon-green)";
            }
        }

        async function escanearServidor() {
            try {
                const respuesta = await fetch('scanner.php');
                const datos = await respuesta.json();

                document.getElementById('val-cpu').innerText = datos.cpu_load;
                document.getElementById('val-ram').innerText = datos.ram_usage_percent + "%";
                document.getElementById('val-disk').innerText = datos.disk_usage;
                document.getElementById('val-uptime').innerText = datos.uptime;
                document.getElementById('val-procs').innerText = datos.active_processes;
                document.getElementById('val-conn').innerText = datos.active_connections;
                document.getElementById('val-lat').innerText = datos.network_latency;
                document.getElementById('val-temp').innerText = datos.cpu_temperature;
                
                document.getElementById('val-ips').innerText = datos.top_ips;
                document.getElementById('val-procesos').innerText = datos.top_processes;

                actualizarMedidor('gauge-cpu', datos.cpu_load * 20, 60, 85); 
                actualizarMedidor('gauge-ram', datos.ram_usage_percent, 70, 90);
                let discoPuro = datos.disk_usage.replace('%', '');
                actualizarMedidor('gauge-disk', discoPuro, 80, 95);
            } catch (error) {
                console.error("Error al obtener datos:", error);
            }
        }

        escanearServidor();
        setInterval(escanearServidor, 3000);

        // =========================================================
        // FUNCIONES DE LA NUEVA TERMINAL (MODAL)
        // =========================================================
        function mostrarTerminal(accion, comando, salida) {
            document.getElementById('modal-titulo').innerHTML = `<i class="fas fa-terminal"></i> REPORTE: ${accion.toUpperCase()}`;
            
            // Formateamos la salida para que parezca una terminal real
            let htmlSalida = `<div class="comando-lanzado">> root@server:~# ${comando}</div>`;
            htmlSalida += `<div>${salida || "Operación completada silenciosamente sin output."}</div>`;
            
            document.getElementById('modal-cuerpo').innerHTML = htmlSalida;
            document.getElementById('modal-reporte').style.display = 'flex';
        }

        function cerrarModal() {
            document.getElementById('modal-reporte').style.display = 'none';
        }

        function copiarReporte() {
            const texto = document.getElementById('modal-cuerpo').innerText;
            navigator.clipboard.writeText(texto).then(() => {
                alert("✅ Reporte copiado al portapapeles.");
            }).catch(err => {
                alert("❌ No se pudo copiar.");
            });
        }

        function descargarReporte() {
            const texto = document.getElementById('modal-cuerpo').innerText;
            const blob = new Blob([texto], { type: "text/plain" });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = `Reporte_SOC_${new Date().getTime()}.txt`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // =========================================================
        // EJECUCIÓN TÁCTICA
        // =========================================================
        async function ejecutarAccion(tipoAccion) {
            let parametro = "";
            
            if (tipoAccion === 'bloquear_ip' || tipoAccion === 'desbloquear_ip') {
                parametro = document.getElementById('input-ip').value;
                if(parametro === "") { alert("Por favor, ingrese una dirección IP."); return; }
            } else if (tipoAccion === 'matar_proceso') {
                parametro = document.getElementById('input-pid').value;
                if(parametro === "") { alert("Por favor, ingrese el ID del proceso (PID)."); return; }
            }

            let mensajeAlerta = "⚠️ PRECAUCIÓN: Está a punto de ejecutar [" + tipoAccion.toUpperCase() + "].\nObjetivo: " + (parametro ? parametro : "Servidor Local") + "\n\nPara confirmar, escriba la palabra ACEPTAR:";
            let confirmacion = prompt(mensajeAlerta);
            
            if (confirmacion !== "ACEPTAR") {
                alert("Protocolo abortado.");
                return; 
            }

            try {
                const respuesta = await fetch('ejecutar_accion.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken 
                    },
                    body: JSON.stringify({ accion: tipoAccion, parametro: parametro })
                });

                const datos = await respuesta.json();

                if (datos.success) {
                    mostrarTerminal(tipoAccion, datos.comando, datos.salida);
                    
                    document.getElementById('input-ip').value = "";
                    document.getElementById('input-pid').value = "";
                } else {
                    alert("❌ ERROR AL EJECUTAR:\n" + datos.mensaje);
                }
            } catch (error) {
                if (tipoAccion === 'reiniciar_apache' || tipoAccion === 'reboot_server') {
                    alert("✅ ORDEN CONFIRMADA:\nComando enviado con éxito. Nginx/Linux se están reiniciando, lo cual desconecta temporalmente a Cloudflare.\n\nEspere 10 segundos y el panel volverá a la vida.");
                } else {
                    alert("❌ Error crítico de comunicación con el backend de ejecución.");
                    console.error(error);
                }
            }
        }
    </script>
</body>
</html>