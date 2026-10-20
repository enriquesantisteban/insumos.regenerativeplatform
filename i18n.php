<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ruta base del sitio si no está en la raíz del dominio (ej. '/insumos').
// Déjalo vacío ('') si el sitio vive en la raíz, ej. http://localhost/producto.php

// Si la web va a ser insumos.com por ejemplo, tengo que quitar /insumos y dejarlo ('')
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '');
}

// Idiomas soportados
$allowed_langs = ['es', 'en', 'pt', 'fr', 'ca'];

// 1. Detectar si el usuario cambia de idioma mediante la URL (?lang=en)
if (isset($_GET['lang']) && in_array($_GET['lang'], $allowed_langs)) {
    $_SESSION['lang'] = $_GET['lang'];
    setcookie('user_lang', $_GET['lang'], time() + (86400 * 30), "/"); // Cookie por 30 días
}

// 2. Definir el idioma activo
if (isset($_SESSION['lang'])) {
    $current_lang = $_SESSION['lang'];
} elseif (isset($_COOKIE['user_lang']) && in_array($_COOKIE['user_lang'], $allowed_langs)) {
    $current_lang = $_COOKIE['user_lang'];
} else {
    $current_lang = 'es'; // Idioma por defecto
}

// 3. Cargar el diccionario estático de interfaz si existe
$lang_file = __DIR__ . "/lang/{$current_lang}.php";
$translations = file_exists($lang_file) ? require $lang_file : [];

// 4. Mapa de rutas de archivo por idioma para páginas sin slug.
$url_map = [
    'es' => ['index' => '', 'blog' => 'blog.php'],
    'en' => ['index' => '', 'blog' => 'news.php'],
    'pt' => ['index' => '', 'blog' => 'noticias.php'],
    'fr' => ['index' => '', 'blog' => 'nouvelles.php'],
    'ca' => ['index' => '', 'blog' => 'noticies.php'],
];
$current_url_map = $url_map[$current_lang] ?? $url_map['es'];


// 5. Segmento de URL "bonito" por idioma para fabricante/producto (sin .php ni id):
//    /es/fabricante/kenogard   /en/manufacturer/kenogard   /fr/fabricant/kenogard ...
//    Estas son las claves que también debe reconocer el .htaccess.
$page_segment_map = [
    'es' => ['fabricante' => 'fabricante',   'producto' => 'producto', 'blog' => 'blog'],
    'en' => ['fabricante' => 'manufacturer', 'producto' => 'product',  'blog' => 'news'],
    'pt' => ['fabricante' => 'fabricante',   'producto' => 'produto',  'blog' => 'noticias'],
    'fr' => ['fabricante' => 'fabricant',    'producto' => 'produit',  'blog' => 'nouvelles'],
    'ca' => ['fabricante' => 'fabricant',    'producto' => 'producte', 'blog' => 'noticies'],
];
$current_page_segment_map = $page_segment_map[$current_lang] ?? $page_segment_map['es'];

// Los scripts reales a los que apunta cada clave de página, siempre en español
// (todas las variantes de idioma se reescriben hacia el mismo archivo canónico).
// Se usa para saber "en qué página estoy", sea cual sea la URL bonita que se usó.
$canonical_script_map = [
    'index.php'      => 'index',
    'fabricante.php' => 'fabricante',
    'producto.php'   => 'producto',
    'blog.php'       => 'blog',
    'news.php'       => 'blog',
    'noticias.php'   => 'blog',
    'nouvelles.php'  => 'blog',
    'noticies.php'   => 'blog',
    'contacto.php'   => 'contacto',
];

/**
 * Traduce textos estáticos del código (interfaz).
 */
function __t($key, $default = '') {
    global $translations;
    return $translations[$key] ?? ($default ?: $key);
}

function __tipo_label($tipo) {
    $value = trim((string)$tipo);
    if ($value === '') {
        return '';
    }

    $translationKeys = [
        'autorizado' => 'producto.tipo_autorizado',
        'regeneradores' => 'producto.tipo_regeneradores',
    ];
    $translationKey = $translationKeys[mb_strtolower($value, 'UTF-8')] ?? null;

    return $translationKey ? __t($translationKey, $value) : $value;
}

function __subtipo_label($subtipo) {
    $code = strtoupper(trim((string)$subtipo));
    if ($code === '') {
        return '';
    }

    $translationKey = 'producto.subtipo_' . strtolower($code);
    return $code . ' | ' . __t($translationKey, $code);
}

/**
 * Devuelve el identificador localizado de una sección de la página principal.
 */
function __anchor($key) {
    global $current_lang;

    $anchors = [
        'fabricantes' => [
            'es' => 'fabricantes',
            'en' => 'manufacturers',
            'pt' => 'fabricantes',
            'fr' => 'fabricants',
            'ca' => 'fabricants',
        ],
        'contacto' => [
            'es' => 'contacto',
            'en' => 'contact',
            'pt' => 'contato',
            'fr' => 'contact',
            'ca' => 'contacte',
        ],
        'impacto' => [
            'es' => 'impacto',
            'en' => 'impact',
            'pt' => 'impacto',
            'fr' => 'impact',
            'ca' => 'impacte',
        ],
    ];

    return $anchors[$key][$current_lang] ?? $anchors[$key]['es'] ?? $key;
}

