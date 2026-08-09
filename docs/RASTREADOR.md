# El rastreador de sitios (`RastreadorSitio`)

Documento descriptivo del comportamiento **actual** del rastreo web. No propone
cambios: describe lo que el código hace hoy, incluidas las rarezas.

Ficheros implicados:

- `app/Enums/PropositoRastreo.php` — para qué se rastrea (`EMAIL`, `AUDITORIA`, `COMPLETO`).
- `app/Enums/Certeza.php` — cuánto se puede afirmar un hallazgo (`HECHO`, `OBSERVADO`, `INDICIO`).
- `app/Services/Web/RastreadorSitio.php` — orquestador (`rastrear` y `simular`).
- `app/Services/Web/ExtractorMetadatos.php` — parseo del HTML.
- `app/Services/Web/DescubridorRutas.php` — rutas candidatas desde la home.
- `app/Services/Web/LectorEnlaces.php` — recorrido compartido de `<a>`.
- `app/Services/Web/ClasificadorEmail.php` — decide qué email se guarda.
- `app/Services/Soporte/GuardiaUrl.php` — anti-SSRF.
- `app/Services/Soporte/ComprobadorRobots.php` — robots.txt (+ `Crawl-delay`).
- `app/Services/Soporte/LimitadorRitmo.php` — límite de peticiones.
- `app/Services/Soporte/InspectorCertificado.php` — TLS: cadena + caducidad.
- `app/Services/Auditoria/MotorAuditoria.php` — consume `paginas` y emite hallazgos con certeza.
- `app/Jobs/RastrearSitioJob.php` — envoltorio de cola.
- `app/Console/Commands/RastrearCommand.php` — encolado por lotes.
- `app/Console/Commands/RastreoDiagnosticoCommand.php` — traza en seco (`simular`).
- `app/DTO/MetadatosPagina.php`, `app/DTO/ResultadoRastreo.php`, `app/DTO/IntentoHttp.php`, `app/DTO/ResultadoDiagnosticoRastreo.php`, `app/DTO/Hallazgo.php`.
- `database/migrations/2026_07_23_172554_crear_tabla_paginas.php` (+ trazabilidad, `cert_cadena_valida`), `app/Models/Pagina.php`.

---

## 1. Puntos de entrada

### 1.1 Llamadas a `RastreadorSitio`

| Origen | Fichero | Método | Detalle |
| --- | --- | --- | --- |
| Job de cola | `app/Jobs/RastrearSitioJob.php` | `rastrear()` | Único invocador de producción que **persiste**. Inyecta el rastreador y le pasa `$this->proposito`. |
| Diagnóstico | `app/Console/Commands/RastreoDiagnosticoCommand.php` | `simular()` | Mismo bucle, **sin escribir** en BD. Luego `MotorAuditoria::evaluar()` en memoria e imprime la traza. |
| Tests | `tests/Feature/RastreadorSitioTest.php`, `RastreadorSitioEscenariosTest.php`, `RastreoDiagnosticoCommandTest.php` | `rastrear()` / `simular()` | Siempre vía `$this->app->make(RastreadorSitio::class)`. |

No hay ningún **controlador**, evento, listener ni observer que llame a
`rastrear()`. El único camino a producción que persiste pasa por `RastrearSitioJob`.

En `tests/Feature/RastrearSitioJobTest.php` la clase se sustituye por un mock
(`$this->mock(RastreadorSitio::class, ...)`), así que ahí `rastrear()` real nunca
se ejecuta: en un test se fuerza `andThrow(LimiteRitmoExcedido)` y en otro se
exige `shouldReceive('rastrear')->never()`.

`simular()` activa un flag interno: las escrituras pasan por `persistirPagina()`
(que en simulación guarda `Pagina` en memoria y registra visitas) y se saltan
emails, `rastreado_at` y `rastreo_fallido_at`. El comando de diagnóstico
comprueba antes/después que el conteo de páginas y `rastreado_at` no cambian.

### 1.2 Quién encola `RastrearSitioJob`

| Origen | Fichero | Detalle |
| --- | --- | --- |
| Comando `leads:rastrear` | `app/Console/Commands/RastrearCommand.php` | `RastrearSitioJob::dispatch($lead->id, $proposito)` sin `onQueue` explícito; la cola `scraping` la fija el constructor del job. El propósito llega en `--proposito=email\|auditoria\|completo` (por defecto `completo`); un valor desconocido aborta con `FAILURE` sin encolar nada. Selecciona leads con `website` no nulo, filtrando por `--solo-sin-rastrear`, por antigüedad de `rastreado_at` (`--dias-desde`, por defecto 90) salvo `--forzar`, y opcionalmente por `--sector`. Orden por `id`, límite `--limite` (200 por defecto). |
| Cosecha Overpass | `app/Services/Overpass/ServicioCosecha.php` | Un `RastrearSitioJob::dispatch($lead->id, PropositoRastreo::COMPLETO)->onQueue('scraping')` por cada lead recién creado, dentro del bucle de candidatos del área. |

El comando `leads:rastrear` **no está en el scheduler**: `routes/console.php` no
lo registra, así que se lanza a mano o desde cron externo. El rastreo continuo
llega, de hecho, por la cosecha.

Tests que cubren el encolado (sin ejecutar el rastreo):
`tests/Feature/RastrearSitioJobTest.php:74-96` (comando) y
`tests/Feature/CosechaTest.php:131-132` (cosecha).

### 1.3 Contexto de ejecución del job

- Cola `scraping`, fijada en `__construct` (`$this->onQueue('scraping')`).
- `__construct(int $leadId, PropositoRastreo $proposito = PropositoRastreo::COMPLETO)`;
  el enum viaja serializado con el job.
- `tries = 3`, `timeout = 120`, `backoff() = [30, 120, 600]`.
- Antes de rastrear marca `Latido::marcar('scrape', $lead->website_dominio)`;
  `config('outreach.latido.procesos.scrape')` vale 900 s.
- Si el lead no existe, `handle()` retorna sin más.
- `LimiteRitmoExcedido` → `release(120)` y retorno (no consume intento).
- `UrlNoPermitida` → marca `rastreado_at` y añade nota al lead. **Ojo:** para la
  URL base, `rastrear()` captura esa excepción internamente y devuelve un
  `ResultadoRastreo` con errores, así que este `catch` no se dispara en ese caso.
