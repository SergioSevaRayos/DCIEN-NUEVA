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
- En producción usa HTTP Basic Auth (`dcien-backend/admin-descargas/.htpasswd`): usuario `admin`. Contraseña actual guardada en `dcien-backend/.env` (`ADMIN_PANEL_USER`/`ADMIN_PANEL_PASSWORD`) — **no aquí**, este fichero está en git. Para resetearla: `htpasswd -nbB admin '<password-nueva>'` y pegar la línea resultante en `.htpasswd`, luego `.\deploy.ps1 -extras`.
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

## Tablas adicionales en BD (no están en el dump original)

```sql
-- Acceso prioritario por serie
CREATE TABLE series_priority (
  id INT AUTO_INCREMENT PRIMARY KEY,
  series_slug VARCHAR(100) NOT NULL UNIQUE,
  priority_until DATETIME NOT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE series_priority_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  series_slug VARCHAR(100) NOT NULL,
  user_id INT NOT NULL,
  granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (series_slug, user_id)
);

-- Leads de ARSENAL (captura de email sin cuenta, desbloqueo de herramientas vía localStorage)
-- tool_slug NUNCA debe ser NULL: MySQL no compara NULL=NULL en UNIQUE KEY, así que
-- dedupar por (email, tool_slug) requiere el default 'general' en vez de nullable.
CREATE TABLE arsenal_leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  tool_slug VARCHAR(100) NOT NULL DEFAULT 'general',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (email, tool_slug)
);

-- Columna gender en series (necesaria para list.php)
ALTER TABLE series ADD COLUMN gender ENUM('male','female','unisex') DEFAULT 'unisex';

-- Historial de emails enviados desde el Gestor de Atletas (admin-descargas/modules/usuarios.php).
-- Se rellena centralizadamente desde sendAdminMail() en modules/config.php, así que cubre
-- tanto comunicados (enviar_email_campana) como protocolos de descuento (enviar_email_protocolo)
-- sin tener que instrumentar cada punto de llamada por separado.
CREATE TABLE admin_email_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  recipient_email VARCHAR(255) NOT NULL,
  recipient_username VARCHAR(255) DEFAULT NULL,
  email_type VARCHAR(50) NOT NULL DEFAULT 'general',
  subject VARCHAR(255) NOT NULL,
  body_html MEDIUMTEXT,
  status ENUM('sent','failed') NOT NULL,
  error_message VARCHAR(500) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_created_at (created_at),
  INDEX idx_status (status)
);

-- Contraseña temporal en claro para poder reenviar el mismo acceso desde el
-- Gestor de Tokens (admin-descargas/modules/tokens.php) sin regenerarlo.
-- temp_password_hash sigue siendo la fuente de verdad para el login (bcrypt,
-- irreversible); temp_password_plain es solo para mostrarla en el panel admin
-- y deja de tener valor en cuanto el token se usa (activated_with_token).
ALTER TABLE activation_tokens ADD COLUMN temp_password_plain VARCHAR(50) DEFAULT NULL;
```

## Generación de PDFs (Dompdf)

- Dompdf v3.1 está en `dcien-backend/vendor/` (añadido vía composer)
- **No usar `display:flex` ni `display:grid`** en HTML para Dompdf — solo tablas
- **Márgenes**: `@page { margin: 0 }` + `body { padding: 14mm 17mm }` — Dompdf ignora `@page margin` con reset CSS
- **Imágenes**: incrustar como base64 con GD redimensionado. `object-fit` no funciona en Dompdf
- Los PDFs se generan en `admin-descargas/ordenes/` con nombre `orden_{order_number}_{timestamp}.pdf`

## Rutas de vendor y public_html — reglas críticas

**NUNCA debe haber `vendor/` en `public_html/`**. Si existe, eliminarlo:
```bash
ssh -p 65002 u755459505@147.79.103.7 "rm -rf /home/u755459505/domains/d-cien.es/public_html/vendor/"
```
El único vendor válido es `dcien-backend/vendor/`. Tenerlo duplicado provoca `Cannot declare class ComposerAutoloader...` al cargar el admin de pedidos.

**Path a vendor desde `admin-descargas/ordenes/index.php`** varía entre entornos:
- Local: `__DIR__ . '/../../vendor/autoload.php'` → `dcien-backend/vendor/`
- Producción (servido desde `public_html/`): `__DIR__ . '/../../../dcien-backend/vendor/autoload.php'`

El código carga el de producción primero y hace fallback al local.

**Path a imágenes** también varía:
- Local: `public/images/series/`
- Producción: `public_html/images/series/`

`img_b64()` en `ordenes/index.php` detecta cuál existe comprobando si `../../../public/images/` es un directorio válido.

## API — añadir nuevos endpoints

El router en `public_html/api/index.php` (solo en servidor) tiene una lista cerrada de rutas. Para añadir un endpoint accesible directamente desde el navegador sin pasar por el router:

