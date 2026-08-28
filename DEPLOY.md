# Guía de Despliegue en Hostinger — Impulsate

> Plataforma: Hostinger Shared Hosting / Business Hosting con cPanel  
> PHP requerido: 8.2+  
> URL de producción: https://lightcyan-mallard-509513.hostingersite.com/

---

## 1. Requisitos Previos

| Requisito | Versión |
|-----------|---------|
| PHP | 8.2+ (con extensiones: PDO, mbstring, openssl, tokenizer, xml, ctype, json, bcmath, fileinfo, gd) |
| MySQL | 8.0+ |
| Composer | 2.x |
| Node.js | 18+ (solo para build local) |
| Git | Cualquier versión |

En Hostinger: verifica que PHP 8.2 esté seleccionado en cPanel → PHP Version Manager.

---

## 2. Variables de Entorno (`.env` en producción)

```env
APP_NAME="Citas Impulsate"
APP_ENV=production
APP_KEY=base64:TU_CLAVE_GENERADA_AQUI
APP_DEBUG=false
APP_URL=https://lightcyan-mallard-509513.hostingersite.com

APP_LOCALE=es
APP_FALLBACK_LOCALE=es

LOG_CHANNEL=stack
LOG_LEVEL=error

# Base de datos MySQL de Hostinger
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nombre_de_tu_bd
DB_USERNAME=usuario_bd
DB_PASSWORD=contraseña_bd

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# SMTP — Gmail / Google Workspace (impulsate@iyemyucatan.com)
# Requiere 2FA activa y Contraseña de Aplicación de 16 chars generada en
# https://myaccount.google.com/apppasswords (NO usar la contraseña normal de la cuenta)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=impulsate@iyemyucatan.com
MAIL_PASSWORD=tu_app_password_16_chars
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="impulsate@iyemyucatan.com"
MAIL_FROM_NAME="Encuentro de Negocios Impulsate"

# Google OAuth (misma app, solo cambia el callback URL)
GOOGLE_CLIENT_ID=tu_client_id
GOOGLE_CLIENT_SECRET=tu_client_secret
GOOGLE_REDIRECT_URI=https://lightcyan-mallard-509513.hostingersite.com/auth/google/callback

# Token de acceso a la Pantalla TV
TV_TOKEN=<token-generado-por-evento-ver-/admin/tv>
```

> ⚠️ **Nunca subas el archivo `.env` al repositorio.** Créalo directamente en el servidor.

---

## 3. Pasos de Subida

### 3.1 Preparar localmente

```bash
# Instalar dependencias de producción (sin devDependencies)
composer install --no-dev --optimize-autoloader

# Compilar assets (ejecutar en tu máquina local)
npm install
npm run build
# Esto genera la carpeta public/build/

# Generar clave si no tienes una
php artisan key:generate --show
# Copia la clave al .env de producción
```

### 3.2 Subir archivos

**Opción A — FTP/SFTP (FileZilla u otro):**

Sube TODO el proyecto **excepto**:
- `node_modules/`
- `.env` (tu .env local)
- `storage/logs/*.log`
- `.git/` (opcional, puedes excluirlo)

Estructura en Hostinger:
```
/home/usuario/
  impulsate/          ← Carpeta del proyecto Laravel (fuera de public_html)
    app/
    bootstrap/
    config/
    database/
    public/           ← Contenido de esta carpeta va a public_html
    resources/
    routes/
    storage/
    vendor/
    .env              ← Crear aquí con los valores de producción
    artisan
    composer.json
    ...

  public_html/        ← Punto de entrada del dominio
    .htaccess
    index.php         ← Modificado para apuntar a /home/usuario/impulsate
    build/            ← Carpeta build de Vite
    images/
    favicon.ico
    ...
```

**Ajustar `public_html/index.php`** para apuntar al proyecto:
```php
<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Apuntar al vendor y bootstrap fuera de public_html
require __DIR__.'/../impulsate/vendor/autoload.php';

$app = require_once __DIR__.'/../impulsate/bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel
    ->handle($request = Request::capture())
    ->send();

$kernel->terminate($request, $response);
```

**Ajustar `public_html/.htaccess`**:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

**Opción B — Git en servidor (si Hostinger lo permite):**
```bash
cd /home/usuario/impulsate
git pull origin main
composer install --no-dev --optimize-autoloader
```

