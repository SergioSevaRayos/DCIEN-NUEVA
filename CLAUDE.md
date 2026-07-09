# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Comandos de desarrollo

```bash
# Frontend Astro (puerto 4321)
npm run dev

# Backend PHP — NO es Laravel, no existe artisan
php -S localhost:8080 -t dcien-backend/

# Build de producción
npm run build

# Instalar dependencias del backend
cd dcien-backend && composer install
```

No hay tests automatizados en este proyecto.

## Arquitectura

El proyecto tiene tres capas independientes:

### 1. Frontend — Astro (`src/`)
- Output **estático** (`output: 'static'` en `astro.config.mjs`)
- Tailwind CSS + TypeScript
- SweetAlert2 para alertas, Partytown para scripts de terceros
- Las páginas que requieren auth comprueban la sesión via fetch al backend en el cliente

### 2. Backend — PHP puro (`dcien-backend/`)
- **No es Laravel.** No hay `artisan`, no hay ORM, no hay framework.
- Cada endpoint es un fichero `.php` individual dentro de `api/`
- Autenticación por **sesiones PHP** (cookies httpOnly `dcien_session`), no JWT
- Todo endpoint incluye `bootstrap.php` (autoload + .env) y `includes/cors.php`
- `config/database.php` expone `getDatabaseConnection()`, `query()`, `queryOne()`, `queryAll()`

### 3. Extras de servidor (`server-extras/`)
- Archivos PHP/JS que van directamente al servidor sin pasar por el build de Astro
- Incluye: carrito, panel admin, landing bonos QR, scripts JS globales
- **No requieren build**; se despliegan tal cual

## Variables de entorno

**Frontend** (`.env` en raíz):
```
PUBLIC_API_URL=http://localhost:8080/api   # URL del backend PHP para fetch del navegador
```

**Backend** (`dcien-backend/.env`):
```
DB_HOST=localhost
DB_NAME=u755459505_limited_tees
DB_USER=root
DB_PASS=
APP_ENV=development          # importante: activa cookies sin 'secure'
APP_URL=http://localhost:4321
JWT_SECRET=...
SESSION_SECRET=...
ADMIN_SECRET=...
CRON_SECRET=...
```

## Proxy Vite vs PUBLIC_API_URL

`astro.config.mjs` tiene un proxy Vite que reenvía `/api/*` a `http://dcien-backend.test` (virtual host configurado en producción/Windows). En Linux local sin ese virtual host, el frontend usa `import.meta.env.PUBLIC_API_URL` directamente en los `fetch()` — las dos aproximaciones coexisten.

## Datos de series

`src/data/series.ts` es la **fuente de verdad** para las series. El frontend lee de este fichero TypeScript, no de la BD. `isActive: true/false` controla qué series aparecen en `/series-activas`. La BD almacena los 100 números (`series_units`) y su estado de reserva/venta.

## Base de datos

- MySQL: `u755459505_limited_tees`
- Dump: `u755459505_limited_tees.sql` en la raíz del proyecto
- Para importar desde cero:
  ```bash
  mysql -u root -e "DROP DATABASE IF EXISTS u755459505_limited_tees; CREATE DATABASE u755459505_limited_tees CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  sed 's/utf8mb4_uca1400_ai_ci/utf8mb4_unicode_ci/g' u755459505_limited_tees.sql | mysql -u root u755459505_limited_tees
  ```

## Deploy (producción — Hostinger)

Ver [DEPLOY.md](DEPLOY.md). En resumen:
- Cambios en `src/` → `.\deploy.ps1 -build -frontend` (build Astro + subir `dist/`)
- Cambios en `dcien-backend/` → `.\deploy.ps1 -backend`
- Cambios en `server-extras/` → `.\deploy.ps1 -extras`
- `dist/` nunca se edita directamente, se regenera con cada build