- `failed()` marca `rastreado_at` y `rastreo_fallido_at`, y deja la nota
  «Rastreo fallido: …».

### 1.4 Cableado de dependencias

`ComprobadorRobots` se resuelve con un binding explícito en
`app/Providers/AppServiceProvider.php:51-52`, que le pasa `config('outreach.scraper')`
como array de configuración en el momento de construirse. Por eso
`RastreadorSitioTest::setUp()` llama a `$this->app->forgetInstance(ComprobadorRobots::class)`
después de cambiar la config: si no, se quedaría con el snapshot anterior.
El resto de colaboradores se autoresuelven.

---

## 2. Flujo de `rastrear(Lead $lead, PropositoRastreo $proposito = COMPLETO): ResultadoRastreo`

### 2.0 El propósito

`App\Enums\PropositoRastreo` es un enum de respaldo `string` con tres casos:

| Caso | Valor | Efecto hoy |
| --- | --- | --- |
| `EMAIL` | `email` | Único propósito que permite el corte anticipado del bucle al reunir correos de rol suficientes. No descubre rutas. |
| `AUDITORIA` | `auditoria` | Recorre todas las rutas previstas aunque ya tenga correos, y amplía la lista con lo que descubra en la home. |
| `COMPLETO` | `completo` | Igual que `AUDITORIA`, y es el valor por defecto. |

Dos métodos del enum gobiernan el bucle: `permiteCorteAnticipadoPorCorreos()`
(solo `EMAIL`) y `permiteDescubrirRutas()` (todos menos `EMAIL`).

El conjunto de rutas se pide a `rutasPara($proposito, $lead)`:

- `EMAIL` → `config('outreach.scraper.rutas.email')`.
- `AUDITORIA` y `COMPLETO` → `rutas.auditoria_comunes` más
  `rutas.auditoria_sector.{$lead->sector}`, deduplicadas manteniendo el orden.
  Si el lead no tiene sector, solo las comunes.

El límite es por propósito: `config('outreach.scraper.max_paginas.{valor}')`
(6 para `email`, 10 para `auditoria`, 12 para `completo`).

### 2.0.1 Descubrimiento de rutas

Las rutas fijas fallan porque cada web nombra sus páginas distinto
(`/carta`, `/menu`, `/nuestra-carta`, `/es/carta`…). Con `AUDITORIA` o
`COMPLETO`, en cuanto la home se procesa con éxito, `DescubridorRutas::descubrir()`
propone hasta 4 rutas internas más a partir de sus enlaces:

- Puntúa cada `<a>` por palabras clave del sector
  (`config('outreach.scraper.palabras_clave_sector')`, mismas familias que
  `CatalogoSectores`): +3 si la palabra aparece en la ruta, +2 si aparece en el
  texto ancla. Sin tildes, en minúsculas y con `-`, `_` y `/` convertidos en
  espacios, así que «/pedir-cita» casa con «pedir cita». Sin sector conocido se
  usan las palabras de todas las familias.
- Descarta anclas, `mailto:`, `tel:`, `javascript:`, otros esquemas, enlaces
  externos, ficheros por extensión (`.pdf`, `.jpg`, `.zip`…), la home y las rutas
  que ya están en el conjunto fijo del sector.
- Normaliza quitando query y fragmento, colapsando barras, quitando la barra
  final y pasando a minúsculas; deduplica por ruta normalizada quedándose con la
  mejor puntuación, y ordena de más a menos puntos (a igual puntuación, orden del
  documento).

Las rutas descubiertas se añaden al final de la cola de trabajo solo si caben en
`max_paginas`; las que no caben se anotan en `rutasOmitidas`. Como entran por el
mismo bucle, siguen pasando por robots.txt y por el limitador de ritmo.

El recorrido de los `<a>` y la resolución de rutas relativas viven en
`App\Services\Web\LectorEnlaces`, compartido con `ExtractorMetadatos`, que lo usa
para contar enlaces internos y externos.

### 2.1 Antes del bucle

1. Inicializa contadores (`paginasVisitadas`, `paginasGuardadas`, `emailsGuardados`,
   `emailsDescartados`), las listas `errores` y `rutasOmitidas`, y el mapa
   `emailsAcumulados` (`email => url_origen`).
2. **Salida anticipada A**: si `lead->website` es `null` o queda vacío tras `trim`,
   devuelve `ResultadoRastreo(0, 0, 0, 0, [], $proposito, [], 'guardia')`. No se
   toca el lead: `rastreado_at` sigue como estaba.
3. `normalizarBase()`: `trim`, antepone `https://` si no hay esquema `http(s)`, y
   hace `rtrim($url, '/')`. La base nunca termina en barra.
4. **Salida anticipada B**: `GuardiaUrl::comprobar($base)`. Si lanza
   `UrlNoPermitida`, devuelve `ResultadoRastreo(0, 0, 0, 0, [mensaje], $proposito, [], 'guardia')`.
   Tampoco se toca el lead. Es el caso que cubre `test_no_visita_ip_privada`.
5. `maxPaginas = config('outreach.scraper.max_paginas.{proposito}')` (mínimo 1) y
   `rutas = array_slice($this->rutasPara($proposito, $lead), 0, maxPaginas)`. El
   recorte se aplica **a la lista de rutas**, antes de filtrar por robots: las
   rutas que caen fuera no se consideran nunca (se anotan en `rutasOmitidas` con
   el motivo «fuera del límite de max_paginas») y una ruta bloqueada por robots
   consume igualmente su plaza.
6. `urlHome = null`, `homeIntentada = false`, `falloHome = null`,
   `emailsRolEncontrados = 0`, `yaDescubierto = false`, `crawlDelay =
   robots->crawlDelayDe($base)`, y la cola de trabajo `pendientes` arranca como
   copia de `rutas`.

### 2.2 Bucle `for ($indice = 0; $indice < count($pendientes); $indice++)`

Es un bucle por índice, no un `foreach`, porque la home puede alargar
`pendientes` con las rutas que descubra mientras se recorre.

