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
- Todo endpoint incluye `cors.php` y `session.php` (no hay bootstrap.php global)
- `config/database.php` expone `getDatabaseConnection()`, `query()`, `queryOne()`, `queryAll()`

### 3. Extras de servidor (`server-extras/`)
- Archivos PHP/JS que van directamente al servidor sin pasar por el build de Astro
- Incluye: carrito, panel admin, landing bonos QR, scripts JS globales
- **No requieren build**; se despliegan tal cual

## Variables de entorno

**Frontend** (`.env` en raíz):
```
PUBLIC_API_URL=/api   # ruta relativa — el proxy Vite reenvía al backend en local
```

**Backend** (`dcien-backend/.env`) — ejemplo completo para desarrollo local:
```
DB_HOST=localhost
DB_NAME=u755459505_limited_tees
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

APP_ENV=development          # CRÍTICO: activa cookies sin 'secure' y con dominio vacío
APP_URL=http://localhost:4321

SESSION_LIFETIME=604800
SESSION_NAME=dcien_session

# Stripe — usar claves de TEST (sk_test_...) en local, nunca las live
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_SUCCESS_URL=http://localhost:4321/checkout/success
STRIPE_CANCEL_URL=http://localhost:4321/series-activas

EMAIL_HOST=smtp.hostinger.com
EMAIL_PORT=465
EMAIL_USER=contacto@d-cien.es
EMAIL_PASS=...
EMAIL_FROM=contacto@d-cien.es
EMAIL_FROM_NAME="DCIEN"
```

## Sesiones PHP en desarrollo local — cosas importantes

`includes/session.php` adapta el comportamiento según `APP_ENV`:
- `APP_ENV=development` → cookies sin `Secure`, dominio vacío (localhost), `session_save_path(/tmp)`
- Sin `APP_ENV=development` → cookies con `Secure: true` y dominio `.d-cien.es` → **el navegador las ignora en localhost HTTP y la sesión no persiste**

En Linux, `/var/lib/php/sessions` puede no tener permisos de escritura para el usuario actual. `session.php` redirige a `sys_get_temp_dir()` (`/tmp`) cuando `APP_ENV=development`.

Para depurar sesiones en local: `GET http://localhost:8080/api/debug-session.php` — muestra cookies recibidas, datos de sesión y ruta de guardado. **Eliminar antes de producción.**

## Proxy Vite en desarrollo local

`astro.config.mjs` tiene un proxy que reenvía `/api/*` y `/admin-descargas/*` a `http://localhost:8080`. Con `PUBLIC_API_URL=/api` (ruta relativa), el navegador ve todo desde el mismo origen (`localhost:4321`) y las cookies de sesión funcionan correctamente.

En producción el proxy no se usa — el frontend sirve ficheros estáticos y el backend PHP está en el mismo servidor.

## Coste de envío

Actualmente **€10**. Si cambia, hay que actualizarlo en **7 sitios**:

| Fichero | Variable/Lugar |
|---|---|
| `src/components/CartDrawer.astro` | `const SHIPPING_FEE = 10.00` y texto `€10.00` |
| `src/pages/checkout/checkout-datos-envio.astro` | `const SHIPPING_FEE = 10.00` |
| `src/pages/checkout/checkout-resumen.astro` | `const SHIPPING_FEE = 10.00` |
| `src/pages/checkout/success.astro` | `order.shipping_fee ?? 10` (dos ocurrencias) |
| `dcien-backend/api/stripe/create-checkout-session.php` | `$shippingFee = 10.00` |
| `dcien-backend/api/cart/checkout.php` | `$shippingFee = 10.00` |

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

## Panel de administración (`dcien-backend/admin-descargas/`)

- Acceso local: `http://localhost:8080/admin-descargas/` — sin autenticación (el PHP built-in server ignora `.htpasswd`)
- En producción usa HTTP Basic Auth (`.htpasswd`): usuario `admin`
- Pipeline de órdenes en `ordenes/index.php`: pestañas Nuevos → Producción → Enviados
- **Eliminación de pedidos deshabilitada en la UI** — solo via SQL directo (decisión deliberada para evitar borrados accidentales). Para eliminar un pedido de prueba:
  ```sql
  -- Pedido de carrito (is_cart_order = 1)
  UPDATE series_units SET status = 'available'
    WHERE (series_slug, unit_number) IN (SELECT series_slug, unit_number FROM order_items WHERE order_id = ?);
  DELETE FROM order_items WHERE order_id = ?;
  DELETE FROM orders WHERE id = ?;
  -- Pedido individual
  UPDATE series_units SET status = 'available'
    WHERE series_slug = (SELECT series_slug FROM orders WHERE id = ?)
      AND unit_number = (SELECT unit_number FROM orders WHERE id = ?);
  DELETE FROM orders WHERE id = ?;
  ```

### Columnas añadidas a `orders` (no están en el dump original)
```sql
ALTER TABLE orders
  ADD COLUMN shipping_company VARCHAR(100) DEFAULT NULL,
  ADD COLUMN tracking_id      VARCHAR(200) DEFAULT NULL;
```
Estas columnas se guardan al marcar un pedido como enviado desde el modal de la UI (empresa de paquetería + ID de seguimiento).

### Tabla `order_documents`
```sql
CREATE TABLE order_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  size_bytes INT NOT NULL,
  uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_order_id (order_id)
);
```
Archivos físicos en `admin-descargas/ordenes/docs/{order_id}/`. Formatos permitidos: PDF, JPG, PNG, WEBP (máx. 10 MB).

### Regla importante: `generar_orden` no revierte estados
El action `generar_orden` solo avanza el estado a `produccion` si el pedido está en `paid/pending/pendiente`. Si ya está en `enviado` o `cancelled`, genera el documento HTML pero **no toca el status**. Esto evita el bug de revertir accidentalmente un pedido enviado a producción.

## Deploy (producción — Hostinger)

Ver [DEPLOY.md](DEPLOY.md). En resumen:
- Cambios en `src/` → `.\deploy.ps1 -build -frontend` (build Astro + subir `dist/`)
- Cambios en `dcien-backend/` → `.\deploy.ps1 -backend`
- Cambios en `server-extras/` → `.\deploy.ps1 -extras`
- `dist/` nunca se edita directamente, se regenera con cada build
