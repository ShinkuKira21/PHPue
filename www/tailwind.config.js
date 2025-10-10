module.exports = {
  content: [
    "./**/*.pvue",
    "./components/**/*.pvue", 
    "./views/**/*.pvue",
    "./*.pvue"
  ],
  theme: {
    extend: {
      colors: {
        'phpue': {
          '50': '#f0f9ff',
          '500': '#0ea5e9', 
          '900': '#0c4a6e',
        }
      }
    },
  },
  plugins: [],
}