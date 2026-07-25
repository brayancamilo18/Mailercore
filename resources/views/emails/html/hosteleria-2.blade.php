<p>Hola,</p>
<p>Te escribí hace unos días por lo de {{ $dominio }}. {{ $apertura }}</p>
<p>Si te encaja le damos una vuelta, y si no, no te molesto más.</p>
<p>Un saludo,</p>
@include('emails.partials.firma_html')
<hr>
<p><small>{{ $remitenteNombre }}, {{ $remitenteDireccion }}.<br>Si no quieres recibir más correos míos, responde BAJA a este mensaje o escribe a {{ $emailBaja }}.</small></p>
