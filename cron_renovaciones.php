<?php
// 1. Cargar configuración antes de comprobar nada
require_once __DIR__ . '/config.php';

// 2. SEGURIDAD: Solo permitir ejecución por CLI o con el token de config.php
$es_consola = (php_sapi_name() === 'cli');
$es_web_autorizado = (isset($_GET['token']) && defined('CRON_TOKEN') && $_GET['token'] === CRON_TOKEN);

if (!$es_consola && !$es_web_autorizado) {
    http_response_code(403);
    die('Acceso denegado.');
}

// 3. Cargar el resto de dependencias existentes
require_once __DIR__ . '/db_connection.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

// 4. Buscar empresas cuya suscripción vence en los próximos 30 días y no se haya enviado aviso
$sql = "SELECT id, nombre, fecha_fin_suscripcion 
        FROM fabricantes 
        WHERE UPPER(activo) = 'S' 
          AND aviso_renovacion_enviado = 0 
          AND fecha_fin_suscripcion IS NOT NULL
          AND fecha_fin_suscripcion <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
          AND fecha_fin_suscripcion >= CURDATE()";

$result = $mysqli->query($sql);

if ($result && $result->num_rows > 0) {
    while ($empresa = $result->fetch_assoc()) {
        $mail = new PHPMailer(true);

        try {
            // Configuración SMTP con las constantes existentes de config.php
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            // Remitente y Destinatario (te llega a ti para gestionar la renovación)
            $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
            $mail->addAddress(SMTP_USER, 'Administración Insumos');

            $mail->isHTML(true);
            $mail->Subject = 'Aviso de vencimiento de suscripción: ' . $empresa['nombre'];
            $mail->Body    = "Hola,<br><br>La suscripción de la empresa <b>" . htmlspecialchars($empresa['nombre']) . "</b> finaliza el día <b>" . date('d/m/Y', strtotime($empresa['fecha_fin_suscripcion'])) . "</b> (en menos de 30 días).<br><br>Es momento de contactar con ellos para tramitar la renovación de su licencia de 9 meses.";
            $mail->AltBody = "La suscripción de la empresa " . $empresa['nombre'] . " finaliza el día " . date('d/m/Y', strtotime($empresa['fecha_fin_suscripcion'])) . ". Contactar para renovar.";

            $mail->send();

            // 5. Marcar en la BD que el aviso ya fue enviado
            $updateStmt = $mysqli->prepare("UPDATE fabricantes SET aviso_renovacion_enviado = 1 WHERE id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param('i', $empresa['id']);
                $updateStmt->execute();
            }

            echo "Aviso enviado para: " . $empresa['nombre'] . "<br>";
        } catch (Exception $e) {
            echo "Error al enviar aviso para " . $empresa['nombre'] . ": " . $mail->ErrorInfo . "<br>";
        }
    }
} else {
    echo "No hay empresas próximas a vencer hoy.<br>";
}