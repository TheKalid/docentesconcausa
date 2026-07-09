<?php
// ===================================================================================
// ARCHIVO: ejecutar_accion.php (Backend) - VERSIÓN SOC BLINDADA v2.0
// ===================================================================================

// 0. Apagamos advertencias visuales para no romper la respuesta JSON
error_reporting(0);

// 1. Iniciamos sesión para leer las credenciales del usuario
session_start();
header('Content-Type: application/json');

// --- [SEGURIDAD 1] BARRERA DE PERMISOS (Lista Blanca y Llave Táctica) ---
$acceso_permitido = false;

// Verificamos si entró por el panel táctico con la llave secreta
if (isset($_SESSION['acceso_dragon']) && $_SESSION['acceso_dragon'] === true) {
    $acceso_permitido = true;
} 
// Si no, verificamos si es uno de los dueños usando su sesión normal
else if (isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id'])) {
    require_once 'conexion.php';
    
    $stmt = $conexion->prepare("SELECT email FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['usuario_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows === 1) {
        $email = $res->fetch_assoc()['email'];
        // Correos de los administradores absolutos
        $admin_emails = [
            'ingarturodalimoran@gmail.com', 
            'arturo.moran@gmail.com'
        ];
        
        if (in_array($email, $admin_emails)) {
            $acceso_permitido = true;
        }
    }
    $stmt->close();
    $conexion->close();
}

// Bloqueo inmediato si no hay autorización
if (!$acceso_permitido) {
    echo json_encode(["success" => false, "mensaje" => "[ALERTA] ACCESO DENEGADO. Intento de ejecución no autorizado y registrado."]);
    exit;
}

// --- [SEGURIDAD 2] VERIFICACIÓN DE FIRMA CSRF ---
$csrf_header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_header)) {
    echo json_encode(["success" => false, "mensaje" => "Firma CSRF inválida o ausente. Petición rechazada."]);
    exit;
}

// --- [SEGURIDAD 3] PROCESAMIENTO ESTRICTO ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "mensaje" => "Método HTTP no permitido."]);
    exit;
}

$datos = json_decode(file_get_contents("php://input"), true);

if (!isset($datos['accion'])) {
    echo json_encode(["success" => false, "mensaje" => "No se recibió ninguna orden."]);
    exit;
}

$accion = $datos['accion'];
$parametro = isset($datos['parametro']) ? trim($datos['parametro']) : "";

// ===================================================================================
// ARMERÍA DE COMANDOS TÁCTICOS Y FORENSES
// ===================================================================================

