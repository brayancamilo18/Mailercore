/**
 * Interacción mapa ↔ tabla en /cosecha.
 * CSP: sin script inline.
 */
(function () {
  const mapa = document.getElementById('mapa-cosecha');
  const tabla = document.getElementById('tabla-provincias');
  if (!mapa || !tabla) return;

  function resaltarFila(code) {
    tabla.querySelectorAll('tr[data-code]').forEach((tr) => {
      const on = tr.getAttribute('data-code') === code;
      tr.style.background = on ? '#F2F8F4' : '';
    });
    const fila = tabla.querySelector('tr[data-code="' + code + '"]');
    if (fila) {
      fila.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
  }

  mapa.addEventListener('spain-map:select', (ev) => {
    resaltarFila(ev.detail.code);
  });

  tabla.addEventListener('click', (ev) => {
    const tr = ev.target.closest('tr[data-code]');
    if (!tr) return;
    const code = tr.getAttribute('data-code');
    if (!code) return;
    mapa.setAttribute('highlight', code);
    resaltarFila(code);
  });
})();
