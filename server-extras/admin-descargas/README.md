# Panel de Administración DCIEN

Panel web completo para gestionar tu tienda.

## 📁 Estructura

```
admin-panel/
├── index.php              ← Dashboard
├── modules/
│   ├── config.php         ← Configuración
│   ├── auditor.php        ← Auditor completo
│   ├── activacion.php     ← Crear tokens
│   └── ver-pedido.php     ← Ver detalle
├── scripts/
│   └── auditor-helpers.php ← Funciones
├── assets/
│   └── style.css          ← Estilos
└── ordenes/
    └── .htaccess          ← Seguridad
```

## 🚀 Instalación

```bash
# Subir al servidor
scp -r * servidor:/home/u755459505/domains/d-cien.es/public_html/admin-descargas/

# Dar permisos
chmod 755 ordenes scripts modules assets
chmod 644 index.php modules/* scripts/*
```

## 🔐 Acceso

URL: https://d-cien.es/admin-descargas/

## ✅ Funcionalidades

- Dashboard con estadísticas
- Auditor de pedidos (filtros, búsqueda, selección múltiple)
- Generar órdenes de trabajo
- Exportar CSV
- Enviar emails
- Ver detalle completo de pedidos
- Crear tokens de activación
