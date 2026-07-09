<?php
// =====================================================================
// ARCHIVO: auditoria_tokens.php
// OBJETIVO: Panel Analítico - Dashboard Visial, Exportación a PDF y EXCEL
// =====================================================================
session_start();

// [SEGURIDAD] CABECERAS
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if (!isset($_SESSION['admin_logeado']) || $_SESSION['admin_logeado'] !== true) {
    header("Location: index.php");
    exit;
}

require 'conexion.php';

// 1. Catálogo Oficial de tus 20 Herramientas
$catalogo_herramientas = [
    'Planeación Básica', 
    'Planeación Avanzada', 
    'Planeación PALE', 
    'Planeación PAM', 
    'Planeación Educación Física', 
    'Evaluaciones Diagnósticas', 
    'Exámenes BIM/TRIM (NEM)', 
    'Protocolos Educativos', 
    'Bitácoras Docentes', 
    'Simuladores USICAMM', 
    'Planificador Inclusivo (DUA)', 
    'Cultura de Paz y Convivencia', 
    'Planeación Transversal', 
    'Adecuaciones Curriculares', 
    'Simulador Padres Problemáticos', 
    'Periódico Mural y Efemérides', 
    'Planeación por Competencias', 
    'Sesiones Psicológicas', 
    'Planeaciones Telesecundarias', 
    'Agente de Estudio USICAMM'
];

// 2. Consultar el total general de HOY
$sql_total_hoy = "SELECT COUNT(*) as total FROM historial_uso WHERE DATE(fecha) = CURDATE()";
$res_total = $conexion->query($sql_total_hoy);
$total_hoy = $res_total ? $res_total->fetch_assoc()['total'] : 0;

// 3. Consultar el uso de HOY agrupado por herramienta
$estadisticas_hoy = array_fill_keys($catalogo_herramientas, 0); // Inicializamos todas en 0
$sql_agrupado = "SELECT herramienta, COUNT(*) as cantidad FROM historial_uso WHERE DATE(fecha) = CURDATE() GROUP BY herramienta";
$res_agrupado = $conexion->query($sql_agrupado);

if ($res_agrupado) {
    while ($fila = $res_agrupado->fetch_assoc()) {
        $nombre_herramienta = $fila['herramienta'];
        if (array_key_exists($nombre_herramienta, $estadisticas_hoy)) {
            $estadisticas_hoy[$nombre_herramienta] = $fila['cantidad'];
        }
    }
}

// 4. Obtenemos el historial completo para la tabla (Últimos 1000)
$sql_historial = "SELECT h.id, h.herramienta, h.ip_usuario, h.fecha, u.nombre, u.email, u.telefono 
        FROM historial_uso h 
        INNER JOIN usuarios u ON h.usuario_id = u.id 
        ORDER BY h.fecha DESC LIMIT 1000";

$resultado_historial = $conexion->query($sql_historial);
$movimientos = [];
if ($resultado_historial) {
    while ($fila = $resultado_historial->fetch_assoc()) {
        $movimientos[] = $fila;
    }
}

