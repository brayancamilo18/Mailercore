# Modelo A — Inteligencia de prospección para agencias web

Plan de ejecución completo · 1 de agosto de 2026

---

## 0. Qué es el producto, exactamente

> Una agencia web paga una cuota mensual por recibir, cada semana, un lote de negocios locales de su territorio cuya web tiene problemas técnicos concretos y medibles — con el hallazgo redactado, los datos de contacto públicos y un mini-informe listo para enseñar.

**Tú no envías nada.** Entregas inteligencia. La agencia decide el canal: teléfono, visita, LinkedIn o email, bajo su responsabilidad.

Lo que recibe el cliente cada semana:

| Columna | Origen en tu código |
|---|---|
| Nombre, sector, ciudad, provincia | `Lead` + `ClasificadorSector` |
| Web, teléfono | `Lead` (OSM) |
| Email de rol verificado | `LeadEmail` + `VerificadorEmail` |
| Puntuación 0-100 | `MotorAuditoria` |
| Hallazgo principal (frase legible) | `RedactorHallazgo` |
| Hallazgo secundario | `RedactorHallazgo` |
| Señales técnicas (sin HTTPS, sin móvil, lenta…) | `Auditoria` |
| Fecha de auditoría | `Auditoria` |
| Enlace al mini-informe PDF | **Nuevo** |

---

## 1. Lo que ya tienes vs. lo que falta

**Ya funciona hoy** — puedes generar la primera lista esta misma semana:

```bash
php artisan cosecha:ejecutar          # negocios de OSM por provincia
php artisan leads:backfill-sectores   # clasifica en 7 familias
php artisan leads:rastrear            # descarga webs + emails de rol
php artisan auditar:web               # 25 comprobaciones
php artisan auditar:pagespeed         # métricas PSI
php artisan emails:verificar          # MX/SMTP
```

**Falta** (por orden de importancia):

| Qué | Esfuerzo | Bloquea la venta |
|---|---|---|
| Comando de exportación a CSV/XLSX | ~1 día | **Sí** |
| Mini-informe PDF por prospecto | ~3 días | **Sí** |
| Territorios por agencia + panel filtrado | ~1 semana | No (al principio va por email) |
| Estado de gestión por lead (contactado, reunión, cliente) | ~2 días | No |
| Control de frescura y de "ya entregado" | ~2 días | No, pero mata la confianza si falla |
| Señales de cualificación extra (WHOIS, agencia existente) | ~1 semana | No |

Fíjate en algo importante: **nada del sistema de envío es necesario para el modelo A.** Todo `app/Services/Envio`, `Inbox` y la rampa se quedan en pausa. No los borres — son la ampliación al modelo B — pero no los toques ahora.

---

## 2. Fase 0 — Validar antes de construir (semanas 1-2)

**Regla: no escribas una línea de código de producto hasta que una agencia diga "lo quiero".**

### 2.1 Genera una ciudad a mano

Elige una provincia con densidad y que conozcas. Corre el pipeline completo. Objetivo: 150-300 negocios auditados con hallazgo.

Filtra a mano los 30 mejores: puntuación baja, negocio claramente vivo, web claramente mejorable. **Revisa uno por uno que el hallazgo sea cierto** — abre la web y compruébalo. Un solo hallazgo falso destruye la credibilidad en la primera reunión.

### 2.2 Monta la muestra gratuita

Un CSV o una hoja de cálculo con 10 prospectos, más 2 mini-informes hechos a mano (Google Docs vale, no necesitas generarlo por código todavía). Eso es tu argumento comercial entero.

### 2.3 Contacta 25-30 agencias

Dónde encontrarlas: búsqueda local ("agencia diseño web + ciudad"), directorios de agencias, LinkedIn, comunidades de desarrollo en español, asociaciones de empresas locales.

Guion, en una frase: *"He generado una lista de 30 negocios en [ciudad] cuya web tiene problemas técnicos concretos. Te paso 10 gratis con el análisis. Si te sirven, hablamos."*

