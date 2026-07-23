# Outreach SilgoDev

Sistema de outreach B2B en Laravel: descubre negocios locales en OpenStreetMap, audita su web, y envía correos cortos y personales con un hallazgo real. Todo el envío está limitado por una rampa de volumen y monitorizado.

## 1. Qué hace

```
OpenStreetMap (Overpass)
        │
        ▼
   cosecha:ejecutar  ──►  leads + websites
        │
        ▼
 leads:backfill-sectores  ──►  sector (7 familias)
        │
        ▼
   leads:rastrear  ──►  páginas + emails de rol
        │
        ▼
   auditar:web (+ pagespeed)  ──►  puntuación + hallazgos
        │
        ▼
 emails:verificar  ──►  MX / SMTP sonda
        │
        ▼
 envio:planificar  ──►  mensajes del día (rampa)
        │
        ▼
 envio:despachar  ──►  cola «envio»  ──►  SMTP
        │
        ▼
 outreach:bandeja (IMAP)  ──►  respuestas / rebotes / bajas
        │
        ▼
   Panel web (auth) + sistema:salud
```

No usa Google Places ni servicios de correo de pago. SMTP nativo de Laravel + IMAP (Webklex). CRM en PostgreSQL (`leads`).

## 2. Arranque con Docker

```bash
cp .env.example .env
# Edita: DB_PASSWORD, PANEL_EMAIL, PANEL_PASSWORD, MAIL_*, IMAP_*, OUTREACH_*

docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan db:seed --class=UsuarioSeeder
```

Servicios: `web` (nginx :8000), `app` (PHP-FPM), `postgres`, `redis`, `scheduler`, colas (`default`, `scraping`, `envio`). Mailpit: `docker compose --profile dev up -d mailpit` → UI en http://localhost:8025.

Panel: http://localhost:8000 (login con `PANEL_EMAIL` / `PANEL_PASSWORD`).

## 3. El pipeline completo (orden)

1. `php artisan cosecha:ejecutar` — Overpass por provincia (52 áreas).
2. `php artisan leads:backfill-sectores` — clasifica sector.
3. `php artisan leads:rastrear` — descarga páginas y emails de rol.
4. `php artisan auditar:web` — 25 comprobaciones locales.
5. `php artisan auditar:pagespeed` — PSI (si hay API key).
6. `php artisan emails:verificar --solo-cola` — valida destinatarios candidatos.
7. `php artisan envio:diagnostico` — **debe salir en verde** antes del primer envío real.
8. `php artisan envio:prueba ...` — prueba manual (Mailpit / mail-tester).
9. `php artisan envio:planificar --dry-run` — revisa la cola del día.
10. `OUTREACH_ENVIO_ACTIVO=true` y `php artisan envio:planificar` / despacho automático.
11. `php artisan outreach:bandeja` — procesa rebotes, bajas y respuestas (cada 10 min en schedule).

## 4. Las 7 familias de sector

| Clave | Etiqueta | Plantilla |
|-------|----------|-----------|
| `hosteleria` | Hostelería | `hosteleria` |
| `salud` | Salud | `salud` |
| `retail` | Comercio | `retail` |
| `servicios_profesionales` | Servicios profesionales | `servicios` |
| `oficios` | Oficios | `oficios` |
| `belleza` | Belleza y bienestar | `belleza` |
| `agencias` | Agencias | `agencias` |

Clasificación (`ClasificadorSector`), en orden:

1. Filtro OSM que trajo el lead (`osm_tag` / `osm_valor`) — confianza 100.
2. Resto de tags OSM en `osm_tags_raw` — 95.
3. Tipo Schema.org detectado en la web — 90.
4. Palabras del nombre / dominio — 70.
5. Heurísticas adicionales del catálogo.
6. Sin clasificar → no entra en envío.

## 5. La auditoría: 25 hallazgos

Motor: `MotorAuditoria` + comprobaciones en `app/Services/Auditoria/Comprobaciones/`.

| Código | Origen de los datos |
|--------|---------------------|
| `sin_https` | Página home: HTTPS / certificado |
| `psi_rendimiento` | PageSpeed Insights — performance |
| `psi_lcp` | PSI — LCP |
| `sin_viewport` | Meta viewport en HTML capturado |
| `sin_reservas` | Señales de reservas (hostelería/salud/belleza) |
| `sin_carrito` | Carrito / checkout (retail) |
| `cert_caduca` | Caducidad del certificado TLS |
| `psi_peso` | PSI — peso de página |
| `web_abandonada` | Año de copyright antiguo |
| `psi_seo` | PSI — SEO |
| `sin_aviso_legal` | Enlaces a aviso legal / privacidad |
| `respuesta_lenta` | Tiempo de respuesta HTTP de la home |
| `psi_accesibilidad` | PSI — accesibilidad |
| `sin_jsonld` | JSON-LD ausente |
| `title_malo` | Title ausente o longitud incorrecta |
| `contacto_roto` | Página de contacto con status malo |
| `sin_meta_description` | Meta description |
| `sin_formulario` | Formulario de contacto |
| `generador_obsoleto` | Generador (Wix/Joomla/WP sin viewport) |
| `html_pesado` | Tamaño HTML |
| `h1_incorrecto` | 0 o más de un H1 |
| `imagenes_sin_alt` | Ratio de imágenes sin alt |
| `sin_cookies` | Aviso de cookies |
| `sin_redes` | Enlaces a redes sociales |
| `sin_whatsapp` | Enlace WhatsApp (oficios/belleza) |