switch ($accion) {
    
    // ---------------------------------------------------------
    // HERRAMIENTAS DE GESTIÓN DE SERVIDOR Y RED
    // ---------------------------------------------------------
    case 'matar_proceso':
        if (is_numeric($parametro)) {
            $comando_linux = "sudo kill -9 " . escapeshellarg($parametro);
            $salida = shell_exec($comando_linux . " 2>&1");
            responder_json(true, $comando_linux, "Proceso $parametro aniquilado. Salida: " . trim($salida));
        } else {
            responder_json(false, "", "El ID del proceso (PID) debe ser numérico.");
        }
        break;

    case 'bloquear_ip':
        if (filter_var($parametro, FILTER_VALIDATE_IP)) {
            $comando_linux = "sudo iptables -A INPUT -s " . escapeshellarg($parametro) . " -j DROP";
            $salida = shell_exec($comando_linux . " 2>&1");
            responder_json(true, $comando_linux, "IP $parametro bloqueada en el firewall (DROP). Salida: " . trim($salida));
        } else {
            responder_json(false, "", "Formato de IP inválido.");
        }
        break;

    case 'desbloquear_ip':
        if (filter_var($parametro, FILTER_VALIDATE_IP)) {
            $comando_linux = "sudo iptables -D INPUT -s " . escapeshellarg($parametro) . " -j DROP";
            $salida = shell_exec($comando_linux . " 2>&1");
            responder_json(true, $comando_linux, "IP $parametro desbloqueada del firewall. Salida: " . trim($salida));
        } else {
            responder_json(false, "", "Formato de IP inválido.");
        }
        break;

    case 'reiniciar_apache':
        $comando_linux = "sudo systemctl restart nginx";
        $salida = shell_exec($comando_linux . " 2>&1");
        responder_json(true, $comando_linux, "Servidor Web reiniciado. Conexiones restablecidas.");
        break;

    case 'reiniciar_mysql':
        $comando_linux = "sudo systemctl restart mysql";
        $salida = shell_exec($comando_linux . " 2>&1");
        responder_json(true, $comando_linux, "Servicio MySQL reiniciado. Memoria de base de datos liberada.");
        break;

    case 'reboot_server':
        $comando_linux = "sudo reboot";
        shell_exec($comando_linux);
        responder_json(true, $comando_linux, "Iniciando secuencia de reinicio de hardware...");
        break;

    // ---------------------------------------------------------
    // NUEVAS HERRAMIENTAS DE NIVEL SOC (AUDITORÍA E INTEGRIDAD)
    // ---------------------------------------------------------
    case 'auditoria_forense':
        $comando = <<<'BASH'
        echo '==================================================';
        echo '   SISTEMA DE AUDITORÍA FORENSE (PASIVO) v2       ';
        echo '==================================================';
        echo '';

        echo '[1] ARCHIVOS PHP MODIFICADOS (ÚLTIMAS 72 HORAS)';
        find /var/www/docentesconcausa.com/html -type f -name "*.php" -mtime -3 2>/dev/null | head -50;
        
        echo '';
        echo '[2] DETECCIÓN DE FUNCIONES PELIGROSAS EN PHP';
        grep -RniE '(eval\s*\(|assert\s*\(|exec\s*\(|shell_exec\s*\(|passthru\s*\(|popen\s*\(|proc_open\s*\(|pcntl_exec\s*\()' /var/www/docentesconcausa.com/html --include=\*.php 2>/dev/null | head -50;

        echo '';
        echo '[3] DETECCIÓN DE CÓDIGO OFUSCADO';
        grep -RniE '(base64_decode|gzinflate|gzdecode|str_rot13|hex2bin|pack\s*\(|chr\s*\(|dechex\s*\()' /var/www/docentesconcausa.com/html --include=\*.php 2>/dev/null | head -50;

        echo '';
        echo '[4] ARCHIVOS PHP EN UPLOADS (ALERTA CRÍTICA)';
        find /var/www/docentesconcausa.com/html -type f -regextype posix-extended -regex '.*\/uploads\/.*\.(php|phtml|php3|php4|php5|phar)$' 2>/dev/null;

        echo '';
        echo '[5] ARCHIVOS CON PERMISOS INSEGUROS (777)';
        find /var/www/docentesconcausa.com/html -perm 777 -type f 2>/dev/null | head -50;

        echo '';
        echo '[6] IFRAMES INYECTADOS (SEO SPAM)';
        grep -Rni '<iframe' /var/www/docentesconcausa.com/html --include=\*.php --include=\*.html 2>/dev/null | head -50;

        echo '';
        echo '[7] ARCHIVOS SCRIPT OCULTOS O ANÓMALOS';
        find /var/www/docentesconcausa.com/html -type f \( -name ".*" -o -name "*.phtml" -o -name "*.phar" -o -name "*.sh" \) 2>/dev/null | head -50;

        echo '';
        echo '[8] LOGS DE ATAQUE EN NGINX/APACHE (ÚLTIMAS CONEXIONES)';
        if [ -f /var/log/nginx/access.log ]; then
            grep -Ei '(union select|sleep\(|benchmark\(|base64|cmd=|shell|wget|curl|\.\.\/|%2e%2e)' /var/log/nginx/access.log 2>/dev/null | tail -50;
        elif [ -f /var/log/apache2/access.log ]; then
            grep -Ei '(union select|sleep\(|benchmark\(|base64|cmd=|shell|wget|curl|\.\.\/|%2e%2e)' /var/log/apache2/access.log 2>/dev/null | tail -50;
        fi
        
        echo '';
        echo '===== FIN DE AUDITORÍA =====';
BASH;
        
        $salida = shell_exec($comando);
        $salida_final = !empty(trim($salida)) ? $salida : 'La auditoría se ejecutó pero no devolvió datos (Todo parece limpio).';
        responder_json(true, 'Auditoría Forense Completa', $salida_final);
        break;

    case 'verificar_integridad':
        $ruta_linea_base = '/var/www/docentesconcausa_baseline.sha256';

        if (!file_exists($ruta_linea_base)) {
            $mensaje_error = "==================================================\n";
            $mensaje_error .= "ERROR CRÍTICO: FALTA LÍNEA BASE\n";
            $mensaje_error .= "==================================================\n";
            $mensaje_error .= "No existe el archivo de control en: $ruta_linea_base\n\n";
            $mensaje_error .= "Para usar esta herramienta, conéctate por SSH y genera la firma ejecutando una sola vez:\n";
            $mensaje_error .= "find /var/www/docentesconcausa.com/html -type f -exec sha256sum {} \; > /var/www/docentesconcausa_baseline.sha256";
            
            responder_json(true, 'Verificación de Integridad (SHA256)', $mensaje_error);
        }

        $comando = "sha256sum -c $ruta_linea_base 2>/dev/null | grep -E 'FAILED|FALTA|MISMATCH'";
        $salida = shell_exec($comando);

        if (trim($salida) === '') {
            $resultado_final = "==================================================\n";
            $resultado_final .= "[✓] ÉXITO: LA INTEGRIDAD ESTÁ INTACTA\n";
            $resultado_final .= "==================================================\n";
            $resultado_final .= "Ningún archivo crítico ha sido modificado, eliminado o alterado desde la última captura de línea base.";
        } else {
            $resultado_final = "==================================================\n";
            $resultado_final .= "⚠️ ADVERTENCIA: SE DETECTARON CAMBIOS O INTRUSIONES\n";
            $resultado_final .= "==================================================\n" . $salida;
        }

        responder_json(true, 'Verificación de Integridad (SHA256)', $resultado_final);
        break;

    default:
        responder_json(false, "", "Comando no reconocido o inexistente en la armería.");
        break;
}

// ===================================================================================
// FUNCIÓN AUXILIAR PARA ESTANDARIZAR LA RESPUESTA
// ===================================================================================
function responder_json($exito, $comando, $salida_o_mensaje) {
    if ($exito) {
        echo json_encode([
            "success" => true,
            "comando" => $comando,
            "salida"  => $salida_o_mensaje
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "mensaje" => $salida_o_mensaje
        ]);
    }
    exit;
}
?>