$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMC | Analytics Dashboard</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Librerías de Exportación -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <style>
        :root {
            --bg-base: #f1f5f9;
            --text-main: #334155;
            --blue-corp: #1e3a8a;
            --green-excel: #107c41;
            --red-pdf: #dc2626;
        }

        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-base); color: var(--text-main); margin: 0; padding: 20px; }
        .admin-container { max-width: 1300px; margin: 0 auto; }
        
        /* HEADER */
        .header-bar { display: flex; justify-content: space-between; align-items: center; background: white; padding: 20px 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px; flex-wrap: wrap; gap: 15px;}
        .header-bar h1 { margin: 0; color: var(--blue-corp); display: flex; align-items: center; gap: 10px; font-size: 1.6rem;}
        
        .btn-volver { background: #475569; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; transition: 0.3s; display: flex; align-items: center; gap: 8px;}
        .btn-volver:hover { background: #334155; }
        
        .btn-excel { background: var(--green-excel); color: white; padding: 10px 20px; border: none; border-radius: 6px; font-weight: bold; font-family: inherit; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 8px; font-size: 1rem;}
        .btn-excel:hover { background: #0b5e31; box-shadow: 0 4px 10px rgba(16, 124, 65, 0.3); }

        .btn-pdf { background: var(--red-pdf); color: white; padding: 10px 20px; border: none; border-radius: 6px; font-weight: bold; font-family: inherit; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 8px; font-size: 1rem;}
        .btn-pdf:hover { background: #b91c1c; }

        /* DASHBOARD DE TERMÓMETROS */
        .panel-metricas { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .panel-metricas h2 { margin-top: 0; color: var(--blue-corp); border-bottom: 2px solid #eef2ff; padding-bottom: 15px; margin-bottom: 20px; font-size: 1.3rem;}
        
        .grid-indicadores { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        
        .medidor-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; position: relative; overflow: hidden;}
        .medidor-card h4 { margin: 0 0 10px 0; font-size: 0.85rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-transform: uppercase; letter-spacing: 0.5px;}
        .medidor-valor { font-size: 1.8rem; font-weight: 700; color: var(--blue-corp); margin-bottom: 10px; display: flex; align-items: baseline; gap: 5px;}
        .medidor-valor span { font-size: 0.8rem; color: #94a3b8; font-weight: 400; }
        
        /* BARRA DE PROGRESO (Termómetro) */
        .progress-bg { background: #e2e8f0; height: 8px; border-radius: 10px; width: 100%; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 10px; transition: width 0.8s ease-out; }
        
        /* TABLA DE AUDITORÍA */
        .table-container { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow-x: auto;}
        table { width: 100%; border-collapse: collapse; min-width: 900px; } 
        th, td { text-align: left; padding: 15px; border-bottom: 1px solid #e2e8f0; font-size: 0.95rem; }
        th { background-color: #f1f5f9; color: #475569; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        tr:hover { background-color: #f8fafc; }
        
        .badge-tool { background: #eef2ff; color: var(--blue-corp); padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; border: 1px solid #c7d2fe; display: inline-block;}
        .date-text { color: #64748b; font-size: 0.85rem; font-weight: 600; }
        .ip-badge { background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 6px; font-family: monospace; font-size: 0.85rem; border: 1px solid #cbd5e1;}

        @media (max-width: 768px) {
            .header-bar { justify-content: center; text-align: center; }
            .btn-excel, .btn-pdf { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

    <div class="admin-container">
        
        <div class="header-bar">
            <h1><i class="fas fa-chart-pie"></i> SMC Analytics</h1>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;">
                <button onclick="exportarExcel()" class="btn-excel">
                    <i class="fas fa-file-excel"></i> Descargar Excel
                </button>
                <button onclick="generarReportePDF()" class="btn-pdf" id="btnExportar">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </button>
                <a href="generador_adm.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver</a>
            </div>
        </div>

        <!-- DASHBOARD VISUAL DE HERRAMIENTAS (HOY) -->
        <div class="panel-metricas" id="contenido-a-exportar">
            <h2><i class="fas fa-tachometer-alt"></i> Velocímetros de Consumo (Hoy: <?= $total_hoy ?> peticiones globales)</h2>
            
            <div class="grid-indicadores">
                <?php 
                // Calculamos un "máximo" visual para que la barra se llene proporcionalmente
                // Asumimos que 50 usos en un día para una sola herramienta es el 100% del termómetro (Puedes ajustarlo)
                $meta_visual = 50; 

                foreach ($estadisticas_hoy as $herramienta => $cantidad): 
                    $porcentaje = min(($cantidad / $meta_visual) * 100, 100);
                    
                    // Lógica de colores del termómetro
                    $color_barra = "#3b82f6"; // Azul por defecto
                    if ($cantidad > 20) $color_barra = "#f59e0b"; // Naranja (Calentándose)
                    if ($cantidad > 40) $color_barra = "#ef4444"; // Rojo (Fuego/Muy usado)
                    if ($cantidad == 0) $color_barra = "#cbd5e1"; // Gris (Inactivo)
                ?>
                    <div class="medidor-card">
                        <h4 title="<?= htmlspecialchars($herramienta) ?>"><?= htmlspecialchars($herramienta) ?></h4>
                        <div class="medidor-valor">
                            <?= $cantidad ?> <span>usos</span>
                        </div>
                        <div class="progress-bg">
                            <div class="progress-fill" style="width: <?= $porcentaje ?>%; background-color: <?= $color_barra ?>;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TABLA HISTÓRICA (Base para Excel) -->
        <div class="table-container">
            <h2 style="margin-top: 0; color: var(--blue-corp); font-size: 1.3rem; margin-bottom: 20px;">
                <i class="fas fa-list"></i> Bitácora Cruda (Raw Data)
            </h2>
            
            <?php if (!empty($movimientos)): ?>
                <table id="tablaAuditoria">
                    <thead>
                        <tr>
                            <th>Fecha y Hora</th>
                            <th>ID Usuario</th>
                            <th>Maestro(a)</th>
                            <th>Email</th>
                            <th>Herramienta Utilizada</th>
                            <th>Dirección IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos as $mov): ?>
                        <tr>
                            <td class="date-text"><?= date('Y-m-d H:i:s', strtotime($mov['fecha'])) ?></td>
                            <td><?= $mov['id'] ?></td>
                            <td style="font-weight: 600; color: var(--blue-corp);"><?= htmlspecialchars($mov['nombre']) ?></td>
                            <td><?= htmlspecialchars($mov['email']) ?></td>
                            <td><span class="badge-tool"><?= htmlspecialchars($mov['herramienta']) ?></span></td>
                            <td>
                                <span class="ip-badge"><?= htmlspecialchars($mov['ip_usuario'] ?? 'Desconocida') ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #94a3b8;">
                    <i class="fas fa-database" style="font-size: 3rem; margin-bottom: 15px; color: #cbd5e1;"></i>
                    <p style="font-size: 1.1rem; font-weight: 600;">La base de datos está en blanco.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // ==========================================================
        // 1. EXPORTACIÓN PROFESIONAL A EXCEL (.XLSX)
        // ==========================================================
        function exportarExcel() {
            // Buscamos la tabla HTML
            var tabla = document.getElementById("tablaAuditoria");
            if(!tabla) {
                alert("No hay datos para exportar a Excel.");
                return;
            }

            // Convertimos la tabla HTML a un libro de trabajo de SheetJS
            var workbook = XLSX.utils.table_to_book(tabla, {sheet: "Auditoría SMC"});
            
            // Ajustamos el ancho de las columnas para que se vea profesional en Excel
            var hoja = workbook.Sheets["Auditoría SMC"];
            if (hoja['!ref']) {
                hoja['!cols'] = [
                    { wch: 20 }, // Fecha
                    { wch: 10 }, // ID
                    { wch: 30 }, // Maestro
                    { wch: 30 }, // Email
                    { wch: 35 }, // Herramienta
                    { wch: 15 }  // IP
                ];
            }

            // Nombre del archivo dinámico con la fecha de hoy
            let dateObj = new Date();
            let fechaStr = dateObj.getFullYear() + "-" + (dateObj.getMonth()+1) + "-" + dateObj.getDate();
            
            // Descargamos el archivo
            XLSX.writeFile(workbook, "Auditoria_SMC_" + fechaStr + ".xlsx");
        }

        // ==========================================================
        // 2. EXPORTACIÓN A PDF (Solo del Dashboard)
        // ==========================================================
        function generarReportePDF() {
            const boton = document.getElementById('btnExportar');
            const contenido = document.getElementById('contenido-a-exportar'); // Solo exporta los termómetros visuales
            
            const textoOriginal = boton.innerHTML;
            boton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando PDF...';
            boton.disabled = true;

            const opciones = {
                margin:       10,
                filename:     'Reporte_Dashboard_SMC.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, backgroundColor: '#f1f5f9' },
                jsPDF:        { unit: 'mm', format: 'letter', orientation: 'landscape' }
            };

            html2pdf().set(opciones).from(contenido).save().then(() => {
                boton.innerHTML = textoOriginal;
                boton.disabled = false;
            });
        }
    </script>

</body>
</html>