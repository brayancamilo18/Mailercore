/**
 * Interacción mapa ↔ tabla en /cosecha (spain-map o region-map).
 */
(function () {
  const mapaSpain = document.getElementById('mapa-cosecha-spain');
  const mapaRegion = document.getElementById('mapa-cosecha-region');
  const tabla = document.getElementById('tabla-provincias');
  if (!tabla) return;

  function filaPor(code, name) {
    if (!code && !name) return null;
    let fila = null;
    if (code) {
      fila = tabla.querySelector('tr[data-code="' + CSS.escape(code) + '"]');
      if (!fila) {
        fila = tabla.querySelector('tr[data-clave="' + CSS.escape(code) + '"]');
      }
    }
    if (!fila && name) {
      const needle = name.toLowerCase();
      tabla.querySelectorAll('tr[data-nombre]').forEach((tr) => {
        if (!fila && (tr.getAttribute('data-nombre') || '').toLowerCase() === needle) {
          fila = tr;
        }
      });
    }
    return fila;
  }

  function resaltarFila(fila) {
    tabla.querySelectorAll('tr.is-map-active').forEach((tr) => tr.classList.remove('is-map-active'));
    if (!fila) return;
    fila.classList.add('is-map-active');
    fila.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  function onSelect(detail) {
    resaltarFila(filaPor(detail.code, detail.name));
  }

  if (mapaSpain) {
    mapaSpain.addEventListener('spain-map:select', (e) => onSelect(e.detail || {}));
  }
  if (mapaRegion) {
    mapaRegion.addEventListener('region-map:select', (e) => onSelect(e.detail || {}));
  }

  tabla.querySelectorAll('tr[data-code], tr[data-nombre]').forEach((tr) => {
    tr.addEventListener('click', () => {
      const code = tr.getAttribute('data-code') || tr.getAttribute('data-clave') || '';
      const name = tr.getAttribute('data-nombre') || '';
      resaltarFila(tr);
      if (mapaSpain && code) mapaSpain.setAttribute('highlight', code);
      if (mapaRegion) mapaRegion.setAttribute('highlight', code || name);
    });
  });
})();
