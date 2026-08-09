<?php

return [

    // Fuerza la redirección a HTTPS en producción. Desactívalo si sirves el
    // panel por IP:puerto sin TLS (VPS sin dominio).
    'forzar_https' => env('OUTREACH_FORZAR_HTTPS', true),

    'overpass' => [
        'endpoints' => [
            'https://overpass-api.de/api/interpreter',
            'https://lz4.overpass-api.de/api/interpreter',
            'https://overpass.kumi.systems/api/interpreter',
        ],
        'timeout' => 90,
        'pausa_peticion_ms' => 1500,
        'user_agent' => 'SilgoDevBot/2.0 (contacto@silgodev.es; +https://silgodev.es)',
        'max_ids_por_lote' => 300,
        'max_filtros_por_consulta' => 15,
        'backoff_base_us' => 1_000_000,
        'backoff_max_us' => 16_000_000,
    ],

    'scraper' => [
        'timeout' => 15,
        'user_agent' => 'SilgoDevBot/2.0 (contacto@silgodev.es; +https://silgodev.es)',
        'respetar_robots' => true,
        'max_paginas' => ['email' => 6, 'auditoria' => 10, 'completo' => 12],
        'max_bytes_html' => 2_097_152,
        'max_redirecciones' => 5,
        'peticiones_por_minuto' => 20,
        'peticiones_por_dominio_por_minuto' => 4,
        'rutas' => [
            'email' => ['', '/contacto', '/contact', '/es/contacto', '/aviso-legal',
                '/legal', '/politica-de-privacidad', '/sobre-nosotros',
                '/nosotros', '/quienes-somos', '/equipo', '/about'],
            'auditoria_comunes' => ['', '/contacto', '/aviso-legal',
                '/politica-de-privacidad'],
            'auditoria_sector' => [
                'hosteleria' => ['/reservas', '/reservar', '/carta', '/menu', '/la-carta'],
                'retail' => ['/tienda', '/shop', '/productos', '/catalogo'],
                'salud' => ['/cita', '/citas', '/pedir-cita', '/cita-previa', '/servicios'],
                'belleza' => ['/reservar', '/cita-previa', '/servicios'],
                'oficios' => ['/servicios', '/presupuesto'],
                'servicios_profesionales' => ['/servicios', '/areas'],
                'agencias' => ['/servicios', '/portfolio', '/proyectos'],
            ],
        ],
        'max_emails_por_lead' => 3,

        // Palabras con las que DescubridorRutas puntúa los enlaces de la home.
        // Las familias son las de CatalogoSectores (config/sectores.php).
        // Sin tildes y en minúsculas: así es como se comparan.
        'palabras_clave_sector' => [
            'hosteleria' => ['reserva', 'reservar', 'mesa', 'carta', 'menu', 'menus',
                'cocina', 'platos', 'habitacion', 'habitaciones', 'alojamiento', 'desayuno'],
            'salud' => ['cita', 'citas', 'pedir cita', 'cita previa', 'consulta', 'consultas',
                'tratamiento', 'tratamientos', 'especialidad', 'especialidades', 'pacientes'],
            'retail' => ['tienda', 'shop', 'producto', 'productos', 'catalogo', 'coleccion',
                'colecciones', 'comprar', 'carrito', 'novedades', 'ofertas'],
            'servicios_profesionales' => ['servicio', 'servicios', 'area', 'areas',
                'especialidad', 'especialidades', 'asesoria', 'consultoria', 'despacho'],
            'oficios' => ['servicio', 'servicios', 'presupuesto', 'presupuestos', 'obra',
                'obras', 'reforma', 'reformas', 'trabajos', 'instalacion', 'instalaciones'],
            'belleza' => ['reserva', 'reservar', 'cita', 'cita previa', 'servicio', 'servicios',
                'tratamiento', 'tratamientos', 'tarifas', 'precios'],
            'agencias' => ['servicio', 'servicios', 'portfolio', 'porfolio', 'proyecto',
                'proyectos', 'casos', 'trabajos', 'clientes'],
        ],

        // Con qué palabras se reconoce, en los enlaces de la home, que el sitio
        // tiene reservas, carrito, formulario o WhatsApp en alguna otra página.
        // Se comparan contra el href y el texto ancla ya normalizados: en
        // minúsculas, sin tildes y con /, -, _ y . convertidos en espacios, así
        // que «wa.me» se escribe aquí «wa me».
        'palabras_clave_senal' => [
            'reservas' => ['reserva', 'reservas', 'reservar', 'reservar mesa', 'reserva online',
                'booking', 'book', 'book now', 'book a table', 'reservation', 'reservations',
                'cita', 'citas', 'pedir cita', 'cita previa', 'agenda', 'agendar',
                'covermanager', 'thefork', 'eltenedor', 'opentable', 'resengo', 'bookline',
                'restoo', 'quandoo', 'doctoralia', 'booksy', 'treatwell', 'calendly'],
            'carrito' => ['carrito', 'cesta', 'cart', 'add to cart', 'basket', 'checkout',
                'finalizar compra', 'comprar', 'compra online', 'tienda', 'tienda online',
                'shop', 'store', 'productos', 'catalogo', 'woocommerce', 'prestashop',
                'shopify', 'wc ajax', 'cart html'],
            'formulario' => ['contacto', 'contactar', 'contactanos', 'contact', 'contact us',
                'escribenos', 'formulario', 'formulario de contacto', 'presupuesto',
                'pedir presupuesto', 'solicitar informacion', 'typeform', 'jotform',
                'docs google com forms'],
            'whatsapp' => ['whatsapp', 'whats app', 'wasap', 'wa me', 'api whatsapp com',
                'chat whatsapp', 'escribenos por whatsapp'],
        ],
    ],

    'clasificador_email' => [
        'dominios_ruido' => [
            'sentry.io', 'wixpress.com', 'sentry-next.wixpress.com', 'example.com',
            'example.org', 'domain.com', 'w3.org', 'schema.org', 'godaddy.com',
            'wordpress.org', 'jquery.com', 'googleapis.com', 'gstatic.com',
            'placeholder.com', 'email.com', 'tuemail.com', 'tudominio.com',
        ],
        'proveedores_gratuitos' => [
            'gmail.com', 'googlemail.com', 'hotmail.com', 'hotmail.es', 'outlook.com',
            'outlook.es', 'yahoo.com', 'yahoo.es', 'icloud.com', 'live.com',
            'me.com', 'protonmail.com', 'proton.me', 'aol.com', 'msn.com',
            'terra.es', 'telefonica.net', 'ono.com',
        ],
        'extensiones_asset' => ['.png', '.jpg', '.jpeg', '.svg', '.gif', '.css', '.js', '.webp', '.ico'],
    ],

    'pagespeed' => [
        'activo' => true,
        'api_key' => env('PAGESPEED_API_KEY'),
        'endpoint' => 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed',
        'estrategia' => 'mobile',
        'timeout' => 90,
        'peticiones_por_minuto' => 30,
        'validez_dias' => 30,
    ],

    'verificador' => [
        'sonda_smtp' => env('OUTREACH_SONDA_SMTP', false),
        'timeout_smtp' => 5,
        'cache_mx_dias' => 7,
        'cache_catchall_dias' => 30,
        'validez_verificacion_dias' => 30,
        'dominios_desechables' => [
            'mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com',
            'yopmail.com', 'trashmail.com', 'throwaway.email', 'getnada.com', 'sharklasers.com',
        ],
    ],

    'auditoria' => [
        'peso_maximo' => 100,
        'umbral_lcp_ms' => 4000,
        'umbral_respuesta_ms' => 2500,
        'umbral_html_kb' => 1500,
        'umbral_psi_rendimiento' => 50,
        'umbral_psi_seo' => 80,
        'umbral_imagenes_sin_alt' => 0.4,
        'anios_web_abandonada' => 2,
    ],

    'envio' => [
        'activo' => env('OUTREACH_ENVIO_ACTIVO', false),
        'dias' => [1, 2, 3, 4],
        'hora_inicio' => '09:15',
        'hora_fin' => '17:45',
        'minutos_min_entre_correos' => 4,
        'max_diario' => (int) env('OUTREACH_MAX_DIARIO', 50),
        'max_por_dominio_destino' => 3,
        'ventana_seguimiento_dias' => [5, 9],
        'porcentaje_seguimientos' => 25,
        'max_palabras_cuerpo' => 110,
        'max_palabras_seguimiento' => 60,
        'max_enlaces' => 0,
        'max_caracteres_asunto' => 60,
        'dkim_selector' => env('OUTREACH_DKIM_SELECTOR'),
        'palabras_prohibidas' => [
            'gratis', 'oferta', 'promoción', 'promocion', 'urgente', 'exclusivo',
            'garantizado', '100%', 'descuento', 'última oportunidad', 'ultima oportunidad',
            'no te lo pierdas', 'oportunidad única', 'click aquí', 'haz clic aquí',
            'presupuesto', 'reunión', 'reunion', 'llamada', 'agendar', 'agenda',
            'sin compromiso', 'servicios', 'nuestros servicios', 'solución', 'solucion',
            'soluciones', 'optimizar', 'maximizar', 'potenciar', 'impulsar',
            'brazo técnico', 'brazo tecnico', 'white-label', 'white label', 'woocommerce',
            'desarrollo web', 'posicionamiento', 'estrategia', 'propuesta comercial',
            'no dudes en', 'quedo a tu disposición', 'quedo a tu disposicion',
            'atentamente', 'cordialmente', 'estimado', 'estimada',
        ],
        'remitente' => [
            'nombre_legal' => env('OUTREACH_NOMBRE_LEGAL'),
            'direccion' => env('OUTREACH_DIRECCION_LEGAL'),
            'email_baja' => env('OUTREACH_EMAIL_BAJA'),
            'url_baja' => env('OUTREACH_URL_BAJA'),
            'responder_a' => env('OUTREACH_RESPONDER_A', env('MAIL_FROM_ADDRESS')),
        ],
    ],

    'rampa' => [
        'escalones' => [
            ['dia_desde' => 1, 'dia_hasta' => 3, 'cuota' => 10],
            ['dia_desde' => 4, 'dia_hasta' => 7, 'cuota' => 15],
            ['dia_desde' => 8, 'dia_hasta' => 12, 'cuota' => 22],
            ['dia_desde' => 13, 'dia_hasta' => 20, 'cuota' => 30],
            ['dia_desde' => 21, 'dia_hasta' => 28, 'cuota' => 40],
            ['dia_desde' => 29, 'dia_hasta' => 9999, 'cuota' => 50],
        ],
        'ventana_salud' => 200,
        'minimo_para_evaluar' => 30,
        // En calentamiento no recortamos la cuota del escalón (antes forzaba 10).
        'cuota_si_pocos_datos' => null,
        // Con poca muestra la tasa de rebote se infla; no parar envíos hasta tener datos.
        'minimo_para_parado' => 150,
        'dias_hueco_rompe_racha' => 3,
        'umbrales' => ['ambar' => 2.0, 'rojo' => 4.0, 'parado' => 6.0],
    ],

    'cosecha' => [
        'activa' => env('OUTREACH_COSECHA_ACTIVA', true),
        // Cada minuto: si ya hay una pasada en marcha, withoutOverlapping la omite.
        'intervalo_minutos' => 1,
        'pausa_entre_areas_segundos' => 15,
        // Máximo de barridos completos por país antes de marcarlo como hecho.
        'max_ciclos_pais' => (int) env('OUTREACH_COSECHA_MAX_CICLOS', 3),
        // Tras un ciclo completo sin áreas pendientes, espera antes de reintentar.
        'pausa_entre_ciclos_segundos' => 300,
        // TTL del lock de negocio. Más corto = recuperación más rápida si el
        // contenedor muere a medias. El servicio Docker «cosecha» relanza solo.
        'lock_segundos' => (int) env('OUTREACH_COSECHA_LOCK', 900),
        // Un área «en_proceso» cuyo latido lleve más de esto sin actualizarse
        // se considera huérfana; el vigilante libera lock y la reencola.
        'area_atascada_segundos' => (int) env('OUTREACH_COSECHA_ATASCADA', 180),
        // Nº máximo de recuperaciones antes de marcar el área como 'error'.
        'max_reintentos' => 5,
    ],

    'latido' => [
        'procesos' => [
            'cosecha' => 900,
            'scrape' => 900,
            'planificador' => 93600,
            'despachador' => 300,
            'bandeja' => 1800,
            'vigilante' => 300,
        ],
    ],

];