Sin presentación, sin deck, sin llamada previa. El entregable **es** la presentación.

### 2.4 Qué mides

- Cuántos abren y responden
- Cuántos dicen "mándame más"
- **Cuántos pagan** — es la única métrica que cuenta

Si de 30 agencias ninguna paga, el problema no es el producto: es que las agencias no compran listas. Mejor saberlo en dos semanas que en seis meses.

---

## 3. Fase 1 — MVP de entrega (semanas 3-6)

Solo si la fase 0 dio señal.

### 3.1 Comando de exportación

```
php artisan leads:exportar --provincia=valencia --sector=hosteleria \
    --min-gravedad=alta --limite=25 --formato=xlsx
```

Debe excluir automáticamente: leads sin auditoría, sin hallazgo, con auditoría de más de 60 días, ya entregados a ese cliente, y **cualquier email que no sea de rol** (`ClasificadorEmail` ya lo distingue).

### 3.2 Mini-informe PDF

Una página por prospecto: puntuación, los 3 problemas concretos con su explicación en lenguaje de negocio (no técnico), y qué implica cada uno. La agencia lo enseña en la visita o lo adjunta.

Es el artefacto que más te diferencia. Nadie más lo entrega, y convierte una lista fría en una conversación con contenido. Tienes toda la data; es una plantilla Blade → PDF.

Importante: el informe habla del **negocio**, no de ti. Sin marca tuya. La agencia lo presenta como suyo — eso es parte de lo que compra.

### 3.3 Entrega

Al principio, por email cada lunes. No construyas el panel todavía. Cuando tengas 4-5 clientes y el envío manual te canse, entonces sí.

---

## 4. Fase 2 — Calidad de datos (meses 2-3)

Lo que sube el valor de la lista de "interesante" a "no puedo vivir sin esto":

- **Antigüedad del dominio (WHOIS).** Una web de 2011 sin tocar es un prospecto mucho mejor que una de 2024. Es probablemente la señal de cualificación más potente que puedes añadir.
- **¿Ya tiene agencia?** Créditos en el pie, "diseñado por", enlaces salientes a estudios. Un negocio con agencia es peor prospecto — o mejor, según la estrategia del cliente. En ambos casos, saberlo vale.
- **¿El negocio está vivo?** Número de reseñas y actividad reciente. Auditar la web de un negocio cerrado es ruido puro.
- **Frescura.** Re-auditar cada 60-90 días. Un hallazgo caduco es peor que ninguno: la agencia llama, el negocio ya lo arregló, y quedas mal tú.

---

## 5. Precio y empaquetado

**Recomendación: suscripción territorial en exclusiva.** Es recurrente, defendible, y la exclusividad justifica el precio por sí sola.

| Plan | Qué incluye | Precio orientativo |
|---|---|---|
| Prueba | 25 leads, una entrega, sin exclusiva | €0 (los primeros 2-3 clientes) |
| Base | 20 leads/semana, 1 provincia + 1 sector, exclusiva | €200-300/mes |
| Pro | 40 leads/semana, provincia completa, exclusiva, mini-informes | €450-600/mes |

Son cifras de mi razonamiento, no de mercado verificado — ajústalas con lo que te digan las primeras conversaciones.

**La exclusividad no es opcional.** Si vendes la misma provincia a dos agencias, los negocios reciben dos llamadas, ambas agencias queman la lista y ambas se van. Vender exclusiva es además el argumento que sube el precio sin añadir trabajo.

Justificación ante el cliente: un proyecto web para un negocio local ronda los €1.500-6.000. Con que cierre **uno** al trimestre, la suscripción ya está pagada varias veces.

---

## 6. Los dos riesgos que matan el negocio

### Riesgo 1 — La agencia no actúa