Condiciones de salida del bucle, en el orden exacto en que se evalúan:

| # | Tipo | Condición | Efecto |
| --- | --- | --- | --- |
| 1 | `break` | `$proposito->permiteCorteAnticipadoPorCorreos() && $emailsRolEncontrados >= self::EMAILS_ROL_SUFICIENTES` | **Solo con propósito `EMAIL`.** Corta el rastreo entero y vuelca las rutas pendientes (desde `$indice`) en `rutasOmitidas` con el motivo «corte anticipado». El umbral es la constante `EMAILS_ROL_SUFICIENTES = 3`, distinta de `max_emails_por_lead`. Se evalúa al principio de la iteración, con lo acumulado en las anteriores. Con `AUDITORIA` o `COMPLETO` esta rama nunca se toma. |
| 2 | `continue` | `! robots->rutaPermitida($base, $ruta)` | Ni se cuenta como visitada ni se crea fila; se anota en `rutasOmitidas` como «bloqueada por robots.txt». Si la ruta bloqueada es la home, el rastreo queda marcado como fallido con `fallo_tipo = 'robots'`. |
| 3 | excepción | `LimitadorRitmo::esperar()` lanza `LimiteRitmoExcedido` | **No se captura dentro de `rastrear()`**: aborta todo el método, el lead no se actualiza y la excepción sube al job, que hace `release(120)`. |
| 4 | `continue` o `break` | Los tres intentos de `pedirConReintentos()` acaban sin respuesta | Añade `"Error HTTP en {url}: …"` a `errores` y **no crea fila**. La página ya se contó como visitada (el `paginasVisitadas++` ocurre **antes** de la petición). Si la que falla es la home y el fallo es de transporte (`dns`, `tls`, `timeout`), se hace `break`: el host no responde, así que el resto de rutas se vuelca en `rutasOmitidas` como «host inaccesible». |
| 5 | `continue` | La URL final tras redirecciones no pasa `GuardiaUrl::comprobar()` | Añade el mensaje a `errores`, no crea fila. La página ya contó como visitada. Si era la home, `fallo_tipo = 'guardia'`. |
| 6 | `continue` | El `Content-Type` no contiene `text/html` ni `application/xhtml` | En rutas que **no** son la home guarda fila (solo campos de transporte) y suma `paginasGuardadas`. Si es la home, **no guarda nada**: se anota el error y `fallo_tipo = 'http'`. |
| 7 | `continue` | `status < 200 || status > 299` | En rutas que **no** son la home guarda fila con `error = 'HTTP {status}'` y `fallo_tipo = 'http'`, y suma `paginasGuardadas`. Si es la home, **no guarda fila**: solo error y `fallo_tipo = 'http'`. |

`rutasOmitidas` recoge solo rutas que **nunca se pidieron** (límite de páginas,
robots, corte anticipado, host inaccesible), en formato `"ruta: motivo"` y con la
ruta vacía representada como `/`. Los fallos de rutas que sí se intentaron viven
en `errores`.

#### 2.2.1 Reintentos y clasificación del fallo

`pedirConReintentos($url)` hace hasta `INTENTOS_MAXIMOS = 3` intentos y devuelve
un `App\DTO\IntentoHttp` (`respuesta`, `ms`, `intentos`, `falloTipo`, `mensaje`):

- Se reintenta **solo** ante excepción de transporte (timeout, conexión caída,
  DNS, TLS) y ante los estados `429`, `502`, `503` y `504`. Cualquier otro 4xx
  se devuelve al primer intento; reintentarlo no cambiaría nada.
- La espera entre intentos es `BACKOFF_SEGUNDOS = [1, 4]`. Si la respuesta trae
  `Retry-After` (segundos o fecha HTTP), manda esa cabecera, recortada a 60 s.
  Las esperas usan `Illuminate\Support\Sleep`, así que los tests las fingen.
- `clasificarExcepcion()` traduce el mensaje de cURL a `timeout`, `dns` o `tls`;
  lo que no encaja queda como `http`.

El número de intentos consumidos se guarda en `paginas.intentos`, y el tipo de
fallo en `paginas.fallo_tipo` (`null` en las filas que se parsearon bien).
`ResultadoRastreo::falloTipo` resume el fallo **del rastreo entero**: es `null`
si la home se obtuvo, y si no vale `'dns'`, `'tls'`, `'timeout'`, `'http'`,
`'robots'` o `'guardia'`. `ResultadoRastreo::fallido()` es el atajo booleano.

#### 2.2.2 La home caída no deja rastro en `paginas`

Una fila de `paginas` sin metadatos es indistinguible de una página que sí se
parseó y no tenía ninguna señal, así que la auditoría la leería como ausencia.
Por eso, cuando la home no llega a producir HTML usable —sin respuesta, no HTML
o estado no 2xx— **no se escribe fila** y el lead se marca con
`rastreo_fallido_at`. Las rutas distintas de la home sí siguen guardando su fila
de error, porque ahí el fallo es de esa URL concreta y no del rastreo.

Cuerpo normal de la iteración (ninguna de las condiciones anteriores se cumple):

1. `LimitadorRitmo::esperar($url, $crawlDelay)` y `paginasVisitadas++`.
2. Petición vía `pedirConReintentos()` con `User-Agent` de config, `From` con el
   correo que anuncia ese mismo user agent, `Accept` de HTML,
   `Accept-Language: es-ES`, `timeout` de config y
   `allow_redirects = ['max' => max_redirecciones, 'track_redirects' => true]`.
   Se mide `ms` alrededor de cada intento.
3. `urlFinalTrasRedirect()`: lee la cabecera `X-Guzzle-Redirect-History`; si está
   vacía usa `effectiveUri()`, y si no toma el último elemento del historial
   (aceptando array o cadena separada por comas).
