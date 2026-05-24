# Limited Tees - Plataforma de Venta de Ediciones Limitadas

Plataforma completa de comercio electrónico para venta de camisetas de edición limitada (100 unidades por serie) con Astro, Laravel backend concepts, TypeScript, Tailwind CSS y PHP.

## 🚀 Características

- ✅ Series limitadas de 100 unidades numeradas (01-100)
- ✅ Configuración de talla, tipo (king size / standard fit), color y número
- ✅ Navegación completa: inicio, archivo, series activas, marca, acceso
- ✅ Autenticación vía Instagram + usuario/contraseña
- ✅ Integración completa con Stripe para pagos
- ✅ Sistema de reserva temporal (5 minutos) para cada número
- ✅ Emails automáticos (bienvenida, recuperación de contraseña, confirmación de pedido)
- ✅ Recuperación de contraseña con tokens
- ✅ Tema oscuro/claro
- ✅ Modal de cookies con consentimiento
- ✅ Alertas con SweetAlert2
- ✅ SEO optimizado y centralizado
- ✅ Rendimiento PSI/Lighthouse optimizado
- ✅ Mobile-first responsive design
- ✅ Protección contra prácticas maliciosas
- ✅ Try-catch en todos los endpoints críticos

## 📋 Requisitos Previos

- Node.js 18+ y npm
- MySQL 8.0+
- Cuenta de Stripe (modo test)
- Cuenta de Instagram Developer (opcional para OAuth)
- Servicio de email (Resend, SendGrid, etc.)

## 🛠️ Instalación

### 1. Clonar e Instalar Dependencias

```bash
npm install
```

### 2. Configurar Base de Datos

Crear base de datos MySQL:

```bash
mysql -u root -p
CREATE DATABASE limited_tees CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;
```

Importar esquema:

```bash
mysql -u root -p limited_tees < database/schema.sql
```

### 3. Configurar Variables de Entorno

Copiar `.env.example` a `.env` y configurar:

```bash
cp .env.example .env
```

Editar `.env` con tus credenciales:

```env
# Database
DATABASE_URL="mysql://usuario:contraseña@localhost:3306/limited_tees"

# Stripe (obtener en https://dashboard.stripe.com/test/apikeys)
STRIPE_SECRET_KEY="sk_test_..."
STRIPE_PUBLIC_KEY="pk_test_..."
STRIPE_WEBHOOK_SECRET="whsec_..."

# Instagram OAuth (opcional)
INSTAGRAM_CLIENT_ID="tu_client_id"
INSTAGRAM_CLIENT_SECRET="tu_client_secret"
INSTAGRAM_REDIRECT_URI="http://localhost:4321/api/auth/instagram/callback"

# Email
EMAIL_FROM="noreply@limitedtees.com"
EMAIL_API_KEY="tu_api_key"

# App
APP_URL="http://localhost:4321"
JWT_SECRET="genera_una_clave_secreta_fuerte"
SESSION_SECRET="genera_otra_clave_secreta"
```

### 4. Configurar Stripe Webhook (en desarrollo)

Instalar Stripe CLI:

```bash
# macOS
brew install stripe/stripe-cli/stripe

# Otros sistemas: https://stripe.com/docs/stripe-cli
```

Login y configurar webhook:

```bash
stripe login
stripe listen --forward-to localhost:4321/api/checkout/webhook
```

Copiar el webhook secret que aparece y agregarlo a `.env` como `STRIPE_WEBHOOK_SECRET`.

## 🚀 Uso

### Desarrollo

```bash
npm run dev
```

La aplicación estará disponible en `http://localhost:4321`

### Build para Producción

```bash
npm run build
```

### Preview de Producción

```bash
npm run preview
```

## 📁 Estructura del Proyecto

```
limited-tees-project/
├── src/
│   ├── components/          # Componentes reutilizables
│   │   ├── Header.astro
│   │   ├── Footer.astro
│   │   ├── SEO.astro
│   │   ├── CookieBanner.astro
│   │   └── NumberSelector.astro
│   ├── layouts/             # Layouts de página
│   │   └── BaseLayout.astro
│   ├── pages/               # Rutas de la aplicación
│   │   ├── index.astro      # /
│   │   ├── series-activas/  # /series-activas
│   │   ├── archivo.astro    # /archivo
│   │   ├── marca.astro      # /marca
│   │   ├── acceso/          # /acceso
│   │   └── api/             # Endpoints API
│   │       ├── auth/
│   │       ├── checkout/
│   │       └── series/
│   ├── lib/                 # Utilidades
│   │   ├── types.ts         # Tipos TypeScript
│   │   ├── db.ts            # Conexión MySQL
│   │   ├── auth.ts          # Autenticación JWT
│   │   ├── email.ts         # Envío de emails
│   │   └── seo.ts           # Configuración SEO
│   └── styles/
│       └── global.css       # Estilos globales
├── public/
│   ├── images/              # Imágenes estáticas
│   └── fonts/               # Fuentes
├── database/
│   └── schema.sql           # Esquema de base de datos
├── package.json
├── astro.config.mjs
├── tailwind.config.mjs
└── tsconfig.json
```

