/* Config de Tailwind CDN — debe cargarse ANTES de cdn.tailwindcss.com */
window.tailwind = window.tailwind || {};
tailwind.config = {
  theme: {
    extend: {
      colors: {
        bosque: '#0B1F1A',
        savia: '#0F6E56',
        brote: '#5DCAA5',
        hueso: '#FAFAF7',
        marca: { txt: '#22312B', sec: '#5F6B66', mut: '#8B968F', bd: '#E4E8E4' },
        ok: { DEFAULT: '#2C7A3F', bg: '#E4F3E7' },
        amb: { DEFAULT: '#96660F', bg: '#FAF0DC' },
        roj: { DEFAULT: '#B0432F', bg: '#F9E9E6' },
        info: { DEFAULT: '#2F5E96', bg: '#E1ECF8' },
        gr: { DEFAULT: '#5F6B66', bg: '#EEF1EF' },
      },
      fontFamily: {
        sans: ['Nunito Sans', 'system-ui', 'sans-serif'],
      },
    },
  },
};
