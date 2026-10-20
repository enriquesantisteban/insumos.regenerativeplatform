<?php
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/db_connection.php';

function tableHasColumn($mysqli, $table, $column) {                 // comprueba si la tabla tiene una columna específica
    $t = $mysqli->real_escape_string($table);                       // limpia el nombre de la tabla para evitar inyecciones SQL
    $c = $mysqli->real_escape_string($column);                      // limpia el nombre de la columna para evitar inyecciones SQL   
    $r = $mysqli->query("SHOW COLUMNS FROM `$t` LIKE '$c'");        // ejecuta la consulta para obtener las columnas de la tabla y verifica si existe la columna especificada
    return $r && $r->num_rows > 0;                                  // devuelve true si la columna existe, false si no
}

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';           // Valida el parámetro 'slug'
if ($slug === '') { header('Location: ' . __url('index')); exit; }          // Redirige a la página principal si el 'slug' no es válido

$imageColumnExists = tableHasColumn($mysqli, 'productos', 'imagen');    // Verifica si existe una columna de imagen en la tabla productos

// Fetch product
$detailSql = 'SELECT p.id, p.nombre, p.slug, p.descripcion, p.tipo, p.subtipo, p.sumatorio, p.recuento';
if ($imageColumnExists) { $detailSql .= ', p.imagen'; }
$detailSql .= ', f.id AS fabricante_id, f.nombre AS fabricante_nombre, f.slug AS fabricante_slug
     FROM productos p
     JOIN fabricantes f ON p.fabricante_id = f.id AND UPPER(f.activo) = \'S\'
     WHERE p.slug = ? AND UPPER(p.activo) = \'S\'';

$stmt = $mysqli->prepare($detailSql);                                   // Prepara la consulta SQL para obtener los detalles del producto
if (!$stmt) { echo 'Error en la consulta del producto: ' . $mysqli->error; exit; }   // Si la preparación falla, muestra un mensaje de error y termina la ejecución
$stmt->bind_param('s', $slug);                                          // Vincula el parámetro 'slug' a la consulta
$stmt->execute();                                                       // Ejecuta la consulta
$producto = $stmt->get_result()->fetch_assoc();                         // Obtiene el resultado de la consulta como un array asociativo
if (!$producto) { echo 'Producto no encontrado.'; exit; }               // Si no se encuentra el producto, muestra un mensaje de error y termina la ejecución

// Fetch especificaciones
$especificaciones = [];                                                 // Inicializa la variable para almacenar las especificaciones
$espStmt = $mysqli->prepare('SELECT id, icono, titulo, descripcion FROM producto_especificaciones WHERE producto_id = ? ORDER BY id');   // Prepara la consulta para obtener las especificaciones del producto
if ($espStmt) {                                                           // Verifica si la consulta se pudo preparar
    $espStmt->bind_param('i', $producto['id']);                           // Vincula el parámetro 'id' a la consulta
    $espStmt->execute();                                                // Ejecuta la consulta
    $resEsp = $espStmt->get_result();                                    // Obtiene el resultado de la consulta como un objeto
    while ($row = $resEsp->fetch_assoc()) { $especificaciones[] = $row; }   // Itera sobre los resultados de la consulta y agrega cada fila a la variable
}

// Fetch other products by same fabricante
$otherSql = 'SELECT id, nombre, slug, descripcion, tipo';
if ($imageColumnExists) { $otherSql .= ', imagen'; }                   // Si la columna de imagen existe, agrega la columna a la consulta
$otherSql .= ' FROM productos WHERE id <> ? AND fabricante_id = ? AND UPPER(activo) = \'S\' ORDER BY nombre';     // Agrega la consulta paara obtener los productos diferenets a este
$otherStmt = $mysqli->prepare($otherSql);                               // Prepara la consulta para obtener los productos diferentes
$otherProducts = false;                                                  // Inicializa la variable para almacenar los productos diferentes
if ($otherStmt) {                                                       // Verifica si la consulta se pudo preparar
    $otherStmt->bind_param('ii', $producto['id'], $producto['fabricante_id']);   // Vincula los parámetros 'id' y 'fabricante_id' a la consulta
    $otherStmt->execute();                                               // Ejecuta la consulta
    $otherProducts = $otherStmt->get_result();                            
}


// Fetch microorganismos + chart data
$microorganismos  = [];                                                  // Inicializa la variable para almacenar los microorganismos
$chartLabels      = [];                                                  // Inicializa la variable para almacenar las etiquetas del gráfico
$chartValues      = [];                                                  // Inicializa la variable para almacenar los valores del gráfico
$chartDetails     = [];                                                  // Inicializa la variable para almacenar los detalles del gráfico
$microorganismData = [];                                                 // Inicializa la variable para almacenar los datos del gráfico

