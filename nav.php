<?php
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/db_connection.php';

// Garantizar que la variable de idioma actual esté siempre definida (Evita Fatal Error)
$current_lang = $current_lang ?? $_SESSION['lang'] ?? 'es';

// Idiomas disponibles en el selector (código => etiqueta corta)
$navLangLabels = [
    'es' => 'ES', 
    'en' => 'EN', 
    'pt' => 'PT', 
    'fr' => 'FR', 
    'ca' => 'CA'
];

// Nombre completo de cada idioma (para el desplegable)
$navLangNames = [
    'es' => 'Español',
    'en' => 'English',
    'pt' => 'Português',
    'fr' => 'Français',
    'ca' => 'Català',
];

// Código de bandera de país (ISO) para cada idioma
$navLangFlagCodes = [
    'es' => 'es',
    'en' => 'gb',
    'pt' => 'pt',
    'fr' => 'fr',
];

/**
 * Devuelve el marcado HTML de la bandera para un código de idioma dado.
 */
function nav_lang_flag_html($langCode, $navLangFlagCodes) {
    if ($langCode === 'ca') {
        return '<svg class="lang-flag" width="20" height="14" viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
             . '<rect width="9" height="6" fill="#FCDD09"/>'
             . '<rect y="0.667" width="9" height="0.667" fill="#DA121A"/>'
             . '<rect y="2" width="9" height="0.667" fill="#DA121A"/>'
             . '<rect y="3.333" width="9" height="0.667" fill="#DA121A"/>'
             . '<rect y="4.667" width="9" height="0.667" fill="#DA121A"/>'
             . '</svg>';
    }
    $flagCode = $navLangFlagCodes[$langCode] ?? $langCode;
    $base = defined('BASE_PATH') ? BASE_PATH : '';
    return '<img class="lang-flag" width="20" height="14" src="' . $base . '/media/flags/' . htmlspecialchars($flagCode) . '.svg" alt="">';
}

// Build fabricantes + productos tree for dropdown
$navFabricantes = [];
$navResult = $mysqli->query(
    'SELECT
        f.id     AS fabricante_id,
        f.nombre AS fabricante_nombre,
        f.slug   AS fabricante_slug,

        p.id     AS producto_id,
        p.nombre AS producto_nombre,
        p.slug   AS producto_slug

     FROM fabricantes f
     LEFT JOIN productos p
        ON p.fabricante_id = f.id AND UPPER(p.activo) = \'S\'
    WHERE f.activo = \'S\'

     ORDER BY f.nombre ASC, p.nombre ASC'
);

if ($navResult) {
    while ($row = $navResult->fetch_assoc()) {
        $fid = $row['fabricante_id'];
        if (!isset($navFabricantes[$fid])) {
            $navFabricantes[$fid] = [
                'id'        => $fid,
                'slug'      => $row['fabricante_slug'],
                'nombre'    => $row['fabricante_nombre'],
                'productos' => [],
            ];
        }
        if (!empty($row['producto_id'])) {
            $navFabricantes[$fid]['productos'][] = [
                'id'     => $row['producto_id'],
                'slug'   => $row['producto_slug'],
                'nombre' => $row['producto_nombre'],
            ];
        }
    }
}
foreach ($navFabricantes as &$fab) {
    $fab['url'] = !empty($fab['slug'])
        ? __url('fabricante', ['slug' => $fab['slug']])
        : __url('fabricante', ['id' => (int)$fab['id']]);
}
unset($fab);
?>
<header class="site-nav" id="siteNav">
    <div class="nav-inner">
        <a href="<?php echo htmlspecialchars(__url('index')); ?>" class="nav-brand">
            <img src="/media/logo.png" alt="Logo Regenerative Agro Platform" class="nav-brand-logo">
            
            <span class="nav-brand-text">
                <span class="nav-brand-line1">Regenerative</span>
                <span class="nav-brand-line2">Agro Platform</span>
            </span>
        </a>
        <!-- Desktop navigation -->
        <nav class="nav-container" aria-label="Navegacion principal">
            <ul class="nav-menu">
                <li><a href="<?php echo htmlspecialchars(__url('index')); ?>"><?php echo __t('nav.inicio', 'Inicio'); ?></a></li>
                <li><a href="<?php echo htmlspecialchars(__url('index')); ?>#<?php echo htmlspecialchars(__anchor('impacto')); ?>"><?php echo __t('nav.impacto', 'Impacto'); ?></a></li>

                <!-- Fabricantes dropdown -->
                <li class="has-submenu">
                    <a href="<?php echo htmlspecialchars(__url('index')); ?>#<?php echo htmlspecialchars(__anchor('fabricantes')); ?>">
                        <?php echo __t('nav.fabricantes', 'Fabricantes'); ?> <i class="fas fa-chevron-down" style="font-size:0.65rem; margin-left:3px; opacity:0.6;"></i>
                    </a>
                    <ul class="submenu">
                        <?php foreach ($navFabricantes as $fab): ?>
                            <li class="<?php echo !empty($fab['productos']) ? 'has-submenu' : ''; ?>">
                                <a href="<?php echo htmlspecialchars($fab['url']); ?>">
                                    <?php echo htmlspecialchars($fab['nombre']); ?>
                                    <?php if (!empty($fab['productos'])): ?>
                                        <span class="submenu-arrow">&#8250;</span>
                                    <?php endif; ?>
                                </a>
                                <?php if (!empty($fab['productos'])): ?>
                                    <ul class="submenu submenu-level2" style="min-width: 220px;">
                                        <?php foreach ($fab['productos'] as $prod): ?>
                                            <li>
                                                <a href="<?php echo htmlspecialchars(__url('producto', ['slug' => $prod['slug']])); ?>">
                                                    <?php echo htmlspecialchars($prod['nombre']); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <li><a href="<?php echo htmlspecialchars(__url('index')); ?>#<?php echo htmlspecialchars(__anchor('contacto')); ?>"><?php echo __t('nav.contacto', 'Contacto'); ?></a></li>
                <li><a href="<?php echo htmlspecialchars(__url('blog')); ?>"><?php echo __t('nav.blog', 'Blog'); ?></a></li>
            </ul>
        </nav>

        <!-- Selector de idioma (desplegable con bandera) -->
        <div class="lang-switcher" id="langSwitcher">
            <button type="button" class="lang-switcher-btn" id="langSwitcherBtn" aria-haspopup="true" aria-expanded="false" aria-controls="langSwitcherMenu">
                <?php echo nav_lang_flag_html($current_lang, $navLangFlagCodes); ?>
                <span><?php echo htmlspecialchars($navLangLabels[$current_lang] ?? strtoupper($current_lang)); ?></span>
                <i class="fas fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="lang-switcher-menu" id="langSwitcherMenu" role="menu">
                <?php foreach ($navLangLabels as $langCode => $langLabel): ?>
                    <a href="<?php echo htmlspecialchars(__lang_switch_url($langCode)); ?>"
                       role="menuitem"
                       class="<?php echo $current_lang === $langCode ? 'active' : ''; ?>">
                        <?php echo nav_lang_flag_html($langCode, $navLangFlagCodes); ?>
                        <span class="lang-name"><?php echo htmlspecialchars($navLangNames[$langCode] ?? $langLabel); ?></span>
                        <span class="lang-code"><?php echo $langLabel; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Hamburger -->
        <button class="nav-toggle" id="navToggle" aria-label="Abrir menu" aria-expanded="false" aria-controls="navMobile">
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
        </button>
    </div>
