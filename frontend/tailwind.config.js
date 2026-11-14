/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,jsx}'],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#f3f4fb',
          100: '#e7e9f7',
          200: '#cfd4ef',
          300: '#aab4df',
          400: '#7f8ecb',
          500: '#5565b4',
          600: '#374698',
          700: '#1e2d76',
          800: '#0e144c',
          900: '#0a103b'
        },
        accent: {
          50: '#fffdea',
          100: '#fff9c5',
          200: '#fff18a',
          300: '#ffe54f',
          400: '#f9f90c',
          500: '#e0ca18',
          600: '#c8ac15',
          700: '#a58a13',
          800: '#7a650f',
          900: '#59470b'
        },
        surface: {
          50: '#fbfcff',
          100: '#f4f7fe',
          200: '#edf1fb'
        }
      },
      fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
      boxShadow: {
        soft: '0 10px 35px rgba(14, 20, 76, 0.08)',
        brand: '0 12px 40px rgba(14, 20, 76, 0.12)'
      },
      backgroundImage: {
        'brand-gradient': 'linear-gradient(135deg, #0e144c 0%, #1e2d76 58%, #374698 100%)',
        'accent-gradient': 'linear-gradient(135deg, #f9f90c 0%, #e0ca18 100%)'
      }
    }
  },
  plugins: []
};
