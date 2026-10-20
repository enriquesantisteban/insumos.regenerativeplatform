<?php
/**
 * Página del fabricante.
 *
 * Muestra los detalles de un fabricante y el catálogo de productos asociados.
 * Utiliza el parámetro GET 'id' para cargar el fabricante correspondiente.
 */
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/db_connection.php';

$slug = trim($_GET['slug'] ?? '');
$fabricanteIdParam = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($slug === '' && !$fabricanteIdParam) { header('Location: ' . __url('index')); exit; }

/**
 * Comprueba si una tabla MySQL contiene una columna concreta.
 *
 * @param mysqli $mysqli Conexión activa a la base de datos.
 * @param string $table Nombre de la tabla.
 * @param string $column Nombre de la columna a buscar.
 * @return bool True si la columna existe; false en caso contrario.
 */
function tableHasColumn($mysqli, $table, $column) {
    $t = $mysqli->real_escape_string($table);
    $c = $mysqli->real_escape_string($column);
    $r = $mysqli->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $r && $r->num_rows > 0;
}


// Detect image column on fabricantes
$fabricanteImageColumn = null;
if (tableHasColumn($mysqli, 'fabricantes', 'imagen'))     { $fabricanteImageColumn = 'imagen'; }
elseif (tableHasColumn($mysqli, 'fabricantes', 'logo'))   { $fabricanteImageColumn = 'logo'; }

// Fetch fabricante
$sqlFab = 'SELECT id, nombre, descripcion, slug';                                     // Selecciona los campos id, nombre y descripcion de la tabla fabricantes
if ($fabricanteImageColumn) { $sqlFab .= ', ' . $fabricanteImageColumn; }       // Si existe una columna de imagen, la agrega a la consulta
$sqlFab .= $slug !== ''
        ? ' FROM fabricantes WHERE slug = ? AND UPPER(activo) = \'S\''
        : ' FROM fabricantes WHERE id = ? AND UPPER(activo) = \'S\'';

$fabStmt = $mysqli->prepare($sqlFab);                                           // Prepara la consulta SQL
if (!$fabStmt) { echo 'Error al cargar el fabricante.'; exit; }                 // Si la preparación falla, muestra un mensaje de error y termina la ejecución
$fabParamType = $slug !== '' ? 's' : 'i';
$fabParamValue = $slug !== '' ? $slug : $fabricanteIdParam;
$fabStmt->bind_param($fabParamType, $fabParamValue);
$fabStmt->execute();                                                            // Ejecuta la consulta
$fabricante = $fabStmt->get_result()->fetch_assoc();                            // Obtiene el resultado de la consulta como un array asociativo
if (!$fabricante) { echo __t('fabricante.not_found', 'Fabricante no encontrado.'); exit; }                   // Si no se encuentra el fabricante, muestra un mensaje de error y termina la ejecución
$fabricanteId = (int)$fabricante['id'];
// Detect image column on productos
$imageColumnExists = tableHasColumn($mysqli, 'productos', 'imagen');            // Verifica si existe una columna de imagen en la tabla productos

// Filtro opcional por tipo de producto.
$tipoFilter = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';

// Fetch products
$sqlProd = 'SELECT id, nombre, slug, descripcion, tipo';                                    // Selecciona los campos del producto
if ($imageColumnExists) { $sqlProd .= ', imagen'; }                                     // Si existe una columna de imagen, la agrega a la consulta
$sqlProd .= ' FROM productos WHERE fabricante_id = ? AND UPPER(activo) = \'S\'';
$bindTypes  = 'i';
$bindParams = [$fabricanteId];
if ($tipoFilter !== '') {
    $sqlProd .= ' AND tipo = ?';
    $bindTypes  .= 's';
    $bindParams[] = $tipoFilter;
}
$sqlProd .= ' ORDER BY nombre';                                                          // ordena los resultados por nombre

$prodStmt = $mysqli->prepare($sqlProd);                                                // Prepara la consulta SQL para obtener los productos del fabricante
if (!$prodStmt) { echo 'Error al cargar los productos.'; exit; }                        // Si la preparación falla, muestra un mensaje de error y termina la ejecución
$prodStmt->bind_param($bindTypes, ...$bindParams);                                      // Vincula el/los parámetro(s) (fabricante y, si aplica, registro) a la consulta
$prodStmt->execute();                                                                   // Ejecuta la consulta para obtener los productos del fabricante
$productos = $prodStmt->get_result();                                                   // Obtiene el resultado de la consulta como un conjunto de resultados

$fabricanteImage = $fabricanteImageColumn ? ($fabricante[$fabricanteImageColumn] ?? null) : null;       // Obtiene la imagen del fabricante si existe, de lo contrario asigna null
$initials = mb_strtoupper(mb_substr($fabricante['nombre'], 0, 2));                                      // Obtiene las iniciales del nombre del fabricante para mostrar en caso de que no haya imagen disponible
?>