</header>

<!-- Mobile menu -->
<nav class="nav-mobile" id="navMobile" aria-label="Menu movil">
    <ul class="mobile-menu-list">
        <li><a href="<?php echo htmlspecialchars(__url('index')); ?>"><?php echo __t('nav.inicio', 'Inicio'); ?></a></li>
        <li><a href="<?php echo htmlspecialchars(__url('index')); ?>#<?php echo htmlspecialchars(__anchor('impacto')); ?>"><?php echo __t('nav.impacto', 'Impacto'); ?></a></li>
        
        <!-- Capa 1: Fabricantes (Plegada principal) -->
        <li class="mobile-dropdown-container">
            <details class="mobile-details">
                <summary class="mobile-summary">
                    <span><?php echo __t('nav.fabricantes', 'Fabricantes'); ?></span>
                    <i class="fas fa-chevron-down mobile-arrow" aria-hidden="true"></i>
                </summary>
                
                <ul class="mobile-submenu-level1">
                    <?php foreach ($navFabricantes as $fab): ?>
                        <li>
                            <?php if (!empty($fab['productos'])): ?>
                                <!-- Capa 2: Fabricante individual con productos (Plegado secundario) -->
                                <details class="mobile-details-sub">
                                    <summary class="mobile-summary-sub">
                                        <a href="<?php echo htmlspecialchars($fab['url']); ?>" class="mobile-fab-link">
                                            <?php echo htmlspecialchars($fab['nombre']); ?>
                                        </a>
                                        <i class="fas fa-chevron-down mobile-arrow-sub" aria-hidden="true"></i>
                                    </summary>
                                    
                                    <!-- Capa 3: Productos -->
                                    <ul class="mobile-submenu-level2">
                                        <?php foreach ($fab['productos'] as $prod): ?>
                                            <li>
                                                <a href="<?php echo htmlspecialchars(__url('producto', ['slug' => $prod['slug']])); ?>">
                                                    <?php echo htmlspecialchars($prod['nombre']); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php else: ?>
                                <!-- Fabricante sin productos (enlace directo) -->
                                <a href="<?php echo htmlspecialchars($fab['url']); ?>" class="mobile-fab-link">
                                    <?php echo htmlspecialchars($fab['nombre']); ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        </li>

        <li><a href="<?php echo htmlspecialchars(__url('index')); ?>#<?php echo htmlspecialchars(__anchor('contacto')); ?>"><?php echo __t('nav.contacto', 'Contacto'); ?></a></li>
        <li><a href="<?php echo htmlspecialchars(__url('blog')); ?>"><?php echo __t('nav.blog', 'Blog'); ?></a></li>
    </ul>
</nav>

<script>
(function () {
    const nav         = document.getElementById('siteNav');
    const toggle      = document.getElementById('navToggle');
    const mobile      = document.getElementById('navMobile');
    const langSwitcher = document.getElementById('langSwitcher');
    const langBtn      = document.getElementById('langSwitcherBtn');

    if (langSwitcher && langBtn) {
        langBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = langSwitcher.classList.toggle('open');
            langBtn.setAttribute('aria-expanded', isOpen);
        });

        document.addEventListener('click', function (e) {
            if (!langSwitcher.contains(e.target)) {
                langSwitcher.classList.remove('open');
                langBtn.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                langSwitcher.classList.remove('open');
                langBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function onScroll() {
        nav.classList.toggle('scrolled', window.scrollY > 20);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    toggle.addEventListener('click', function () {
        const isOpen = mobile.classList.toggle('open');
        toggle.setAttribute('aria-expanded', isOpen);
        toggle.setAttribute('aria-label', isOpen ? 'Cerrar menu' : 'Abrir menu');
    });

    mobile.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            mobile.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        });
    });

    document.addEventListener('click', function (e) {
        if (!nav.contains(e.target) && !mobile.contains(e.target)) {
            mobile.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>