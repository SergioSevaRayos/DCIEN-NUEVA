# DCIEN — Guía de Deploy

## Estructura del proyecto

```
DCIEN-nueva/
├── src/                  ← Fuentes Astro (páginas, componentes, estilos)
├── public/               ← Assets estáticos (imágenes, fuentes)
├── dist/                 ← Build compilado — NO editar directamente
├── dcien-backend/        ← Backend PHP (APIs, config, includes)
└── server-extras/        ← Archivos extra del servidor (no son de Astro)
    ├── admin-descargas/  ← Panel de administración
    ├── api/              ← Proxies PHP públicos
    ├── bono/             ← Landing de bonos QR
    ├── carrito/          ← Página del carrito
    └── js/               ← cart.js y otros scripts globales
```

---

## Qué editar según el cambio

| Quiero cambiar... | Edito en... | Necesito build? |
|---|---|---|
| Una página de la web (home, serie, checkout...) | `src/pages/` | ✅ Sí |
| Un componente (header, footer...) | `src/components/` | ✅ Sí |
| Estilos globales | `src/styles/` | ✅ Sí |
| El carrito (`/carrito/`) | `server-extras/carrito/` | ❌ No |
| APIs PHP del backend | `dcien-backend/api/` | ❌ No |
| Panel de administración | `server-extras/admin-descargas/` | ❌ No |
| `cart.js` u otros scripts | `server-extras/js/` | ❌ No |
| Landing de bonos QR | `server-extras/bono/` | ❌ No |

---

## Comandos de deploy

Abre PowerShell en `C:\Users\Trending Pc\Documents\DCIEN\DCIEN-nueva`

### Caso 1 — Solo cambié páginas de Astro (`src/`)

```powershell
.\deploy.ps1 -build -frontend
```

### Caso 2 — Solo cambié el backend PHP

```powershell
.\deploy.ps1 -backend
```

### Caso 3 — Solo cambié extras (carrito, api, admin, js, bono)

```powershell
.\deploy.ps1 -extras
```

### Caso 4 — Cambié varias cosas a la vez

```powershell
.\deploy.ps1 -all
```

Esto hace: build de Astro → sube frontend → sube backend → sube extras.

### Caso 5 — Solo hacer build sin subir

```powershell
.\deploy.ps1 -build
```

---

## Primer uso — Ejecutar el script

PowerShell bloquea scripts por defecto. Ejecuta esto una sola vez:

```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

Luego ya puedes usar `.\deploy.ps1` normalmente.

---

## Conexión al servidor

| Dato | Valor |
|---|---|
| Host | `147.79.103.7` |
| Puerto SSH | `65002` |
| Usuario | `u755459505` |
| Contraseña | `9400Jet_` |
| Dominio | `d-cien.es` |

Para conectarte por SSH directamente:

```powershell
ssh -p 65002 u755459505@147.79.103.7
```

---

## Bajar cambios del servidor al local

Si hiciste cambios directamente en el servidor vía SSH y quieres traerlos al local:

```powershell
# Backend
scp -P 65002 -r u755459505@147.79.103.7:/home/u755459505/domains/d-cien.es/dcien-backend "C:\Users\Trending Pc\Documents\DCIEN\DCIEN-nueva\"

# Extras
scp -P 65002 -r u755459505@147.79.103.7:/home/u755459505/domains/d-cien.es/public_html/admin-descargas "C:\Users\Trending Pc\Documents\DCIEN\DCIEN-nueva\server-extras\"
scp -P 65002 -r u755459505@147.79.103.7:/home/u755459505/domains/d-cien.es/public_html/carrito "C:\Users\Trending Pc\Documents\DCIEN\DCIEN-nueva\server-extras\"
scp -P 65002 -r u755459505@147.79.103.7:/home/u755459505/domains/d-cien.es/public_html/api "C:\Users\Trending Pc\Documents\DCIEN\DCIEN-nueva\server-extras\"
scp -P 65002 -r u755459505@147.79.103.7:/home/u755459505/domains/d-cien.es/public_html/js "C:\Users\Trending Pc\Documents\DCIEN\DCIEN-nueva\server-extras\"
scp -P 65002 -r u755459505@147.79.103.7:/home/u755459505/domains/d-cien.es/public_html/bono "C:\Users\Trending Pc\Documents\DCIEN\DCIEN-nueva\server-extras\"
```

---

## Notas importantes

- **Nunca edites `dist/` directamente** — se sobreescribe con cada build.
- **El `.env` del backend** (`dcien-backend/.env`) contiene las claves de Stripe y BD. No lo subas a git.
- **Después de subir**, verifica siempre en `https://d-cien.es` que todo funciona.
- Si el servidor tiene cambios que no tienes en local, **baja primero** antes de subir para no sobreescribir.
