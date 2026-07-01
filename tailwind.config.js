/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./**/*.{html,php,js}",
    "!./node_modules/**"
  ],
  corePlugins: {
    preflight: false,
  },
  theme: {
    extend: {
      colors: {
        gold: '#b8973a',
        'gold-light': '#d4af5a',
      }
    }
  },
  plugins: [],
}
