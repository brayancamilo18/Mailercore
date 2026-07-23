<?php

/**
 * Frases de apertura/asunto por código de hallazgo.
 * Marcadores: {nombre}, {dominio}, {segundos}, {porcentaje}, {anio},
 * {kb}, {mb}, {ms}, {total}, {longitud}, {status}, {dias}, {generador}, {puntuacion}
 */
return [
    'sin_viewport' => [
        'generico' => [
            'asunto' => 'vuestra web en el móvil',
            'apertura' => 'Entré en {dominio} desde el móvil y la página no se adapta a la pantalla: hay que hacer zoom para leer.',
        ],
        'hosteleria' => [
            'asunto' => 'la carta en el móvil',
            'apertura' => 'Vuestra web no se adapta a la pantalla del móvil, y ahí es donde la mayoría de la gente decide dónde va a comer.',
        ],
        'retail' => [
            'asunto' => 'vuestra tienda en el móvil',
            'apertura' => 'Entré en {dominio} desde el móvil y la página no se adapta: hay que hacer zoom para leer los precios.',
        ],
        'salud' => [
            'asunto' => 'la web en el móvil',
            'apertura' => 'Vuestra web no está adaptada al móvil, que es desde donde la gente busca clínica y compara.',
        ],
        'servicios_profesionales' => [
            'asunto' => 'la web en el móvil',
            'apertura' => 'Entré en {dominio} desde el móvil y la página no se adapta bien a la pantalla.',
        ],
        'oficios' => [
            'asunto' => 'vuestra web en el móvil',
            'apertura' => 'Desde el móvil {dominio} no se lee bien: hay que hacer zoom para ver el teléfono o el formulario.',
        ],
        'belleza' => [
            'asunto' => 'la cita en el móvil',
            'apertura' => 'Vuestra web no se adapta al móvil, y es desde ahí donde mucha gente busca hueco para una cita.',
        ],
        'agencias' => [
            'asunto' => 'vuestra web en el móvil',
            'apertura' => 'Entré en {dominio} desde el móvil y la maquetación no se adapta a la pantalla.',
        ],
    ],

    'title_malo' => [
        'generico' => [
            'asunto' => 'el título de vuestra web',
            'apertura' => 'En {dominio} el título de la página mide {longitud} caracteres; Google suele mostrar mejor entre 30 y 60.',
        ],
    ],

    'sin_meta_description' => [
        'generico' => [
            'asunto' => 'la descripción en google',
            'apertura' => 'En {dominio} no encuentro una meta description clara; en Google suele salir un trozo de texto al azar.',
        ],
    ],

    'h1_incorrecto' => [
        'generico' => [
            'asunto' => 'el encabezado principal',
            'apertura' => 'En la home de {dominio} veo {total} encabezados H1; lo habitual es tener uno solo y claro.',
        ],
    ],

    'imagenes_sin_alt' => [
        'generico' => [
            'asunto' => 'imágenes sin texto alt',
            'apertura' => 'En {dominio} un {porcentaje}% de las imágenes van sin texto alternativo; eso afecta a accesibilidad y a cómo las lee Google.',
        ],
    ],

    'sin_jsonld' => [
        'generico' => [
            'asunto' => 'datos para google',
            'apertura' => 'En {dominio} no encuentro datos estructurados (JSON-LD); Google no tiene pistas claras de qué tipo de negocio sois.',
        ],
    ],

    'sin_https' => [
        'generico' => [
            'asunto' => 'el candado de vuestra web',
            'apertura' => 'Al entrar en {dominio} el navegador no muestra una conexión segura; mucha gente se va en ese momento.',
        ],
        'hosteleria' => [
            'asunto' => 'conexión sin candado',
            'apertura' => 'Vuestra web no carga con HTTPS. Quien reserva o pide mesa desde el móvil suele desconfiar si el navegador avisa.',
        ],
        'retail' => [
            'asunto' => 'tienda sin https',
            'apertura' => 'En {dominio} la conexión no es segura. En una tienda online eso frena justo antes de comprar.',
        ],
        'salud' => [
            'asunto' => 'clínica sin https',
            'apertura' => 'Vuestra web no usa HTTPS. En salud la gente es especialmente sensible a avisos del navegador.',
        ],
        'servicios_profesionales' => [
            'asunto' => 'web sin https',
            'apertura' => 'En {dominio} no hay candado de seguridad. Para un despacho o consulta transmite poca confianza.',
        ],
        'oficios' => [
            'asunto' => 'web sin candado',
            'apertura' => 'Al abrir {dominio} el navegador no marca la conexión como segura; muchos dejan de pedir presupuesto ahí.',
        ],
        'belleza' => [
            'asunto' => 'web sin https',
            'apertura' => 'Vuestra web no carga con HTTPS. Quien quiere pedir cita suele abandonar si el navegador avisa.',
        ],
        'agencias' => [
            'asunto' => 'web sin https',
            'apertura' => 'En {dominio} no veo HTTPS. Si vendéis servicios digitales, el aviso del navegador pesa bastante.',
        ],
    ],

    'cert_caduca' => [
        'generico' => [
            'asunto' => 'el certificado ssl',
            'apertura' => 'El certificado SSL de {dominio} caduca en {dias} días; cuando expire el navegador empezará a avisar.',
        ],
    ],

    'web_abandonada' => [
        'generico' => [
            'asunto' => 'el año del copyright',
            'apertura' => 'En el pie de {dominio} el copyright sigue en {anio}; da la impresión de que la web lleva tiempo sin tocarse.',
        ],
        'hosteleria' => [
            'asunto' => 'copyright de {anio}',
            'apertura' => 'En vuestra web el copyright marca {anio}. Quien mira la carta online puede pensar que el local ya no actualiza nada.',
        ],
        'retail' => [
            'asunto' => 'tienda de {anio}',
            'apertura' => 'El pie de {dominio} sigue en {anio}. En comercio eso suele leerse como catálogo viejo.',
        ],
        'salud' => [
            'asunto' => 'web de {anio}',
            'apertura' => 'El copyright de vuestra web es de {anio}. En clínica eso transmite poco cuidado con la información pública.',
        ],
        'servicios_profesionales' => [
            'asunto' => 'web de {anio}',
            'apertura' => 'En {dominio} el copyright sigue en {anio}; parece una web dejada de lado.',
        ],
        'oficios' => [
            'asunto' => 'web de {anio}',
            'apertura' => 'El pie de {dominio} marca {anio}. Quien busca un oficio suele preferir un sitio al día.',
        ],
        'belleza' => [
            'asunto' => 'web de {anio}',
            'apertura' => 'Vuestra web lleva el copyright de {anio}; da sensación de sitio sin actualizar.',
        ],
        'agencias' => [
            'asunto' => 'web de {anio}',
            'apertura' => 'En {dominio} el copyright es de {anio}. Para una agencia, una web antigua pesa en la primera impresión.',
        ],
    ],

    'generador_obsoleto' => [
        'generico' => [
            'asunto' => 'web hecha con {generador}',
            'apertura' => 'En {dominio} el generador detectado es {generador}; en muchos casos limita el diseño y la velocidad.',
        ],
    ],

    'sin_aviso_legal' => [
        'generico' => [
            'asunto' => 'aviso legal y privacidad',
            'apertura' => 'En {dominio} no encuentro aviso legal ni política de privacidad visibles.',
        ],
    ],

    'sin_cookies' => [
        'generico' => [
            'asunto' => 'el aviso de cookies',
            'apertura' => 'En {dominio} no veo un aviso de cookies; es algo que mucha gente (y la normativa) espera encontrar.',
        ],
    ],

    'sin_redes' => [
        'generico' => [
            'asunto' => 'enlaces a redes sociales',
            'apertura' => 'En {dominio} no encuentro enlaces a redes sociales desde la web.',
        ],
    ],

    'sin_formulario' => [
        'generico' => [
            'asunto' => 'cómo contactaros',
            'apertura' => 'En {dominio} no encuentro un formulario de contacto; no queda claro cómo escribiros desde la web.',
        ],
    ],

    'contacto_roto' => [
        'generico' => [
            'asunto' => 'la página de contacto',
            'apertura' => 'La ruta de contacto de {dominio} responde con error {status}; quien quiere escribiros se queda a medias.',
        ],
    ],

    'sin_reservas' => [
        'generico' => [
            'asunto' => 'reservar desde la web',
            'apertura' => 'En {dominio} no encuentro forma de reservar online.',
        ],
        'hosteleria' => [
            'asunto' => 'reservar mesa online',
            'apertura' => 'En vuestra web no encuentro forma de reservar mesa. Mucha gente que decide desde el móvil se va a otro sitio.',
        ],
        'salud' => [
            'asunto' => 'pedir cita online',
            'apertura' => 'En {dominio} no veo forma de pedir cita online; quien busca hueco suele irse a otra clínica.',
        ],
        'belleza' => [
            'asunto' => 'reservar cita online',
            'apertura' => 'No encuentro en la web una forma clara de reservar cita; mucha gente lo busca antes de llamar.',
        ],
    ],

    'sin_carrito' => [
        'generico' => [
            'asunto' => 'comprar desde la web',
            'apertura' => 'En {dominio} no detecto carrito ni checkout; no queda claro cómo comprar online.',
        ],
        'retail' => [
            'asunto' => 'el carrito de compra',
            'apertura' => 'En vuestra tienda online no encuentro carrito ni proceso de compra visible.',
        ],
    ],

    'sin_whatsapp' => [
        'generico' => [
            'asunto' => 'whatsapp en la web',
            'apertura' => 'En {dominio} no veo un enlace a WhatsApp; mucha gente prefiere escribir por ahí antes de llamar.',
        ],
    ],

    'html_pesado' => [
        'generico' => [
            'asunto' => 'peso de la página',
            'apertura' => 'La home de {dominio} pesa unos {kb} KB de HTML; eso suele ir lento en móvil con mala cobertura.',
        ],
    ],

    'respuesta_lenta' => [
        'generico' => [
            'asunto' => '{segundos} s de respuesta',
            'apertura' => 'La home de {dominio} tarda unos {segundos} segundos en responder; en móvil se nota enseguida.',
        ],
        'hosteleria' => [
            'asunto' => 'web lenta en móvil',
            'apertura' => 'Vuestra web tarda unos {segundos} segundos en responder. Quien busca dónde comer no suele esperar.',
        ],
        'retail' => [
            'asunto' => 'tienda lenta',
            'apertura' => 'En {dominio} la respuesta tarda unos {segundos} segundos; en tienda online eso corta muchas visitas.',
        ],
        'salud' => [
            'asunto' => 'web lenta',
            'apertura' => 'Vuestra web tarda unos {segundos} segundos en responder; quien busca cita desde el móvil lo nota.',
        ],
        'servicios_profesionales' => [
            'asunto' => 'web lenta',
            'apertura' => 'En {dominio} la respuesta tarda unos {segundos} segundos; da sensación de sitio poco cuidado.',
        ],
        'oficios' => [
            'asunto' => 'web lenta',
            'apertura' => 'Vuestra web tarda unos {segundos} segundos en cargar la primera respuesta; en móvil se abandona fácil.',
        ],
        'belleza' => [
            'asunto' => 'web lenta',
            'apertura' => 'La web tarda unos {segundos} segundos en responder; quien quiere pedir cita desde el móvil se impacienta.',
        ],
        'agencias' => [
            'asunto' => 'web lenta',
            'apertura' => 'En {dominio} la respuesta tarda unos {segundos} segundos; para una agencia es una primera impresión floja.',
        ],
    ],

    'psi_rendimiento' => [
        'generico' => [
            'asunto' => 'rendimiento {puntuacion}/100',
            'apertura' => 'PageSpeed marca el rendimiento de {dominio} en {puntuacion} sobre 100 en móvil.',
        ],
        'hosteleria' => [
            'asunto' => 'rendimiento {puntuacion}/100',
            'apertura' => 'En móvil PageSpeed deja el rendimiento de vuestra web en {puntuacion}/100. Quien busca mesa desde el móvil lo nota.',
        ],
        'retail' => [
            'asunto' => 'rendimiento {puntuacion}/100',
            'apertura' => 'PageSpeed da {puntuacion}/100 de rendimiento a {dominio} en móvil; en tienda eso suele restar ventas.',
        ],
        'salud' => [
            'asunto' => 'rendimiento {puntuacion}/100',
            'apertura' => 'El rendimiento móvil de vuestra web está en {puntuacion}/100 según PageSpeed.',
        ],
        'servicios_profesionales' => [
            'asunto' => 'rendimiento {puntuacion}/100',
            'apertura' => 'PageSpeed deja {dominio} en {puntuacion}/100 de rendimiento en móvil.',
        ],
        'oficios' => [
            'asunto' => 'rendimiento {puntuacion}/100',
            'apertura' => 'En móvil el rendimiento de {dominio} sale a {puntuacion}/100 en PageSpeed.',
        ],
        'belleza' => [
            'asunto' => 'rendimiento {puntuacion}/100',
            'apertura' => 'PageSpeed marca {puntuacion}/100 de rendimiento en móvil; quien busca cita suele abandonar webs lentas.',
        ],
        'agencias' => [
            'asunto' => 'rendimiento {puntuacion}/100',
            'apertura' => 'El rendimiento móvil de {dominio} está en {puntuacion}/100 según PageSpeed; llama la atención en una agencia.',
        ],
    ],

    'psi_lcp' => [
        'generico' => [
            'asunto' => '{segundos} segundos de carga',
            'apertura' => 'Vuestra web tarda {segundos} segundos en cargar en móvil. Google considera aceptable por debajo de 2,5.',
        ],
        'hosteleria' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'Vuestra web tarda {segundos} segundos en cargar en el móvil. Quien está buscando dónde cenar no espera tanto.',
        ],
        'retail' => [
            'asunto' => '{segundos} s de carga',
            'apertura' => 'En {dominio} el contenido principal tarda {segundos} segundos en verse en móvil; mucha gente se va antes.',
        ],
        'salud' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'Vuestra web tarda {segundos} segundos en mostrar el contenido en móvil; quien busca clínica suele comparar rápido.',
        ],
        'servicios_profesionales' => [
            'asunto' => '{segundos} s de carga',
            'apertura' => 'En móvil {dominio} tarda {segundos} segundos en pintar el contenido principal.',
        ],
        'oficios' => [
            'asunto' => '{segundos} s de carga',
            'apertura' => 'Vuestra web tarda {segundos} segundos en cargar en móvil; quien necesita un oficio suele llamar a otro.',
        ],
        'belleza' => [
            'asunto' => '{segundos} segundos',
            'apertura' => 'La web tarda {segundos} segundos en cargar en el móvil; quien busca cita no suele quedarse esperando.',
        ],
        'agencias' => [
            'asunto' => '{segundos} s de carga',
            'apertura' => 'En móvil {dominio} tarda {segundos} segundos en cargar el contenido principal.',
        ],
    ],

    'psi_peso' => [
        'generico' => [
            'asunto' => '{mb} mb de peso',
            'apertura' => 'PageSpeed mide unos {mb} MB de peso total en {dominio}; en móvil con datos limitados se nota.',
        ],
    ],

    'psi_seo' => [
        'generico' => [
            'asunto' => 'seo a {puntuacion} puntos',
            'apertura' => 'PageSpeed deja el SEO de {dominio} en {puntuacion} sobre 100; hay señales básicas que no están bien marcadas.',
        ],
    ],

    'psi_accesibilidad' => [
        'generico' => [
            'asunto' => 'accesibilidad {puntuacion}/100',
            'apertura' => 'La accesibilidad de {dominio} sale a {puntuacion}/100 en PageSpeed; hay elementos difíciles de usar o de leer.',
        ],
    ],
];
