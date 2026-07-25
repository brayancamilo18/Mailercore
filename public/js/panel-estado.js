/**
 * Polling ligero del estado del panel (Resumen).
 * data-url en #panel-estado-poll o meta[name="panel-estado-url"].
 */
(function () {
  const el = document.getElementById('panel-estado-poll');
  const meta = document.querySelector('meta[name="panel-estado-url"]');
  const url = (el && el.dataset.url) || (meta && meta.content);
  if (!url) return;

  setInterval(async () => {
    try {
      const res = await fetch(url, { headers: { Accept: 'application/json' } });
      if (!res.ok) return;
      window.__panelEstado = await res.json();
    } catch (e) {}
  }, 15000);
})();