1. Crear la lógica real en `dcien-backend/api/{grupo}/{nombre}.php`
2. Crear un proxy en `server-extras/api/{grupo}/{nombre}.php` con este patrón:
```php
<?php
$backend_root = dirname(dirname(dirname(__DIR__)));
require_once $backend_root . '/dcien-backend/api/{grupo}/{nombre}.php';
```
3. Referenciar la URL con extensión `.php`: `https://d-cien.es/api/{grupo}/{nombre}.php`

Ejemplo real: `server-extras/api/stripe/cancel-checkout.php` → accesible en `d-cien.es/api/stripe/cancel-checkout.php`

## Cancelación de pago Stripe

Al crear una sesión de Stripe, la `cancel_url` apunta a `cancel-checkout.php` que:
1. Busca el pedido por `stripe_session_id` con status `pending/pendiente`
2. Libera las `series_units` a `available`
3. Elimina `order_items` y el pedido
4. Redirige a `/series-activas`

**No usar `STRIPE_CANCEL_URL` del .env como cancel_url directa** — el pedido quedaría huérfano en BD con la unidad bloqueada en estado `checkout`.

## Blog (`src/content/blog/`)

Colección de contenido de Astro (`type: 'content'`, definida en `src/content/config.ts`). Cada artículo es un `.md` en `src/content/blog/` con este frontmatter:

```yaml
---
title: "Título del artículo"
description: "Meta description — también se usa como description del JSON-LD"
keywords: "keyword 1, keyword 2, keyword 3"
publishDate: 2026-08-20
updatedDate: 2026-09-01       # opcional, solo si se edita tras publicar
coverImage: "/images/brand/xxx.webp"   # reutilizar imágenes de public/images/brand/, no hace falta generar nuevas
relatedSeries: ["serie-09", "serie-10"]   # slugs de src/data/series.ts — enlace interno real, no adorno
author: "Equipo DCIEN"        # opcional — si se omite, usa BLOG_DEFAULT_AUTHOR (src/lib/seo.ts)
healthDisclaimer: true         # true si el artículo da consejo de salud/entrenamiento/nutrición
---
```

**Reglas de contenido:**
- **Validar el tema con demanda real antes de escribir** — no intuir. Ver metodología usada para los 4 artículos existentes: se descartó "qué ropa llevar después de entrenar" por no tener ningún rastro de búsqueda real, y se sustituyó por temas confirmados (guías de HYROX, lesiones, protección física, nutrición) comprobando qué contenido ya sostienen clínicas/marcas especializadas de forma sostenida.
- 700-1000 palabras, tono directo de la marca ("protocolo", "registro", sin relleno de marketing vacío).
- Cierre honesto con enlace interno a `/marca` y/o series concretas de `/series-activas` — sin forzar la conexión si el tema no encaja de verdad con el producto (streetwear, no equipo técnico de entrenamiento).
- `healthDisclaimer: true` en cualquier artículo con consejo de salud, lesiones, nutrición o entrenamiento — el aviso se renderiza solo, no hay que escribirlo a mano en el markdown.
- Nunca copiar contenido de otras webs — investigar para validar el tema/estructura, pero redactar siempre en prosa 100% original.

**Estructura de URLs — sin silos por ahora:** las URLs se quedan planas en `/blog/{slug}`, sin subcarpetas por categoría (`/blog/hyrox/...`, `/blog/nutricion/...`). Decisión deliberada: la estructura de silos solo aporta señal SEO real cuando hay volumen suficiente por temática (referencia habitual: 15+ artículos por silo); con pocos artículos es arquitectura prematura, y cambiar la URL de un post ya publicado obliga a redirección 301 y arriesga el posicionamiento que ya tenga sin beneficio real a cambio. En su lugar: reforzar el enlazado interno entre artículos de la misma temática (ver `pacing-crossfit-hyrox.md` enlazando a `hyrox-principiantes-espana.md` como ejemplo) — aporta la mayoría del beneficio de un silo sin tocar URLs. Revisar esta decisión cuando haya masa crítica de contenido por tema.

**Lo que ya se genera solo (no tocar nada más al añadir un artículo):**
- Página de detalle (`/blog/{slug}`) y su entrada en el listado (`/blog`).
- SEO completo: `<title>`, meta description, canonical, Open Graph tipo `article` con `article:published_time/modified_time/author`, Twitter Card.
- JSON-LD `BlogPosting` (con `author`, `keywords`, `wordCount` calculado automáticamente) + `BreadcrumbList`.
- Entrada en el sitemap (`astro.config.mjs` ya permite `/blog/*` por defecto).
- Entrada en el feed RSS (`/blog/rss.xml`) y en `/llms.txt` — ambos se regeneran en cada build, no son ficheros estáticos a mantener a mano.

## Deploy (producción — Hostinger)

Ver [DEPLOY.md](DEPLOY.md). En resumen:
- Cambios en `src/` → `.\deploy.ps1 -build -frontend` (build Astro + subir `dist/`)
- Cambios en `dcien-backend/` → `.\deploy.ps1 -backend`
- Cambios en `server-extras/` → `.\deploy.ps1 -extras`
- `dist/` nunca se edita directamente, se regenera con cada build
