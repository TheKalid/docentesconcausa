<?php
// =====================================================================
// ARCHIVO: generador_adm.php
// OBJETIVO: Panel de administración blindado (Anti-CSRF, Anti-XSS, Headers)
// =====================================================================
session_start();

// [SEGURIDAD 1] CABECERAS HTTP ESTRICTAS
header("X-Frame-Options: DENY"); 
header("X-XSS-Protection: 1; mode=block"); 
header("X-Content-Type-Options: nosniff"); 
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0"); 

// [SEGURIDAD 2] VERIFICACIÓN DE SESIÓN DE ADMINISTRADOR
if (!isset($_SESSION['admin_logeado']) || $_SESSION['admin_logeado'] !== true) {
    header("Location: index.php");
    exit;
}

// [SEGURIDAD 3] GENERACIÓN DE FIRMA DIGITAL (TOKEN CSRF)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

require 'conexion.php';

$mensaje = '';
$usuario_editar = null;
$resultados_busqueda = [];

// ==========================================
// A. LÓGICA DE BÚSQUEDA (GET)
// ==========================================
if (isset($_GET['buscar']) && !empty(trim($_GET['buscar']))) {
    $termino = "%" . trim($_GET['buscar']) . "%";
    
    $sql_buscar = "SELECT id, nombre, email, plan_activo FROM usuarios WHERE nombre LIKE ? OR email LIKE ?";
    $stmt_buscar = $conexion->prepare($sql_buscar);
    $stmt_buscar->bind_param("ss", $termino, $termino);
    $stmt_buscar->execute();
    $res = $stmt_buscar->get_result();
    
    while ($fila = $res->fetch_assoc()) {
        $resultados_busqueda[] = $fila;
    }
    $stmt_buscar->close();
}

// ==========================================
// B. LÓGICA PARA CARGAR DATOS AL SELECCIONAR (GET)
// ==========================================
if (isset($_GET['editar_id'])) {
    $id_editar = intval($_GET['editar_id']);
    $sql_editar = "SELECT * FROM usuarios WHERE id = ?";
    $stmt_editar = $conexion->prepare($sql_editar);
    $stmt_editar->bind_param("i", $id_editar);
    $stmt_editar->execute();
    $usuario_editar = $stmt_editar->get_result()->fetch_assoc();
    $stmt_editar->close();
}

// ==========================================
// C. LÓGICA DE ACTUALIZACIÓN BLINDADA (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_usuario'])) {
    
    // [SEGURIDAD 4] VALIDACIÓN DEL TOKEN CSRF
    $token_post = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token_post)) {
        die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Error de Seguridad</h2><p>Firma de seguridad inválida. Acción bloqueada.</p></div>");
    }

    $id_usuario = intval($_POST['id_usuario']);
    
    if ($id_usuario > 0) {
        $plan = intval($_POST['plan_activo']);
        $basico = intval($_POST['usos_basico']);
        $intermedio = intval($_POST['usos_intermedio']);
        $diagnostica = intval($_POST['usos_diagnostica']);
        $protocolos = intval($_POST['usos_protocolos']);
        $bitacora = intval($_POST['usos_bitacora']);
        $fisica = intval($_POST['usos_fisica']);
        $examenes = intval($_POST['usos_examenes']);

        if (!empty($_POST['nueva_password'])) {
            $password_hash = password_hash($_POST['nueva_password'], PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET plan_activo=?, usos_plan_basico=?, usos_plan_intermedio=?, usos_evaluacion_diagnostica=?, usos_protocolos=?, usos_bitacora=?, usos_fisica=?, usos_examenes=?, password=? WHERE id=?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("iiiiiiiisi", $plan, $basico, $intermedio, $diagnostica, $protocolos, $bitacora, $fisica, $examenes, $password_hash, $id_usuario);
        } else {
            $sql = "UPDATE usuarios SET plan_activo=?, usos_plan_basico=?, usos_plan_intermedio=?, usos_evaluacion_diagnostica=?, usos_protocolos=?, usos_bitacora=?, usos_fisica=?, usos_examenes=? WHERE id=?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("iiiiiiiii", $plan, $basico, $intermedio, $diagnostica, $protocolos, $bitacora, $fisica, $examenes, $id_usuario);
        }

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $mensaje = "<div class='alerta exito'>✅ Usuario ID {$id_usuario} actualizado con éxito.</div>";
                $sql_editar = "SELECT * FROM usuarios WHERE id = ?";
                $stmt_editar = $conexion->prepare($sql_editar);
                $stmt_editar->bind_param("i", $id_usuario);
                $stmt_editar->execute();
                $usuario_editar = $stmt_editar->get_result()->fetch_assoc();
                $stmt_editar->close();
            } else {
                $mensaje = "<div class='alerta advertencia'>⚠️ No se hicieron cambios (datos iguales a los actuales).</div>";
            }
        } else {
            $mensaje = "<div class='alerta error'>❌ Error al actualizar: " . htmlspecialchars($stmt->error) . "</div>";
        }
        
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
    }
}

