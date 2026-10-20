<?php
/**
 * config.example.php
 * Plantilla de configuración. Copia este archivo como "config.php" en el mismo
 * directorio y rellena tus propias credenciales. config.php NUNCA se sube a git.
 */

// --- Base de datos ---
define('DB_HOST', 'localhost');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_password');
define('DB_NAME', 'insumos');

// --- Correo SMTP (formulario de contacto) ---
define('SMTP_HOST', 'smtp.tu-proveedor.com');
define('SMTP_USER', 'tu_correo@ejemplo.com');
define('SMTP_PASS', 'tu_password_smtp');
define('SMTP_PORT', 465);
define('SMTP_FROM_NAME', 'Regenerative Agro Platform'); 

// --- Seguridad de tareas programadas (Cron) ---
define('CRON_TOKEN', 'tu_clave_secreta_aqui_123456'); // Genera una clave aleatoria alfanumérica larga