El hallazgo principal es el de mayor peso; el secundario el siguiente (paso 2 / seguimiento).

## 6. Las plantillas

Ubicación:

- `resources/views/emails/texto/{plantilla}-{1|2}.blade.php`
- `resources/views/emails/html/{plantilla}-{1|2}.blade.php`
- Frases por hallazgo: `resources/data/frases_hallazgo.php`

Reglas que **no se pueden romper** (validadas por `Renderizador`):

- Asunto ≤ 60 caracteres.
- Cuerpo paso 1 ≤ 150 palabras; seguimiento ≤ 45 (sin el pie legal tras `---`).
- Como máximo **1** enlace `<a>` en el HTML.
- **Sin** imágenes.
- Sin palabras prohibidas (gratis, oferta, urgente…).
- Sin marcadores `{...}` sin sustituir.
- Remitente legal + opción de baja visibles; cabeceras `List-Unsubscribe`.

El envío usa el texto/HTML **ya guardado** en `mensajes` (no re-renderiza en el job).

## 7. La rampa de envío

| Días de racha | Cuota diaria |
|---------------|--------------|
| 1–3 | 10 |
| 4–7 | 15 |
| 8–12 | 22 |
| 13–20 | 30 |
| 21+ | 40 (tope `OUTREACH_MAX_DIARIO`) |

Ventana de salud: últimos 200 enviados. Umbrales de tasa de rebote duro:

| Salud | Umbral |
|-------|--------|
| ámbar | ≥ 2 % |
| rojo | ≥ 4 % (cuota a la mitad) |
| parado | ≥ 6 % (cuota 0) |

Pausar desde el panel escribe `envio:pausado` en caché y frena `envio:despachar`.

## 8. Resiliencia 24/7 (autocuración)

El sistema está pensado para correr **sin supervisión**: ante un fallo, se recupera solo. Cinco capas:

### Capa 1 — Contenedores que resucitan solos
Todos los servicios de larga duración llevan `restart: unless-stopped`. Si un
worker o el scheduler **crashea (error fatal, OOM, kill)**, Docker lo relanza
automáticamente. `postgres` y `redis` tienen `healthcheck` y el resto espera a
que estén *healthy* antes de arrancar (`depends_on: condition: service_healthy`).
Los workers usan `stop_grace_period` para terminar el job en curso antes de
morir (apagado elegante de `queue:work`).

> Nota: `docker stop`/`docker kill` (parada manual) **no** relanza — es
> intencionado. Solo se resucita ante caídas no provocadas.

### Capa 2 — Workers que se autoreciclan
Cada `queue:work` corre con `--max-jobs`, `--max-time` y `--memory`: se apaga
limpio cada cierto tiempo/uso (evita fugas de memoria) y Docker lo vuelve a
levantar fresco. `retry_after` (240 s) es **mayor** que el `timeout` más alto de
los jobs (scraping 180 s, PSI 150 s), así que un job lento nunca se procesa dos veces.

### Capa 3 — Jobs con reintentos y backoff
`RastrearSitioJob` / `AnalizarPageSpeedJob`: `tries=3` con backoff progresivo y
`failed()` que registra el fallo en el lead. Límites de ritmo (`release()`) no
consumen intento. `EnviarMensajeJob`: `tries=1` a propósito (el reintento lo hace
`envio:recuperar`, idempotente por estado).

### Capa 4 — Cosecha a prueba de procesos muertos
- Latido en vivo cada ≤ 60 s mientras cosecha.
- Si el proceso muere, el área queda `en_proceso` huérfana. Tanto
  `cosecha:ejecutar` (al recuperar el lock) como el **vigilante** la detectan por
  latido caduco, liberan el lock y la devuelven a `pendiente`.
- Reintentos acotados (`max_reintentos`, def. 5): un área que siempre falla pasa
  a `error` para no bloquear el barrido del resto.

### Capa 5 — Vigilante (watchdog) cada minuto
`php artisan sistema:vigilante` (programado `everyMinute`):
1. Recupera áreas de cosecha huérfanas.
2. Devuelve a la cola mensajes colgados en `enviando` sin evidencia de envío.
3. Registra en el log si un latido **crítico** lleva demasiado mudo.

Además `sistema:salud --json` se registra cada 15 min en `storage/logs/salud.log`
(para alertas externas). La bandeja IMAP y el envío se **omiten** de las alertas
mientras no estén configurados (fase de solo cosecha), sin generar falsos críticos.

### Tabla de fallos y recuperación