4. Segunda comprobación de `GuardiaUrl` sobre la URL final.
5. `ExtractorMetadatos::extraer($body, $urlFinal, $status, $ms, $contentType, $redirigidaA)`.
6. Al array de `aArrayBd()` se le añaden `lead_id`, `es_https`
   (`str_starts_with($urlFinal, 'https://')`), `capturada_at = now()`,
   `intentos`, `fallo_tipo = null` y la **trazabilidad**
   (`proposito` + `origen_ruta` = `fija`|`descubierta`). Todo pasa por
   `persistirPagina()`, que en modo real hace
   `Pagina::updateOrCreate(['lead_id' => …, 'url' => $urlFinal], $datos)` y en
   simulación acumula la fila en memoria. `paginasGuardadas++`.
7. Si la ruta es `''` o `'/'`, `urlHome = $urlFinal` y, si el propósito permite
   descubrir, se encolan hasta `max_paginas` rutas de `DescubridorRutas`.
8. Por cada email de `metadatos->emailsEncontrados`: se pasa a minúsculas y se
   recorta; los vacíos y los ya vistos se saltan; el resto se guarda en
   `emailsAcumulados[email] = urlFinal` (gana la **primera** URL donde aparece).
   Si `ClasificadorEmail::clasificar()` devuelve `ROL`, incrementa
   `emailsRolEncontrados` (el contador que dispara el `break` de la siguiente
   vuelta, solo con propósito `EMAIL`).

### 2.3 Después del bucle

1. **Certificado de la home**: solo si `urlHome !== null` **y** el entorno no es
   `testing`. `actualizarCertificadoHome()` delega en `InspectorCertificado`, que
   abre `ssl://{host}:443` primero con `verify_peer` y `verify_peer_name` a
   `true` (si conecta, `cert_cadena_valida = true`) y, si eso falla, repite sin
   verificar solo para leer `validTo_time_t` (`cert_cadena_valida = false` pero
   `cert_expira_at` relleno). Escribe `cert_valido` (= no caducado),
   `cert_cadena_valida`, `cert_expira_at` y `es_https` en **todas** las páginas
   del lead cuya `url` sea la home *o* cuya `ruta` esté en `['/', '']`. Si no hay
   TLS, los tres campos quedan en `null` (y aun así ejecuta el `update`).
2. **Filtrado de candidatos**. Por cada email acumulado, se descarta
   (sumando `emailsDescartados`) si:
   - la clasificación no es `ROL`;
   - `Suppression::existe($email)` (mira email exacto, hash SHA-256 y dominio);
   - ya existe ese email en `lead_emails` (comprobación **global**, no por lead).
   Los que sobreviven pasan a `candidatosRol` con `prioridad` y `prefijo`.
3. **Orden y recorte** (211-213): `usort` ascendente por `prioridad` (0 = `info`,
   `contacto`, `hola`…; 9 = desconocido) y `array_slice` a
   `max_emails_por_lead` (3).
4. **Persistencia de emails** (215-227): un `LeadEmail::create()` por candidato con
   `tipo = ROL`, `origen = 'web'`, `url_origen`, `prioridad` y
   `es_principal = ($i === 0)`. No se consulta si el lead ya tenía un principal
   (por ejemplo, uno de origen `osm` creado por la cosecha).
5. **Actualización del lead**: `forceFill` con
   `website_dominio = Suppression::dominioDeWeb($base) ?? $lead->website_dominio`,
   `rastreado_at = now()` y `rastreo_fallido_at`, que vale `now()` si se intentó
   la home y no se obtuvo HTML usable, y `null` en caso contrario (un rastreo
   bueno limpia la marca de uno malo anterior). Solo si `emailsGuardados > 0`
   **y** el estado actual es exactamente `'nuevo'`, añade `estado = 'rastreado'`.
   Se usa `forceFill`, así que el `$fillable` del modelo no interviene.
6. Devuelve `ResultadoRastreo(paginasVisitadas, paginasGuardadas, emailsGuardados,
   emailsDescartados, errores, proposito, rutasOmitidas, falloTipo)`. Los tres
   últimos campos tienen valor por defecto (`COMPLETO`, `[]` y `null`); las dos
   salidas anticipadas devuelven `falloTipo = 'guardia'`.

### 2.4 Consecuencias observables del orden actual

- `paginasVisitadas` cuenta rutas intentadas, no éxitos ni peticiones: una ruta
  que consume tres intentos suma 1. Incluye timeouts y URLs rechazadas por el
  guardia tras redirección. `paginasGuardadas` cuenta filas escritas, incluyendo
  no-HTML y errores HTTP de rutas que no son la home.
- Dos rutas distintas que redirijan a la misma URL final producen **una sola fila**
  (la clave de `updateOrCreate` es la URL final) pero suman **dos** a
  `paginasGuardadas`.
- Las salidas anticipadas A y B devuelven sin tocar `rastreado_at`, de modo que el
  filtro de `leads:rastrear` («sin rastrear» o «rastreado hace más de N días»)
  vuelve a seleccionar esos leads en cada pasada.
- En la rama del extractor, `ruta` no es la ruta de config sino
  `parse_url($urlFinal, PHP_URL_PATH)`, mientras que las ramas de no-HTML y de
  error HTTP escriben explícitamente `'/'` cuando la ruta de config era `''` o
  `'/'`. Como `normalizarBase()` quita la barra final, la home solo acaba con
  `ruta = '/'` si la URL final la recupera (redirección o normalización del
  cliente HTTP); si el path final queda vacío, `parse_url` devuelve `null` y la
  fila se guarda con `ruta = NULL`. `Pagina::scopeHome()` y `Pagina::esHome()`
  filtran por `ruta IN ('/', '')`, que no captura `NULL`.

---

## 3. Campos que rellena `ExtractorMetadatos`

`extraer()` solo se invoca en la rama de éxito (HTML + 2xx). Cada extractor
individual se envuelve en `seguro()`, que **captura cualquier `Throwable` y
devuelve `null`**. La capacidad de distinguir «ausencia confirmada» de «no
observado» depende de si el valor de ausencia coincide con `null` y de si hay un
valor por defecto que colapse el fallo.

Antes de parsear, si el HTML supera `max_bytes_html` (2 MiB) se conserva el
60 % inicial y el 40 % final del límite, unidos por `<!-- TRUNCADO -->`, y se
marca `contenido_truncado = true`. Así el pie (copyright, legales, redes) no se
pierde. Sin truncar, `contenido_truncado` queda en `false`.

