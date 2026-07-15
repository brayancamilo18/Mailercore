<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fuentes de leads activas
    |--------------------------------------------------------------------------
    |
    | Cada clave debe resolverse en App\Services\Sources\LeadSourceManager.
    | Añade nuevas entradas (enabled => true) sin tocar el comando de búsqueda.
    |
    */

    'sources' => [
        'overpass' => [
            'enabled' => true,
        ],

        /*
         * Directorio de asociación (plantilla clonable).
         * Déjala DESACTIVADA hasta configurar una URL cuyos términos de uso
         * y robots.txt permitan el scraping. No uses sitios que lo prohíban.
         */
        'association_directory' => [
            'enabled' => false,
            // Ejemplo ficticio — sustituye por la URL real permitida:
            'base_url' => 'https://directorio-asociacion.example',
            'listing_paths' => [
                '/miembros',
            ],
            'pagination' => [
                'enabled' => true,
                'query_param' => 'page',
                'start' => 1,
                'max_pages' => 50,
            ],
            'selectors' => [
                'card' => '.member-card',
                'name' => '.member-name',
                'website' => 'a.member-website',
                'phone' => '.member-phone',
                'address' => '.member-address',
            ],
            'timeout' => 15,
            'user_agent' => 'SilgoDevBot/1.0',
            // Pausa entre páginas (ms). Por defecto ~2–4 s.
            'pause_min_ms' => 2000,
            'pause_max_ms' => 4000,
            'respect_robots' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Overpass (OpenStreetMap)
    |--------------------------------------------------------------------------
    */

    'overpass' => [
        'endpoint' => 'https://overpass-api.de/api/interpreter',
        // Espejos: si el principal está saturado (504/429/406), se prueba el siguiente.
        'endpoints' => [
            'https://overpass-api.de/api/interpreter',
            'https://lz4.overpass-api.de/api/interpreter',
            'https://overpass.kumi.systems/api/interpreter',
        ],
        'timeout' => 90,
        // Pausa entre peticiones (ms) para no saturar Overpass.
        'request_pause_ms' => 750,
        // Fallback si "areas" está vacío (admin_level 8 = municipio).
        'area' => env('OUTREACH_OVERPASS_AREA', 'Madrid'),
        // Áreas administrativas a consultar (name + admin_level OSM).
        'areas' => [
            ['name' => 'Comunidad de Madrid', 'admin_level' => 4],
            ['name' => 'Madrid', 'admin_level' => 8],
        ],
        // Filtros alineados con agencias creativas / tech / marketing → segmento "agencia".
        'filters' => [
            ['office', 'advertising_agency'],
            ['office', 'marketing'],
            ['office', 'advertising'],
            ['office', 'graphic_design'],
            ['office', 'web_design'],
            ['office', 'design'],
            ['office', 'it'],
            ['shop', 'advertising_agency'],
            ['craft', 'photographer'],
        ],
        /*
         * Negocios locales con potencial de web/tienda online → segmento "negocio".
         * Desactivados por defecto: actívalos con `php artisan agencies:search --negocios`.
         * Solo se capturan si OSM trae website (clientes finales con presencia online).
         */
        'filters_negocios' => [
            ['shop', 'jewelry'],
            ['shop', 'furniture'],
            ['shop', 'clothes'],
            ['craft', 'carpenter'],
            ['office', 'lawyer'],
            ['office', 'estate_agent'],
            ['shop', 'interior_decoration'],
        ],
        // Filtros amplios pero ruidosos (no se consultan por defecto).
        'noisy_filters' => [
            ['office', 'consulting'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scraper de sitios web
    |--------------------------------------------------------------------------
    */

    'scraper' => [
        'timeout' => 12,
        // UA identificable (contacto + URL). Obligatorio para ser un buen ciudadano.
        'user_agent' => 'SilgoDevBot/1.0 (contacto@onez.es; +https://silgodev.es)',
        'respect_robots' => true,
        'contact_paths' => [
            '',
            '/contacto',
            '/contact',
            '/aviso-legal',
            '/legal',
            '/es/contacto',
        ],
        'team_paths' => [
            '/equipo',
            '/nosotros',
            '/team',
            '/about',
            '/quienes-somos',
            '/es/equipo',
        ],
        'blacklist_domains' => [
            'sentry.io',
            'wixpress.com',
            'example.com',
            'domain.com',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Verificación de emails (nativa, sin APIs de pago)
    |--------------------------------------------------------------------------
    */

    'verifier' => [
        'smtp_probe' => (bool) env('OUTREACH_SMTP_PROBE', false),
        'smtp_timeout' => (int) env('OUTREACH_SMTP_TIMEOUT', 5),
        'disposable_domains' => [
            'mailinator.com',
            'guerrillamail.com',
            '10minutemail.com',
            'tempmail.com',
            'yopmail.com',
            'trashmail.com',
            'throwaway.email',
            'getnada.com',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Envío de correos
    |--------------------------------------------------------------------------
    */

    'sending' => [
        // PAUSA POR DEFECTO: el envío automático (schedule) NO se dispara hasta
        // poner OUTREACH_SENDING_ENABLED=true. Independiente de la cosecha.
        // El comando manual `agencies:send` sigue funcionando para pruebas.
        'enabled' => (bool) env('OUTREACH_SENDING_ENABLED', false),
        'reply_to' => env('OUTREACH_REPLY_TO', env('MAIL_FROM_ADDRESS')),
        'max_daily' => (int) env('OUTREACH_MAX_DAILY', 25),
        'warmup' => [
            0 => 5,
            10 => 8,
            30 => 12,
            60 => 18,
            120 => 25,
        ],
        'delay_min' => 25,
        'delay_max' => 75,
        'send_days' => [1, 2, 3, 4],
        'sender_legal_name' => env('OUTREACH_LEGAL_NAME'),
        'sender_address' => env('OUTREACH_LEGAL_ADDRESS'),
        'unsubscribe_email' => env('OUTREACH_UNSUBSCRIBE_EMAIL'),
        'unsubscribe_url' => env('OUTREACH_UNSUBSCRIBE_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Orquestador de cosecha por áreas (HarvestArea)
    |--------------------------------------------------------------------------
    |
    | harvest:run toma una área pendiente, Overpass + batch de scrape, y marca
    | hecho al terminar el lote. Pausar con harvest:pause (Cache, no .env).
    |
    */

    'harvest' => [
        // Valor por defecto; harvest:pause / harvest:resume lo sobreescriben en Cache.
        'enabled' => (bool) env('OUTREACH_HARVEST_ENABLED', true),
        // Minutos entre ejecuciones del schedule (cron */N).
        'interval' => (int) env('OUTREACH_HARVEST_INTERVAL', 5),
        // Segundos mínimos tras finalizar un área antes de coger la siguiente.
        'pause_between_areas_seconds' => (int) env('OUTREACH_HARVEST_PAUSE_SECONDS', 30),
        // TTL del Cache::lock de harvest:run (Overpass puede tardar).
        'lock_seconds' => (int) env('OUTREACH_HARVEST_LOCK_SECONDS', 900),
        // Un área en_proceso sin lote de scrape activo y más vieja que esto (segundos)
        // se considera atascada (p. ej. reinicio en fase Overpass) y se reintenta.
        'stale_area_seconds' => (int) env('OUTREACH_HARVEST_STALE_AREA_SECONDS', 1800),
        // Latido fresco (verde en panel) si age < esto (segundos).
        'heartbeat_ok_seconds' => (int) env('OUTREACH_HARVEST_HEARTBEAT_OK', 120),
        // Healthcheck: harvest:status sale ≠0 si age ≥ esto (segundos).
        'heartbeat_stale_seconds' => (int) env('OUTREACH_HARVEST_HEARTBEAT_STALE', 600),

        // Concurrencia de scrape: nº de workers Docker recomendado (2–3).
        'scraping_concurrency' => (int) env('OUTREACH_SCRAPING_CONCURRENCY', 2),
        // Throttle scrape: peticiones HTTP/minuto (global).
        'requests_per_minute' => (int) env('OUTREACH_SCRAPE_RPM', 20),
        // Throttle scrape: peticiones/minuto por host.
        'requests_per_domain_per_minute' => (int) env('OUTREACH_SCRAPE_RPM_HOST', 6),
        // Pausa mínima entre peticiones Overpass (ms). Se combina con overpass.request_pause_ms (max).
        'overpass_delay' => (int) env('OUTREACH_OVERPASS_DELAY_MS', 1000),
        // Reinicio periódico de queue:work (anti-fugas PHP).
        'worker_max_jobs' => (int) env('OUTREACH_WORKER_MAX_JOBS', 50),
        'worker_max_time' => (int) env('OUTREACH_WORKER_MAX_TIME', 1800),
        'worker_memory' => (int) env('OUTREACH_WORKER_MEMORY_MB', 160),
    ],

];