<!DOCTYPE html>                 
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpg" href="<?php echo BASE_PATH; ?>/media/favicon.png">
    <title><?php echo htmlspecialchars($fabricante['nombre']); ?> | Regenerative Agro Platform</title>
    <meta name="description" content="Productos biologicos de <?php echo htmlspecialchars($fabricante['nombre']); ?> en el catalogo AgroRegen.">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<?php require_once __DIR__ . '/nav.php'; ?>

<!-- ====== HERO BACKGROUND ====== -->
<section class="hero-section">
    <div class="hero-orb" style="width:420px;height:420px;top:5%;left:10%;background:rgba(35,166,213,0.13);"></div>
    <div class="hero-orb" style="width:360px;height:360px;bottom:5%;right:10%;background:rgba(231,60,126,0.1);"></div>

    <div class="hero-content hero-product" style="position:relative;z-index:2;">
        <div class="hero-text">                                                                                         <!-- Contenedor que incluye enlace a Inicio, Tí­tulo, Descripción y botones de acción -->
            <a href="<?php echo htmlspecialchars(__url('index')); ?>" class="hero-eyebrow" style="text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem;">        
                <i class="fas fa-arrow-left" style="font-size:0.75rem;"></i> <?php echo __t('fabricante.breadcrumb_inicio', 'Inicio'); ?>                                                         <!-- Botón de vuelta al menú principal --> 
            </a>

            <h1 class="hero-title" style="margin-top:0.75rem;">                        <!-- contenedor tí­tulo del fabricante -->
                <?php echo htmlspecialchars($fabricante['nombre']); ?>                 <!-- tí­tulo del fabricante -->
            </h1>

            <p class="hero-desc" style="max-width:520px;margin-left:0;margin-bottom:1.5rem;">       <!-- contenedor tí­tulo del fabricante -->
                <?php echo !empty($fabricante['descripcion'])                                       // si tiene descripción, la muestra (traducida segun idioma activo)
                    ? nl2br(htmlspecialchars(__tdb($mysqli, 'fabricantes', $fabricante['id'], 'descripcion', $fabricante['descripcion'])))
                    : __t('fabricante.desc_fallback', 'Explora los productos biológicos disponibles de este fabricante.'); ?>        <!-- sino muestra este mensaje -->
            </p>

            <div class="hero-image-mobile">
                <?php if ($fabricanteImage): ?>
                    <img src="<?php echo htmlspecialchars(__asset_url($fabricanteImage)); ?>"
                         alt="Logo de <?php echo htmlspecialchars($fabricante['nombre']); ?>">
                <?php else: ?>
                    <div class="manufacturer-hero-initials">
                        <?php echo htmlspecialchars($initials); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="hero-chips" style="justify-content:flex-start;margin-bottom:1.75rem;">                          <!-- contenedor chips de información-->
                <span class="hero-chip"><i class="fas fa-industry" aria-hidden="true"></i> <?php echo __t('fabricante.chip_fabricante', 'Fabricante'); ?></span>
                <span class="hero-chip"><i class="fas fa-box" aria-hidden="true"></i>
                    <?php echo $productos->num_rows; ?> <?php echo $productos->num_rows != 1 ? __t('fabricante.producto_plural', 'productos') : __t('fabricante.producto_singular', 'producto'); ?>     <!-- imprime el número de productos del fabricante (traducido, con plural) -->
                </span>
            </div>

            <div class="hero-ctas" style="justify-content:flex-start;">                         <!-- contenedor botones de acción -->
                <a href="#products" class="btn-hero btn-hero-primary">                          <!-- botón ver productos -->    
                    <?php echo __t('fabricante.ver_productos', 'Ver productos'); ?> <i class="fas fa-arrow-down" aria-hidden="true"></i>
                </a>
                <a href="<?php echo htmlspecialchars(__url('index')); ?>#<?php echo htmlspecialchars(__anchor('fabricantes')); ?>" class="btn-hero btn-hero-secondary">                       <!-- botón ver otros fabricantes -->
                    <?php echo __t('fabricante.otros_fabricantes', 'Otros fabricantes'); ?>
                </a>
            </div>
        </div>

        <div class="hero-image">                                                          <!-- contenedor imagen -->    
            <?php if ($fabricanteImage): ?>                                               <!-- si existe imagen, muestra -->   
                <img src="<?php echo htmlspecialchars(__asset_url($fabricanteImage)); ?>"              
                     alt="Logo de <?php echo htmlspecialchars($fabricante['nombre']); ?>">   <!-- texto alternativo -->
            <?php else: ?>                                                              <!-- sino, muestra -->
                <div style="width:220px;height:220px;border-radius:32px;background:rgba(255,255,255,0.15);backdrop-filter:blur(10px);display:flex;align-items:center;justify-content:center;font-size:5rem;font-weight:900;color:rgba(255,255,255,0.9);">   
                    <?php echo htmlspecialchars($initials); ?>                              <!-- iniciales del nombre del fabricante -->
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>