| Campo BD | Origen | Ausencia confirmada | Fallo / no observado | ¿Distingue? |
| --- | --- | --- | --- | --- |
| `url` | Argumento (URL final) | — | — | N/A: siempre presente |
| `ruta` | `parse_url(url, PATH)` | `null` (URL sin path) | `null` | **No** |
| `http_status` | Argumento de `rastrear()` | — | — | N/A: siempre presente |
| `content_type` | Argumento | `null` si la cabecera venía vacía | `null` | **No** |
| `bytes` | `strlen($html)` tras el recorte cabeza+cola | — | — | N/A (mide el HTML ya recortado, no el cuerpo real) |
| `respuesta_ms` | Argumento | — | — | N/A |
| `redirigida_a` | Argumento | `null` = no hubo redirección | `null` (historial ilegible) | **No** |
| `title` | `<title>` | `null` (sin etiqueta o vacía) | `null` | **No** |
| `title_longitud` | `mb_strlen(title)` | `null` | `null` | **No** |
| `meta_description` | `<meta name=description>` | `null` | `null` | **No** |
| `meta_desc_longitud` | Derivado | `null` | `null` | **No** |
| `h1_texto` | Primer `<h1>` | `null` (sin h1 o texto vacío) | `null` | **No** |
| `h1_total` | `count('h1')` | `0` | `null` (default `['total' => null]`) | **Sí** |
| `h2_total` | `count('h2')` | `0` | `null` | **Sí** |
| `idioma` | `<html lang>` | `null` | `null` | **No** |
| `canonical` | `<link rel=canonical>` | `null` | `null` | **No** |
| `generador` | `<meta name=generator>` + huellas en el HTML | `null` (sin meta y sin huella); `'otro'` si hay meta pero desconocido | `null` | **No** |
| `charset` | `<meta charset>` o `http-equiv` | `null` | `null` | **No** |
| `tiene_viewport` | `<meta name=viewport>` con `width=device-width` | `false` | `null` | **Sí** |
| `tiene_favicon` | `<link rel*=icon>` | `false` | `null` | **Sí** |
| `tiene_og` | `og:title` u `og:image` | `false` | `null` | **Sí** |
| `tiene_jsonld` | `script[type=ld+json]` con `@type` | `false` | `false` (default `['tiene' => false]`) | **No** |
| `jsonld_tipos` | `@type` recursivos, únicos | `[]` | `[]` (mismo default) | **No** |
| `imagenes_total` | `count('img')` | `0` | `null` | **Sí** |
| `imagenes_sin_alt` | `img` sin `alt` o con `alt` vacío | `0` | `null` | **Sí** |
| `enlaces_internos` | `<a>` relativos o del mismo host | `0` | `null` | **Sí** |
| `enlaces_externos` | `<a>` de otro host | `0` | `null` | **Sí** |
| `redes_sociales` | `<a>` a 7 dominios conocidos, con path | `[]` | `null` (sin default) | **Sí** |
| `telefonos` | `a[href^=tel:]` + regex de móvil ES, máx. 5 | `[]` | `null` | **Sí** |
| `emails_encontrados` | `mailto:` + regex de email sobre el HTML decodificado | `[]` | `null` | **Sí** |
| `tiene_formulario` | `form` con `textarea`, `input[type=email]` o `name` con «mail» | No aplica: nunca `false` | `null` | **No, a propósito** (ver abajo) |
| `tiene_whatsapp` | `<a>` a `wa.me` o `api.whatsapp.com` | No aplica: nunca `false` | `null` | **No, a propósito** |
| `tiene_reservas` | Patrones en texto normalizado, href o texto de enlaces | No aplica: nunca `false` | `null` | **No, a propósito** |
| `tiene_carrito` | Generador shopify/prestashop, patrones, o clases `cart`/`basket` | No aplica: nunca `false` | `null` | **No, a propósito** |
| `tiene_aviso_legal` | Texto/href de `<a>` | No aplica: nunca `false` | `null` | **No, a propósito** |
| `tiene_privacidad` | Texto/href de `<a>` | No aplica: nunca `false` | `null` | **No, a propósito** |
| `tiene_cookies` | Texto/href de `<a>` | No aplica: nunca `false` | `null` | **No, a propósito** |
| `anio_copyright` | Regex de `©`/`copyright` + año entre 1995 y el actual | `null` (sin marca o año fuera de rango) | `null` | **No** |
| `html_hash` | `sha1` del HTML con espacios colapsados | — | — | N/A: siempre presente |
| `contenido_truncado` | `true` si se aplicó el recorte cabeza+cola | `false` = cabía entero | — | **Sí** |
| `error` | Constante | Siempre `null` en esta rama | — | N/A |
| `capturada_at` | `now()` (lo reescribe `rastrear()`) | — | — | N/A |

Campos de `paginas` que el extractor **no** toca:

| Campo | Quién lo escribe | Semántica del `null` |
| --- | --- | --- |
| `lead_id` | `rastrear()` en las tres ramas | Nunca nulo |
| `es_https` | `rastrear()` y `actualizarCertificadoHome()` | Nunca nulo en filas escritas por el rastreo |
| `cert_valido` | Solo `InspectorCertificado` vía `actualizarCertificadoHome()` | `null` = no medido; `true`/`false` = **solo** si ha caducado o no |
| `cert_cadena_valida` | Solo `InspectorCertificado` | `null` = no medido; `true` = OpenSSL aceptó cadena y nombre; `false` = falló la verificación (autofirmado, nombre incorrecto…) pero se pudo leer la fecha en un segundo intento sin verificar |
| `cert_expira_at` | Solo `InspectorCertificado` | Se rellena aunque la cadena falle, si el segundo intento consigue el certificado |
| `intentos` | `rastrear()` en las ramas que escriben fila | Cuántas peticiones costó esa URL (1 a 3) |
| `fallo_tipo` | `rastrear()` | `null` = la página se parseó bien; `'http'` = respondió con un estado no 2xx |
| `proposito` | `rastrear()` en las tres ramas | Con qué propósito se pidió. `NULL` solo en filas anteriores a esta columna; `Pagina::señal()` las trata como sin cobertura |
| `origen_ruta` | `rastrear()` en las tres ramas | `'fija'` si la ruta venía de config, `'descubierta'` si la propuso `DescubridorRutas` |
| `enlaces_senal` | `DetectorEnlacesSenal` vía `rastrear()`, **solo en la home** | `null` = no se midió (no es la home, o el HTML no se pudo recorrer). `{"reservas": true, …}` = qué señales anunciaba la home en sus enlaces internos |

