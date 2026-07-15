<x-mail::message>
# Hola, equipo de {{ $agencyName }}

Soy **Camilo Silva Gómez** y trabajo como **brazo técnico white-label** para agencias de marketing y publicidad: desarrollo webs, tiendas online y productos a medida mientras vosotros mantenéis la relación con el cliente.

Trabajo con **Laravel**, **WooCommerce** y **Shopify**, según lo que encaje mejor en cada proyecto.

**Algunos proyectos recientes:**

- Tienda online de joyería en **WooCommerce** (catálogo, pagos y envíos).
- Web corporativa para empresa de **logística** (presencia clara y captación de leads).
- **SaaS propio** para crear y gestionar webs de clientes desde un panel centralizado.

Si os encaja tener a alguien fiable para ejecutar la parte técnica sin ampliar plantilla, ¿os va una **llamada de 15 minutos** esta semana para ver encaje?

Un saludo,<br>
**Camilo Silva Gómez**<br>
[silgodev.es](https://silgodev.es)

---

<small>
<strong>Identificación del remitente:</strong> {{ $senderLegalName }}, {{ $senderAddress }}.<br>
<strong>Baja:</strong> si no desea recibir más mensajes, responda <strong>BAJA</strong> a este correo o escríbanos a {{ $unsubscribeEmail }}.
@if($unsubscribeUrl)
 También puede darse de baja aquí: [{{ $unsubscribeUrl }}]({{ $unsubscribeUrl }}).
@endif
</small>
</x-mail::message>