$microStmt = $mysqli->prepare(                                           // Prepara la consulta para obtener los microorganismos del producto
    'SELECT m.id, m.nombre_bicho, m.descripcion_bicho, m.importancia_beneficios, m.imagen_bicho_src, m.condiciones_campo, pmr.valor_especifico  
     FROM producto_microorganismos_relacion pmr
     JOIN microorganismos m ON pmr.microorganismo_id = m.id
     WHERE pmr.producto_id = ?  
     ORDER BY pmr.id'
);
if ($microStmt) {                                                        // Verifica si la consulta se pudo preparar
    $microStmt->bind_param('i', $producto['id']);                         // Vincula el parámetro 'id' a la consulta
    $microStmt->execute();                                               // Ejecuta la consulta
    $resMicro = $microStmt->get_result();                                // Obtiene el resultado de la consulta como un objeto
    while ($row = $resMicro->fetch_assoc()) {                             // Itera sobre los resultados de la consulta y agrega cada fila a la variable
        // Traduce los campos del microorganismo segun el idioma activo (usa el texto original en español si no hay traduccion)
        $descBicho    = __tdb($mysqli, 'microorganismos', $row['id'], 'descripcion_bicho', $row['descripcion_bicho']);
        $importancia  = __tdb($mysqli, 'microorganismos', $row['id'], 'importancia_beneficios', $row['importancia_beneficios']);
        $condiciones  = __tdb($mysqli, 'microorganismos', $row['id'], 'condiciones_campo', $row['condiciones_campo']);
        $imagenBicho  = __tdb($mysqli, 'microorganismos', $row['id'], 'imagen_bicho_src', $row['imagen_bicho_src']); // NUEVO


        $row['descripcion_bicho']     = $descBicho;
        $row['importancia_beneficios']= $importancia;
        $row['condiciones_campo']     = $condiciones;
        $row['imagen_bicho_src']      = $imagenBicho; 


        $microorganismos[] = $row;                                       // Agrega la fila (ya traducida) a la variable
        $microorganismData[$row['nombre_bicho']] = [                      // Agrega la información del microorganismo a la variable
            'id'                   => $row['id'],                         // Agrega el ID del microorganismo
            'nombre_bicho'         => $row['nombre_bicho'],               // Agrega el nombre del microorganismo
            'descripcion_bicho'    => $descBicho,                         // Agrega la descripción del microorganismo (traducida)
            'importancia_beneficios'=> $importancia,                      // Agrega la importancia y beneficios del microorganismo (traducida)
            'imagen_bicho_src'     => __asset_url($imagenBicho),     // Agrega la URL de la imagen del microorganismo (ya resuelta)
            'condiciones_campo'    => $condiciones,                       // Agrega las condiciones en campo del microorganismo (traducida)
            'valor_especifico'     => isset($row['valor_especifico']) ? (int)$row['valor_especifico'] : 0,   // Agrega el valor especifico del microorganismo
        ];
    }
}

foreach ($microorganismos as $micro) {                                    // Itera sobre los microorganismos y agrega las etiquetas y valores al gráfico
    $chartLabels[]  = $micro['nombre_bicho'];                             // Agrega la etiqueta al gráfico
    $chartValues[]  = isset($micro['valor_especifico']) ? (int)$micro['valor_especifico'] : 0;   // Agrega el valor al gráfico
    $chartDetails[$micro['nombre_bicho']] = !empty($micro['descripcion_bicho'])                // Agrega la descripción (ya traducida) al gráfico
        ? $micro['descripcion_bicho']                                         // Si la descripción no es vacía, agrega la descripción
        : (!empty($micro['importancia_beneficios']) ? $micro['importancia_beneficios'] : 'Información detallada no disponible.');   // Si la descripción es vacía y la importancia y beneficios no son vacíos, agrega la importancia y beneficios
}

$tipoText = !empty($producto['tipo'])
    ? __tipo_label($producto['tipo'])
    : '';
$subtipoText = __subtipo_label($producto['subtipo'] ?? '');

