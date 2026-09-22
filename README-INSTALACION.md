# SAS-PDV — Manual de Instalación

## Índice
1. [Requisitos](#1-requisitos)
2. [Instalación local](#2-instalación-local)
3. [Instalación en producción](#3-instalación-en-producción)
4. [Variables de entorno (.env)](#4-variables-de-entorno-env)
5. [Configuración de correo (Resend)](#5-configuración-de-correo-resend)
6. [Configuración de WebSockets (Reverb)](#6-configuración-de-websockets-reverb)
7. [Configuración de push notifications (VAPID)](#7-configuración-de-push-notifications-vapid)
8. [Supervisores (producción)](#8-supervisores-producción)

---

## 1. Requisitos

| Herramienta | Versión mínima |
|-------------|---------------|
| PHP         | 8.3+          |
| Composer    | 2.x           |
| Node.js     | 18+           |
| MySQL       | 8.0+          |
| Git         | cualquiera    |

Extensiones PHP requeridas: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`,
`xml`, `ctype`, `json`, `bcmath`, `gd`, `zip`.

---

## 2. Instalación local

### 2.1 Clonar el repositorio

```bash
git clone https://github.com/tu-usuario/sas-pdv.git
cd sas-pdv
```

### 2.2 Instalar dependencias

```bash
composer install
npm install
```

### 2.3 Crear el archivo .env

```bash
cp .env.example .env
php artisan key:generate
```

Edita `.env` con los valores de tu entorno local (ver sección 4).

### 2.4 Crear la base de datos

```sql
CREATE DATABASE sas_pdv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2.5 Ejecutar migraciones y seeders

```bash
php artisan migrate --seed
```

### 2.6 Crear enlace de almacenamiento

```bash
php artisan storage:link
```

### 2.7 Compilar assets

```bash
npm run dev        # desarrollo con hot-reload
# npm run build    # compilación final
```

### 2.8 Iniciar el servidor

```bash
php artisan serve
```

Accede en `http://localhost:8000/admin`.

> **Nota:** Para el panel PDV con multi-tenant necesitas configurar un dominio
> local (ej. con Valet o XAMPP con hosts virtuales).

---

## 3. Instalación en producción

Seguir el manual:
**[MANUAL DE DESPLIEGUE LARAVEL CON CLOUDPANEL Y DPLOY](https://drive.google.com)**
(disponible en el Drive del equipo).

Ese manual cubre la configuración del servidor VPS con CloudPanel y el flujo
de despliegue automático con Dploy. Una vez completado el despliegue base,
continúa con las secciones siguientes de este documento.

---

## 4. Variables de entorno (.env)

Copia `.env.example` y ajusta los valores. Las secciones críticas:

```env
APP_NAME=tukipu
APP_ENV=production           # local en desarrollo
APP_DEBUG=false              # true en desarrollo
APP_URL=https://tukipu.cloud

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nombre_base_datos
DB_USERNAME=usuario_db
DB_PASSWORD=contraseña_db

QUEUE_CONNECTION=database    # sync solo para pruebas locales rápidas
```

---

## 5. Configuración de correo (Resend)

El sistema usa [Resend](https://resend.com) para el envío de correos transaccionales
(recuperación de contraseña, notificaciones, etc.).

### 5.1 Crear cuenta y dominio en Resend

1. Crear cuenta en https://resend.com
2. Ir a **Domains** → **Add Domain** y agregar el dominio (ej. `tukipu.cloud`)
3. Agregar los registros DNS que Resend indica (TXT y MX) en el panel de tu proveedor de dominio
4. Esperar la verificación (puede tardar unos minutos)
5. Ir a **API Keys** → **Create API Key** y copiar la clave generada

### 5.2 Configurar .env

```env
MAIL_MAILER=smtp
MAIL_SCHEME=ssl
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_USERNAME=resend
MAIL_PASSWORD=re_XXXXXXXXXXXXXXXXXXXX   # API Key de Resend
MAIL_FROM_ADDRESS="no-reply@tukipu.cloud"
MAIL_FROM_NAME="${APP_NAME}"
```

> **Nota:** El plan gratuito de Resend incluye 3,000 correos/mes y 100/día.

---

## 6. Configuración de WebSockets (Reverb)

Laravel Reverb gestiona las conexiones en tiempo real (notificaciones live,
actualizaciones de pedidos, etc.).

### 6.1 .env en producción

```env
REVERB_APP_ID=tu_app_id
REVERB_APP_KEY=tu_app_key
REVERB_APP_SECRET=tu_app_secret
REVERB_HOST=127.0.0.1
REVERB_PORT=9001
REVERB_SCHEME=http

REVERB_MAX_REQUEST_SIZE=500000
REVERB_APP_MAX_MESSAGE_SIZE=500000
```

> Reverb escucha internamente en `127.0.0.1:9001`. El VHost de nginx expone
> ese puerto al exterior bajo `/app` con SSL.

### 6.2 Configurar el VHost en nginx (CloudPanel)

En el archivo de configuración del sitio en CloudPanel, dentro del bloque
`server`, agregar:

```nginx
location /app {
    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_set_header Scheme $scheme;
    proxy_set_header SERVER_PORT $server_port;
    proxy_set_header REMOTE_ADDR $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";

    proxy_pass http://0.0.0.0:9001;
}
```

Esto permite que el frontend se conecte a `wss://tukipu.cloud/app` y nginx
enrute la conexión WebSocket a Reverb.

### 6.3 .env en desarrollo local

```env
REVERB_APP_ID=tu_app_id
REVERB_APP_KEY=tu_app_key
REVERB_APP_SECRET=tu_app_secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

Iniciar Reverb localmente:

```bash
php artisan reverb:start
```

---

## 7. Configuración de push notifications (VAPID)

### ¿Qué son las notificaciones push?

Son notificaciones que el sistema envía **directamente al navegador** del
usuario administrador, incluso cuando no tiene la pestaña abierta — similar
a las notificaciones de una app móvil.

**Cuándo se envían en este sistema:**
- Cuando llega una **nueva orden** desde el catálogo o el PDV — se notifica
  automáticamente a todos los usuarios con rol **Administrador** de esa empresa.

El usuario debe haber dado permiso de notificaciones en el navegador al menos
una vez (el sistema muestra el prompt de permiso en el panel PDV).

**VAPID** (Voluntary Application Server Identification) son las claves
criptográficas que identifican tu servidor ante el servicio push del navegador
(Chrome, Firefox, etc.) y garantizan que las notificaciones vengan de tu app.

### 7.1 Generar claves VAPID

Solo se hace **una vez** por instalación. Las claves se guardan permanentemente:

```bash
php artisan webpush:vapid
```

Copia las tres claves que genera al `.env`:

```env
VAPID_PUBLIC_KEY=clave_publica_generada
VAPID_PRIVATE_KEY=clave_privada_generada
VAPID_SUBJECT=mailto:admin@tukipu.cloud
```

> **Importante:** Las claves VAPID deben mantenerse estables entre deploys.
> Si se regeneran, todos los usuarios suscritos pierden sus suscripciones y
> deben volver a dar permiso en el navegador. En producción, agrégalas al
> `.env` del servidor y nunca las cambies.

### 7.2 ¿Por qué solo aparecen en producción?

En local no son necesarias: las notificaciones push requieren HTTPS para
funcionar en la mayoría de navegadores (Chrome las bloquea en HTTP salvo en
`localhost`). En entorno local puedes omitir las claves VAPID; el sistema
simplemente no enviará push pero funcionará con normalidad.

---

## 8. Supervisores (producción)

En producción el queue worker y Reverb deben correr como procesos persistentes
gestionados por Supervisor.

### 8.1 Crear archivos de configuración

Crear `/etc/supervisor/conf.d/tukipu-worker.conf`:

```ini
[program:tukipu-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/tukipu/htdocs/tukipu.cloud/current/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=tukipu
numprocs=2
redirect_stderr=true
stdout_logfile=/home/tukipu/logs/worker.log
stopwaitsecs=3600
```

Crear `/etc/supervisor/conf.d/tukipu-reverb.conf`:

```ini
[program:tukipu-reverb]
process_name=%(program_name)s_%(process_num)02d
command=php /home/tukipu/htdocs/tukipu.cloud/current/artisan reverb:start --host=127.0.0.1 --port=9001
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=tukipu
numprocs=1
redirect_stderr=true
stdout_logfile=/home/tukipu/logs/reverb.log
```

### 8.2 Activar y arrancar

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start tukipu-worker:*
sudo supervisorctl start tukipu-reverb:*
```

### 8.3 Reiniciar después de cada deploy

```bash
sudo supervisorctl restart tukipu-worker:*
sudo supervisorctl restart tukipu-reverb:*
```

O usando el comando de Laravel para señalizar a los workers:

```bash
php artisan queue:restart
```

---

> Para el manual de funcionalidades del sistema ver `README-FUNCIONALIDADES.md`.