| Servicio caído | Efecto | Recuperación (automática salvo nota) |
|----------------|--------|--------------|
| Worker (cualquiera) | Deja de consumir su cola | Docker lo relanza (`restart: unless-stopped`) |
| Proceso de cosecha | Área queda `en_proceso` | Vigilante/`cosecha:ejecutar` la recuperan |
| Scheduler | Nada se programa | Docker relanza el contenedor `scheduler` |
| Postgres | App y jobs paran | Healthcheck + restart; reintentos de jobs al volver |
| Redis | Colas/latidos/sesiones | Healthcheck + restart; jobs reservados vuelven tras `retry_after` |
| SMTP | `EnviarMensajeJob` falla | `envio:recuperar` reprograma (auto, cada 10 min) |
| IMAP | Rebotes/bajas sin procesar | Se omite si no está configurado; con IMAP: reintenta |
| Overpass saturado | Área tarda/falla | Backoff entre espejos; reintento acotado del área |
| Disco lleno | Logs / fallos | `sistema:podar` (auto, domingo 03:15) |

Monitor externo recomendado: `php artisan sistema:salud` (exit `0` OK / `1` avisos
/ `2` crítico) enganchado a tu sistema de alertas.

## 9. ANTES DEL PRIMER ENVÍO

1. Configurar **SPF**, **DKIM** y **DMARC** en el DNS del dominio remitente.
2. `php artisan envio:diagnostico` → tiene que salir en verde.
3. `php artisan envio:prueba a-tu-correo@ejemplo.com` — revisar en Mailpit.
4. `php artisan envio:prueba test-xxxx@mail-tester.com` — puntuación ≥ 9/10.
5. `php artisan emails:verificar --solo-cola`
6. `php artisan envio:planificar --dry-run` — revisar la tabla.
7. Solo entonces: `OUTREACH_ENVIO_ACTIVO=true` y redeploy / `config:clear`.

## 10. Operación diaria (orden)

1. Panel → **Resumen**: rampa, semáforo, embudo.
2. `php artisan sistema:salud` (o el cron de monitorización).
3. Panel → **Cola**: mensajes de hoy/mañana; cancelar si hace falta.
4. Panel → **Salud**: latidos y rebotes.
5. `php artisan outreach:bandeja-estado` si hay dudas de IMAP.
6. Panel → **Cosecha**: avance de provincias.
7. Al cierre: mirar respuestas en Resumen / leads `respondido`.

## 11. Solución de problemas (10 fallos frecuentes)

| Síntoma | Comando / acción |
|---------|------------------|
| No salen correos | `envio:estado` · ¿`OUTREACH_ENVIO_ACTIVO`? ¿pausado en panel? |
| Cola llena y nada se envía | `envio:despachar` · worker `queue-envio` |
| Mensajes atrapados en `enviando` | `envio:recuperar` |
| Rebotes altos / salud rojo | Panel Salud · bajar ritmo · revisar lista |
| IMAP no conecta | `outreach:bandeja` · logs `outreach` · `bandeja:fallos_seguidos` |
| Cosecha parada | `cosecha:estado` · `cosecha:reanudar` |
| Sin candidatos a planificar | `leads:sectores-status` · auditar · verificar emails |
| Plantilla inválida | `envio:diagnostico` (bloque plantillas) |
| Panel 500 / Redis | Docker Redis · `SESSION_DRIVER` |
| Solicitud “borrad mis datos” | `datos:supresion email@dominio.es` |

## 12. Tabla de referencia de comandos Artisan

| Comando | Para qué |
|---------|----------|
| `cosecha:ejecutar` | Cosecha Overpass de un área pendiente |
| `cosecha:estado` | Avance de las 52 áreas |
| `cosecha:pausar` / `cosecha:reanudar` | Pausa/reanudación de cosecha |
| `leads:backfill-sectores` | Clasifica sectores |
| `leads:sectores-status` | Conteos por sector |
| `leads:rastrear` | Encola scrape de webs |
| `auditar:web` | Auditoría local |
| `auditar:pagespeed` | Encola PSI |
| `emails:verificar` | Verificación MX/SMTP de emails |
| `envio:diagnostico` | Prerrequisito DNS/SMTP/IMAP/plantillas |
| `envio:prueba` | Un correo de prueba renderizado |
| `envio:planificar` | Genera mensajes del día |
| `envio:despachar` | Encola pendientes vencidos |
| `envio:recuperar` | Descolgar / reprogramar / limpiar |
| `envio:estado` | Resumen de envío |
| `outreach:bandeja` | Procesa IMAP |
| `outreach:bandeja-estado` | Eventos de bandeja |
| `sistema:salud` | Monitor (exit 0/1/2, `--json`) |
| `sistema:vigilante` | Watchdog de resiliencia (autocuración) |
| `sistema:podar` | Limpieza logs / jobs / páginas |
| `datos:supresion` | Supresión RGPD (borra lead, deja hash) |

Schedule (Madrid): **vigilante cada minuto**, cosecha cada 5 min, salud cada 15 min,
planificar laborables 07:00, despachar cada minuto, bandeja/recuperar cada 10 min
(bandeja solo si IMAP configurado), verificar emails 20:00, podar domingo 03:15.
