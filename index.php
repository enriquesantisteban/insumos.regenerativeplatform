<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Incluir los archivos de PHPMailer de tu carpeta /insumos/PHPMailer
if (file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
} elseif (file_exists(__DIR__ . '/PHPMailer/PHPMailer.php')) {
    require_once __DIR__ . '/PHPMailer/Exception.php';
    require_once __DIR__ . '/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/SMTP.php';
} elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/config.php';
$form_message = '';
$form_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'contact') {
    $name    = isset($_POST['name']) ? trim(htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8')) : '';
    $email   = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL));
    $message = isset($_POST['message']) ? trim(htmlspecialchars($_POST['message'], ENT_QUOTES, 'UTF-8')) : '';

    if ($name && $email && $message) {
        $mail = new PHPMailer(true);

        try {
            // Configuración del servidor SMTP de serviciodecorreo.es
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;          // Usuario
            $mail->Password   = SMTP_PASS;     // Definido en config.php (no versionado)
            
            // Cifrado y puerto SMTP habituales
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Cifrado SSL (O usa ENCRYPTION_STARTTLS para puerto 587)
            $mail->Port       = SMTP_PORT;                          // Puerto 465 para SSL (o 587 para TLS)
            $mail->CharSet    = 'UTF-8';

            // Remitente, destinatario y dirección de respuesta
            $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
            $mail->addAddress(SMTP_USER, 'Regenerative Agro Platform');
            $mail->addReplyTo($email, $name);

            // Contenido del mensaje
            $mail->isHTML(true);
            $mail->Subject = 'Nuevo mensaje de contacto desde la Regenerative Agro Platform';
            $mail->Body    = "Has recibido un nuevo mensaje de contacto:<br><br>"
               . "<b>Nombre:</b> " . htmlspecialchars($name) . "<br>"
               . "<b>Correo:</b> " . htmlspecialchars($email) . "<br><br>"
               . "<b>Mensaje:</b><br>" . nl2br(htmlspecialchars($message)) . "<br>";

            // Texto plano de respaldo por si el gestor bloquea el HTML
            $mail->AltBody = "Has recibido un nuevo mensaje de contacto:\n\n"
               . "Nombre: " . $name . "\n"
               . "Correo: " . $email . "\n\n"
               . "Mensaje:\n" . $message;

            $mail->send();
            $form_success = true;
        } catch (Exception $e) {
            $form_message = __t('contact.send_error', 'Error al enviar el mensaje. Inténtalo de nuevo.');
        }
    } else {
        $form_message = __t('contact.validation_error', 'Por favor, completa todos los campos correctamente.');
    }
}
/*
__DIR__ ==> constante mágica de PHP que devuelve el directorio del archivo actual.
require_once ==> signfica que se importa el archivo una sola vez.
*/
require_once __DIR__ . '/db_connection.php'; // importa el archivo de conexión a la base de datos

// Check if fabricantes table has an image column
function tableHasColumn($mysqli, $table, $column) {     // comprueba si la tabla tiene una columna específica
    $t = $mysqli->real_escape_string($table);           // limpia el nombre de la tabla para evitar inyecciones SQL
    $c = $mysqli->real_escape_string($column);          // limpia el nombre de la columna para evitar inyecciones SQL
    $r = $mysqli->query("SHOW COLUMNS FROM `$t` LIKE '$c'");    // ejecuta la consulta para obtener las columnas de la tabla y verifica si existe la columna especificada
    return $r && $r->num_rows > 0;                      // devuelve true si la columna existe, false si no
}


/*
ESTO HACE QUE SI SE CAMBIA EL NOMBRE DE LA COLUMNA DE IMAGEN DEL FABRICANTE EN LA BASE DE DATOS, EL CÓDIGO SE ADAPTE AUTOMÁTICAMENTE Y SIGA FUNCIONANDO.
*/ 

