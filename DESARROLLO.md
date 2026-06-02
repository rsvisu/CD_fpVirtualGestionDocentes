# Guía de desarrollo

Cómo levantar el proyecto en local para desarrollo.

El entorno está configurado siguiendo un enfoque **entorno local nativo + docker para la base de datos**. Esto ofrece el máximo rendimiento, y perfecta integración con el IDE y las herramientas de validación (`pest`, `pint`) instaladas localmente en las dependencias de desarrollo.

## Índice

- [Requisitos](#requisitos)
- [Desarrollo en local](#desarrollo-en-local) — trabajar en tu propio PC
- [Servidor local de pruebas](#servidor-local-de-pruebas) — desplegar en la LAN para verlo por IP
- [Acceso a la aplicación (login)](#acceso-a-la-aplicación-login)
- [Datos de prueba](#datos-de-prueba)
- [Tests y utilidades](#tests-y-utilidades)
- [Acceder a la base de datos](#acceder-a-la-base-de-datos)
- [Variables de entorno (Moodle)](#variables-de-entorno-moodle)
- [Problemas frecuentes](#problemas-frecuentes)

## Requisitos

- PHP 8.2+ local.
- Composer local.
- Node.js 18+ y npm locales.
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (o simplemente el motor de Docker) para levantar MySQL.
- Git.

## Desarrollo en local

Trabajar en el código en tu propio PC: PHP y Node corren de forma nativa, y Docker solo levanta la base de datos.

### Configuración inicial (solo la primera vez)

```bash
# 1. Clonar el repositorio e instalar dependencias locales
git clone https://github.com/FPVirtual/fp-virtual-gestion-docentes.git
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
DB_HOST=127.0.0.1
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

### Flujo de trabajo diario

Son dos pasos: primero la base de datos (Docker), luego la app.

#### 1. Base de datos

```bash
docker compose up -d   # arranca MariaDB en segundo plano; solo si no está ya corriendo
```

#### 2. La app

```bash
composer dev
```

`composer dev` arranca **a la vez** estos cuatro procesos (con un solo `Ctrl+C` los paras todos):

| Proceso | Equivale a | Para qué |
|---|---|---|
| server | `php artisan serve` | La app en **http://localhost:8000** |
| vite | `npm run dev` | Assets con hot-reload |
| queue | `php artisan queue:listen --tries=1` | Worker de colas (jobs) |
| logs | `php artisan pail` | Visor de logs en vivo |

> **En Windows**, el proceso `logs` (pail) falla al arrancar porque requiere la extensión `pcntl`, que no existe en PHP para Windows. Es inofensivo: los otros tres siguen funcionando. Para ver logs, lee `storage/logs/laravel.log` directamente.

Si prefieres lanzarlos por separado, cada fila de la tabla es el comando individual equivalente.

## Servidor local de pruebas

Para levantar la aplicación en un servidor de la red local y acceder a ella desde otro equipo por IP. Usa `docker-compose.server.yml`, que construye la imagen completa (PHP + assets Vite compilados) y la sirve junto a MariaDB.

**Requisitos en el servidor:** Docker y Docker Compose. No necesita PHP ni Node instalados.

Cómo funciona: la imagen lleva dentro el código y los assets ya compilados; la app se sirve con `php artisan serve` (suficiente para una demo interna en la LAN, no para tráfico real). MariaDB no expone ningún puerto al exterior: solo es accesible para la app dentro de la red de Docker.

> **Importante:** el código queda *dentro* de la imagen Docker. Los cambios no se sincronizan automáticamente — hay que reconstruir la imagen (ver [Actualizar el código](#actualizar-el-código)).

### Configuración inicial (solo la primera vez)

```bash
# 1. Clonar el repositorio en el servidor
git clone https://github.com/FPVirtual/fp-virtual-gestion-docentes.git
cd fp-virtual-gestion-docentes

# 2. Crear el .env
cp .env.example .env
```

**Edita el `.env`** (se monta dentro del contenedor). Ajusta estos valores:

```ini
APP_ENV=local
APP_DEBUG=true
APP_URL=http://192.168.1.50:8082   # IP del servidor en tu red, con el puerto

DB_CONNECTION=mariadb
DB_HOST=mariadb-db   # nombre del servicio docker (NO 127.0.0.1)
DB_PORT=3306
DB_DATABASE=gestor_profesores
DB_USERNAME=gestor
DB_PASSWORD=gestor
```

#### 3. Generar la APP_KEY (una sola vez — se guarda en el .env del servidor)

Si el servidor **no tiene PHP** (lo normal usando Docker), genérala con un contenedor temporal. El `--rm` borra ese contenedor de usar y tirar al terminar; la clave queda guardada en tu `.env`:

```bash
docker compose -f docker-compose.server.yml run --rm fp-app php artisan key:generate
```

Si el servidor **sí tiene PHP** instalado, es más directo generarla sin Docker:

```bash
php artisan key:generate
```

### Levantar el servidor

```bash
docker compose -f docker-compose.server.yml build
docker compose -f docker-compose.server.yml up -d
```

La aplicación queda accesible en **`http://IP_DEL_SERVIDOR:8082`**.

Al arrancar, el contenedor ejecuta automáticamente las migraciones pendientes (solo aplica lo que falta) tras esperar a que la base de datos esté lista.

### Poblar con datos de prueba (opcional, solo la primera vez)

```bash
docker compose -f docker-compose.server.yml exec fp-app php artisan db:seed
docker compose -f docker-compose.server.yml exec fp-app php artisan db:seed --class=DocenteSeeder
```

### Actualizar el código

```bash
git pull
docker compose -f docker-compose.server.yml build
docker compose -f docker-compose.server.yml up -d
```

Los contenedores se recrean con la nueva imagen (código y assets actualizados). La base de datos no se toca.

### Otros comandos útiles

```bash
# Ver logs en tiempo real
docker compose -f docker-compose.server.yml logs -f

# Parar (conserva la BD)
docker compose -f docker-compose.server.yml down

# Consola dentro del contenedor de la app
docker compose -f docker-compose.server.yml exec fp-app bash

# Reiniciar solo la app (p.ej. tras cambiar el .env)
docker compose -f docker-compose.server.yml restart fp-app
```

### Resetear la base de datos

```bash
# ⚠ Borra todos los datos
docker compose -f docker-compose.server.yml down -v
docker compose -f docker-compose.server.yml up -d
```

## Acceso a la aplicación (login)

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

## Tests y utilidades

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

## Acceder a la base de datos

El puerto `3306` está expuesto. Configura DBeaver/TablePlus:

| Campo | Valor |
|---|---|
| Host | 127.0.0.1 |
| Port | 3306 |
| Database | gestor_profesores |
| User | gestor o root |
| Password | gestor o root12345 |

## Variables de entorno (Moodle)

El archivo `.env.example` contiene valores base. Para que funcione la creación de docentes desde `/admin/alta-plataforma`:

```env
MOODLE_URL=https://tu-moodle.example
MOODLE_TOKEN=<token-de-web-services>
MOODLE_USER_AUTH=oauth2
MOODLE_USER_LANG=es
MOODLE_TIMEOUT=15
```

El token se obtiene en el Moodle de destino (Administración del sitio -> Servidor -> Web Services -> Tokens).

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