Es el riesgo principal, por encima de cualquier problema técnico. Le entregas una lista excelente, no hacen nada durante tres semanas, y se dan de baja diciendo "no funcionó".

Mitigaciones, todas de producto:

- **Lotes semanales pequeños (20), no volcados de 200.** Un lote pequeño se siente accionable; uno grande se pospone para siempre.
- **Incluye el texto listo para usar** — guion de llamada y email redactado. Que no tengan que pensar nada.
- **El mini-informe les da algo que enseñar**, que es la excusa perfecta para la visita o la llamada.
- **Onboarding en llamada** con el primer lote. Que hagan la primera llamada contigo delante.
- **Pídeles el estado.** Aunque sea por WhatsApp: "¿cómo fue con los 20 de la semana pasada?". Si no llaman, lo detectas en la semana 2 y no en la baja.

### Riesgo 2 — Un dato mal

Un teléfono incorrecto o un hallazgo falso ("tu web es lenta" cuando va perfecta) te cuesta la credibilidad entera en una sola llamada. La agencia queda en ridículo delante de su prospecto y no te lo perdona.

Mitigación: **revisión manual de los primeros lotes de cada cliente**. Sí, no escala. No importa: con 5 clientes son 100 registros a la semana. Automatizas la QA cuando duela, no antes.

---

## 7. Legal mínimo antes de facturar

Modelo A te deja en una posición cómoda, pero no en cero:

1. **Solo direcciones de rol en la entrega.** Nunca `nombre@negocio.es`. Es tu política de producto y va por escrito.
2. **Política de privacidad publicada** que explique el origen de los datos (OpenStreetMap y webs públicas), la finalidad y cómo ejercer derechos.
3. **Canal de supresión operativo.** Ya tienes `datos:supresion`; solo necesita una dirección pública donde recibir la solicitud.
4. **Contrato o condiciones con la agencia** con una cláusula clara: ellos son responsables del uso que hagan de los datos y del canal que elijan. No es un escudo absoluto, pero define responsabilidades.
5. **Alta como autónomo o sociedad, y facturación con IVA** si no lo tienes ya.

Con esto puedes empezar. La consulta con abogado —200-300 €— la necesitas antes del modelo B, no antes del A.

---

## 8. Checklist en orden

**Esta semana**

- [ ] Elegir provincia y correr el pipeline completo
- [ ] Filtrar los 30 mejores y verificar a mano cada hallazgo
- [ ] Montar la muestra de 10 + 2 mini-informes a mano
- [ ] Lista de 30 agencias con contacto

**Semana 2**

- [ ] Contactar las 30
- [ ] Registrar respuestas y objeciones
- [ ] 2-3 llamadas con los interesados
- [ ] **Decisión: sigue o para**

**Semanas 3-6** (solo si hay señal)

- [ ] `leads:exportar` con filtros y exclusiones
- [ ] Plantilla del mini-informe PDF
- [ ] Marcar leads como entregados por cliente
- [ ] Primeros 2 clientes gratis a cambio de feedback semanal
- [ ] Política de privacidad y condiciones

**Meses 2-3**

- [ ] Cobrar a los primeros clientes
- [ ] WHOIS y detección de agencia existente
- [ ] Re-auditoría automática cada 60 días
- [ ] Panel con territorio y estados
- [ ] Con datos reales: decidir si el modelo B tiene sentido

---

## 9. Qué hacer con el resto del código

No borres nada. `Envio`, `Inbox`, `Verificacion` y la rampa son el modelo B, y el modelo B solo tiene sentido cuando tengas clientes del A pidiéndote que además envíes tú.

Lo único que sí conviene hacer ya, porque es barato y te evita un susto: **dejar `OUTREACH_ENVIO_ACTIVO=false` y aplicar los arreglos críticos del análisis anterior** — `APP_DEBUG=false` en producción y el TTL de `envio:reanudado_manual`. El sistema va a seguir corriendo la cosecha 24/7, que es justo lo que el modelo A necesita.
