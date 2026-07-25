/**
 * <spain-map statuses='{"28":"hecho",…}'>
 * Choropleth de provincias (es-atlas / códigos INE).
 * Adaptado de la maqueta ONEZ (SpainMap.js) para el panel Laravel.
 *
 * Eventos:
 *   spain-map:ready  — geometría cargada
 *   spain-map:select — clic en provincia { code, name, status }
 */
(function () {
  if (customElements.get('spain-map')) return;

  const COLORS = {
    hecho: '#0F6E56',
    proceso: '#5DCAA5',
    error: '#C0503F',
    pendiente: '#E2E8E3',
  };

  const LABELS = {
    hecho: 'hecho',
    proceso: 'en proceso',
    error: 'error',
    pendiente: 'pendiente',
  };

  class SpainMap extends HTMLElement {
    static get observedAttributes() {
      return ['statuses', 'highlight'];
    }

    connectedCallback() {
      if (this._started) return;
      this._started = true;
      this._load();
    }

    attributeChangedCallback(name) {
      if (!this._fc) return;
      if (name === 'statuses' || name === 'highlight') this._render();
    }

    async _load() {
      for (let i = 0; i < 120 && !(window.d3 && window.topojson); i++) {
        await new Promise((r) => setTimeout(r, 100));
      }
      if (!(window.d3 && window.topojson)) return this._fail('Faltan d3 o topojson.');

      const sources = [
        this.getAttribute('src'),
        '/data/provinces.json',
        'https://cdn.jsdelivr.net/npm/es-atlas@0.6.0/es/provinces.json',
      ].filter(Boolean);

      let lastErr = null;
      for (const url of sources) {
        try {
          const res = await fetch(url);
          if (!res.ok) throw new Error('HTTP ' + res.status);
          const topo = await res.json();
          this._fc = window.topojson.feature(topo, topo.objects.provinces);
          this._render();
          this.dispatchEvent(new CustomEvent('spain-map:ready', { bubbles: true }));
          return;
        } catch (e) {
          lastErr = e;
        }
      }
      console.error('[spain-map] no se pudo cargar la geometría', lastErr);
      this._fail('No se pudo cargar la geometría del mapa.');
    }

    _fail(msg) {
      this.innerHTML =
        '<div style="padding:24px;font-size:12px;color:#8B968F;text-align:center">' +
        this._esc(msg) +
        '</div>';
    }

    _statuses() {
      try {
        return JSON.parse(this.getAttribute('statuses') || '{}');
      } catch (e) {
        return {};
      }
    }

    _esc(s) {
      return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/"/g, '&quot;');
    }

    _render() {
      const d3 = window.d3;
      const st = this._statuses();
      const highlight = String(this.getAttribute('highlight') || '').padStart(2, '0');
      const W = 800;
      const H = 600;
      const isCanaria = (f) => {
        const c = String(f.id).padStart(2, '0');
        return c === '35' || c === '38';
      };

      const mainland = {
        type: 'FeatureCollection',
        features: this._fc.features.filter((f) => !isCanaria(f)),
      };
      const canarias = {
        type: 'FeatureCollection',
        features: this._fc.features.filter(isCanaria),
      };

      const pMain = d3
        .geoConicConformal()
        .rotate([3.7, 0])
        .fitExtent(
          [
            [8, 8],
            [W - 8, H - 64],
          ],
          mainland
        );
      const pathMain = d3.geoPath(pMain);

      const inset = { x: 12, y: H - 176, w: 236, h: 150 };
      const pCan = d3.geoMercator().fitExtent(
        [
          [inset.x + 8, inset.y + 8],
          [inset.x + inset.w - 8, inset.y + inset.h - 8],
        ],
        canarias
      );
      const pathCan = d3.geoPath(pCan);

      const paths = (fc, pathFn) =>
        fc.features
          .map((f) => {
            const code = String(f.id).padStart(2, '0');
            const estado = st[code] || 'pendiente';
            const fill = COLORS[estado] || COLORS.pendiente;
            const name = f.properties?.name || code;
            const active = highlight === code;
            const stroke = active ? '#0B1F1A' : '#FAFAF7';
            const sw = active ? '2.2' : '1';
            return (
              '<path d="' +
              pathFn(f) +
              '" fill="' +
              fill +
              '" stroke="' +
              stroke +
              '" stroke-width="' +
              sw +
              '" data-code="' +
              code +
              '" data-name="' +
              this._esc(name) +
              '" data-status="' +
              estado +
              '" style="cursor:pointer">' +
              '<title>' +
              this._esc(name) +
              ' — ' +
              (LABELS[estado] || estado) +
              '</title></path>'
            );
          })
          .join('');

      this.innerHTML =
        '<svg viewBox="0 0 ' +
        W +
        ' ' +
        H +
        '" role="img" aria-label="Mapa de provincias españolas" style="width:100%;height:auto;display:block;min-height:280px">' +
        paths(mainland, pathMain) +
        '<rect x="' +
        inset.x +
        '" y="' +
        inset.y +
        '" width="' +
        inset.w +
        '" height="' +
        inset.h +
        '" fill="none" stroke="#D6DDD8" stroke-width="1" rx="6"></rect>' +
        paths(canarias, pathCan) +
        '</svg>';

      this.querySelectorAll('path[data-code]').forEach((el) => {
        el.addEventListener('click', () => {
          const code = el.getAttribute('data-code');
          const name = el.getAttribute('data-name');
          const status = el.getAttribute('data-status');
          this.setAttribute('highlight', code);
          this.dispatchEvent(
            new CustomEvent('spain-map:select', {
              bubbles: true,
              detail: { code, name, status },
            })
          );
        });
      });
    }
  }

  customElements.define('spain-map', SpainMap);
})();