/**
 * Devuelve la clave de página (ej. 'fabricante', 'producto', 'index') correspondiente
 * al script que se está ejecutando actualmente. Como todas las variantes de idioma
 * reescriben siempre hacia el mismo archivo canónico (fabricante.php, producto.php...),
 * basta con mirar el nombre real del script en ejecución.
 */
function __current_page_key() {
    global $canonical_script_map;
    $current_script = basename($_SERVER['SCRIPT_NAME']);
    return $canonical_script_map[$current_script] ?? null;
}

/**
 * Convierte un texto (ej. el nombre de un fabricante o producto) en un slug apto
 * para URL: minúsculas, sin acentos, espacios y símbolos convertidos en guiones.
 * Ej: "Kenogard S.A." -> "kenogard-s-a"
 * (Nota: si ya tienes una columna 'slug' en la base de datos, úsala directamente;
 * esta función queda disponible como respaldo o para generar slugs nuevos.)
 */
function __slugify($text) {
    $text = trim((string)$text);
    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    if ($transliterated !== false) {
        $text = $transliterated;
    }
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'item';
}

/**
 * Genera la URL de la página EQUIVALENTE en otro idioma, manteniendo el slug (para
 * fabricante/producto) o el resto de parámetros GET, y con el prefijo /es/, /en/...
 * y el segmento de página ya traducido (fabricante -> manufacturer, etc.).
 * Se usa para construir el selector de idioma en nav.php.
 */
function __lang_switch_url($lang) {
    global $url_map, $page_segment_map, $mysqli, $current_lang;

    $query = $_GET;
    unset($query['lang']);

    $page_key = __current_page_key();

    // Detección robusta de la clave de página si __current_page_key() falla en Nginx
    if (!$page_key) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('#/(producto|product|produto|produit|producte)/#i', $uri) || isset($query['slug']) && strpos($uri, 'producto') !== false) {
            $page_key = 'producto';
        } elseif (preg_match('#/(fabricante|manufacturer|fabricant)/#i', $uri) || isset($query['slug']) && strpos($uri, 'fabricante') !== false) {
            $page_key = 'fabricante';
        } elseif (preg_match('#/(blog|news|noticias|nouvelles|noticies)/#i', $uri) || !empty($query['slug'])) {
            $page_key = 'blog';
        }
    }

    // 1. Páginas de detalle con slug (blog, producto, fabricante)
    if (in_array($page_key, ['fabricante', 'producto', 'blog'], true) && !empty($query['slug'])) {
        $target_segments = $page_segment_map[$lang] ?? $page_segment_map['es'];
        $segment = $target_segments[$page_key] ?? $page_key;
        
        // Decodificar el slug por si viene con caracteres especiales (%C3%A9 -> é)
        $raw_slug = rawurldecode($query['slug']);
        $slug = $raw_slug;
        unset($query['slug']);

        if ($page_key === 'blog' && isset($mysqli)) {
            $articleId = null;

            // Caso A: El slug actual es el original de la tabla blog (español)
            $stmt = $mysqli->prepare('SELECT id FROM blog WHERE slug = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $raw_slug);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                $articleId = $res['id'] ?? null;
            }

            // Caso B: El slug actual es de otro idioma (está en la tabla traducciones)
            if (!$articleId) {
                $stmt = $mysqli->prepare("SELECT traduccion_id FROM traducciones WHERE tabla = 'blog' AND campo = 'slug' AND texto = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('s', $raw_slug);
                    $stmt->execute();
                    $res = $stmt->get_result()->fetch_assoc();
                    $articleId = $res['traduccion_id'] ?? null;
                }
            }

            // Si encontramos el artículo, resolvemos el slug para el idioma destino ($lang)
            if ($articleId) {
                if ($lang === 'es') {
                    // Si el destino es español, obtenemos el slug base de la tabla blog
                    $stmt = $mysqli->prepare('SELECT slug FROM blog WHERE id = ? LIMIT 1');
                    if ($stmt) {
                        $stmt->bind_param('i', $articleId);
                        $stmt->execute();
                        $res = $stmt->get_result()->fetch_assoc();
                        if (!empty($res['slug'])) {
                            $slug = $res['slug'];
                        }
                    }
                } else {
                    // Si el destino es otro idioma, buscamos su traducción en la tabla traducciones
                    $stmt = $mysqli->prepare("SELECT texto FROM traducciones WHERE tabla = 'blog' AND traduccion_id = ? AND campo = 'slug' AND idioma = ? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param('is', $articleId, $lang);
                        $stmt->execute();
                        $res = $stmt->get_result()->fetch_assoc();
                        if (!empty($res['texto'])) {
                            $slug = $res['texto'];
                        }
                    }
                }
            }
        }

        $base = defined('BASE_PATH') ? BASE_PATH : '';
        $url = $base . '/' . $lang . '/' . $segment . '/' . rawurlencode($slug);
        return $url . (!empty($query) ? '?' . http_build_query($query) : '');
    }

    // 2. Resto de páginas estándar
    $target_map = $url_map[$lang] ?? $url_map['es'];
    $path = ($page_key !== null && isset($target_map[$page_key]))
        ? $target_map[$page_key]
        : '';

    $base = defined('BASE_PATH') ? BASE_PATH : '';
    $url = $base . '/' . $lang . '/' . ltrim($path, '/');
    return $url . (!empty($query) ? '?' . http_build_query($query) : '');
}

