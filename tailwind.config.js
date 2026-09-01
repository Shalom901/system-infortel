module.exports = {
  darkMode: 'class', // Obligatorio usar la estrategia de clase
  theme: {
    extend: {
      colors: {
        paradise: {
          blue: 'var(--color-primary)',
          // ... otros tonos de azul
        },
        slate: {
          50: '#F8FAFC',
          // ... extender la paleta de grises de tailwind si es necesario
        }
      },
      backgroundColor: {
        body: 'var(--color-bg-body)',
        container: 'var(--color-bg-container)',
        sidebar: 'var(--color-bg-sidebar)',
      },
      textColor: {
        primary: 'var(--color-text-primary)',
        sidebar: 'var(--color-text-sidebar)',
      },
      // ... espaciado matemático y tipografía funcional
    },
  },
  plugins: [],
}