/** @type {import('tailwindcss').Config} */
export default {
  content: ['./src/**/*.{astro,html,js,jsx,md,mdx,svelte,ts,tsx,vue}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // Redefinimos el negro para que sea el gris oscuro DCIEN
        black: '#1a1a1a', 
        white: '#ffffff',
        red: '#ff0000',
        blue: '#1800ad',
        yellow: '#ffbd59',
        // Colores específicos para componentes
        dark: {
          bg: '#1a1a1a',
          card: '#2a2a2a',
          text: '#e0e0e0',
          border: '#404040',
        },
      },
      fontFamily: {
        brand: ['Bebas Neue', 'sans-serif'],
        body: ['Inter', 'system-ui', 'sans-serif'],
      },
      animation: {
        'fade-in': 'fadeIn 0.3s ease-in',
        'slide-up': 'slideUp 0.4s ease-out',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { transform: 'translateY(20px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
      },
    },
  },
  plugins: [],
};