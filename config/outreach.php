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
        'max_paginas_por_sitio' => 6,
        'max_bytes_html' => 2_097_152,
        'max_redirecciones' => 5,
        'peticiones_por_minuto' => 20,
        'peticiones_por_dominio_por_minuto' => 4,
        'rutas' => [
            '', '/contacto', '/contact', '/es/contacto', '/aviso-legal', '/legal',
            '/politica-de-privacidad', '/sobre-nosotros', '/nosotros',
            '/quienes-somos', '/equipo', '/about',
        ],
        'max_emails_por_lead' => 3,
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
        'max_diario' => (int) env('OUTREACH_MAX_DIARIO', 40),
        'max_por_dominio_destino' => 3,
        'ventana_seguimiento_dias' => [5, 9],
        'porcentaje_seguimientos' => 25,
        'max_palabras_cuerpo' => 150,
        'max_palabras_seguimiento' => 45,
        'max_enlaces' => 1,
        'max_caracteres_asunto' => 60,
        'palabras_prohibidas' => [
            'gratis', 'oferta', 'promoción', 'promocion', 'urgente', 'exclusivo',
            'garantizado', '100%', 'descuento', 'última oportunidad', 'ultima oportunidad',
            'no te lo pierdas', 'oportunidad única', 'click aquí', 'haz clic aquí',
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
            ['dia_desde' => 21, 'dia_hasta' => 9999, 'cuota' => 40],
        ],
        'ventana_salud' => 200,
        'minimo_para_evaluar' => 30,
        'cuota_si_pocos_datos' => 10,
        'dias_hueco_rompe_racha' => 3,
        'umbrales' => ['ambar' => 2.0, 'rojo' => 4.0, 'parado' => 6.0],
    ],

    'cosecha' => [
        'activa' => env('OUTREACH_COSECHA_ACTIVA', true),
        'intervalo_minutos' => 5,
        'pausa_entre_areas_segundos' => 30,
        // TTL del lock: generoso, para que una provincia grande (con Overpass
        // lento) no lo pierda a media cosecha. La detección real de procesos
        // muertos la hace el vigilante por latido, no por este TTL.
        'lock_segundos' => 7200,
        // Un área «en_proceso» cuyo latido de cosecha lleve más de esto sin
        // actualizarse se considera huérfana (proceso muerto) y se recupera.
        'area_atascada_segundos' => 600,
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
