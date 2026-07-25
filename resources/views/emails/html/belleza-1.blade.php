<p>Hola,</p>
<p>{{ $apertura }}</p>
<p>Con unos ajustes le daríamos un aire más cuidado y con más estilo.</p>
<p>Tengo algunas cosas hechas que te puedo enseñar; si te apetece verlas respóndeme y hablamos.</p>
<p>Mi nombre es Camilo Silva, soy desarrollador web y tengo más de 6 años de experiencia.</p>
<p>Un saludo,</p>
@include('emails.partials.firma_html')
<hr>
<p><small>{{ $remitenteNombre }}, {{ $remitenteDireccion }}.<br>Si no quieres recibir más correos míos, responde BAJA a este mensaje o escribe a {{ $emailBaja }}.</small></p>
