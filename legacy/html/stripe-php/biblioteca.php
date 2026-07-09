<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Conexión a la Base de Datos ---
$host = 'localhost';
// ... el resto de tu código sigue igual ...
// --- Conexión a la Base de Datos ---
$host = 'localhost';
$dbname = 'causa_db';
$user = 'root';
$pass = '';
$recursos = []; // Inicializar array para evitar errores si la conexión falla

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- Obtener todos los recursos de la BD ---
    $stmt = $pdo->query("SELECT * FROM recursos ORDER BY fecha_subida DESC");
    $recursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // En caso de error, puedes mostrar un mensaje o simplemente no mostrar recursos.
    $error_message = "Error al conectar con la base de datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biblioteca Docente - Docentes con Causa</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --color-primario: #2c3e50;
            --color-acento: #e67e22;
            --color-acento-hover: #d35400;
            --color-fondo: #f4f7f9;
            --color-tarjeta: #ffffff;
            --color-borde: #dfe6e9;
            --color-texto-secundario: #636e72;
            --fuente-titulos: 'Montserrat', sans-serif;
            --fuente-cuerpo: 'Lato', sans-serif;
        }
        body{font-family:var(--fuente-cuerpo);background-color:var(--color-fondo);margin:0;color:var(--color-texto);line-height:1.6}.container{max-width:1400px;margin:0 auto;padding:30px}.page-header{text-align:center;margin-bottom:50px}.page-header h1{font-family:var(--fuente-titulos);font-size:2.8rem;color:var(--color-primario);margin:0 0 10px 0}.page-header p{font-size:1.15rem;color:var(--color-texto-secundario);max-width:600px;margin:0 auto}.main-layout{display:grid;grid-template-columns:350px 1fr;gap:40px}.upload-section{background-color:var(--color-tarjeta);padding:30px;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,0.05);height:fit-content}.upload-section h2{font-family:var(--fuente-titulos);font-size:1.5rem;color:var(--color-primario);margin-top:0;margin-bottom:25px;border-bottom:2px solid var(--color-borde);padding-bottom:15px}.form-group{margin-bottom:20px}.form-group label{display:block;font-weight:700;margin-bottom:8px;font-size:0.9rem}.form-group input,.form-group select,.form-group textarea{width:100%;padding:12px;border:1px solid var(--color-borde);border-radius:8px;font-family:var(--fuente-cuerpo);font-size:1rem;box-sizing:border-box}.form-group textarea{resize:vertical;min-height:100px}.form-group input[type=file]{padding:8px}.btn-submit{width:100%;padding:15px;background-color:var(--color-acento);color:white;border:none;border-radius:8px;font-size:1.1rem;font-weight:700;cursor:pointer;transition:background-color .3s}.btn-submit:hover{background-color:var(--color-acento-hover)}.library-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;flex-wrap:wrap;gap:20px}.library-header h2{font-family:var(--fuente-titulos);font-size:1.5rem;color:var(--color-primario);margin:0}.filters{display:flex;gap:15px}.filters input,.filters select{padding:10px;border:1px solid var(--color-borde);border-radius:8px;font-family:var(--fuente-cuerpo)}.resource-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:30px}.resource-card{background-color:var(--color-tarjeta);border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,0.05);display:flex;flex-direction:column;transition:transform .3s}.resource-card:hover{transform:translateY(-5px)}.card-header{display:flex;align-items:flex-start;padding:20px}.pdf-icon{font-size:2.5rem;margin-right:15px;color:#e74c3c}.card-info{flex-grow:1}.card-title{font-family:var(--fuente-titulos);font-size:1.2rem;margin:0 0 5px 0;color:var(--color-primario)}.category-tag{display:inline-block;background-color:#ecf0f1;color:var(--color-texto-secundario);padding:3px 10px;border-radius:20px;font-size:0.8rem;font-weight:700}.author-info{font-size:0.9rem;color:var(--color-texto-secundario);margin-top:15px}.card-footer{padding:20px;margin-top:auto;border-top:1px solid var(--color-borde)}.btn-download{display:block;width:100%;text-align:center;background-color:var(--color-primario);color:white;text-decoration:none;padding:12px;border-radius:8px;font-weight:700;transition:background-color .3s}.btn-download:hover{background-color:#34495e}
        
        /* --- MODIFICACIÓN AQUÍ --- */
        .return-section{
            text-align:center; /* Centra el contenido de la sección */
            margin-top:50px
        }
        
        .btn-return{display:inline-flex;align-items:center;gap:8px;background-color:var(--color-primario);color:white;padding:12px 25px;border-radius:50px;text-decoration:none;font-weight:600;transition:background-color .3s,transform .2s}.btn-return:hover{background-color:#1a2c3d;transform:translateY(-2px)}footer{background-color:var(--color-primario);color:#e0e7ff;text-align:center;padding:25px 20px;margin-top:40px}@media (max-width:992px){.main-layout{grid-template-columns:1fr}}@media (max-width:576px){.page-header h1{font-size:2.2rem}.filters{flex-direction:column;width:100%}}
    </style>
</head>
<body>

    <div class="container">
        <header class="page-header">
            <h1>Biblioteca Docente</h1>
            <p>Comparte y descarga materiales creados por la comunidad educativa para enriquecer tu práctica.</p>
        </header>

        <main class="main-layout">
            
            <aside class="upload-section">
                <h2>Aporta a la Comunidad</h2>
                <form action="subir_recurso.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="titulo">Título del Recurso</label>
                        <input type="text" id="titulo" name="titulo" required>
                    </div>
                    <div class="form-group">
                        <label for="descripcion">Descripción Breve</label>
                        <textarea id="descripcion" name="descripcion" rows="4" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="categoria">Categoría</label>
                        <select id="categoria" name="categoria" required>
                            <option value="">-- Elige una categoría --</option>
                            <option value="planeaciones">Planeaciones</option>
                            <option value="examenes">Exámenes</option>
                            <option value="juegos">Juegos Didácticos</option>
                            <option value="guias">Guías NEM</option>
                            <option value="material">Material de Apoyo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="archivo">Selecciona tu archivo (PDF)</label>
                        <input type="file" id="archivo" name="archivo" accept=".pdf" required>
                    </div>
                    <button type="submit" class="btn-submit">Subir Recurso</button>
                </form>
            </aside>

            <section class="library-section">
                <div class="library-header">
                    <h2>Explora los Recursos</h2>
                    <div class="filters">
                        <input type="search" placeholder="Buscar por palabra clave...">
                        <select id="filtro-categoria" name="filtro-categoria">
                            <option value="">Todas las categorías</option>
                            </select>
                    </div>
                </div>

                <div class="resource-grid">
                    
                    <?php if (isset($error_message)): ?>
                        <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
                    <?php elseif (empty($recursos)): ?>
                        <p>Aún no hay recursos en la biblioteca. ¡Sé el primero en aportar!</p>
                    <?php else: ?>
                        <?php foreach ($recursos as $recurso): ?>
                            <article class="resource-card">
                                <div class="card-header">
                                    <div class="pdf-icon">📄</div>
                                    <div class="card-info">
                                        <h3 class="card-title"><?php echo htmlspecialchars($recurso['titulo']); ?></h3>
                                        <span class="category-tag"><?php echo htmlspecialchars($recurso['categoria']); ?></span>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <p class="author-info">Subido el: <?php echo date('d/m/Y', strtotime($recurso['fecha_subida'])); ?></p>
                                    <a href="<?php echo htmlspecialchars($recurso['ruta_archivo']); ?>" class="btn-download" download>
                                        Ver / Descargar
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </section>
        </main>
        
            <section class="return-section">
                <a href="index.php" class="btn-return">
                <span>⬅️</span>
                <span>Regresar a la Página Principal</span>
                </a>
            </section>
    </div>
    <footer>
        <p>&copy; 2025 Planeando con Causa | Todos los derechos reservados</p>
    </footer>
</body>
</html>