/**
 * Genera una URL localizada para una página clave.
 * - Para 'fabricante', 'producto' o 'blog' con ['slug' => ...]: ruta bonita y con el
 *   segmento ya traducido al idioma activo, ej. /es/fabricante/kenogard,
 *   /en/manufacturer/kenogard, /fr/fabricant/kenogard.
 * - Para el resto (ej. 'index'): ruta de archivo, ej. /es/index.php.
 *
 * @param string $page_key La clave de la página (ej. 'fabricante', 'producto', 'index').
 * @param array $params Parámetros. Usa 'slug' para fabricante/producto.
 * @return string La URL completa y localizada.
 */
function __url($page_key, $params = []) {
    global $current_url_map, $current_lang, $current_page_segment_map;

    if (in_array($page_key, ['fabricante', 'producto'], true) && empty($params['slug']) && isset($params['id'])) {
        $id = (int)$params['id'];
        return BASE_PATH . '/' . $current_lang . '/' . $page_key . '.php?id=' . $id;
    }

    if (in_array($page_key, ['fabricante', 'producto', 'blog'], true) && !empty($params['slug'])) {
        $segment = $current_page_segment_map[$page_key] ?? $page_key;
        $slug = $params['slug'];
        unset($params['slug']);
        $url = BASE_PATH . '/' . $current_lang . '/' . $segment . '/' . rawurlencode($slug);
        return $url . (!empty($params) ? '?' . http_build_query($params) : '');
    }

    $path = $current_url_map[$page_key] ?? ($page_key . '.php');
    $url = BASE_PATH . '/' . $current_lang . '/' . $path;
    return $url . (!empty($params) ? '?' . http_build_query($params) : '');
}

/**
 * Resuelve una ruta de imagen/archivo guardada en la Base de Datos para que funcione
 * bajo cualquier URL (ej. /insumos/es/producto/algo), sin importar la profundidad de la URL.
 * Las imágenes antiguas de fabricantes se buscan en sus nuevas carpetas logo/ y productos/.
 * Si la ruta ya es absoluta (http://, https:// o empieza por /), se devuelve tal cual,
 * salvo que corresponda a una imagen de fabricante movida a una de esas carpetas.
 * Si es relativa (ej. 'uploads/logo.png'), se le antepone BASE_PATH para que apunte
 * siempre a la carpeta real del sitio y no a la carpeta del idioma/segmento actual.
 */
function __asset_url($path) {
    if (empty($path)) {
        return $path;
    }
    if (preg_match('#^(https?:)?//#i', $path)) {
        return $path;
    }

    $isRootRelative = $path[0] === '/';
    $relativePath = preg_replace('#^(?:\./)+#', '', ltrim($path, '/'));
    if (preg_match('#^media/fabricantes/([^/]+)/([^/]+)$#i', $relativePath, $matches)) {
        foreach (['logo', 'productos'] as $imageType) {
            $candidate = 'media/fabricantes/' . $matches[1] . '/' . $imageType . '/' . $matches[2];
            $candidateFile = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
            if (is_file($candidateFile)) {
                return $isRootRelative ? '/' . $candidate : BASE_PATH . '/' . $candidate;
            }
        }
    }

    if ($isRootRelative) {
        return $path;
    }
    return BASE_PATH . '/' . ltrim($path, './');
}

/**
 * Traduce campos dinámicos almacenados en la Base de Datos.
 * Si el idioma es 'es' o la traducción no existe en la tabla, devuelve el texto base.
 */
function __tdb($mysqli, $tabla, $traduccion_id, $campo, $textoOriginal) {
    global $current_lang;

    if ($current_lang === 'es' || empty($textoOriginal)) {
        return $textoOriginal;
    }

    $stmt = $mysqli->prepare('SELECT texto FROM traducciones WHERE tabla = ? AND traduccion_id = ? AND campo = ? AND idioma = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('siss', $tabla, $traduccion_id, $campo, $current_lang);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            return $row['texto']; // Retorna tu traducción revisada de MySQL
        }
        $GLOBALS['__tdb_debug'] = "prepare OK, pero 0 filas encontradas para tabla={$tabla} traduccion_id={$traduccion_id} campo={$campo} idioma={$current_lang}";
    } else {
        // Captura el motivo real por el que prepare() falló (ej. columna inexistente)
        $GLOBALS['__tdb_debug'] = "PREPARE FALLÓ: " . $mysqli->error;
    }

    return $textoOriginal; // Retorna el idioma original si no hay traducción cargada aún
}
?>