### Señales de presencia: por qué nunca valen `false`

`tiene_formulario`, `tiene_whatsapp`, `tiene_reservas`, `tiene_carrito`,
`tiene_aviso_legal`, `tiene_privacidad` y `tiene_cookies` solo prueban
presencia. Devolvían `false` tanto cuando se había comprobado que no estaban
como cuando no se había mirado la página adecuada, y esa ambigüedad es la raíz
de los falsos positivos de la auditoría. Ahora valen `true` (detectado en esta
página) o `null` (no detectado aquí, que no es prueba de ausencia).

La ausencia se decide a nivel de lead con
`Pagina::señal($paginas, $campo, $sector)`:

- `true` si alguna página la detectó.
- `false` solo si hubo **cobertura sana** y, en las señales sectoriales, además
  se miró donde esa señal vive.
- `null` en el resto: nadie la detectó y no se miró donde había que mirar.

**Cobertura sana** (`Pagina::aportaCobertura()`): la página se pidió con
propósito `AUDITORIA` o `COMPLETO`, respondió `200` y su `fallo_tipo` es `null`.
Un 404 o un timeout visitan el sitio, pero no demuestran nada, así que no
cuentan aunque sumen al contador de páginas.

Las señales se parten en dos grupos:

| Grupo | Campos | Regla para el `false` |
| --- | --- | --- |
| De sitio | `tiene_aviso_legal`, `tiene_privacidad`, `tiene_cookies` | Basta con cobertura sana: viven en el pie de cualquier página |
| Sectoriales | `tiene_reservas`, `tiene_carrito`, `tiene_formulario`, `tiene_whatsapp` | Cobertura sana **y** al menos una página sana que sea ruta descubierta o ruta del sector del lead en `outreach.scraper.rutas.auditoria_sector` |

Sobre las sectoriales manda además un veto: si la home enlazaba a esa señal
(`paginas.enlaces_senal`), la señal se queda en `null` aunque se cumpla lo
anterior. Una web que anuncia «Reservar mesa» en el menú no se puede acusar de
no tener reservas solo porque nadie abrió ese enlace. `enlaces_senal` **solo
sirve para callar, nunca para afirmar**.

`Pagina::motivoSenal()` devuelve, cuando la señal queda en `null`, cuál de los
tres motivos fue: `sin cobertura sana`, `sin ruta sectorial visitada` o
`la home enlaza a esa señal`. Es lo que imprime `rastreo:diagnostico`.

Quien lo consume son las comprobaciones `SinReservas`, `SinCarrito`,
`SinFormularioContacto` y `SinWhatsapp`: solo emiten hallazgo cuando `señal()`
devuelve `false`, y entonces lo marcan con `Certeza::OBSERVADO` y una evidencia
que nombra las rutas sanas donde se buscó («buscado en /, /carta, /reservas»).
Con `null` no emiten nada, así que un lead rastreado solo con propósito `EMAIL`,
o uno de hostelería cuya `/reservas` dio 404, nunca genera un `sin_reservas`.

Los que sí son observables mirando solo el HTML de la página siguen siendo
`bool`: `tiene_viewport`, `tiene_favicon`, `tiene_og` y `tiene_jsonld`.

### De las señales a los hallazgos (`Certeza`)

`MotorAuditoria::auditar()` (persistente) y `MotorAuditoria::evaluar()` (en
memoria, usado por el diagnóstico) recorren las 25 comprobaciones. Cada
`Hallazgo` lleva `Certeza` y `evidencia`:

| Certeza | Cuándo | Ejemplos | ¿En el correo? |
| --- | --- | --- | --- |
| `HECHO` | Medido en la respuesta o en PSI | `sin_https`, `cert_caduca`, `contacto_roto`, `respuesta_lenta`, `html_pesado`, las cinco `psi_*` | Sí |
| `OBSERVADO` | `Pagina::señal()` devolvió `false` | `sin_reservas`, `sin_carrito`, `sin_formulario`, `sin_whatsapp` | Sí |
| `INDICIO` | Señal de una sola página o ausencia no cubierta por `señal()` | `sin_viewport`, `title_malo`, `web_abandonada`, `sin_aviso_legal`, `sin_cookies`, `sin_redes`, … | **No**: `RedactorHallazgo` cae a la apertura genérica del sector |

Los `INDICIO` **sí suman peso** y ordenan el ranking interno; solo se omiten al
redactar. La columna `auditorias.hallazgo_certeza` guarda la del principal; cada
entrada del JSON `hallazgos` lleva `certeza` y `evidencia`.

`RedactorHallazgo` falla en cerrado: si en una auditoría antigua no consta la
certeza, no se afirma el hallazgo y se usa la apertura genérica del sector.

### Diagnóstico en seco

```bash
php artisan rastreo:diagnostico {id|dominio} --proposito=completo
```

Imprime: rutas fijas vs descubiertas, visitas (HTTP/ms/bytes/truncado/fallo),
omitidas, tabla de señales (`sí` / `no` / `no observado`, con el motivo del «no
observado») y hallazgos con certeza. Es la herramienta para verificar webs a
mano sin ensuciar la BD.

Dos matices que afectan a todo el bloque de metadatos:

1. En las ramas de **no-HTML** y **error HTTP**, `updateOrCreate` solo escribe los
   campos de transporte. En una fila nueva, todas las columnas de metadatos quedan
   `NULL`; en una fila que ya existía de un rastreo anterior, **conservan los
   valores viejos**. Un `NULL` de metadatos significa, por tanto, «esta URL nunca
   se parseó con éxito», no «la página no tenía eso».
2. `error` solo se escribe en la rama de error HTTP. Como la rama de éxito lo pone
   a `null` vía `aArrayBd()`, un rastreo posterior correcto limpia el error previo.

---

## 4. Invariantes que el código asume hoy