$heroImage    = $imageColumnExists ? ($producto['imagen'] ?? null) : null;                  // Obtiene la imagen del producto
$hasProductData = !empty($producto['descripcion'])
    || !empty($producto['tipo'])
    || !empty($producto['subtipo'])
    || !empty($heroImage)
    || !empty($especificaciones)
    || !empty($microorganismos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpg" href="<?php echo BASE_PATH; ?>/media/favicon.png">
    <title><?php echo htmlspecialchars($producto['nombre']); ?> | Regenerative Agro Platform</title>
    <meta name="description" content="Ficha tecnica de <?php echo htmlspecialchars($producto['nombre']); ?>. Ensayos de inocuidad sobre microorganismos beneficiosos.">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<?php require_once __DIR__ . '/nav.php'; ?>


<!-- ====== HERO ====== -->
<section class="hero-section">
    <div class="hero-orb" style="width:460px;height:460px;top:8%;left:8%;background:rgba(35,166,213,0.13);"></div>
    <div class="hero-orb" style="width:380px;height:380px;bottom:8%;right:8%;background:rgba(231,60,126,0.11);"></div>

    <div class="hero-content hero-product" style="position:relative;z-index:2;">
        <div class="hero-text">
            <a href="<?php echo htmlspecialchars(!empty($producto['fabricante_slug'])
                ? __url('fabricante', ['slug' => $producto['fabricante_slug']])
                : __url('fabricante', ['id' => (int)$producto['fabricante_id']])); ?>" class="hero-eyebrow" style="text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem;">    <!-- Enlace al fabricante -->
                <i class="fas fa-arrow-left" style="font-size:0.75rem;"></i>    <!-- Icono de flecha izquierda -->
                <?php echo htmlspecialchars($producto['fabricante_nombre']); ?>    <!-- Nombre del fabricante -->
            </a>

            <h1 class="hero-title" style="margin-top:0.75rem;">     <!-- Título del producto -->
                <?php echo htmlspecialchars($producto['nombre']); ?>    <!-- Nombre del producto -->
            </h1>

            <p class="hero-desc" style="max-width:520px;margin-left:0;margin-bottom:1.5rem;">     <!-- Descripción del producto -->
                <?php echo !empty($producto['descripcion'])     
                    ? nl2br(htmlspecialchars(__tdb($mysqli, 'productos', $producto['id'], 'descripcion', $producto['descripcion'])))      // Si la descripción del producto no es vacía, muestra la descripción (traducida) en un párrafo
                    : __t('producto.desc_fallback', 'Descripción del producto no disponible.'); ?>         <!--Si la descripción del producto es vacía, muestra un mensaje de texto alternativo -->
            </p>

            <div class="hero-image-mobile">
                <?php if ($heroImage): ?>
                    <img src="<?php echo htmlspecialchars(__asset_url($heroImage)); ?>"
                         alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
                <?php else: ?>
                    <div style="width:220px;height:220px;border-radius:32px;background:rgba(255,255,255,0.15);backdrop-filter:blur(10px);display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-flask" style="font-size:5rem;color:rgba(255,255,255,0.5);"></i>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$hasProductData): ?>
                <p class="product-empty-notice">
                    <i class="fas fa-circle-info" aria-hidden="true"></i>
                    <?php echo __t('producto.no_data_desc', 'No hay datos disponibles de este producto.'); ?>
                </p>
            <?php endif; ?>

            <div class="hero-meta">     <!-- Contenedor para los metadatos -->
                <?php if ($tipoText !== ''): ?>
                <div class="hero-meta-row">     <!-- Contenedor para la fila de metadatos -->
                    <span class="hero-meta-label"><?php echo __t('producto.tipo', 'Tipo'); ?></span>
                    <span class="badge"><?php echo htmlspecialchars($tipoText); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($subtipoText !== ''): ?>
                <div class="hero-meta-row">     <!-- Contenedor para la fila de metadatos -->
                    <span class="hero-meta-label"><?php echo __t('producto.subtipo', 'Subtipo'); ?></span>
                    <span class="badge"><?php echo htmlspecialchars($subtipoText); ?></span>
                </div>
                <?php endif; ?>
                <div class="hero-meta-row">
                    <span class="hero-meta-label"><?php echo __t('producto.fabricante', 'Fabricante'); ?></span>     <!-- Etiqueta de fabricante -->
                    <span class="badge last"><?php echo htmlspecialchars($producto['fabricante_nombre']); ?></span>     <!-- Valor de fabricante -->
                </div>
            </div>

            <div class="hero-ctas">     <!-- Contenedor para las llamadas a acción -->
                <a href="#grafica" class="btn-hero btn-hero-primary"><?php echo __t('producto.ver_grafica', 'Ver gráfica'); ?>    <!-- Botón que redirige a la sección de gráfica -->
                   <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>    <!-- Botón  de gráfica -->
                </a>

                <a href="#products" class="btn-hero btn-hero-secondary"><?php echo __t('producto.otros_productos', 'Otros productos'); ?></a>
            </div>
        </div>

        <div class="hero-image">
            <?php if ($heroImage): ?>                <!-- Comprueba si hay una imagen disponible para el producto -->
                <img src="<?php echo htmlspecialchars(__asset_url($heroImage)); ?>"  
                     alt="<?php echo htmlspecialchars($producto['nombre']); ?>">    
            <?php else: ?>
                <div style="width:220px;height:220px;border-radius:32px;background:rgba(255,255,255,0.15);backdrop-filter:blur(10px);display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-flask" style="font-size:5rem;color:rgba(255,255,255,0.5);"></i>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ====== GRÁFICA DE MICROORGANISMOS ====== -->
