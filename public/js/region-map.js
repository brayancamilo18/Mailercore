/**
 * <region-map src="…geo.json" statuses='{"antioquia":"hecho",…}'>
 * Choropleth sobre GeoJSON de Highcharts Map Collection.
 *
 * Importante: esos ficheros NO van en lat/lon; traen coords ya proyectadas
 * (+ hc-transform). Hay que usar d3.geoIdentity, no Mercator.
 *
 * Eventos:
 *   region-map:ready
 *   region-map:select — { code, name, status }
 */
(function () {
  if (customElements.get('region-map')) return;

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

  function norm(s) {
    return String(s || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-|-$/g, '');
  }

  /** ¿Parecen lon/lat geográficas? (Highcharts suele usar valores > 180) */
  function pareceGeografico(fc) {
    let minX = Infinity;
    let maxX = -Infinity;
    let minY = Infinity;
    let maxY = -Infinity;
    let n = 0;

    const walk = (coords) => {
      if (!Array.isArray(coords) || coords.length === 0) return;
      if (typeof coords[0] === 'number' && typeof coords[1] === 'number') {
        minX = Math.min(minX, coords[0]);
        maxX = Math.max(maxX, coords[0]);
        minY = Math.min(minY, coords[1]);
        maxY = Math.max(maxY, coords[1]);
        n++;
        return;
      }
      for (let i = 0; i < coords.length; i++) walk(coords[i]);
    };

    (fc.features || []).forEach((f) => {
      if (f.geometry && f.geometry.coordinates) walk(f.geometry.coordinates);
    });

    if (n === 0) return false;
    // Lon/lat típico: X ∈ [-180,180], Y ∈ [-90,90]
    return Math.abs(minX) <= 180 && Math.abs(maxX) <= 180 && Math.abs(minY) <= 90 && Math.abs(maxY) <= 90;
  }

  class RegionMap extends HTMLElement {
    static get observedAttributes() {
      return ['statuses', 'highlight', 'src'];
    }

    connectedCallback() {
      if (this._started) return;
      this._started = true;
      this._load();
    }

    attributeChangedCallback(name) {
      if (name === 'src' && this._started) {
        this._fc = null;
        this._load();
        return;
      }
      if (!this._fc) return;
      if (name === 'statuses' || name === 'highlight') this._render();
    }

    async _load() {
      for (let i = 0; i < 120 && !window.d3; i++) {
        await new Promise((r) => setTimeout(r, 100));
      }
      if (!window.d3) return this._fail('Falta d3.');

      const url = this.getAttribute('src');
      if (!url) return this._fail('Sin geometría de mapa para este país.');

      try {
        const res = await fetch(url);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        this._raw = data;
        this._fc = this._toFeatureCollection(data);
        if (!this._fc.features || this._fc.features.length === 0) {
          return this._fail('El mapa no tiene regiones.');
        }
        this._render();
        this.dispatchEvent(new CustomEvent('region-map:ready', { bubbles: true }));
      } catch (e) {
        console.error('[region-map] geometría', e);
        this._fail('No se pudo cargar el mapa de este país.');
      }
    }

    _toFeatureCollection(data) {
      if (data && data.type === 'FeatureCollection' && Array.isArray(data.features)) {
        return { type: 'FeatureCollection', features: data.features };
      }
      if (data && data.type === 'Topology' && window.topojson) {
        const key = Object.keys(data.objects || {})[0];
        if (key) return window.topojson.feature(data, data.objects[key]);
      }
      if (data && Array.isArray(data.features)) {
        return { type: 'FeatureCollection', features: data.features };
      }
      throw new Error('Formato de mapa no reconocido');
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

    _estadoDe(f, st) {
      const props = f.properties || {};
      const candidates = [
        props['hc-key'],
        props['hasc'],
        f.id,
        props.id,
        norm(props.name),
        props.name,
        norm(props['name:es'] || ''),
        norm(props['alt-name'] || ''),
      ].filter(Boolean);

      for (const c of candidates) {
        const k = String(c);
        if (st[k]) return st[k];
        const nk = norm(k);
        if (st[nk]) return st[nk];
      }
      return 'pendiente';
    }

    _nombreDe(f) {
      const p = f.properties || {};
      return p.name || p['name:es'] || p['hc-key'] || String(f.id || '');
    }

    _codigoDe(f) {
      const p = f.properties || {};
      return String(p['hc-key'] || p.hasc || f.id || norm(this._nombreDe(f)));
    }

    _projection(W, H) {
      const d3 = window.d3;
      const pad = [
        [12, 12],
        [W - 12, H - 12],
      ];

      if (pareceGeografico(this._fc)) {
        return d3.geoMercator().fitExtent(pad, this._fc);
      }

      // Highcharts mapdata: plano proyectado; Y crece hacia arriba → reflectY.
      return d3.geoIdentity().reflectY(true).fitExtent(pad, this._fc);
    }

    _render() {
      const d3 = window.d3;
      const st = this._statuses();
      const highlight = String(this.getAttribute('highlight') || '');
      const W = 800;
      const H = 560;

      const projection = this._projection(W, H);
      const path = d3.geoPath(projection);

      const paths = this._fc.features
        .map((f) => {
          const d = path(f);
          if (!d || d.indexOf('NaN') !== -1) return '';

          const code = this._codigoDe(f);
          const name = this._nombreDe(f);
          const estado = this._estadoDe(f, st);
          const fill = COLORS[estado] || COLORS.pendiente;
          const active =
            highlight &&
            (highlight === code || norm(highlight) === norm(name) || highlight === norm(name));
          const stroke = active ? '#0B1F1A' : '#FAFAF7';
          const sw = active ? '2.2' : '0.8';
          return (
            '<path d="' +
            d +
            '" fill="' +
            fill +
            '" stroke="' +
            stroke +
            '" stroke-width="' +
            sw +
            '" data-code="' +
            this._esc(code) +
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

      if (!paths) {
        return this._fail('No se pudo dibujar el mapa (geometría inválida).');
      }

      this.innerHTML =
        '<svg viewBox="0 0 ' +
        W +
        ' ' +
        H +
        '" role="img" aria-label="Mapa de regiones" style="width:100%;height:auto;display:block;min-height:280px">' +
        paths +
        '</svg>';

      this.querySelectorAll('path[data-code]').forEach((el) => {
        el.addEventListener('click', () => {
          const code = el.getAttribute('data-code');
          const name = el.getAttribute('data-name');
          const status = el.getAttribute('data-status');
          this.setAttribute('highlight', code);
          this.dispatchEvent(
            new CustomEvent('region-map:select', {
              bubbles: true,
              detail: { code, name, status },
            })
          );
        });
      });
    }
  }

  customElements.define('region-map', RegionMap);
})();