// ==========================================
// D. NUEVA LÓGICA: GESTIÓN CONVENIO CETL (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_cetl'])) {
    
    // Validar CSRF
    $token_post = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token_post)) {
        die("<div style='text-align:center; padding:50px;'><h2>Error de Seguridad</h2><p>Firma de seguridad inválida.</p></div>");
    }

    $accion = $_POST['accion_cetl'];
    
    // Procesar ALTAS y BAJAS
    if ($accion === 'alta' || $accion === 'baja') {
        $ids_raw = explode(',', $_POST['ids_cetl']);
        $ids_limpios = [];
        
        // Sanitizar asegurando que solo sean números enteros
        foreach ($ids_raw as $id_raw) {
            $val = intval(trim($id_raw));
            if ($val > 0) {
                $ids_limpios[] = $val;
            }
        }
        
        if (!empty($ids_limpios)) {
            $ids_str = implode(',', $ids_limpios);
            
            if ($accion === 'alta') {
                // 1. Insertar en tabla convenio (Si ya existe, reactivarlo con ON DUPLICATE KEY)
                $sql_ins = "INSERT INTO convenio_cetl (usuario_id, activo) VALUES ";
                $values = [];
                foreach ($ids_limpios as $id) {
                    $values[] = "($id, 1)";
                }
                $sql_ins .= implode(', ', $values);
                $sql_ins .= " ON DUPLICATE KEY UPDATE activo = 1";
                $conexion->query($sql_ins);
                
                // 2. Activar plan premium en tabla usuarios
                $conexion->query("UPDATE usuarios SET plan_activo = 2 WHERE id IN ($ids_str)");
                
                $mensaje = "<div class='alerta exito'>🎓 ✅ Usuarios dados de ALTA en el convenio CETL y pasados a Premium: <b>$ids_str</b></div>";
            } 
            elseif ($accion === 'baja') {
                // 1. Marcar como inactivo en la tabla del convenio
                $conexion->query("UPDATE convenio_cetl SET activo = 0 WHERE usuario_id IN ($ids_str)");
                
                // 2. Regresar al plan inactivo/básico (0) en tabla usuarios
                $conexion->query("UPDATE usuarios SET plan_activo = 0 WHERE id IN ($ids_str)");
                
                $mensaje = "<div class='alerta advertencia'>🎓 ⚠️ Usuarios dados de BAJA del convenio CETL (Pasados a inactivos): <b>$ids_str</b></div>";
            }
        } else {
            $mensaje = "<div class='alerta error'>❌ Por favor ingresa IDs válidos separados por coma.</div>";
        }
    } 
    // Procesar RENOVACIÓN MENSUAL
    elseif ($accion === 'renovar') {
        $sql_renovar = "UPDATE usuarios u 
                        INNER JOIN convenio_cetl c ON u.id = c.usuario_id 
                        SET 
                            u.usos_plan_basico = 2, 
                            u.usos_plan_intermedio = 10, 
                            u.usos_evaluacion_diagnostica = 4, 
                            u.usos_protocolos = 3, 
                            u.usos_bitacora = 3, 
                            u.usos_fisica = 2, 
                            u.usos_examenes = 4 
                        WHERE c.activo = 1 AND u.plan_activo = 2";
                        
        if ($conexion->query($sql_renovar)) {
            $filas = $conexion->affected_rows;
            $mensaje = "<div class='alerta exito'>🔄 <b>Éxito:</b> Se han renovado los intentos a <b>$filas</b> maestros activos del convenio CETL.</div>";
        } else {
            $mensaje = "<div class='alerta error'>❌ Error al renovar: " . htmlspecialchars($conexion->error) . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
    body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; color: #334155; padding: 40px 20px; }
    .admin-container { max-width: 900px; margin: 0 auto; background: white; padding: 40px; border-radius: 15px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
    h1, h2 { color: #1e3a8a; margin-top: 0; }
    
    .search-box { display: flex; gap: 10px; margin-bottom: 30px; background: #eef2ff; padding: 20px; border-radius: 10px; }
    .search-box input { flex: 1; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem; }
    .search-box button { background: #1e3a8a; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; white-space: nowrap; }
    
    .table-responsive { width: 100%; overflow-x: auto; margin-bottom: 30px; border-radius: 8px; box-shadow: 0 0 0 1px #e2e8f0; }
    table { width: 100%; border-collapse: collapse; min-width: 600px; } 
    th, td { text-align: left; padding: 12px; border-bottom: 1px solid #e2e8f0; }
    th { background-color: #f1f5f9; color: #475569; }
    .btn-seleccionar { background: #10b981; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 0.9rem; font-weight: bold; white-space: nowrap; }
    .btn-seleccionar:hover { background: #059669; }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; background: #f8fafc; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; }
    .form-group { display: flex; flex-direction: column; }
    .form-group.full-width { grid-column: 1 / -1; }
    label { font-weight: 600; margin-bottom: 5px; font-size: 0.9rem; color: #475569; }
    input, select { padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; }
    input[readonly] { background-color: #e2e8f0; cursor: not-allowed; }
    .btn-guardar { background-color: #f39c12; color: white; border: none; padding: 15px; border-radius: 8px; font-weight: 700; cursor: pointer; margin-top: 20px; width: 100%; font-size: 1.1rem; }
    .btn-guardar:hover { background-color: #d68910; }
    
    .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid #eef2ff; padding-bottom: 15px; flex-wrap: wrap; gap: 15px; }
    .alerta { padding: 15px; border-radius: 8px; margin-bottom: 25px; font-weight: 600; }
    .exito { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .advertencia { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
    .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .usuario-badge { display: inline-block; background: #1e3a8a; color: white; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; margin-bottom: 15px; }

    @media (max-width: 768px) {
        body { padding: 20px 10px; }
        .admin-container { padding: 20px; }
        .header-bar { flex-direction: column; text-align: center; }
        .search-box { flex-direction: column; }
        .search-box button { width: 100%; }
        .form-grid { grid-template-columns: 1fr; } 
        h1 { font-size: 1.5rem; }
    }
</style>
</head>
<body>

<div class="admin-container">
    <div class="header-bar">
        <h1>⚙️ Gestor de Accesos</h1>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; justify-content: center;">
            
            <a href="auditoria_tokens.php" style="background: #3b82f6; color: white; text-decoration: none; padding: 8px 15px; border-radius: 6px; font-weight: bold; font-size: 0.9rem;">📊 Auditoría IA</a>

            <form method="POST" action="generar_negociacion_precios.php" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="admin_key" value="Golosa69">
                <button type="submit" style="background: #10b981; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; font-family: inherit;">💰 Negociar</button>
            </form>
            
            <a href="logout_adm.php" style="color: #ef4444; text-decoration: none; font-weight: 600; padding: 8px;">Cerrar Sesión</a>
        </div>
    </div>

    <?= $mensaje ?>

    <!-- NUEVO: PANEL DE GESTIÓN CETL -->
    <div style="background: #f0fdf4; padding: 20px; border-radius: 10px; border: 1px solid #bbf7d0; margin-bottom: 30px;">
        <h3 style="color: #166534; margin-top: 0; display: flex; align-items: center; gap: 8px;">🎓 Gestión de Convenio CETL</h3>
        
        <form method="POST" action="" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <div style="flex: 1; min-width: 250px;">
                <label style="color: #166534; font-weight: 600; font-size: 0.9rem; display: block; margin-bottom: 5px;">IDs de Usuarios (separados por coma):</label>
                <input type="text" name="ids_cetl" placeholder="Ej: 301, 305, 380" style="width: 100%; box-sizing: border-box; border: 1px solid #bbf7d0; padding: 10px; border-radius: 6px;">
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="accion_cetl" value="alta" style="background: #10b981; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: bold;">➕ Dar de Alta</button>
                <button type="submit" name="accion_cetl" value="baja" style="background: #ef4444; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: bold;">➖ Dar de Baja</button>
            </div>
        </form>

        <hr style="border: 1px solid #bbf7d0; margin: 15px 0;">
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <button type="submit" name="accion_cetl" value="renovar" style="background: #3b82f6; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%;">🔄 Renovar Sesiones Mensuales CETL (Aplica a todos los activos)</button>
        </form>
    </div>
    <!-- FIN NUEVO PANEL CETL -->

    <form method="GET" action="" class="search-box">
        <input type="text" name="buscar" placeholder="Buscar por Nombre o Correo Electrónico..." value="<?= htmlspecialchars($_GET['buscar'] ?? '') ?>">
        <button type="submit">🔍 Buscar</button>
    </form>

    <?php if (!empty($resultados_busqueda)): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Plan</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultados_busqueda as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['nombre']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= $user['plan_activo'] == 2 ? 'Premium' : ($user['plan_activo'] == 1 ? 'Básico' : 'Inactivo') ?></td>
                        <td>
                            <a href="?editar_id=<?= $user['id'] ?>&buscar=<?= urlencode($_GET['buscar'] ?? '') ?>" class="btn-seleccionar">Editar ✏️</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif(isset($_GET['buscar'])): ?>
    <div class="alerta advertencia">No se encontraron usuarios con ese nombre o correo.</div>
    <?php endif; ?>

        
    <?php if ($usuario_editar): ?>
        <hr style="border: 1px solid #eef2ff; margin: 40px 0;">
        <h2>Modificar Cuenta</h2>
        <span class="usuario-badge">Editando a: <?= htmlspecialchars($usuario_editar['nombre']) ?> (<?= htmlspecialchars($usuario_editar['email']) ?>)</span>

        <div style="background: #eef2ff; padding: 25px; border-radius: 10px; margin: 25px 0; border: 1px solid #c7d2fe;">
            <h3 style="color: #1e3a8a; margin-top: 0; display: flex; align-items: center; gap: 8px;">
                <span>💰</span> Generar Link de Pago con Descuento
            </h3>
            <p style="font-size: 0.9rem; color: #475569; margin-bottom: 20px;">
                Haz clic para copiar el enlace de la oferta que negociaste. El enlace ya lleva incrustado el <b>ID: <?= $usuario_editar['id'] ?></b> del maestro <b><?= htmlspecialchars($usuario_editar['nombre']) ?></b>.
            </p>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                
                <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                    <h4 style="margin-top: 0; color: #10b981; text-align: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">🟢 Ofertas Plan Básico</h4>
                    <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
                        
                        <button type="button" onclick="copiarLigaM('https://buy.stripe.com/eVq9ATeQQ9nS3CP8Rr4AU0k?client_reference_id=<?= $usuario_editar['id'] ?>')" style="background: #10b981; color: white; border: none; padding: 10px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">Básico - 110</button>
                        
                        <button type="button" onclick="copiarLigaM('https://buy.stripe.com/cNi4gz100bw02yLd7H4AU0i?client_reference_id=<?= $usuario_editar['id'] ?>')" style="background: #10b981; color: white; border: none; padding: 10px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">Básico - 99</button>

                        <button type="button" onclick="copiarLigaM('https://buy.stripe.com/28EcN5gYYarWb5hgjT4AU0l?client_reference_id=<?= $usuario_editar['id'] ?>')" style="background: #10b981; color: white; border: none; padding: 10px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">Básico - 89</button>

                    </div>
                </div>

                <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                    <h4 style="margin-top: 0; color: #f39c12; text-align: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">🟠 Ofertas Plan Mentor</h4>
                    <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
                        <button type="button" onclick="copiarLigaM('https://buy.stripe.com/28EbJ1100bw0gpB4Bb4AU0m?client_reference_id=<?= $usuario_editar['id'] ?>')" style="background: #f39c12; color: white; border: none; padding: 10px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">Mentor - 145</button>
                        
                        <button type="button" onclick="copiarLigaM('https://buy.stripe.com/bJecN57oo57C1uH7Nn4AU0n?client_reference_id=<?= $usuario_editar['id'] ?>')" style="background: #f39c12; color: white; border: none; padding: 10px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">Mentor - 135</button>
                        
                        <button type="button" onclick="copiarLigaM('https://buy.stripe.com/6oU3cvfUUcA42yL3x74AU0o?client_reference_id=<?= $usuario_editar['id'] ?>')" style="background: #f39c12; color: white; border: none; padding: 10px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">Mentor - 125</button>
                        
                    </div>
                </div>

            </div>
        </div>

        <script>
            function copiarLigaM(url) {
                navigator.clipboard.writeText(url);
                alert("✅ ¡Liga copiada al portapapeles!\n\nSe incluyó el ID del usuario automáticamente.\nYa puedes pegarla en WhatsApp.");
            }
        </script>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="form-grid">
                <div class="form-group full-width">
                    <label>ID del Usuario (Bloqueado):</label>
                    <input type="number" name="id_usuario" value="<?= $usuario_editar['id'] ?>" readonly required>
                </div>

                <div class="form-group">
                    <label>Plan Activo:</label>
                    <select name="plan_activo">
                        <option value="0" <?= $usuario_editar['plan_activo'] == 0 ? 'selected' : '' ?>>0 - Inactivo</option>
                        <option value="1" <?= $usuario_editar['plan_activo'] == 1 ? 'selected' : '' ?>>1 - Básico</option>
                        <option value="2" <?= $usuario_editar['plan_activo'] == 2 ? 'selected' : '' ?>>2 - Premium / Completo</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Cambiar Contraseña (Opcional):</label>
                    <input type="text" name="nueva_password" placeholder="Dejar en blanco para conservar la actual">
                </div>

                <div class="form-group"><label>Usos Plan Básico:</label><input type="number" name="usos_basico" value="<?= $usuario_editar['usos_plan_basico'] ?>"></div>
                <div class="form-group"><label>Usos Plan Intermedio:</label><input type="number" name="usos_intermedio" value="<?= $usuario_editar['usos_plan_intermedio'] ?>"></div>
                <div class="form-group"><label>Usos Ev. Diagnóstica:</label><input type="number" name="usos_diagnostica" value="<?= $usuario_editar['usos_evaluacion_diagnostica'] ?>"></div>
                <div class="form-group"><label>Usos Protocolos:</label><input type="number" name="usos_protocolos" value="<?= $usuario_editar['usos_protocolos'] ?>"></div>
                <div class="form-group"><label>Usos Bitácora:</label><input type="number" name="usos_bitacora" value="<?= $usuario_editar['usos_bitacora'] ?>"></div>
                <div class="form-group"><label>Usos Física:</label><input type="number" name="usos_fisica" value="<?= $usuario_editar['usos_fisica'] ?>"></div>
                <div class="form-group"><label>Usos Exámenes:</label><input type="number" name="usos_examenes" value="<?= $usuario_editar['usos_examenes'] ?>"></div>
            </div>

            <button type="submit" class="btn-guardar">Guardar Cambios</button>
        </form>
    <?php else: ?>
        <div style="text-align: center; color: #94a3b8; margin-top: 40px;">
            <p>Utiliza el buscador de arriba para seleccionar un usuario y modificar sus accesos.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>