# Carpeta de Fuentes

## Añade aquí tu archivo BebasNeue-Regular.woff2

### Cómo obtener la fuente:

1. **Descarga desde Google Fonts:**
   https://fonts.google.com/specimen/Bebas+Neue
   
2. **O desde FontSquirrel:**
   https://www.fontsquirrel.com/fonts/bebas-neue

3. **Convierte a WOFF2 si es necesario:**
   https://cloudconvert.com/ttf-to-woff2

4. **Coloca el archivo aquí:**
   ```
   public/fonts/BebasNeue-Regular.woff2
   ```

5. **Actualiza src/styles/global.css:**
   
   Descomenta estas líneas al inicio del archivo:
   
   ```css
   @font-face {
     font-family: 'Bebas Neue';
     src: url('/fonts/BebasNeue-Regular.woff2') format('woff2');
     font-weight: normal;
     font-style: normal;
     font-display: swap;
   }
   ```
   
   Y comenta la línea de Google Fonts:
   
   ```css
   /* @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&...'); */
   ```

### Mientras tanto...

La fuente se carga desde **Google Fonts** automáticamente. 
El sitio funciona perfectamente sin necesidad de archivo local.