<!-- ====== PRODUCTOS ====== -->
<section class="section-pad" id="products">                                            <!-- contenedor para productos -->
    <div class="section-inner">                                                        <!-- contenedor para el contenido del productos -->
        <div class="section-header">                                                   <!-- contenedor para la sección de productos -->
            <span class="eyebrow" style="background:#eff6ff;color:#2563eb;"><?php echo __t('fabricante.eyebrow_catalogo', 'Catálogo'); ?></span>   <!-- texto destacado que indica la sección de productos -->
            <h2 class="section-title" style="margin-bottom:0.5rem;">                     <!-- tí­tulo principal de la sección de productos -->                                        
                <?php echo __t('fabricante.productos_de', 'Productos de'); ?> <span class="gradient-text"><?php echo htmlspecialchars($fabricante['nombre']); ?></span>   <!-- texto que muestra el nombre del fabricante -->
            </h2>
            <?php if ($tipoFilter !== ''): ?>
                <p class="active-filter-note">
                    <?php echo __t('fabricante.filter_tipo_active_prefix', 'Mostrando solo productos de tipo:'); ?>
                    <strong><?php echo htmlspecialchars(__tipo_label($tipoFilter)); ?></strong>
                    <a href="<?php echo htmlspecialchars(__url('fabricante', ['slug' => $slug])); ?>" class="filter-clear-link">
                        <i class="fas fa-xmark" aria-hidden="true"></i> <?php echo __t('fabricante.filter_clear', 'Ver todos los productos'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <?php
        // Reset pointer
        $productos->data_seek(0);                                                       // resetea el puntero de la consulta
        if ($productos->num_rows > 0):                                                // comprueba si hay productos en la consulta y muestra la lista de productos si hay
        ?>
            <div class="product-grid">                                                <!-- contenedor para la cuadrí­cula de productos -->
                <?php while ($prod = $productos->fetch_assoc()):                         // recorre cada producto y muestra su información en una tarjeta -->
                    $prodImg = $imageColumnExists ? ($prod['imagen'] ?? null) : null;    // obtiene la URL de la imagen del producto si existe, de lo contrario, será null
                    
                    $tipoText = !empty($prod['tipo'])
                        ? __tipo_label($prod['tipo'])
                        : '';
                ?> 
                    <article class="product-card">                                      <!-- tarjeta individual para cada producto que muestra su imagen, nombre, descripción y número de productos -->
                        <div class="card-img">                                           <!-- contenedor para la imagen del producto -->
                            <?php if ($prodImg): ?>                                        <!-- comprueba si hay una imagen disponible para el producto -->
                                <img src="<?php echo htmlspecialchars(__asset_url($prodImg)); ?>"    
                                     alt="<?php echo htmlspecialchars($prod['nombre']); ?>">    
                            <?php else: ?>
                                <i class="fas fa-flask" style="font-size:3rem;color:#cbd5e1;"></i>   <!-- si no hay imagen, muestra un flask -->
                            <?php endif; ?>
                        </div>
                        <div class="card-body">                                          <!-- contenedor para el contenido del producto, que incluye el nombre, descripción y botón de acción -->
                            <h3><?php echo htmlspecialchars($prod['nombre']); ?></h3>      <!-- muestra el nombre del producto en un encabezado de nivel 3 -->
                            <p><?php echo htmlspecialchars($prod['descripcion'] ? __tdb($mysqli, 'productos', $prod['id'], 'descripcion', $prod['descripcion']) : __t('fabricante.desc_producto_fallback', 'Descripción no disponible.')); ?></p>    <!-- muestra la descripción del producto (traducida) en un párrafo -->
                            <?php if ($tipoText !== ''): ?>
                                <span class="price"><?php echo htmlspecialchars($tipoText); ?></span>
                            <?php endif; ?>
                            <a href="<?php echo htmlspecialchars(__url('producto', ['slug' => $prod['slug']])); ?>" class="btn btn-full">   <!-- botón que redirige a la página del producto -->
                                <?php echo __t('fabricante.ver_ficha', 'Ver ficha completa'); ?> <i class="fas fa-arrow-right" aria-hidden="true" style="font-size:0.75rem;margin-left:4px;"></i>     <!-- muestra un icono de flecha a la derecha despuí©s del texto del botón -->
                            </a>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty">                                                         <!-- contenedor para el contenido vací­o -->
                <i class="fas fa-box-open" style="font-size:3rem;color:#cbd5e1;display:block;margin-bottom:1rem;"></i>   <!-- icono de caja abierta -->
                <h3><?php echo __t('fabricante.empty_title', 'No hay productos registrados'); ?></h3>                                   <!-- tí­tulo del contenido vací­o -->
                <p><?php echo __t('fabricante.empty_desc', 'Este fabricante aún no tiene productos en la base de datos.'); ?></p>       <!-- descripción del contenido vací­o -->
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ====== FOOTER ====== -->
 
<?php require_once __DIR__ . '/footer.php'; ?>


</body>
</html>