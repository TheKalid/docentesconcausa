<?php
// ===================================================================================
// ARCHIVO: scanner.php (Backend - VERSIÓN 5.0: Francotirador de Procesos)
// OBJETIVO: Extracción de métricas + Radar de IPs + Rastreador de PIDs
// ===================================================================================
session_start();

// Función de seguridad anti-errores
function run_cmd($cmd, $default = "0") {
    $res = shell_exec($cmd);
    return ($res !== null && $res !== false) ? trim($res) : $default;
}

function obtenerCargaCPU() { $carga = sys_getloadavg(); return isset($carga[0]) ? $carga[0] : 0; }
function obtenerUsoRAM() { return run_cmd("free -m | awk 'NR==2{printf \"%.2f\", $3*100/$2 }'", "0"); }
function obtenerUptime() { return run_cmd("uptime -p", "N/A"); }
function obtenerEspacioDisco() { return run_cmd("df -h / | awk 'NR==2 {print $5}'", "0"); }
function obtenerProcesosActivos() { return run_cmd("ps aux | wc -l", "0"); }
function obtenerConexionesRed() { return run_cmd("ss -tn state established | wc -l", "0"); }
function obtenerLatencia() { 
    $lat = run_cmd("ping -c 1 8.8.8.8 | tail -1 | awk '{print $4}' | cut -d '/' -f 2", "");
    return $lat ? $lat . " ms" : "Timeout"; 
}
function obtenerTemperaturaCPU() {
    $temp = run_cmd("cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null", "");
    return ($temp && is_numeric($temp)) ? round($temp/1000, 1)." °C" : "N/A";
}
function obtenerListaIPs() {
    $comando = "ss -ntu | awk '{print $6}' | cut -d: -f1 | grep -v '127.0.0.1' | grep -v 'Address' | grep -E '[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+' | sort | uniq -c | sort -nr | head -n 6";
    $ips = run_cmd($comando, "");
    return $ips ? $ips : "Sin conexiones externas.";
}

// -------------------------------------------------------------------
// NUEVA FUNCIÓN: RASTREADOR DE PROCESOS (EL RADAR DEL FRANCOTIRADOR)
// -------------------------------------------------------------------
function obtenerTopProcesos() {
    // Comando Linux táctico:
    // 1. ps -eo: Muestra todos los procesos con un formato específico.
    // 2. pid,user,%cpu,%mem,comm: Pedimos el ID, Usuario, %CPU, %RAM y Nombre del comando.
    // 3. --sort=-%cpu: Ordena la lista de mayor a menor consumo de CPU (el '-' es para orden descendente).
    // 4. head -n 6: Nos trae la cabecera (títulos) y los 5 procesos más pesados.
    $comando = "ps -eo pid,user,%cpu,%mem,comm --sort=-%cpu | head -n 6";
    
    $procesos = run_cmd($comando, "");
    return $procesos ? $procesos : "Fallo al leer procesos.";
}

// Empaquetado de datos final
$datosServidor = [
    "cpu_load" => obtenerCargaCPU(),
    "ram_usage_percent" => obtenerUsoRAM(),
    "uptime" => obtenerUptime(),
    "disk_usage" => obtenerEspacioDisco(),
    "active_processes" => obtenerProcesosActivos(),
    "active_connections" => obtenerConexionesRed(),
    "network_latency" => obtenerLatencia(),
    "cpu_temperature" => obtenerTemperaturaCPU(),
    "top_ips" => obtenerListaIPs(),
    "top_processes" => obtenerTopProcesos() // Inyectamos la nueva tabla de PIDs al JSON
];

header('Content-Type: application/json');
echo json_encode($datosServidor);
?>