$fabricanteImageColumn = null;                                  // variable para almacenar el nombre de la columna de imagen del fabricante
if (tableHasColumn($mysqli, 'fabricantes', 'imagen')) {         // comprueba si la tabla fabricantes tiene una columna llamada 'imagen'
    $fabricanteImageColumn = 'imagen';                          // si existe, asigna el nombre de la columna a la variable
} elseif (tableHasColumn($mysqli, 'fabricantes', 'logo')) {     // si no existe, comprueba si la tabla tiene una columna llamada 'logo'
    $fabricanteImageColumn = 'logo';                            // si existe, asigna el nombre de la columna a la variable
}



// Fetch fabricantes with product count
$sql = 'SELECT f.id, f.nombre, f.slug, f.descripcion';                                  // consulta SQL para obtener los fabricantes con el conteo de productos
if ($fabricanteImageColumn) { $sql .= ', f.' . $fabricanteImageColumn; }        // si existe una columna de imagen, la agrega a la consulta
    // Reúne los tipos de producto por fabricante para los filtros del catálogo.
        $sql .= ', COUNT(p.id) AS num_productos, GROUP_CONCAT(DISTINCT NULLIF(TRIM(p.tipo), \'\') SEPARATOR \'||\') AS tipos_raw
                FROM fabricantes f                                                     
                LEFT JOIN productos p ON p.fabricante_id = f.id AND UPPER(p.activo) = \'S\'
                WHERE UPPER(f.activo) = \'S\'
                GROUP BY f.id                                                          
            ORDER BY f.nombre';                                                    // ordena los resultados por el nombre del fabricante
$fabricantesResult = $mysqli->query($sql);                                      // ejecuta la consulta y almacena el resultado en la variable $fabricantesResult

// Totals for stats bar
$totalFabricantes = 0;                                      // variable para almacenar el total de fabricantes
$totalProductos   = 0;                                      // variable para almacenar el total de productos
$fabricantesData  = [];                                     // array para almacenar los datos de los fabricantes
$tipoOptions = [];
if ($fabricantesResult) {                                   // comprueba si la consulta se ejecutó correctamente
    while ($row = $fabricantesResult->fetch_assoc()) {      // recorre los resultados de la consulta y los almacena en un array asociativo
        $row['tipos'] = !empty($row['tipos_raw']) ? explode('||', $row['tipos_raw']) : [];
        foreach ($row['tipos'] as $tipo) {
            $tipoOptions[$tipo] = $tipo;
        }
        $fabricantesData[] = $row;                          // agrega cada fila de resultados al array $fabricantesData
        $totalFabricantes++;                                // incrementa el contador de fabricantes
        $totalProductos += (int)$row['num_productos'];      // incrementa el contador de productos con el valor del conteo de productos de cada fabricante
    }
}
natcasesort($tipoOptions);
$tipoOptions = array_values($tipoOptions);

$totalMicro = (int)$mysqli->query('SELECT COUNT(*) AS c FROM microorganismos')->fetch_assoc()['c'];     // obtiene el total de microorganismos evaluados en la base de datos y lo almacena en la variable $totalMicro

