# Política de seguridad y datos (Outreach)

## Qué se guarda de cada empresa

Por cada lead (negocio) el sistema puede conservar:

- Identificadores públicos de OpenStreetMap (`place_id`, tags OSM).
- Nombre comercial, web, teléfono y dirección tal como aparecen en OSM o en la propia web.
- Sector clasificado y metadatos de clasificación.
- Páginas HTML **analizadas** (URL, status, títulos, señales técnicas: viewport, JSON-LD, formularios, etc.). No se archiva el HTML completo de forma indefinida como producto; se guardan métricas y hashes útiles para la auditoría.
- Emails de **rol** encontrados en la web pública del negocio (info@, contacto@…), con resultado de verificación MX/SMTP.
- Auditoría: puntuación, hallazgos y, si aplica, métricas PageSpeed.
- Mensajes de outreach generados/enviados (asunto, cuerpo texto/HTML, estado, Message-ID).
- Eventos de bandeja ligados a esos envíos (rebote, baja, respuesta, etc.).

No se almacenan contraseñas de terceros ni contenidos de paneles privados de las empresas contactadas.

## De dónde salen los datos

1. **OpenStreetMap / Overpass** — directorio abierto de puntos de interés (nombre, web, teléfono, ubicación).
2. **La propia web pública del negocio** — solo URLs alcanzables sin autenticación, respetando ritmos de rastreo y, cuando aplica, robots.
3. **Respuestas SMTP/IMAP propias** — rebotes, bajas y respuestas a nuestros correos.
4. **PageSpeed Insights** (opcional) — métricas de rendimiento si hay API key.

No se compran listas. No se usa Google Places.

## Cuánto tiempo se conserva

- Leads y auditoría: mientras el ciclo de outreach o la relación comercial lo justifique; se pueden podar páginas antiguas que no sean la última captura por ruta (`sistema:podar`, >180 días).
- Eventos de bandeja tipo `ignorado`: se eliminan a los 90 días.
- `failed_jobs`: se podan según `--dias` (por defecto 30).
- Logs de aplicación: se recortan si superan el umbral configurado en `sistema:podar`.
- Suppressions: se conservan de forma persistente para no volver a contactar (incluidas las de RGPD, solo por hash).

## Solicitud de supresión (RGPD)

Comando:

```bash
php artisan datos:supresion interesado@dominio.es --motivo="Solicitud del interesado"
```

Efectos:

1. Borra el `Lead` y, en cascada, páginas, auditoría, emails y mensajes asociados.
2. No deja el email en claro en `suppressions`.
3. Inserta una única fila con `email_hash` (SHA-256 del email normalizado) y `motivo = supresion_rgpd`.
4. Imprime un justificante con fecha/hora y el hash.

Cualquier intento posterior de planificar/enviar a ese email se bloquea porque `Suppression::existe()` compara también por hash.

## Cómo funciona la baja del correo

Cada mensaje incluye:

- Identificación del remitente (nombre legal y dirección de contacto).
- Instrucciones visibles de baja en el cuerpo.
- Cabecera `List-Unsubscribe` (mailto y, si está configurada, URL) y `List-Unsubscribe-Post` cuando hay URL.

Canales de baja:

1. Responder al correo con la palabra **BAJA** (u otras fórmulas detectadas) → `outreach:bandeja` clasifica `baja`, suprime el email y cancela pendientes.
2. Mailto de baja / URL one-click si está desplegada.
3. Solicitud formal RGPD con `datos:supresion` (borrado + hash).

Las quejas de spam elevan la salud del día a `rojo` y suprimen el remitente afectado.
