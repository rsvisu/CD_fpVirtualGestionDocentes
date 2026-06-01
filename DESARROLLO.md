# Guía de desarrollo

Cómo levantar el proyecto en local para desarrollo.

El entorno está configurado siguiendo el enfoque de **Entorno Local Nativo + Docker solo para la base de datos**. Esto ofrece el máximo rendimiento, perfecta integración con tu IDE (VS Code) y las herramientas de validación (`pest`, `pint`) instaladas localmente en tus dependencias de desarrollo.

## Requisitos

- PHP 8.2+ local.
- Composer local.
- Node.js 18+ y npm locales.
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (o simplemente el motor de Docker) para levantar MySQL.
- Git.

## Pasos de Configuración Inicial

```bash
# 1. Clonar el repositorio e instalar dependencias locales
git clone <url-del-repo>
cd fp-virtual-gestion-docentes
composer install
npm install

# 2. Configurar variables de entorno
cp .env.example .env
```

**Edita el fichero `.env`** y asegúrate de configurar los siguientes valores esenciales para el desarrollo local:

```ini
APP_ENV=local
APP_URL=http://localhost:8000

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1   # Esto es clave para conectar al puerto expuesto por Docker
DB_PORT=3306
DB_DATABASE=gestor_profesores
DB_USERNAME=gestor
DB_PASSWORD=gestor
```

```bash
# 3. Levantar la base de datos con Docker
docker compose up -d

# 4. Generar la APP_KEY, crear las tablas y hacer seed
php artisan key:generate
php artisan migrate:fresh --seed
```

## Flujo de Trabajo Diario

Una vez configurado, solo necesitas levantar los tres servicios de desarrollo. Puedes hacerlo individualmente o todo a la vez con el comando directo de composer:

### Opción Rápida (Recomendada)
```bash
composer dev
# Esto levantará a la vez el servidor de PHP, Node (Vite), logs y el worker de queues.
```

### Comandos manuales correspondientes:
- Backend: `php artisan serve` (La app estará en **http://localhost:8000**)
- Frontend: `npm run dev` (Vite, para hot-reload)
- Base de datos (si estuviera apagada): `docker compose up -d`

## Variables de entorno

El archivo `.env.example` contiene valores base. Para integraciones clave en local:

### Moodle (integración API)

Para que funcione la creación de docentes desde `/admin/alta-plataforma`:

```env
MOODLE_URL=https://tu-moodle.example
MOODLE_TOKEN=<token-de-web-services>
MOODLE_USER_AUTH=oauth2
MOODLE_USER_LANG=es
MOODLE_TIMEOUT=15
```

El token se obtiene en el Moodle de destino (Administración del sitio -> Servidor -> Web Services -> Tokens).

## Cómo se entra (login)

Hay dos guards distintos:

### /admin/login — área de administración

| Campo | Valor |
|---|---|
| Usuario | Admin |
| Contraseña | 12345678 |

Procede del `AdminSeeder`. Da acceso a `/admin/docentes`, `/admin/centros`, `/admin/alta-plataforma`.

### /login — usuarios de centro

El campo "usuario" es el **código del centro** (8 dígitos), no un email. Por defecto la contraseña es igual al usuario.

Ejemplos del `UsuarioSeeder`:

| Centro | Usuario | Contraseña |
|---|---|---|
| CPIFP Montearagón | 22002491 | 22002491 |
| IES Luis Buñuel | 50008460 | 50008460 |
| Campus Digital | 50020125 | 50020125 |

## Datos de prueba

`migrate --seed` solo carga tres seeders básicos (`CentrosCiclosModulosSeeder`, `UsuarioSeeder`, `AdminSeeder`). Los docentes no se siembran por defecto. Para generar docentes de prueba:

```bash
php artisan db:seed --class=DocenteSeeder
```

## Ejecución de utilidades y Tests

Dado que las dependencias (`vendor`) están instaladas en el host, tu IDE funcionará perfectamente y podrás ejecutar todos los binarios localmente sin fricción:

```bash
# Ejecutar Tests
./vendor/bin/pest
./vendor/bin/pest tests/Feature/GeneradorEmailVirtualTest.php --no-coverage
./vendor/bin/pest --filter "genera el email correcto"

# Formatear el código
./vendor/bin/pint

# Ejecutar un job en background (si no usas composer dev)
php artisan queue:work
```

Los tests usan `RefreshDatabase`, así que crean y destruyen las tablas en la propia BD (`gestor_profesores`). No afectan a datos manuales que tengas si los re-siembras después.

## Acceder a la BD desde el host

El puerto `3306` está expuesto. Configura DBeaver/TablePlus:

| Campo | Valor |
|---|---|
| Host | 127.0.0.1 |
| Port | 3306 |
| Database | gestor_profesores |
| User | gestor o root |
| Password | gestor o root12345 |

## Problemas frecuentes

**"[1044] Access denied for user 'gestor'@'%' to database 'gestor_profesores'"**  
El usuario autentica bien pero no tiene permisos sobre la BD: tu volumen db_data se inicializó antes (con otro nombre de BD o de usuario). Recrea el volumen:

```bash
docker compose down -v   # ¡borra la BD local!
docker compose up -d
php artisan migrate --seed
```

**Logs de Moodle vacíos**  
Las llamadas a la API de Moodle se loguean en `storage/logs/moodle_api.log` (canal `moodle_api`), separado de `laravel.log`. Si no aparece nada, comprueba que MOODLE_URL y MOODLE_TOKEN están rellenos.

_Nota: El `Dockerfile` y scripts adjuntos como `start.sh` o `supervisord.conf` son utilizados estrictamente para empaquetar la aplicación en el despliegue de Producción._