// Conteo de productos por fabricante y tipo para actualizar las tarjetas y sus enlaces.
$tipoCounts = [];                                                // [fabricante_id => [tipo => count]]
$countsResult = $mysqli->query(
    "SELECT p.fabricante_id, NULLIF(TRIM(p.tipo), '') AS tipo, COUNT(*) AS cnt
     FROM productos p
     JOIN fabricantes f ON p.fabricante_id = f.id AND UPPER(f.activo) = 'S'
     WHERE UPPER(p.activo) = 'S'
     GROUP BY p.fabricante_id, p.tipo"
);
if ($countsResult) {
    while ($row = $countsResult->fetch_assoc()) {
        if ($row['tipo'] === null) { continue; }
        $tipoCounts[$row['fabricante_id']][$row['tipo']] = (int)$row['cnt'];
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpg" href="<?php echo BASE_PATH; ?>/media/favicon.png">
    <title><?php echo htmlspecialchars(__t('nav.inicio', 'Inicio')); ?> | Regenerative Agro Platform</title>    
    <meta name="description" content="Catálogo unificado de insumos agrícolas para la agricultura regenerativa. Productos evaluados con ensayos de inocuidad certificados segun el protocolo MBG-EHB-01.">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<?php require_once __DIR__ . '/nav.php'; ?>     <!-- importa el archivo de navegación (nav.php) que contiene el menú de navegación del sitio web -->

<!-- ====== HERO ====== -->
<section class="hero-section" id="inicio">         <!-- sección principal de la página de inicio con un diseño destacado y llamativo -->
    <!-- Decorative orbs -->
    <div class="hero-orb" style="width:480px;height:480px;top:10%;left:15%;background:rgba(35,166,213,0.13);"></div>      <!-- elemento decorativo en forma de círculo con un color de fondo semitransparente -->  
    <div class="hero-orb" style="width:400px;height:400px;bottom:10%;right:12%;background:rgba(231,60,126,0.11);"></div>  <!-- elemento decorativo en forma de círculo con un color de fondo semitransparente -->

    <div class="hero-content">                                                          <!-- contenedor para el contenido principal de la sección hero, que incluye el título, descripción y botones de llamada a la acción -->
        <span class="hero-eyebrow"><?php echo __t('index.hero_eyebrow', 'Plataforma de Agricultura Regenerativa'); ?></span>        <!-- texto destacado que indica el propósito de la plataforma -->

        <h1 class="hero-title">                                                         <!-- título principal de la sección hero -->
            <?php echo __t('index.hero_title_1', 'Insumos agrícolas'); ?><br>
            <span class="highlight"><?php echo __t('index.hero_title_2', 'que regeneran'); ?></span>                                 
        </h1>

        <p class="hero-desc">                                                           <!-- párrafo descriptivo de los insumos agrícolas -->
            <?php echo __t('index.hero_desc', 'Catálogo unificado de productos fitosanitarios agrícolas de los principales fabricantes. 
            Cada producto evaluado con ensayos de inocuidad sobre microorganismos beneficiosos del suelo.'); ?>
        </p>

        <div class="hero-chips">                                                        <!-- contenedor para los chips de información que destacan las características de la plataforma -->
            <span class="hero-chip"><i class="fas fa-leaf" aria-hidden="true"></i><?php echo __t('index.chip_regen', 'Agricultura regenerativa'); ?></span>
            <span class="hero-chip"><i class="fas fa-flask" aria-hidden="true"></i><?php echo __t('index.chip_assays', 'Ensayos in vitro certificados'); ?></span>
            <span class="hero-chip"><i class="fas fa-shield-alt" aria-hidden="true"></i><?php echo __t('index.chip_registry', 'Registro oficial'); ?></span>
        </div>

        <div class="hero-ctas">                                                         <!-- contenedor para los botones de llamada a la acción -->
            <a href="#<?php echo htmlspecialchars(__anchor('fabricantes')); ?>" class="btn-hero btn-hero-primary">                   <!-- botón que redirige a la sección de fabricantes --> <!-- <a> sirve para crear un enlace a otra sección de la página -->
                <?php echo __t('index.cta_catalog', 'Ver catálogo'); ?> <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>
            <a href="#<?php echo htmlspecialchars(__anchor('impacto')); ?>" class="btn-hero btn-hero-secondary">                       <!-- botón que redirige a la sección de información sobre la plataforma -->
                <?php echo __t('index.impact_eyebrow', 'Nuestro Impacto'); ?>
            </a>
        </div>

        <!-- Stats bar -->
        <div class="stats-bar" role="list" aria-label="Estadisticas de la plataforma">      <!-- barra de estadísticas que muestra el total de fabricantes, productos y microorganismos evaluados -->
            <div class="stat-item" role="listitem">                                         
                <span class="stat-value"><?php echo $totalFabricantes; ?>+</span>
                <span class="stat-label"><?php echo __t('index.stat_manufacturers', 'Fabricantes'); ?></span>
            </div>
            <div class="stat-item" role="listitem">
                <span class="stat-value"><?php echo $totalProductos; ?>+</span>
                <span class="stat-label"><?php echo __t('index.stat_products', '  Productos'); ?></span>
            </div>
            <div class="stat-item" role="listitem">
                <span class="stat-value"><?php echo $totalMicro; ?></span>
                <span class="stat-label"><?php echo __t('index.stat_microorganisms', 'Microorganismos evaluados'); ?></span>
            </div>
            <div class="stat-item" role="listitem">
                <span class="stat-value">100%</span>
                <span class="stat-label"><?php echo __t('index.stat_biological_control', 'Control biologico'); ?></span>
            </div>
        </div>
    </div>
</section>

<!-- ====== IMPACTO ====== -->
<section class="info-section" id="<?php echo htmlspecialchars(__anchor('impacto')); ?>">
    <div class="section-inner">
        <div class="section-header" style="text-align: center; max-width: 700px; margin: 0 auto 3rem auto;">
            <span class="eyebrow" style="background:#dcfce7; color:#166534; font-weight: 600; padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center;">
                <i class="fas fa-chart-line" aria-hidden="true" style="margin-right: 6px; line-height: 1;"></i>
                <?php echo __t('index.impact_eyebrow', 'Nuestro Impacto'); ?>
            </span>
            <h2 class="section-title" style="margin-top: 1rem; margin-bottom: 0.75rem; color:#fff">
                <?php echo __t('index.impact_title_1', 'Transformando el suelo para el'); ?> 
                <span class="gradient-text"><?php echo __t('index.impact_title_2', 'futuro del campo'); ?></span>
            </h2>
            <p style="color: #64748b; font-size: 1.05rem; line-height: 1.6;">
                <?php echo __t('index.impact_desc', 'Fomentamos el uso de insumos evaluados que protegen la microbiología nativa, restauran la fertilidad natural y aseguran cosechas rentables y sostenibles.'); ?>
            </p>
        </div>

        <div class="info-grid info-grid--four-cols">
            <div class="info-card">
                <i class="fas fa-seedling" aria-hidden="true"></i>
                <h3><?php echo __t('index.info_card_1_title', 'Restauración del suelo como base productiva'); ?></h3>
                <p><?php echo __t('index.info_card_1_desc', 'La agricultura regenerativa prioriza la regeneración de la fertilidad del suelo mediante el uso de compost, abonos verdes, estiercol y cobertura vegetal permanente, mejorando su estructura, retención de agua y actividad microbiana.'); ?></p>
            </div>
            <div class="info-card">
                <i class="fas fa-apple-whole" aria-hidden="true"></i>
                <h3><?php echo __t('index.info_card_2_title', 'Diversificación y rotación de cultivos'); ?></h3>
                <p><?php echo __t('index.info_card_2_desc', 'Este modelo promueve la alternancia de especies vegetales para evitar el agotamiento de nutrientes, reducir la presión de plagas y enfermedades, y mejorar la biodiversidad del agroecosistema.'); ?></p>
            </div>
            <div class="info-card">
                <i class="fas fa-water" aria-hidden="true"></i>
                <h3><?php echo __t('index.info_card_3_title', 'Reducción de insumos químicos'); ?></h3>
                <p><?php echo __t('index.info_card_3_desc', 'La agricultura regenerativa minimiza el uso de fertilizantes sintéticos y pesticidas, favoreciendo el control biológico e incorporando técnicas de riego eficiente y captación de agua de lluvia.'); ?></p>
            </div>
            <div class="info-card">
                <i class="fas fa-microscope" aria-hidden="true"></i>
                <h3><?php echo __t('index.info_card_4_title', 'Protocolo MBG-EHB-01'); ?></h3>
                <p><?php echo __t('index.info_card_4_desc', 'Todos los productos del catálogo han sido evaluados mediante ensayo in vitro según el procedimiento MBG-EHB-01, que determina el índice de inocuidad relativa sobre microorganismos beneficiosos del suelo.'); ?></p>
            </div>
        </div>

        <div style="margin-top: 3rem; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 16px; padding: 2.5rem; color: #ffffff; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; box-shadow: 0 10px 25px rgba(15,23,42,0.15);">
            <div style="max-width: 650px;">
                <h3 style="font-size: 1.35rem; font-weight: 700; margin-bottom: 0.5rem; color: #38bdf8; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-check-double" style="line-height: 1;"></i>
                    <?php echo __t('index.impact_banner_title', 'Rigor Científico al Servicio del Agricultor'); ?>
                </h3>
                <p style="color: #94a3b8; font-size: 0.95rem; margin: 0; line-height: 1.5;">
                    <?php echo __t('index.impact_banner_desc', 'Cada insumo de nuestro catálogo ha sido sometido a ensayos estandarizados MBG-EHB-01 para verificar su compatibilidad real con la vida biológica del suelo.'); ?>
                </p>
            </div>
            <div>
                <a href="#<?php echo htmlspecialchars(__anchor('contacto')); ?>" class="btn" style="background: #22c55e; color: #ffffff; font-weight: 600; padding: 0.85rem 1.5rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <?php echo __t('index.impact_banner_cta', 'Saber más'); ?> 
                    <i class="fas fa-arrow-right" style="line-height: 1;"></i>
                </a>
            </div>
        </div>

    </div>
</section>


<!-- ====== FABRICANTES ====== -->
<section class="section-pad" id="<?php echo htmlspecialchars(__anchor('fabricantes')); ?>">
    <div class="section-inner">
        <div class="section-header">
            <span class="eyebrow" style="background:#eff6ff;color:#2563eb;"><?php echo __t('index.stat_manufacturers', 'Fabricantes registrados'); ?></span>      <!-- texto destacado que indica la sección de fabricantes registrados -->
            <h2 class="section-title" style="margin-bottom:0.5rem;">                                            <!-- título principal de la sección de fabricantes -->
                <?php echo __t('index.manufacturers_title_1', 'Explora por'); ?> <span class="gradient-text"><?php echo __t('index.manufacturers_title_2', 'fabricante'); ?></span>
            </h2>
            <p><?php echo __t('index.manufacturers_desc', 'Selecciona un fabricante para ver todos sus productos biológicos disponibles en la plataforma.'); ?></p>
        </div>

        <?php if (!empty($fabricantesData)): ?>                 <!-- comprueba si hay fabricantes registrados y muestra la lista de fabricantes -->

            <!-- ====== FILTROS: fabricante y/o tipo ====== -->
            <div class="manufacturers-filters" id="manufacturersFilters">
                <div class="filter-group">
                    <label for="filterFabricante"><?php echo __t('index.filter_manufacturer_label', 'Fabricante'); ?></label>
                    <select id="filterFabricante">
                        <option value=""><?php echo __t('index.filter_all', 'Todos'); ?></option>
                        <?php foreach ($fabricantesData as $fabOpt): ?>
                            <option value="<?php echo (int)$fabOpt['id']; ?>"><?php echo htmlspecialchars($fabOpt['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filterTipo"><?php echo __t('index.filter_tipo_label', 'Tipo'); ?></label>
                    <select id="filterTipo">
                        <option value=""><?php echo __t('index.filter_all', 'Todos'); ?></option>
                        <?php foreach ($tipoOptions as $tipo): ?>
                            <option value="<?php echo htmlspecialchars($tipo); ?>"><?php echo htmlspecialchars(__tipo_label($tipo)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" id="filterClearBtn" class="filter-clear-btn">
                    <i class="fas fa-xmark" aria-hidden="true"></i> <?php echo __t('index.filter_clear', 'Limpiar filtros'); ?>
                </button>
            </div>

            <div class="manufacturers-grid" id="manufacturersGrid">                    <!-- contenedor para la cuadrícula de fabricantes -->
                <?php foreach ($fabricantesData as $fab):       // recorre cada fabricante registrado y muestra su información en una tarjeta -->
                    $imgCol  = $fabricanteImageColumn ? ($fab[$fabricanteImageColumn] ?? null) : null;      // obtiene la URL de la imagen del fabricante si existe, de lo contrario, será null
                    $initials = mb_strtoupper(mb_substr($fab['nombre'], 0, 2));                             // obtiene las iniciales del nombre del fabricante para mostrar en caso de que no haya imagen disponible
                    $fabricanteUrl = !empty($fab['slug'])
                        ? __url('fabricante', ['slug' => $fab['slug']])
                        : __url('fabricante', ['id' => (int)$fab['id']]);
                ?>
                    <article class="manufacturer-card" data-fabricante="<?php echo (int)$fab['id']; ?>" data-tipos="<?php echo htmlspecialchars(implode('|', $fab['tipos'])); ?>" data-total="<?php echo (int)$fab['num_productos']; ?>" data-tipo-counts="<?php echo htmlspecialchars(json_encode(!empty($tipoCounts[$fab['id']]) ? $tipoCounts[$fab['id']] : new stdClass(), JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>" data-label-unit="<?php echo htmlspecialchars(__t('index.producto_singular', ' producto')); ?>">
                        <div class="manufacturer-card-img">         <!-- contenedor para la imagen del fabricante -->
                            <?php if ($imgCol): ?>                  <!-- comprueba si hay una imagen disponible para el fabricante -->
                                <img src="<?php echo htmlspecialchars(__asset_url($imgCol)); ?>" alt="Logo de <?php echo htmlspecialchars($fab['nombre']); ?>">  <!-- muestra la imagen del fabricante con un texto alternativo que describe el logo del fabricante -->
                            <?php else: ?>              <!-- si no hay imagen disponible, muestra las iniciales del fabricante -->
                                <div class="manufacturer-initials" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>          <!-- muestra las iniciales del fabricante en un contenedor con estilo -->
                            <?php endif; ?>
                        </div>
                        <div class="manufacturer-card-body">                                    <!-- contenedor para el contenido de la tarjeta del fabricante, que incluye el nombre, descripción y número de productos -->
                            <h3><?php echo htmlspecialchars($fab['nombre']); ?></h3>            <!-- muestra el nombre del fabricante en un encabezado de nivel 3 -->
                            <?php if (!empty($fab['descripcion'])): ?>                          <!-- comprueba si hay una descripción disponible para el fabricante -->
                                <p><?php echo htmlspecialchars(__tdb($mysqli, 'fabricantes', $fab['id'], 'descripcion', $fab['descripcion'])); ?></p>     <!-- muestra la descripción del fabricante (traducida segun idioma activo) en un párrafo -->
                            <?php else: ?>                                                      <!-- si no hay descripción disponible, muestra un mensaje predeterminado -->
                                <p><?php echo __t('index.desc_fallback', 'Fabricante de insumos agrícolas para la agricultura regenerativa.'); ?></p>
                            <?php endif; ?>
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:1rem;flex-wrap:wrap;gap:0.5rem;">  <!-- contenedor para mostrar el número de productos del fabricante y un botón para ver los productos -->
                                <span style="font-size:0.8rem;color:#64748b;">                                                                      <!-- muestra el número de productos del fabricante con un icono de caja -->
                                    <i class="fas fa-box" aria-hidden="true" style="margin-right:4px;"></i>                                         <!-- muestra un icono de caja antes del número de productos -->
                                    <span class="count-num"><?php echo (int)$fab['num_productos']; ?></span><span class="count-label"><?php echo __t('index.producto_singular', ' producto'); ?><?php echo $fab['num_productos'] != 1 ? 's' : ''; ?></span>
                                </span>
                                <a href="<?php echo htmlspecialchars($fabricanteUrl); ?>" data-href-base="<?php echo htmlspecialchars($fabricanteUrl); ?>" class="btn manufacturer-cta">
                                    <?php echo __t('index.manufacturers_cta', 'Ver productos'); ?> <i class="fas fa-arrow-right" aria-hidden="true" style="margin-left:4px;font-size:0.75rem;"></i>  <!-- muestra un icono de flecha a la derecha después del texto del botón -->
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>    <!-- cierra el bucle foreach que recorre los fabricantes registrados -->
            </div>

            <!-- Mensaje mostrado por JS cuando los filtros no dejan ningún fabricante visible -->
            <div class="empty" id="manufacturersEmptyFiltered" style="display:none;">
                <i class="fas fa-filter" style="font-size:3rem;color:#cbd5e1;display:block;margin-bottom:1rem;"></i>
                <h3><?php echo __t('index.filter_empty_title', 'Ningún fabricante coincide con los filtros'); ?></h3>
                <p><?php echo __t('index.filter_empty_desc', 'Prueba a cambiar o limpiar los filtros seleccionados.'); ?></p>
            </div>

            <script>
            (function () {
                var fabSelect  = document.getElementById('filterFabricante');
                var tipoSelect = document.getElementById('filterTipo');
                var clearBtn   = document.getElementById('filterClearBtn');
                var grid       = document.getElementById('manufacturersGrid');
                var emptyState = document.getElementById('manufacturersEmptyFiltered');
                var cards      = grid ? grid.querySelectorAll('.manufacturer-card') : [];

                function applyFilters() {
                    var fabVal = fabSelect ? fabSelect.value : '';
                    var tipoVal = tipoSelect ? tipoSelect.value : '';
                    var visibleCount = 0;

                    cards.forEach(function (card) {
                        var cardFab  = card.getAttribute('data-fabricante') || '';
                        var cardTipos = (card.getAttribute('data-tipos') || '').split('|');

                        var matchesFab = !fabVal || cardFab === fabVal;
                        var matchesTipo = !tipoVal || cardTipos.indexOf(tipoVal) !== -1;
                        var visible     = matchesFab && matchesTipo;

                        card.style.display = visible ? '' : 'none';
                        if (visible) visibleCount++;

                        // Recalcula el número de productos mostrado según el tipo seleccionado.
                        var total = parseInt(card.getAttribute('data-total'), 10) || 0;
                        var counts = {};
                        try { counts = JSON.parse(card.getAttribute('data-tipo-counts') || '{}'); } catch (e) {}
                        var displayCount = tipoVal ? (counts[tipoVal] || 0) : total;

                        var countNumEl   = card.querySelector('.count-num');
                        var countLabelEl = card.querySelector('.count-label');
                        var unitLabel    = card.getAttribute('data-label-unit') || '';
                        if (countNumEl)   { countNumEl.textContent = displayCount; }
                        if (countLabelEl) { countLabelEl.textContent = unitLabel + (displayCount !== 1 ? 's' : ''); }

                        // Actualiza el enlace para mostrar únicamente el tipo seleccionado.
                        var link = card.querySelector('.manufacturer-cta');
                        if (link) {
                            var base = link.getAttribute('data-href-base') || link.getAttribute('href');
                            var href = base;
                            if (tipoVal) {
                                href += (base.indexOf('?') === -1 ? '?' : '&') + 'tipo=' + encodeURIComponent(tipoVal);
                            }
                            link.setAttribute('href', href);
                        }
                    });

                    if (grid)       { grid.style.display = visibleCount > 0 ? '' : 'none'; }
                    if (emptyState) { emptyState.style.display = visibleCount > 0 ? 'none' : ''; }
                }

                if (fabSelect) { fabSelect.addEventListener('change', applyFilters); }
                if (tipoSelect) { tipoSelect.addEventListener('change', applyFilters); }
                if (clearBtn) {
                    clearBtn.addEventListener('click', function () {
                        if (fabSelect) { fabSelect.value = ''; }
                        if (tipoSelect) { tipoSelect.value = ''; }
                        applyFilters();
                    });
                }
            })();
            </script>
        <?php else: ?>          <!-- si no hay fabricantes registrados, muestra un mensaje indicando que no hay fabricantes disponibles -->
            <div class="empty">
                <i class="fas fa-industry" style="font-size:3rem;color:#cbd5e1;display:block;margin-bottom:1rem;"></i>
                <h3><?php echo __t('index.empty_title', 'No hay fabricantes registrados'); ?></h3>
                <p><?php echo __t('index.empty_desc', 'Importa la base de datos y vuelve a cargar esta pagina.'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>


<!-- ====== CONTACTO ====== -->
<section class="contact-section" id="<?php echo htmlspecialchars(__anchor('contacto')); ?>">
    <div class="section-inner">
        <h2 class="section-title"><?php echo __t('contact.title', 'Contacta con nosotros'); ?></h2>
        <p class="section-desc"><?php echo __t('contact.description', '¿Tienes preguntas o quieres colaborar? Rellena el formulario o contáctanos directamente.'); ?></p>
        
        <div class="contact-layout">
            <div class="contact-form-container">
                <form action="#" method="post" id="contactForm" novalidate>
                    <input type="hidden" name="form_type" value="contact">
                    <div class="form-group">
                        <label for="name"><?php echo __t('contact.form_name', 'Nombre'); ?></label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email"><?php echo __t('contact.form_email', 'Correo Electrónico'); ?></label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="message"><?php echo __t('contact.form_message', 'Mensaje'); ?></label>
                        <textarea id="message" name="message" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn-submit"><?php echo __t('contact.form_submit', 'Enviar Mensaje'); ?></button>
                    <?php if ($form_message && !$form_success): ?>
                        <p style="color: #f87171; margin-top: 1rem; text-align: center;"><?php echo $form_message; ?></p>
                    <?php endif; ?>
                </form>
            </div>
            
<!-- ====== INFORMACIÓN DE CONTACTO ====== -->

            <div class="contact-info-container">
                <div class="contact-info-card">
                    <i class="fas fa-phone-alt" aria-hidden="true"></i>
                    <h3><?php echo __t('contact.phone_title', 'Teléfono'); ?></h3>
                    <p><a href="tel:+34689626646">+34 689 626 646</a></p>
                </div>

        <!--
                <div class="contact-info-card">
                    <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                    <h3><?php echo __t('contact.address_title', 'Dirección'); ?></h3>
                    <p>Aquí va la dirección, CP Ciudad, País</p>
                </div>
            </div>
                    -->

        </div>
    </div>
</section>

<!-- ====== POPUP DE CONFIRMACIÓN ====== -->
<div class="chart-popup-overlay" id="confirmationPopup" style="display: none;">
    <div class="chart-popup-modal" style="max-width: 420px; text-align: center;">
        <button class="chart-popup-close" id="confirmationPopupClose" type="button" aria-label="Cerrar">&#215;</button>
        <div style="font-size: 3rem; color: var(--teal); margin-bottom: 1rem;">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3 id="popup-title" style="font-size: 1.5rem; margin-bottom: 0.5rem;"><?php echo __t('contact.popup_title', '¡Mensaje Enviado!'); ?></h3>
        <p id="popup-desc" style="margin-bottom: 1.5rem;"><?php echo __t('contact.popup_desc', 'Gracias por contactarnos. Te responderemos lo antes posible.'); ?></p>
        <button type="button" class="btn" id="confirmationPopupOk"><?php echo __t('contact.popup_ok', 'Aceptar'); ?></button>
    </div>
</div>





<!-- ====== FOOTER ====== -->
 
<?php require_once __DIR__ . '/footer.php'; ?>

<script>
<?php if ($form_success): ?>
(function() {
    var popup = document.getElementById('confirmationPopup');
    var closeBtn = document.getElementById('confirmationPopupClose');
    var okBtn = document.getElementById('confirmationPopupOk');

    function closePopup() {
        popup.style.display = 'none';
    }

    if (popup) {
        popup.style.display = 'flex';
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', closePopup);
    }
    if (okBtn) {
        okBtn.addEventListener('click', closePopup);
    }
    popup.addEventListener('click', function(e) { if (e.target === popup) closePopup(); });
})();
<?php endif; ?>
</script>
<script>
(function () {
    var form = document.getElementById('contactForm');
    if (!form) return;

    var fields = form.querySelectorAll('input[required], textarea[required]');
    var requiredMessage = <?php echo json_encode(__t('contact.required_error', 'Completa este campo.'), JSON_UNESCAPED_UNICODE); ?>;
    var emailMessage = <?php echo json_encode(__t('contact.email_error', 'Introduce una dirección de correo válida.'), JSON_UNESCAPED_UNICODE); ?>;

    function validateField(field) {
        field.setCustomValidity('');
        if (!field.value.trim()) {
            field.setCustomValidity(requiredMessage);
            return false;
        }
        if (field.type === 'email' && !field.validity.valid) {
            field.setCustomValidity(emailMessage);
            return false;
        }
        field.setCustomValidity('');
        return true;
    }

    fields.forEach(function (field) {
        field.addEventListener('input', function () { validateField(field); });
    });

    form.addEventListener('submit', function (event) {
        var firstInvalidField = null;
        fields.forEach(function (field) {
            if (!validateField(field) && !firstInvalidField) {
                firstInvalidField = field;
            }
        });
        if (firstInvalidField) {
            event.preventDefault();
            firstInvalidField.reportValidity();
        }
    });
})();
</script>
</body>
</html>