### Tabla `paginas`

1. **Índice único `paginas_lead_url_unico`**. En PostgreSQL es
   `(lead_id, md5(url))`; en SQLite (tests) es `(lead_id, url)` porque no hay
   `md5()` nativo. La unicidad es sobre la **URL final tras redirecciones**, no
   sobre la ruta de configuración.
2. **`updateOrCreate` siempre por `['lead_id', 'url']`**, en las tres ramas de
   escritura. Cambiar la clave (por ejemplo a `ruta`) rompería la protección del
   índice único. `ruta` no forma parte de la clave: un cambio de ruta que apunte a
   la misma URL actualiza la fila existente.
3. **Escrituras parciales**. Las ramas de no-HTML y de error HTTP pasan un
   subconjunto de columnas; el resto no se resetea. El código de análisis asume
   que puede haber filas con columnas de metadatos obsoletas o nulas.
   `proposito`, `origen_ruta` e `intentos` sí los escriben las tres ramas: sin
   `proposito` en la fila, `Pagina::señal()` nunca puede devolver `false`.
4. **`capturada_at` es `NOT NULL` sin default**. Las tres ramas lo fijan
   explícitamente a `now()`; en la rama del extractor, `rastrear()` sobrescribe el
   `capturada_at` que ya traía el DTO.
5. **La `ruta` de la home siempre la escribe la rama del extractor**, porque las
   de no-HTML y error HTTP ya no guardan fila para la home. Esa rama guarda el
   path de la URL final, que puede ser `NULL`, pese a que `Pagina::scopeHome()` y
   `Pagina::esHome()` filtran por `ruta IN ('/', '')`.
   `actualizarCertificadoHome()` no depende solo de eso porque también empareja
   por `url`.
6. Índices auxiliares existentes: `(lead_id, capturada_at)` y `(lead_id, ruta)`.
7. Los campos JSON (`jsonld_tipos`, `redes_sociales`, `telefonos`,
   `emails_encontrados`) son `jsonb` y el modelo los castea a `array`; el
   extractor puede entregar `null` en cualquiera de ellos.

### Tabla `lead_emails`

8. **`lead_emails.email` es único global** (no por lead). El
   `LeadEmail::where('email', …)->exists()` previo al `create()` es lo único que
   evita una `QueryException`; si se quita, un email compartido entre dos leads
   aborta el rastreo entero.
9. **`es_principal` se decide por posición** (`$i === 0` sobre la lista ya
   ordenada y recortada), sin mirar si el lead ya tenía un principal de otro
   origen (la cosecha crea emails `origen = 'osm'` con `es_principal = true`).
10. **`prioridad` menor es mejor** y determina qué email es el principal;
    `ClasificadorEmail::prioridad()` devuelve 0-3 para prefijos conocidos y 9 para
    el resto.
11. Solo se persisten emails clasificados como `ROL`. `ClasificadorEmail` cae en
    `ROL` por defecto (regla 11) tras dejar un log de depuración, así que
    «desconocido» acaba guardándose.
12. El filtro de bajas usa `Suppression::existe()`, que comprueba email exacto,
    hash SHA-256 y dominio.

### Límites de ritmo

13. **`LimitadorRitmo::esperar($url, $crawlDelay)` se llama una vez por ruta, antes
    de la petición y antes de incrementar `paginasVisitadas`**. Consume dos claves:
    `scrape:global` (`peticiones_por_minuto`, 20) y `scrape:host:{host}`
    (`peticiones_por_dominio_por_minuto`, 4), ambas con ventana de 60 s.
14. **Espera activa acotada**: hasta 30 vueltas de `usleep(500 ms)` por clave, es
    decir ~15 s por clave y hasta ~30 s por ruta; después lanza
    `LimiteRitmoExcedido`.
15. **El `Crawl-delay` de robots.txt solo se respeta si es mayor que nuestro
    propio intervalo** (`60 / peticiones_por_dominio_por_minuto`, 15 s con la
    config actual). El limitador recuerda en memoria la hora de la última
    petición por host y duerme lo que falte con `Illuminate\Support\Sleep`; ese
    recuerdo vive en la instancia, así que no cruza procesos.
16. **`LimiteRitmoExcedido` no se captura en `rastrear()`**. El contrato es que lo
    gestione `RastrearSitioJob` con `release(120)`. Cualquier otro invocador
    directo tendría que manejarlo por su cuenta.
17. Con `$timeout = 120` en el job, hasta 3 intentos por ruta y sus esperas de
    backoff, el peor caso de esperas más timeouts HTTP puede superar el timeout
    del job.
18. **La descarga de `robots.txt` no pasa por el limitador de ritmo** y usa su
    propio timeout de 10 s.

### robots.txt

19. **Se consulta antes de cada ruta**, incluida la home, mediante
    `rutaPermitida($base, $ruta)`.
20. **Caché de 24 h por host**, clave `robots:{host}` en el store de caché por
    defecto. Guarda un array `['disallows' => …, 'crawl_delay' => …]`, del que
    tiran tanto `rutaPermitida()` como `crawlDelayDe()`. El valor cacheado se
    construye a partir de la `$urlBase` de la primera llamada, así que el esquema
    y el puerto de esa primera URL determinan de dónde se descargó. Los tests
    hacen `Cache::flush()` y `forgetInstance(ComprobadorRobots::class)` en `setUp`
    por este motivo.
21. **Fallo silencioso permisivo**: si la petición falla o no es 2xx, no hay
    `Disallow` ni `Crawl-delay`, es decir, todo permitido y sin espera extra.
22. **Se interpretan `Disallow` y `Crawl-delay`** de los bloques cuyo `User-agent`
    sea `*` o esté contenido (`stripos`) en nuestro user-agent. Con varios bloques
    aplicables manda el `Crawl-delay` más lento, recortado a 30 s. `Allow` y los
    comodines (`*`, `$`) se siguen ignorando: el emparejamiento es prefijo
    literal. `Disallow: /` bloquea el sitio completo y produce 0 páginas.
23. `respetar_robots => false` en `config('outreach.scraper')` cortocircuita todo
    el comprobador, incluido el `Crawl-delay`. El array de config se inyecta en el
    binding del provider, no se lee en caliente.

