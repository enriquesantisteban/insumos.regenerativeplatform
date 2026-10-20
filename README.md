# Regenerative Agro Platform 🌱

Plataforma web dinámica desarrollada en **PHP nativo y MariaDB/MySQL** diseñada para conectar fabricantes, distribuidores y agricultores en el sector de la **agricultura regenerativa**, facilitando la catalogación de insumos agrícolas sostenibles y la gestión de suscripciones comerciales.

---

## 📌 Características Principales

- **Catálogo de Insumos y Fabricantes:** Directorio clasificado por categorías, tipos y subtipos de insumos específicos para prácticas de regeneración de suelos.
- **Arquitectura Multiidioma (i18n):** Soporte de internacionalización para múltiples idiomas (`es`, `en`, `fr`, `ca`, `pt`) mediante routing semántico y plantillas modulares (`lang/`).
- **Sistema Automatizado de Suscripciones (Cron Job):** Script en segundo plano (`cron_renovaciones.php`) que audita el vencimiento de los planes de prueba y suscripciones de los fabricantes, notificando automáticamente mediante **PHPMailer** con avisos preventivos a 30 días de la expiración.
- **Sección de Noticias y Blog:** Módulo de artículos informativos y novedades del sector con opciones para compartir contenido por correo.
- **Despliegue Continuo Automatizado:** Flujo de actualización en servidor Linux mediante script Bash (`update.sh`) con gestión de ramas Git, recreación segura de esquemas y ajuste estricto de permisos `www-data`.

---

## 🛠️ Tecnologías Utilizadas

- **Backend:** PHP 8.x (Programación procedimental/modular estructurada)
- **Base de Datos:** MariaDB / MySQL (Restricciones de claves foráneas, índices optimizados y soporte UTF-8 mb4)
- **Servicio de Correo:** PHPMailer
- **Servidor Web:** Nginx / Apache (`.htaccess` con reescritura de URLs amigables)
- **Entorno de Servidor:** Ubuntu Server (Despliegues en producción y automatización con Cron)
- **Frontend:** HTML5 semántico, CSS3 modular (`style.css`), diseño responsivo

---

## 📁 Estructura del Proyecto

```text
├── cron_renovaciones.php   # Tarea programada para control de vencimientos y alertas
├── db_connection.php       # Manejo de conexión a la base de datos
├── config.example.php      # Plantilla de credenciales y variables de entorno
├── i18n.php                # Motor de internacionalización y detección de idioma
├── lang/                   # Diccionarios y traducciones por idioma
├── PHPMailer/              # Librería para envío de correos transaccionales
├── blog.php / news.php     # Vistas y lógica del módulo de noticias
├── fabricante.php          # Detalle y catálogo filtrado por fabricante
├── producto.php            # Ficha técnica y clasificación de insumos
├── media/                  # Recursos gráficos y logotipos de empresas
├── .gitignore              # Exclusión de datos sensibles y archivos de entorno
└── update.sh               # Script de despliegue y sincronización en producción
```

## 👤 Autor
Enrique Santisteban Fernández