## 🎨 Paleta de Colores

```javascript
colors: {
  black: '#000000',
  white: '#ffffff',
  red: '#ff0000',
  blue: '#1800ad',
  yellow: '#ffbd59',
}
```

## 🔐 Seguridad

- Autenticación JWT con cookies httpOnly
- Validación de sesiones en cada request
- Transacciones SQL con row locking para evitar race conditions
- Protección CSRF
- Validación de webhooks de Stripe
- Sanitización de inputs
- Rate limiting en endpoints críticos (implementar con middleware)

## 📧 Sistema de Emails

Configurar tu proveedor preferido en `src/lib/email.ts`:

- **Resend** (recomendado): https://resend.com
- **SendGrid**: https://sendgrid.com
- **Amazon SES**: https://aws.amazon.com/ses/

Tipos de emails automatizados:
- Bienvenida con credenciales temporales
- Recuperación de contraseña con token
- Confirmación de pedido

## 🎯 Flujo de Compra

1. Usuario se autentica vía Instagram
2. Recibe credenciales por email
3. Navega series activas
4. Selecciona serie, talla, color, fit y número
5. Click en "Comprar" → número se reserva 5 minutos
6. Redirect a Stripe Checkout
7. Completa pago
8. Webhook confirma pago
9. Número se marca como vendido
10. Email de confirmación enviado
11. Orden creada en BD

## 🔧 Configuración de Series

Para agregar nuevas series, insertar en la tabla `series`:

```sql
INSERT INTO series (name, slug, description, price, images, colors, sizes, release_date, seo_title, seo_description, seo_keywords) 
VALUES (
  'NOMBRE SERIE',
  'slug-serie',
  'Descripción...',
  59.99,
  '["imagen1.jpg", "imagen2.jpg"]',
  '["Color1", "Color2"]',
  '[{"size": "M", "type": "standard", "available": true}]',
  NOW(),
  'SEO Title',
  'SEO Description',
  'keywords'
);
```

Luego crear las 100 unidades correspondientes.

## 📊 Base de Datos

Tablas principales:
- `users` - Usuarios registrados
- `series` - Series de camisetas
- `series_units` - Unidades individuales (1-100)
- `orders` - Pedidos completados
- `password_reset_tokens` - Tokens de recuperación

Triggers automáticos:
- Actualización de `sold_units` al vender
- Liberación de reservas expiradas cada minuto

## 🌐 Despliegue

### Vercel (Recomendado)

```bash
npm i -g vercel
vercel
```

Configurar variables de entorno en Vercel Dashboard.

### Otros Proveedores

Compatible con cualquier plataforma que soporte Node.js y SSR de Astro:
- Netlify
- AWS
- Railway
- Render

## 📈 Optimización de Rendimiento

- Lazy loading de imágenes
- Minificación CSS/JS
- Compresión HTML
- Preconnect a dominios externos
- Cache de assets estáticos
- Optimización de fuentes
- Code splitting automático

## 🐛 Debugging

Ver logs en consola del servidor para errores con contexto completo.

Todos los endpoints tienen try-catch con logging detallado:

```
✅ = Operación exitosa
❌ = Error
📧 = Email enviado
```

## 📝 Notas Adicionales

- **Instagram OAuth**: Si no implementas OAuth de Instagram, puedes crear usuarios manualmente en la BD
- **Testing de Stripe**: Usar tarjetas de prueba: `4242 4242 4242 4242`
- **Liberación de reservas**: El evento de MySQL libera automáticamente cada minuto
- **Customización**: Todos los textos, colores y estilos están centralizados para fácil modificación

## 🤝 Contribuir

Para agregar funcionalidades:
1. Mantener try-catch en todos los endpoints
2. Seguir convenciones de nomenclatura
3. Actualizar tipos en `types.ts`
4. Documentar cambios en README

## 📄 Licencia

Propietario - Todos los derechos reservados

---

**Limited Tees** - Donde la exclusividad se encuentra con el estilo.
