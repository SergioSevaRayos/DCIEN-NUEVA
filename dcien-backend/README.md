# 🔐 BACKEND DCIEN - Instalación y Uso

## 📁 Estructura

```
dcien-backend/
├── api/
│   └── auth/
│       ├── login.php      ✅ POST - Login usuario
│       ├── logout.php     ✅ POST - Logout
│       └── check.php      ✅ GET - Verificar sesión
├── config/
│   └── database.php       ✅ Conexión MySQL
├── includes/
│   ├── cors.php           ✅ Headers CORS
│   ├── session.php        ✅ Gestión sesiones
│   └── helpers.php        ✅ Funciones helper
├── .env                   ⚠️ CONFIGURAR
├── .htaccess              ✅ Apache config
└── README.md              📖 Esta guía
```

## 🚀 Instalación

### 1. Subir archivos

Sube la carpeta completa a tu servidor:
```
public_html/dcien-backend/
```

### 2. Configurar .env

Edita `.env` con tus credenciales reales:
- Base de datos
- Stripe keys
- Email credentials

### 3. Configurar permisos

```bash
chmod 600 .env
chmod 644 api/auth/*.php
chmod 644 config/*.php
chmod 644 includes/*.php
```

### 4. Crear usuario de prueba

En phpMyAdmin:
```sql
INSERT INTO users (email, password_hash, is_verified) 
VALUES (
  'test@dcien.es',
  '$2y$10$...',  -- Generar con password_hash('tu_password', PASSWORD_BCRYPT)
  1
);
```

## 🧪 Testing

### Login
```bash
curl -X POST https://d-cien.es/dcien-backend/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test@dcien.es","password":"test123"}' \
  -c cookies.txt
```

### Check sesión
```bash
curl https://d-cien.es/dcien-backend/api/auth/check.php -b cookies.txt
```

### Logout
```bash
curl -X POST https://d-cien.es/dcien-backend/api/auth/logout.php -b cookies.txt
```

## 📊 Endpoints

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/api/auth/login.php` | POST | Login usuario |
| `/api/auth/logout.php` | POST | Logout |
| `/api/auth/check.php` | GET | Verificar sesión |

## 🔐 Seguridad

- ✅ Contraseñas con bcrypt
- ✅ Sesiones httpOnly + secure
- ✅ CORS configurado
- ✅ Prepared statements
- ✅ Input sanitization
- ✅ Error logging

## 📝 Logs

Los errores se registran en el error log de PHP.
Ver en: `/var/log/php_errors.log` o según configuración del servidor.