<section class="graph-section" id="grafica">
    <div class="section-inner">
        <div class="section-header">
            <span class="eyebrow" style="background:#eff6ff;color:#2563eb;"><?php echo __t('producto.inocuidad_in_vitro', 'Inocuidad in vitro'); ?></span>
            <h2 class="section-title" style="margin-bottom:0.5rem;"><?php echo __t('producto.grafica_microorganismos', 'Gráfica de microorganismos'); ?></h2>
            <p style="color:#64748b;max-width:720px;margin:0.75rem auto 0;font-size:0.875rem;line-height:1.6;"><?php echo __t('producto.grafica_texto_descripcion', 'El índice In de inocuidad relativa 
            constituye un valor comparativo entre el crecimiento de cada microorganismo con el insumo, respecto del crecimiento con el testigo inocuo (agua, valor 1). Los valores de crecimiento se han 
            evaluado mediante ensayo in vitro, y el In mediante formula, ambos según el procedimiento MBG-EHB-01.'); ?></p>
            <p style="color:#94a3b8;font-size:0.82rem;margin-top:0.75rem;">
                <i class="fas fa-thin fa-arrow-down" aria-hidden="true"></i><?php echo __t('producto.grafica_pulsa_sobre', 'Pulsa sobre el nombre de un microorganismo para ver su ficha completa.'); ?> <i class="fas fa-thin fa-arrow-down" aria-hidden="true"></i>
            </p>
        </div>

        <?php if (!empty($microorganismos)): ?>
        <div class="chart-wrapper">
            <canvas id="radarChart" aria-label="Gráfica radar de inocuidad"></canvas>                 <!-- Canvas para el gráfico radar -->

            <!-- Popup -->
            <div id="chart-popup-overlay" class="chart-popup-overlay">      
                <div id="chart-popup" class="chart-popup-modal">        
                    <button id="chart-popup-close" class="chart-popup-close" type="button" aria-label="Cerrar información">&#215;</button>
                    <div id="popup-image"></div>    
                    <h4 id="popup-title"></h4>
                    <p id="popup-desc"></p>

                    <!-- Importancia y Beneficios: contraido por defecto -->
                    <div id="popup-benefits" class="popup-collapsible" style="display:none;">
                        <span class="popup-collapsible-title"><?php echo __t('producto.popup_beneficios', 'Importancia y Beneficios'); ?></span>
                        <div class="popup-collapsible-body" id="popup-benefits-body"></div>
                        <button type="button" class="popup-toggle-btn" data-target="popup-benefits-body">
                            <span class="toggle-label"><?php echo __t('producto.popup_ver_mas', 'Ver más'); ?></span> <i class="fas fa-chevron-down" aria-hidden="true"></i>
                        </button>
                    </div>

                    <!-- Condiciones en Campo: contraido por defecto -->
                    <div id="popup-conditions" class="popup-collapsible" style="display:none;">
                        <span class="popup-collapsible-title"><?php echo __t('producto.popup_condiciones', 'Condiciones en Campo'); ?></span>
                        <div class="popup-collapsible-body" id="popup-conditions-body"></div>
                        <button type="button" class="popup-toggle-btn" data-target="popup-conditions-body">
                            <span class="toggle-label"><?php echo __t('producto.popup_ver_mas', 'Ver más'); ?></span> <i class="fas fa-chevron-down" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Lightbox -->
            <div id="image-lightbox-overlay" class="image-lightbox-overlay" aria-hidden="true">        <!-- Contenedor para la ventana de imagen ampliada -->
                <button id="image-lightbox-close" class="image-lightbox-close" type="button" aria-label="Cerrar imagen ampliada">&#215;</button>            
                <img id="image-lightbox-img" alt="Imagen ampliada">     <!-- Imagen ampliada -->
            </div>
        </div>
        <div class="chart-summary">
            <p class="chart-summary-item">
                <strong><?php echo __t('producto.sumatorio_in', 'Sumatorio In*'); ?></strong>
                <span class="chart-summary-equals">=</span>
                <strong class="chart-summary-value"><?php echo htmlspecialchars((string)$producto['sumatorio']); ?></strong>
            </p>
            <p class="chart-summary-item">
                <strong><?php echo __t('producto.recuento_in', 'Recuento de In ≥ 0,5*'); ?></strong>
                <span class="chart-summary-equals">=</span>
                <strong class="chart-summary-value"><?php echo htmlspecialchars((string)$producto['recuento']); ?></strong>
            </p>
            <p class="chart-summary-note"><?php echo __t('producto.sumatorio_nota', '*Sumatorio de In en base a 10.'); ?></p>
        </div>
        <?php else: ?>
            <div class="empty product-data-empty">
                <i class="fas fa-chart-line" aria-hidden="true"></i>
                <h3><?php echo __t('producto.grafica_empty_title', 'No hay datos de este producto'); ?></h3>
                <p><?php echo __t('producto.grafica_empty_desc', 'Todavía no se han cargado datos para generar la gráfica.'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ====== ESPECIFICACIONES ====== -->
<section class="info-section" id="especificaciones">                         <!-- Sección de especificaciones -->   
    <div class="section-inner">                                               <!-- Contenedor para el contenido de la sección -->
        <h2 class="section-title" style="color:#fff;"><?php echo __t('producto.especificaciones', 'Especificaciones clave'); ?></h2>    <!-- Título de la sección -->

        <?php if (!empty($especificaciones)): ?>                                 <!-- Comprueba si hay especificaciones registradas -->
            <div class="info-grid">                                             <!-- Contenedor para la cuadrícula de especificaciones -->
                <?php foreach ($especificaciones as $esp): ?>                   <!-- Itera sobre las especificaciones y las muestra en una tarjeta -->
                    <div class="info-card">                                     <!-- Contenedor para la tarjeta de especificación -->
                        <?php if (!empty($esp['icono']) && preg_match('/\b(fa|fas|far|fal|fab)\b/', $esp['icono'])): ?>   <!-- Comprueba si hay un icono de la especificación -->
                            <i class="<?php echo htmlspecialchars($esp['icono']); ?>" aria-hidden="true"></i>    <!-- Muestra el icono de la especificación -->
                        <?php elseif (!empty($esp['icono'])): ?>                  <!-- Si no hay icono de la especificación, muestra el texto de la especificación -->
                            <img src="<?php echo htmlspecialchars(__asset_url($esp['icono'])); ?>"                                  
                                 alt="<?php echo htmlspecialchars($esp['titulo'] ?: 'Icono'); ?>"   
                                 style="width:48px;height:48px;object-fit:contain;margin-bottom:1rem;">     <!-- Muestra el icono de la especificación -->
                        <?php else: ?>
                            <i class="fas fa-seedling" aria-hidden="true"></i>      <!-- Muestra un icono de semillas -->
                        <?php endif; ?>
                        <h3><?php echo htmlspecialchars(!empty($esp['titulo']) ? __tdb($mysqli, 'producto_especificaciones', $esp['id'], 'titulo', $esp['titulo']) : 'Especificación'); ?></h3>    <!-- Título de la especificación (traducido) -->
                        <p><?php echo nl2br(htmlspecialchars(!empty($esp['descripcion']) ? __tdb($mysqli, 'producto_especificaciones', $esp['id'], 'descripcion', $esp['descripcion']) : 'Sin descripción disponible.')); ?></p>    <!-- Descripción de la especificación (traducida), sino se muestra Sin descripción disponible. -->
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="info-grid">
                <div class="info-card">
                    <i class="fas fa-seedling" aria-hidden="true"></i>
                    <h3><?php echo __t('index.info_card_1_title', 'Restauración del suelo como base productiva'); ?></h3>
                    <p><?php echo __t('index.info_card_1_desc', 'La agricultura regenerativa prioriza la regeneración de la fertilidad del suelo mediante prácticas como el uso de compost, abonos verdes, estiércol y cobertura vegetal permanente. Estas acciones mejoran la estructura del suelo, aumentan su capacidad de retención de agua y estimulan la actividad microbiana, lo que permite una producción más resiliente y sostenible.'); ?></p>
                </div>
                <div class="info-card">
                    <i class="fas fa-apple-whole" aria-hidden="true"></i>
                    <h3><?php echo __t('index.info_card_2_title', 'Diversificación y rotación de cultivos'); ?></h3>
                    <p><?php echo __t('index.info_card_2_desc', 'Este modelo promueve la alternancia de especies vegetales para evitar el agotamiento de nutrientes, reducir la presión de plagas y enfermedades, y mejorar la biodiversidad. La rotación de cultivos y la inclusión de especies complementarias imitan los procesos ecológicos naturales, fortaleciendo el equilibrio del agroecosistema.'); ?></p>
                </div>
                <div class="info-card">
                    <i class="fas fa-water" aria-hidden="true"></i>
                    <h3><?php echo __t('index.info_card_3_title', 'Reducción de insumos químicos'); ?></h3>
                    <p><?php echo __t('index.info_card_3_desc', 'La agricultura regenerativa busca minimizar el uso de fertilizantes sintéticos y pesticidas, favoreciendo el control biológico y la autosuficiencia agrícola. Además, incorpora técnicas de riego eficiente, captación de agua de lluvia y conservación de humedad, esenciales para enfrentar el cambio climático y preservar los recursos hídricos.'); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
        

<!-- ====== OTROS PRODUCTOS ====== -->
<section class="section-pad" id="products" style="background:#f8fafc;">                 <!-- Sección de otros productos -->
    <div class="section-inner">                                                       <!-- Contenedor para el contenido de la sección -->
        <div class="section-header">                                                 <!-- Contenedor para la cabecera de la sección -->
            <h2 class="section-title">                                                <!-- Título de la sección --> 
                <?php echo __t('producto.otros_productos', 'Otros productos de'); ?> <span class="gradient-text"><?php echo htmlspecialchars($producto['fabricante_nombre']); ?></span>
            </h2>
        </div>

        <?php if ($otherProducts && $otherProducts->num_rows > 0): ?>                   <!-- Comprueba si hay productos registrados -->
            <div class="carousel-wrapper">                                             <!-- Contenedor para el carrusel de productos -->
                <div class="carousel-viewport">                                       <!-- Contenedor para el contenido del carrusel -->
                    <div class="carousel-track" id="otherProductsTrack">               <!-- Contenedor para el contenido del carrusel -->
                        <?php while ($other = $otherProducts->fetch_assoc()):   
                            $otherImg  = $imageColumnExists ? ($other['imagen'] ?? null) : null;    // Obtiene la URL de la imagen del producto si existe, de lo contrario, será null
                            
                            $otherTipo = !empty($other['tipo'])
                                ? __tipo_label($other['tipo'])
                                : '';
                        ?>
                            <article class="product-card">                             <!-- Contenedor para el producto -->
                                <div class="card-img">                                   <!-- Contenedor para la imagen del producto -->
                                    <?php if ($otherImg): ?>                             <!-- Comprueba si hay una imagen disponible para el producto -->
                                        <img src="<?php echo htmlspecialchars(__asset_url($otherImg)); ?>"       
                                             alt="<?php echo htmlspecialchars($other['nombre']); ?>">    <!-- Muestra la imagen del producto -->
                                    <?php else: ?>
                                        <i class="fas fa-flask" style="font-size:2.5rem;color:#cbd5e1;"></i>    <!-- Muestra un icono de flask -->
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">                                   <!-- Contenedor para el contenido del producto -->
                                    <h3><?php echo htmlspecialchars($other['nombre']); ?></h3>    <!-- Título del producto -->
                                    <p><?php echo htmlspecialchars($other['descripcion'] ? __tdb($mysqli, 'productos', $other['id'], 'descripcion', $other['descripcion']) : 'Descripcion no disponible.'); ?></p>    <!-- Descripción del producto (traducida) -->
                                    <?php if ($otherTipo !== ''): ?>
                                        <span class="price"><?php echo htmlspecialchars($otherTipo); ?></span>
                                    <?php endif; ?>
                                    <a href="<?php echo htmlspecialchars(__url('producto', ['slug' => $other['slug']])); ?>" class="btn btn-full">    <!-- Enlace al producto -->
                                        <?php echo __t('producto.ver_ficha', 'Ver ficha'); ?> <i class="fas fa-arrow-right" aria-hidden="true" style="font-size:0.75rem;margin-left:4px;"></i>    <!-- Icono de flecha derecha -->
                                    </a>
                                </div>
                            </article>
                        <?php endwhile; ?>
                    </div>
                </div>
                <div class="carousel-dots" id="carouselDots"></div>                   <!-- Contenedor para los puntos de control del carrusel -->
            </div>
        <?php else: ?>
            <div class="empty">                                                     <!-- Contenedor para el mensaje de texto -->
                <i class="fas fa-box-open" style="font-size:3rem;color:#cbd5e1;display:block;margin-bottom:1rem;"></i>    <!-- Icono de caja abierta -->
                <h3><?php echo __t('producto.other_empty_title', 'Este es el unico producto'); ?></h3>
                <p><?php echo __t('producto.other_empty_desc', 'No hay otros productos registrados para este fabricante.'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ====== FOOTER ====== -->

<?php require_once __DIR__ . '/footer.php'; ?>


<!-- ====== CAROUSEL JS ====== -->
<script>
(function () {
    const track = document.getElementById('otherProductsTrack');
    const dotsContainer = document.getElementById('carouselDots');
    if (!track || !dotsContainer) return;

    const cards = Array.from(track.children);
    let currentPage = 0;

    function getCardsPerView() {
        const w = window.innerWidth;
        if (w <= 600) return 1;
        if (w <= 900) return 2;
        return 3;
    }

    function getTotalPages() {
        return Math.max(1, Math.ceil(cards.length / getCardsPerView()));
    }

    function renderDots() {
        const pages = getTotalPages();
        dotsContainer.innerHTML = '';
        for (let i = 0; i < pages; i++) {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'carousel-dot' + (i === currentPage ? ' active' : '');
            dot.setAttribute('aria-label', 'Ir a la pagina ' + (i + 1));
            dot.addEventListener('click', () => goToPage(i));
            dotsContainer.appendChild(dot);
        }
    }

    function goToPage(page) {
        const pages = getTotalPages();
        currentPage = Math.max(0, Math.min(page, pages - 1));
        const viewport = track.parentElement;
        const maxOffset = Math.max(0, track.scrollWidth - viewport.clientWidth);
        const cardsPerView = getCardsPerView();
        const cardWidth = cards[0].getBoundingClientRect().width;
        const trackStyles = window.getComputedStyle(track);
        const cardGap = parseFloat(trackStyles.columnGap || trackStyles.gap) || 0;
        const pageWidth = cardsPerView * (cardWidth + cardGap);
        const pageOffset = currentPage === pages - 1
            ? maxOffset
            : currentPage * pageWidth;
        const offset = Math.min(pageOffset, maxOffset);
        track.style.transform = `translateX(-${offset}px)`;
        renderDots();
    }

    window.addEventListener('resize', () => {
        if (currentPage >= getTotalPages()) currentPage = getTotalPages() - 1;
        goToPage(currentPage);
    });

    renderDots();
})();
</script>

<!-- ====== CHART JS ====== -->
<script>
(function () {
    const radarCanvas    = document.getElementById('radarChart');
    if (!radarCanvas) return;
    const ctx            = radarCanvas.getContext('2d');
    const popupOverlay   = document.getElementById('chart-popup-overlay');
    const popup          = document.getElementById('chart-popup');
    const popupTitle     = document.getElementById('popup-title');
    const popupDesc      = document.getElementById('popup-desc');
    const popupImage     = document.getElementById('popup-image');
    const popupBenefits  = document.getElementById('popup-benefits');
    const popupBenefitsBody   = document.getElementById('popup-benefits-body');
    const popupConditions     = document.getElementById('popup-conditions');
    const popupConditionsBody = document.getElementById('popup-conditions-body');
    const popupClose     = document.getElementById('chart-popup-close');
    const lightboxOverlay= document.getElementById('image-lightbox-overlay');
    const lightboxImage  = document.getElementById('image-lightbox-img');
    const lightboxClose  = document.getElementById('image-lightbox-close');

    const chartDetails     = <?php echo json_encode($chartDetails,     JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const microorganismData= <?php echo json_encode($microorganismData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    // Textos traducidos que necesita el JS (se sobrescriben dinámicamente, así que no basta con traducirlos en el HTML)
    const i18nVerMas   = <?php echo json_encode(__t('producto.popup_ver_mas', 'Ver más'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const i18nVerMenos = <?php echo json_encode(__t('producto.popup_ver_menos', 'Ver menos'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    // Alterna una sección contraible (Importancia y Beneficios / Condiciones en Campo)
    function toggleCollapsible(btn) {
        const body  = document.getElementById(btn.dataset.target);
        const label = btn.querySelector('.toggle-label');
        const isOpen = body.classList.toggle('open');
        btn.classList.toggle('open', isOpen);
        label.textContent = isOpen ? i18nVerMenos : i18nVerMas;
    }

    // Prepara una sección contraible: la cierra y le carga el contenido
    function setCollapsible(wrapperEl, bodyEl, btnEl, html) {
        if (!html) {
            wrapperEl.style.display = 'none';
            return;
        }
        wrapperEl.style.display = '';
        bodyEl.innerHTML = html;
        bodyEl.classList.remove('open');
        btnEl.classList.remove('open');
        btnEl.querySelector('.toggle-label').textContent = i18nVerMas;
    }

    document.querySelectorAll('.popup-toggle-btn').forEach((btn) => {
        btn.addEventListener('click', () => toggleCollapsible(btn));
    });

    function wrapChartLabel(label) {
        const words = String(label).trim().split(/\s+/).filter(Boolean);
        if (words.length <= 2) return words;

        const midpoint = Math.ceil(words.length / 2);
        return [words.slice(0, midpoint).join(' '), words.slice(midpoint).join(' ')];
    }

    const radarLabels = <?php echo json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const radarValues = <?php echo json_encode($chartValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const referenceValues = radarLabels.map(() => 100);
    const radarLabelCount = radarLabels.length;

    const radarChart = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: radarLabels,
            datasets: [
                {
                    label: '<?php echo addslashes($producto['nombre']); ?>',
                    data: radarValues,
                    fill: true,
                    backgroundColor: 'rgba(37, 99, 235, 0.2)',
                    borderColor: '#2563eb',
                    pointBackgroundColor: '#e73c7e',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#e73c7e',
                    pointRadius: 7,
                    pointHoverRadius: 10,
                    borderWidth: 3,
                    spanGaps: true
                },
                {
                    label: <?php echo json_encode(__t('producto.grafica_referencia', 'Inocuo (referencia)')); ?>,
                    data: referenceValues,
                    fill: true,
                    backgroundColor: 'rgba(148, 163, 184, 0.1)',
                    borderColor: '#94a3b8',
                    pointBackgroundColor: '#94a3b8',
                    pointBorderColor: '#fff',
                    pointRadius: 3,
                    borderDash: [5, 5],
                    spanGaps: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: 24
            },
            scales: {
                r: {
                    angleLines: { color: 'rgba(0,0,0,0.1)' },
                    grid:        { color: 'rgba(0,0,0,0.05)' },
                    pointLabels: {
                        font: (context) => ({
                            size: context.chart.width <= 480
                                ? (radarLabelCount > 8 ? 11 : 13)
                                : context.chart.width <= 768
                                    ? (radarLabelCount > 10 ? 12 : 14)
                                    : (radarLabelCount > 12 ? 14 : 16),
                            family: "'Segoe UI', sans-serif",
                            weight: '600'
                        }),
                        callback: (label) => wrapChartLabel(label),
                        color: '#334155',
                        padding: 28
                    },
                    ticks:       { display: false, backdropColor: 'transparent' },
                    suggestedMin: 0,
                    suggestedMax: 100
                }
            },
            plugins: {
                legend:  { position: 'bottom', align: 'center', labels: { font: { size: 14 }, padding: 22, boxWidth: 18, usePointStyle: true } },
                tooltip: { enabled: false }
            },
            onClick: (event) => {
                const canvasPosition = Chart.helpers.getRelativePosition(event, radarChart);
                const activePoints   = radarChart.getElementsAtEventForMode(event, 'nearest', { intersect: true }, true);
                const scale          = radarChart.scales.r;
                let clickedLabel     = null;

                if (activePoints.length > 0) {
                    clickedLabel = radarChart.data.labels[activePoints[0].index];
                } else {
                    const { x, y } = canvasPosition;
                    const dx = x - scale.xCenter;
                    const dy = y - scale.yCenter;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    if (distance >= scale.drawingArea * 0.7) {
                        const numLabels = radarChart.data.labels.length;
                        let angle = Math.atan2(dy, dx);
                        if (angle < 0) angle += 2 * Math.PI;
                        let closestIndex = 0, minDiff = Infinity;
                        for (let i = 0; i < numLabels; i++) {
                            let la = (i * 2 * Math.PI / numLabels) - Math.PI / 2;
                            if (la < 0) la += 2 * Math.PI;
                            let diff = Math.abs(angle - la);
                            if (diff > Math.PI) diff = 2 * Math.PI - diff;
                            if (diff < minDiff) { minDiff = diff; closestIndex = i; }
                        }
                        if (minDiff < 0.4) clickedLabel = radarChart.data.labels[closestIndex];
                    }
                }

                if (clickedLabel) {
                    const data = microorganismData[clickedLabel] || null;
                    popupTitle.textContent = data?.nombre_bicho || clickedLabel;
                    popupDesc.innerHTML    = data?.descripcion_bicho || chartDetails[clickedLabel] || 'Informacion no disponible.';

                    setCollapsible(
                        popupBenefits, popupBenefitsBody,
                        popupBenefits.querySelector('.popup-toggle-btn'),
                        data?.importancia_beneficios ? data.importancia_beneficios.replace(/\n/g, '<br>') : ''
                    );
                    setCollapsible(
                        popupConditions, popupConditionsBody,
                        popupConditions.querySelector('.popup-toggle-btn'),
                        data?.condiciones_campo ? data.condiciones_campo.replace(/\n/g, '<br>') : ''
                    );

                    const imgSrc  = data?.imagen_bicho_src || '';
                    const imgName = data?.nombre_bicho || clickedLabel;
                    popupImage.innerHTML = imgSrc
                        ? `<img src="${imgSrc}" alt="${imgName}" class="popup-img">`
                        : '';

                    const imgEl = popupImage.querySelector('.popup-img');
                    if (imgEl) {
                        imgEl.addEventListener('click', () => openLightbox(imgSrc, imgName));
                    }

                    popupOverlay.classList.add('open');
                } else {
                    closePopup();
                }
            },
            onHover: (event, elements) => {
                event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
            }
        }
    });

    function closePopup() { popupOverlay.classList.remove('open'); }

    function openLightbox(src, alt) {
        if (!src) return;
        lightboxImage.src = src;
        lightboxImage.alt = alt || 'Imagen ampliada';
        lightboxOverlay.classList.add('open');
        lightboxOverlay.setAttribute('aria-hidden', 'false');
    }

    function closeLightbox() {
        lightboxOverlay.classList.remove('open');
        lightboxOverlay.setAttribute('aria-hidden', 'true');
        lightboxImage.src = '';
    }

    popupClose.addEventListener('click', (e) => {
        e.stopPropagation();
        closePopup();
    });
    popupOverlay.addEventListener('click', (e) => { if (e.target === popupOverlay) closePopup(); });
    lightboxClose.addEventListener('click', (e) => {
        e.stopPropagation();
        closeLightbox();
    });
    lightboxOverlay.addEventListener('click', (e) => { if (e.target === lightboxOverlay) closeLightbox(); });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { closePopup(); closeLightbox(); }
    });

    // Close popup when clicking outside chart
    document.addEventListener('click', (e) => {
        const canvas = document.getElementById('radarChart');
        if (!canvas.contains(e.target) && !popup.contains(e.target)) closePopup();
    });
})();
</script>
</body>
</html>