### 3.3 Subir archivos de assets

La carpeta `public/build/` generada por `npm run build` debe copiarse a `public_html/build/`.

---

## 4. Post-Deploy (ejecutar en el servidor vía SSH o Terminal de Hostinger)

```bash
cd /home/usuario/impulsate

# Ejecutar migraciones
php artisan migrate --force

# Seed de roles y permisos (solo primera vez)
php artisan db:seed --class=RolesAndPermissionsSeeder

# Crear symlink de storage
php artisan storage:link

# Cachear configuración (importante en producción)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Verificar que todo está bien
php artisan about
```

> Si no tienes SSH, usa el Terminal de cPanel (siempre disponible en Hostinger Business).

---

## 5. Scheduler (Recordatorios de Citas) — cron en cPanel

En cPanel → Cron Jobs, agrega:

```
* * * * * /usr/local/bin/php /home/USUARIO/impulsate/artisan schedule:run >> /dev/null 2>&1
```

Reemplaza `USUARIO` con tu nombre de usuario de Hostinger.

Esto ejecuta el scheduler de Laravel cada minuto, que a su vez corre `citas:enviar-recordatorios` cada 15 minutos.

---

## 6. Queue Worker (Jobs de correo)

Si Hostinger permite procesos en background (Business Hosting), agrega otro cron:

```
*/5 * * * * /usr/local/bin/php /home/USUARIO/impulsate/artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

Si no está disponible, cambia en `.env`:
```env
QUEUE_CONNECTION=sync
```
Esto procesa los jobs inmediatamente (sin cola), lo que puede ser más lento pero funciona en shared hosting.

---

## 7. Permisos

```bash
chmod -R 755 /home/usuario/impulsate/storage
chmod -R 755 /home/usuario/impulsate/bootstrap/cache
chmod 644 /home/usuario/impulsate/.env
```

---

## 8. Google OAuth — Actualizar Callback URL

En Google Cloud Console → Credenciales → OAuth 2.0:
- Agrega URI de redireccionamiento autorizado:
  `https://lightcyan-mallard-509513.hostingersite.com/auth/google/callback`

---

## 9. Pantalla TV

Acceder en:
```
https://lightcyan-mallard-509513.hostingersite.com/tv/<token-generado-por-evento-ver-/admin/tv>
```

Para cambiar el token, actualizar `TV_TOKEN` en `.env` y ejecutar `php artisan config:cache`.

---

## 10. Checklist Final de Verificación

- [ ] `APP_DEBUG=false` en `.env`
- [ ] `APP_ENV=production` en `.env`
- [ ] Base de datos conecta correctamente (`php artisan migrate:status`)
- [ ] `php artisan storage:link` ejecutado (imágenes subidas visibles)
- [ ] Correos funcionan (enviar un correo de prueba desde el dashboard)
- [ ] Google OAuth callback URL actualizada en Google Cloud Console
- [ ] HTTPS activo (Hostinger lo incluye con Let's Encrypt)
- [ ] Cron configurado para el scheduler
- [ ] Carpeta `public_html/build/` con los assets compilados
- [ ] `.env` NO está en el repositorio (`.gitignore` lo excluye)
- [ ] `php artisan config:cache`, `route:cache`, `view:cache` ejecutados
- [ ] Pantalla TV accesible en `/tv/TU-TOKEN`
- [ ] Migración de roles ejecutada (`php artisan db:seed --class=RolesAndPermissionsSeeder`)

---

## 11. Solución de Problemas Comunes

### Error 500 al acceder al sitio
```bash
# Ver logs de Laravel
tail -50 /home/usuario/impulsate/storage/logs/laravel.log
```

### Las imágenes no se muestran
```bash
php artisan storage:link
# Verifica que public_html/storage apunte a storage/app/public
```

### Error "Class not found" después de deploy
```bash
composer dump-autoload --optimize
php artisan config:clear && php artisan config:cache
```

### Sesiones no persisten
- Verifica que `SESSION_DRIVER=database` y la tabla `sessions` existe
- Ejecutar `php artisan migrate` si falta

### Correos no se envían
- Verifica credenciales SMTP en `.env`
- Prueba: `php artisan tinker` → `Mail::raw('test', fn($m) => $m->to('tu@email.com')->subject('test'))`

---

*Generado el 2026-06-03 — Proyecto Citas Impulsate*