### Seguridad de URLs (anti-SSRF)

24. **`GuardiaUrl::comprobar()` se ejecuta dos veces**: sobre la base antes del
    bucle y sobre la URL final después de seguir redirecciones. Ambas son
    necesarias; la segunda es la que cubre redirecciones a IPs internas.
25. Rechaza esquemas distintos de `http`/`https`, hosts `localhost` y
    `metadata.google.internal`, sufijos `.local` y `.internal`, y resuelve el host
    por DNS (`DNS_A`, `DNS_AAAA` y, como último recurso, `gethostbyname`)
    comparando contra 10 rangos IPv4 reservados más `::1`, `fc00::/7` y `fe80::/10`.
26. Una IP que no valide como IPv4 ni IPv6 se considera prohibida.
27. **No hay caché de DNS**: cada comprobación resuelve otra vez, y existe la
    ventana clásica entre la resolución del guardia y la que hace el cliente HTTP.

### Configuración y contadores

28. `max_paginas.{proposito}` recorta la **lista de rutas** con `array_slice`, no
    el número de páginas efectivamente descargadas, y también limita cuántas
    rutas descubiertas caben en la cola. Si la clave faltara se usa 6, y nunca
    baja de 1.
29. **El corte por emails de rol es la constante `RastreadorSitio::EMAILS_ROL_SUFICIENTES`
    (3)**, distinta e independiente de `max_emails_por_lead` (que solo recorta la
    lista final de candidatos), y **solo se aplica con propósito `EMAIL`**. Con
    `AUDITORIA` y `COMPLETO` el número de páginas depende de las rutas previstas,
    las descubiertas en la home, `max_paginas` y robots. El descubrimiento ocurre
    **una sola vez** por rastreo (`yaDescubierto`) y solo desde la rama de éxito
    HTML+2xx de la home: si la home falla o no es HTML, no se descubre nada.
30. **El correo de la cabecera `From` sale del propio `user_agent`**: se extrae
    con una expresión regular del texto configurado. Si el user agent no lleva
    correo, la cabecera no se envía.
31. `rastrear()` lee de config: `max_paginas.*`, `rutas.*`, `user_agent`,
    `timeout`, `max_redirecciones`, `max_emails_por_lead`; el descubridor lee
    `palabras_clave_sector` y `rutas.auditoria_*`; el extractor lee
    `max_bytes_html`; el limitador, `peticiones_por_minuto` y
    `peticiones_por_dominio_por_minuto`; el clasificador,
    `outreach.clasificador_email.*`.
32. `ClasificadorEmail` cachea `prefijos_rol.php` y `nombres_es.php` en
    **propiedades estáticas**, compartidas entre instancias y persistentes durante
    todo el proceso del worker.

### Estado del lead y entorno

33. `rastreado_at`, `rastreo_fallido_at` y `website_dominio` solo se escriben si
    el rastreo llega al final del método; las dos salidas anticipadas (sin web,
    base no permitida) lo dejan intacto, aunque devuelvan `falloTipo = 'guardia'`.
34. **`rastreo_fallido_at` se reescribe en cada rastreo**: `now()` si la home no
    dio HTML usable, `null` si sí. Un lead con esa marca no debe auditarse: sus
    páginas, si las hay, son de rutas sueltas y no de la home.
35. La transición de estado es únicamente `nuevo → rastreado`, y solo si se guardó
    al menos un email. Ningún otro estado se toca.
36. **`actualizarCertificadoHome()` se salta en el entorno `testing`**
    (`! app()->environment('testing')`), de modo que los tests nunca ejercitan esa
    ruta ni abren sockets TLS.
37. Ese mismo método actualiza en bloque todas las páginas del lead que casen por
    URL o por `ruta IN ('/', '')`, así que puede tocar más de una fila.
38. El job registra `Latido::marcar('scrape', $lead->website_dominio)` antes de
    rastrear, con TTL de 900 s en `config('outreach.latido.procesos.scrape')`.
    `VigilanteCommand::revisarLatidosCriticos()` **no** incluye `scrape` en su
    lista de procesos críticos, así que ese latido hoy solo sirve de
    observabilidad.
39. Los tests asumen que el job se encola en `scraping` (`Queue::assertPushedOn`),
    cola fijada en el constructor y no en los puntos de despacho.

---

## 5. Limitaciones conocidas

Documentadas por `tests/Unit/ExtractorMetadatosFixturesTest` contra los HTML de
`tests/Fixtures/webs/`. No se «arreglan» forzando el valor esperado del test.

1. **«Derechos reservados» dispara `tiene_reservas`**. El patrón busca la
   subcadena `reserva` en el texto normalizado de toda la página. Un pie típico
   con «Todos los derechos reservados» (fixture `web-abandonada-2018.html`)
   produce `tiene_reservas = true` aunque la web no tenga ningún widget ni enlace
   de reservas. El caso queda marcado como incompleto hasta que el emparejamiento
   deje de ser un `str_contains` tan corto.

2. **Sin JavaScript no hay contenido de SPA**. El fixture `spa-react-vacia.html`
   solo trae `<div id="root"></div>` y el bundle externo. Sin ejecutar JS,
   `tiene_reservas`, `tiene_carrito`, `tiene_formulario` y `anio_copyright`
   quedan en `null` (no en `false`): no es prueba de ausencia, es que no se
   observó nada. El viewport del `<head>` estático sí se detecta.

3. **La ausencia de enlaces de una señal en la home no autoriza a afirmar la
   ausencia**, porque el rastreo estático no ve los enlaces generados por
   JavaScript. Esa inferencia queda pendiente de la capa de auditoría visual. Por
   eso `paginas.enlaces_senal` solo se usa para callar una comprobación, nunca
   para dispararla: el `false` de una señal sectorial exige haber leído una
   página del sector o una ruta descubierta.

4. **WooCommerce se clasifica como WordPress**. No hay huella propia de
   WooCommerce en `generador()`; una tienda con `/wp-content/` o
   `<meta name="generator" content="WordPress …">` se guarda como `wordpress`.
   El carrito sí se detecta por clases/textos (`cart`, «añadir al carrito»).
