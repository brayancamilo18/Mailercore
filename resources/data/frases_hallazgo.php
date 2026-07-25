<?php

/**
 * Frases de apertura/asunto por código de hallazgo.
 * Marcadores: {nombre}, {dominio}, {segundos}, {porcentaje}, {anio},
 * {kb}, {mb}, {ms}, {total}, {longitud}, {status}, {dias}, {generador}, {puntuacion}
 */
return [
    'sin_viewport' => [
        'generico' => [
            'asunto' => 'tu web en el móvil',
            'apertura' => 'Estuve trasteando con {dominio} desde el móvil y me di cuenta de que la página no se adapta bien a la pantalla: toca hacer zoom para leer.',
        ],
        'hosteleria' => [
            'asunto' => 'la carta en el móvil',
            'apertura' => 'Le eché un ojo a tu web desde el móvil y la carta no se adapta del todo a la pantalla; se lee un poco a trompicones.',
        ],
        'retail' => [
            'asunto' => 'tu tienda en el móvil',
            'apertura' => 'Entré en {dominio} desde el móvil y me costó un poco moverme por la tienda: la página no se ajusta a la pantalla.',
        ],
        'salud' => [
            'asunto' => 'tu web en el móvil',
            'apertura' => 'Le eché un ojo a {dominio} desde el móvil y la página no se adapta del todo; hay que acercar un poco para leer con calma.',
        ],
        'servicios_profesionales' => [
            'asunto' => 'tu web en el móvil',
            'apertura' => 'Entré en {dominio} desde el móvil y la página no se adapta bien a la pantalla; se lee un pelín incómoda.',
        ],
        'oficios' => [
            'asunto' => 'tu web en el móvil',
            'apertura' => 'Desde el móvil {dominio} se lee a trompicones: toca hacer zoom para ver el teléfono o el formulario.',
        ],
        'belleza' => [
            'asunto' => 'la cita en el móvil',
            'apertura' => 'Le eché un ojo a tu web desde el móvil y no se adapta del todo a la pantalla; pedir cita se ve un poco engorroso.',
        ],
        'agencias' => [
            'asunto' => 'tu web en el móvil',
            'apertura' => 'Entré en {dominio} desde el móvil y la maquetación no se adapta bien a la pantalla; se nota al navegar.',
        ],
    ],

    'title_malo' => [
        'generico' => [
            'asunto' => 'el título de tu web',
            'apertura' => 'Le di una vuelta a {dominio} y el título de la página mide {longitud} caracteres; Google suele mostrar mejor entre 30 y 60.',
        ],
    ],

    'sin_meta_description' => [
        'generico' => [
            'asunto' => 'una cosilla de tu web',
            'apertura' => 'En {dominio} no veo una descripción clara para Google; en los resultados suele salir un trozo de texto al azar.',
        ],
    ],

    'h1_incorrecto' => [
        'generico' => [
            'asunto' => 'el encabezado de tu web',
            'apertura' => 'En la home de {dominio} veo {total} encabezados principales; lo habitual es tener uno solo y clarito.',
        ],
    ],

    'imagenes_sin_alt' => [
        'generico' => [
            'asunto' => 'un detalle de tu web',
            'apertura' => 'En {dominio} un {porcentaje}% de las imágenes van sin texto alternativo; es de esas cosillas que Google y la accesibilidad agradecen.',
        ],
    ],

    'sin_jsonld' => [
        'generico' => [
            'asunto' => 'una cosilla del seo',
            'apertura' => 'Le eché un ojo a {dominio} y hay un par de cosas del SEO que Google agradecería; no veo bien marcados los datos del negocio.',
        ],
    ],

    'sin_https' => [
        'generico' => [
            'asunto' => 'el candado de tu web',
            'apertura' => 'Entré en {dominio} y el navegador la marca como no segura (le falta el candadito de HTTPS).',
        ],
        'hosteleria' => [
            'asunto' => 'el candado de tu web',
            'apertura' => 'Al abrir tu web el navegador no pone el candadito de HTTPS; se ve como no segura.',
        ],
        'retail' => [
            'asunto' => 'el candado de tu tienda',
            'apertura' => 'Entré en {dominio} y el navegador avisa de que la conexión no es segura; le falta el HTTPS.',
        ],
        'salud' => [
            'asunto' => 'el candado de tu web',
            'apertura' => 'Al entrar en tu web el navegador la marca como no segura; no veo el candado de HTTPS.',
        ],
        'servicios_profesionales' => [
            'asunto' => 'el candado de tu web',
            'apertura' => 'En {dominio} no aparece el candadito de seguridad; el navegador la trata como no segura.',
        ],
        'oficios' => [
            'asunto' => 'el candado de tu web',
            'apertura' => 'Al abrir {dominio} el navegador no marca la conexión como segura; le falta el HTTPS.',
        ],
        'belleza' => [
            'asunto' => 'el candado de tu web',
            'apertura' => 'Entré en tu web y el navegador avisa de que no es segura; no veo el candado de HTTPS.',
        ],
        'agencias' => [
            'asunto' => 'el candado de tu web',
            'apertura' => 'En {dominio} no veo HTTPS; el navegador la deja marcada como no segura.',
        ],
    ],

    'cert_caduca' => [
        'generico' => [
            'asunto' => 'el certificado de tu web',
            'apertura' => 'Le eché un ojo a {dominio} y el certificado de seguridad caduca en {dias} días; cuando expire el navegador empezará a avisar.',
        ],
    ],

    'web_abandonada' => [
        'generico' => [
            'asunto' => 'una cosilla de tu web',
            'apertura' => 'Le di una vuelta a {dominio} y vi que el pie sigue marcando {anio}; tiene pinta de que hace un tiempo que no le das un repaso.',
        ],
        'hosteleria' => [
            'asunto' => 'una cosilla de tu web',
            'apertura' => 'En tu web el pie sigue en {anio}; da la sensación de que la carta online lleva un tiempo sin tocarse.',
        ],
        'retail' => [
            'asunto' => 'una cosilla de tu tienda',
            'apertura' => 'El pie de {dominio} sigue en {anio}; parece que el catálogo lleva un rato sin un lavado de cara.',
        ],
        'salud' => [
            'asunto' => 'una cosilla de tu web',
            'apertura' => 'El copyright de tu web es de {anio}; tiene pinta de que hace tiempo que no le das una vuelta.',
        ],
        'servicios_profesionales' => [
            'asunto' => 'una cosilla de tu web',
            'apertura' => 'En {dominio} el pie sigue en {anio}; parece una web a la que hace un tiempo que no se le echa un ojo.',
        ],
        'oficios' => [
            'asunto' => 'una cosilla de tu web',
            'apertura' => 'El pie de {dominio} marca {anio}; da la impresión de que lleva un tiempo sin actualizarse.',
        ],
        'belleza' => [
            'asunto' => 'una cosilla de tu web',
            'apertura' => 'Tu web lleva el copyright de {anio}; se nota un pelín dejada de lado.',
        ],
        'agencias' => [
            'asunto' => 'una cosilla de tu web',
            'apertura' => 'En {dominio} el pie marca {anio}; da la sensación de que hace un tiempo que no le das un repaso.',
        ],
    ],

    'generador_obsoleto' => [
        'generico' => [
            'asunto' => 'un detalle de tu web',
            'apertura' => 'Cacharreando con {dominio} vi que está hecha con {generador}; a veces eso deja la web un poco pillada de diseño y de velocidad.',
        ],
    ],

    'sin_aviso_legal' => [
        'generico' => [
            'asunto' => 'una cosilla legal',
            'apertura' => 'Le eché un ojo a {dominio} y no encuentro aviso legal ni política de privacidad a la vista.',
        ],
    ],

    'sin_cookies' => [
        'generico' => [
            'asunto' => 'el aviso de cookies',
            'apertura' => 'En {dominio} no veo un aviso de cookies; es de esas cosas que suele echarse en falta al entrar.',
        ],
    ],

    'sin_redes' => [
        'generico' => [
            'asunto' => 'un detalle de tu web',
            'apertura' => 'Estuve mirando {dominio} y no encontré enlaces a tus redes desde la web.',
        ],
    ],

    'sin_formulario' => [
        'generico' => [
            'asunto' => 'cómo contactarte',
            'apertura' => 'En {dominio} no encuentro un formulario de contacto; no queda del todo claro cómo escribirte desde la web.',
        ],
    ],

    'contacto_roto' => [
        'generico' => [
            'asunto' => 'la página de contacto',
            'apertura' => 'La ruta de contacto de {dominio} responde con error {status}; quien quiere escribirte se queda a medias.',
        ],
    ],

    'sin_reservas' => [
        'generico' => [
            'asunto' => 'reservar desde la web',
            'apertura' => 'Estuve curioseando {dominio} y no encontré forma de reservar desde ahí.',
        ],
        'hosteleria' => [
            'asunto' => 'reservar desde la web',
            'apertura' => 'Estuve curioseando tu web y no encontré forma de reservar mesa desde ahí; se ve que todo va por teléfono.',
        ],
        'salud' => [
            'asunto' => 'pedir cita desde la web',
            'apertura' => 'Le eché un ojo a {dominio} y no veo forma clara de pedir cita desde la web; parece que va más por teléfono.',
        ],
        'belleza' => [
            'asunto' => 'reservar cita online',
            'apertura' => 'En tu web no encontré una forma clara de reservar cita; se echa un poco en falta al curiosear.',
        ],
    ],

    'sin_carrito' => [
        'generico' => [
            'asunto' => 'el catálogo de {dominio}',
            'apertura' => 'Le eché un ojo a {dominio} y vi el catálogo, pero no encontré forma clara de comprar desde la web.',
        ],
        'retail' => [
            'asunto' => 'el catálogo de {dominio}',
            'apertura' => 'Le eché un ojo a {dominio} y vi que tienes el catálogo puesto pero no se puede comprar desde la web.',
        ],
    ],

    'sin_whatsapp' => [
        'generico' => [
            'asunto' => 'whatsapp en tu web',
            'apertura' => 'En {dominio} no veo un enlace a WhatsApp; a veces se echa de menos para escribir rápido.',
        ],
    ],

    'html_pesado' => [
        'generico' => [
            'asunto' => 'el peso de tu web',
            'apertura' => 'Estuve mirando {dominio} y la home pesa unos {kb} KB de HTML; en el móvil con mala cobertura se nota un pelín.',
        ],
    ],

    'respuesta_lenta' => [
        'generico' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'Estuve mirando {dominio} y tarda unos {segundos} segundos en responder; en el móvil se nota al entrar.',
        ],
        'hosteleria' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'Tu web tarda unos {segundos} segundos en responder; desde el móvil se nota un pelín lenta al abrirla.',
        ],
        'retail' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'En {dominio} la tienda tarda unos {segundos} segundos en responder; se nota al entrar desde el móvil.',
        ],
        'salud' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'Tu web tarda unos {segundos} segundos en responder; desde el móvil se nota al primer vistazo.',
        ],
        'servicios_profesionales' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'En {dominio} la respuesta tarda unos {segundos} segundos; da un pelín de sensación de web perezosa.',
        ],
        'oficios' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'Tu web tarda unos {segundos} segundos en dar señales de vida; en el móvil se nota al entrar.',
        ],
        'belleza' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'La web tarda unos {segundos} segundos en responder; desde el móvil se hace un poco de espera.',
        ],
        'agencias' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'En {dominio} la respuesta tarda unos {segundos} segundos; se nota un pelín lenta al abrirla.',
        ],
    ],

    'psi_rendimiento' => [
        'generico' => [
            'asunto' => 'un detalle de tu web',
            'apertura' => 'Le di una vuelta a {dominio} en el móvil y el rendimiento sale a {puntuacion} sobre 100; hay margen para que vaya más fina.',
        ],
        'hosteleria' => [
            'asunto' => 'un detalle de tu web',
            'apertura' => 'En el móvil el rendimiento de tu web sale a {puntuacion}/100; se nota que podría ir un poco más ágil.',
        ],
        'retail' => [
            'asunto' => 'un detalle de tu tienda',
            'apertura' => 'Entré en {dominio} desde el móvil y el rendimiento marca {puntuacion}/100; la tienda se siente un pelín pesada.',
        ],
        'salud' => [
            'asunto' => 'un detalle de tu web',
            'apertura' => 'El rendimiento móvil de tu web está en {puntuacion}/100; hay un par de cosas que la dejan un poco lenta.',
        ],
        'servicios_profesionales' => [
            'asunto' => 'un detalle de tu web',
            'apertura' => 'Le eché un ojo a {dominio} en el móvil y el rendimiento queda en {puntuacion}/100.',
        ],
        'oficios' => [
            'asunto' => 'un detalle de tu web',
            'apertura' => 'En el móvil el rendimiento de {dominio} sale a {puntuacion}/100; se nota al navegar un rato.',
        ],
        'belleza' => [
            'asunto' => 'un detalle de tu web',
            'apertura' => 'Desde el móvil tu web marca {puntuacion}/100 de rendimiento; va un pelín justa.',
        ],
        'agencias' => [
            'asunto' => 'un detalle de tu web',
            'apertura' => 'El rendimiento móvil de {dominio} está en {puntuacion}/100; hay margen para dejarla más fina.',
        ],
    ],

    'psi_lcp' => [
        'generico' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'Estuve mirando {dominio} y tarda {segundos} segundos en cargar en el móvil; se nota un pelín lenta al entrar.',
        ],
        'hosteleria' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'Tu web tarda {segundos} segundos en cargar en el móvil; al entrar se nota un pelín de espera.',
        ],
        'retail' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'En {dominio} el contenido principal tarda {segundos} segundos en verse en el móvil; se hace un poco de espera.',
        ],
        'salud' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'Tu web tarda {segundos} segundos en mostrar el contenido en el móvil; se nota al primer vistazo.',
        ],
        'servicios_profesionales' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'En el móvil {dominio} tarda {segundos} segundos en pintar el contenido principal; va un pelín justa.',
        ],
        'oficios' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'Tu web tarda {segundos} segundos en cargar en el móvil; al abrirla se nota la espera.',
        ],
        'belleza' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'La web tarda {segundos} segundos en cargar en el móvil; se siente un poco lenta al entrar.',
        ],
        'agencias' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'En el móvil {dominio} tarda {segundos} segundos en cargar el contenido principal; se nota al entrar.',
        ],
    ],

    'psi_peso' => [
        'generico' => [
            'asunto' => 'el peso de tu web',
            'apertura' => 'Estuve cacharreando con {dominio} y la página se va a unos {mb} MB; en el móvil con datos se nota un pelín.',
        ],
    ],

    'psi_seo' => [
        'generico' => [
            'asunto' => 'una cosilla del seo',
            'apertura' => 'Le eché un ojo a {dominio} y hay un par de cosas del SEO que Google agradecería; ahora mismo queda en {puntuacion} sobre 100.',
        ],
    ],

    'psi_accesibilidad' => [
        'generico' => [
            'asunto' => 'un detalle de tu web',
            'apertura' => 'En {dominio} hay un par de detalles de accesibilidad que se notan al usarla; sale a {puntuacion}/100.',
        ],
    ],
];
