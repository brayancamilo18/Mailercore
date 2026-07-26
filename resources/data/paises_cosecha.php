<?php

/**
 * Catálogo de países de cosecha (España + Centroamérica + Sudamérica hispana).
 *
 * - admin_level: nivel OSM de la subdivisión (España=6 provincias; LATAM suele=4).
 * - mapa_src: GeoJSON Highcharts Map Collection (FeatureCollection).
 * - areas: nombre tal cual en OSM (name=…).
 *
 * max_ciclos por defecto: config('outreach.cosecha.max_ciclos_pais', 3)
 */
return [
    'ES' => [
        'nombre' => 'España',
        // Tras LATAM (la campaña arranca por Colombia).
        'prioridad' => 100,
        'mapa_motor' => 'spain',
        'mapa_src' => null,
        'admin_level' => 6,
        'admin_level_especial' => ['Ceuta' => 4, 'Melilla' => 4],
        'areas' => [
            'Madrid', 'Barcelona', 'Valencia', 'Sevilla', 'Málaga', 'Alicante', 'Vizcaya',
            'Zaragoza', 'Murcia', 'Baleares', 'Las Palmas', 'Santa Cruz de Tenerife',
            'A Coruña', 'Asturias', 'Cádiz', 'Pontevedra', 'Granada', 'Guipúzcoa',
            'Tarragona', 'Girona', 'Álava', 'Albacete', 'Almería', 'Ávila', 'Badajoz',
            'Burgos', 'Cáceres', 'Cantabria', 'Castellón', 'Ceuta', 'Ciudad Real',
            'Córdoba', 'Cuenca', 'Guadalajara', 'Huelva', 'Huesca', 'Jaén', 'La Rioja',
            'León', 'Lleida', 'Lugo', 'Melilla', 'Navarra', 'Ourense', 'Palencia',
            'Salamanca', 'Segovia', 'Soria', 'Teruel', 'Toledo', 'Valladolid', 'Zamora',
        ],
    ],

    // —— Centroamérica ——
    'GT' => [
        'nombre' => 'Guatemala',
        'prioridad' => 10,
        'mapa_motor' => 'geojson',
        'mapa_src' => 'https://code.highcharts.com/mapdata/countries/gt/gt-all.geo.json',
        'admin_level' => 4,
        'areas' => [
            'Guatemala', 'Alta Verapaz', 'Baja Verapaz', 'Chimaltenango', 'Chiquimula',
            'El Progreso', 'Escuintla', 'Huehuetenango', 'Izabal', 'Jalapa', 'Jutiapa',
            'Petén', 'Quetzaltenango', 'Quiché', 'Retalhuleu', 'Sacatepéquez',
            'San Marcos', 'Santa Rosa', 'Sololá', 'Suchitepéquez', 'Totonicapán', 'Zacapa',
        ],
    ],
    'SV' => [
        'nombre' => 'El Salvador',
        'prioridad' => 11,
        'mapa_motor' => 'geojson',
        'mapa_src' => 'https://code.highcharts.com/mapdata/countries/sv/sv-all.geo.json',
        'admin_level' => 4,
        'areas' => [
            'Ahuachapán', 'Cabañas', 'Chalatenango', 'Cuscatlán', 'La Libertad',
            'La Paz', 'La Unión', 'Morazán', 'San Miguel', 'San Salvador',
            'San Vicente', 'Santa Ana', 'Sonsonate', 'Usulután',
        ],
    ],
    'HN' => [
        'nombre' => 'Honduras',
        'prioridad' => 12,
        'mapa_motor' => 'geojson',
        'mapa_src' => 'https://code.highcharts.com/mapdata/countries/hn/hn-all.geo.json',
        'admin_level' => 4,
        'areas' => [
            'Atlántida', 'Choluteca', 'Colón', 'Comayagua', 'Copán', 'Cortés',
            'El Paraíso', 'Francisco Morazán', 'Gracias a Dios', 'Intibucá',
            'Islas de la Bahía', 'La Paz', 'Lempira', 'Ocotepeque', 'Olancho',
            'Santa Bárbara', 'Valle', 'Yoro',
        ],
    ],
    'NI' => [
        'nombre' => 'Nicaragua',
        'prioridad' => 13,
        'mapa_motor' => 'geojson',
        'mapa_src' => 'https://code.highcharts.com/mapdata/countries/ni/ni-all.geo.json',
        'admin_level' => 4,
        'areas' => [
            'Boaco', 'Carazo', 'Chinandega', 'Chontales', 'Estelí', 'Granada',
            'Jinotega', 'León', 'Madriz', 'Managua', 'Masaya', 'Matagalpa',
            'Nueva Segovia', 'Río San Juan', 'Rivas',
            'Costa Caribe Norte', 'Costa Caribe Sur',
        ],
    ],
    'CR' => [
        'nombre' => 'Costa Rica',
        'prioridad' => 14,
        'mapa_motor' => 'geojson',
        'mapa_src' => 'https://code.highcharts.com/mapdata/countries/cr/cr-all.geo.json',
        'admin_level' => 4,
        'areas' => [
            'San José', 'Alajuela', 'Cartago', 'Heredia', 'Guanacaste', 'Puntarenas', 'Limón',
        ],
    ],
    'PA' => [
        'nombre' => 'Panamá',
        'prioridad' => 15,
        'mapa_motor' => 'geojson',
        'mapa_src' => 'https://code.highcharts.com/mapdata/countries/pa/pa-all.geo.json',
        'admin_level' => 4,
        'areas' => [
            'Panamá', 'Panamá Oeste', 'Colón', 'Chiriquí', 'Coclé', 'Veraguas',
            'Herrera', 'Los Santos', 'Darién', 'Bocas del Toro',
            'Emberá', 'Guna Yala', 'Ngäbe-Buglé',
        ],
    ],

    // —— Sudamérica ——
    'CO' => [
        'nombre' => 'Colombia',
        'prioridad' => 1,
        'mapa_motor' => 'geojson',
        'mapa_src' => 'https://code.highcharts.com/mapdata/countries/co/co-all.geo.json',
        'admin_level' => 4,
        'areas' => [
            'Amazonas', 'Antioquia', 'Arauca', 'Atlántico', 'Bolívar', 'Boyacá',
            'Caldas', 'Caquetá', 'Casanare', 'Cauca', 'Cesar', 'Chocó', 'Córdoba',
            'Cundinamarca', 'Guainía', 'Guaviare', 'Huila', 'La Guajira', 'Magdalena',
            'Meta', 'Nariño', 'Norte de Santander', 'Putumayo', 'Quindío', 'Risaralda',
            'San Andrés y Providencia', 'Santander', 'Sucre', 'Tolima', 'Valle del Cauca',
            'Vaupés', 'Vichada', 'Bogotá D.C.',
        ],
    ],
    'VE' => [
        'nombre' => 'Venezuela',
        'prioridad' => 21,
        'mapa_motor' => 'geojson',
        'mapa_src' => 'https://code.highcharts.com/mapdata/countries/ve/ve-all.geo.json',
        'admin_level' => 4,
        'areas' => [
            'Amazonas', 'Anzoátegui', 'Apure', 'Aragua', 'Barinas', 'Bolívar',
            'Carabobo', 'Cojedes', 'Delta Amacuro', 'Falcón', 'Guárico', 'Lara',
            'Mérida', 'Miranda', 'Monagas', 'Nueva Esparta', 'Portuguesa', 'Sucre',
            'Táchira', 'Trujillo', 'Vargas', 'Yaracuy', 'Zulia', 'Distrito Capital',
        ],
    ],
    'EC' => [
        'nombre' => 'Ecuador',
        'prioridad' => 22,
        'mapa_motor' => 'geojson',
        'mapa_src' => 'https://code.highcharts.com/mapdata/countries/ec/ec-all.geo.json',
        'admin_level' => 4,
        'areas' => [
            'Azuay', 'Bolívar', 'Cañar', 'Carchi', 'Chimborazo', 'Cotopaxi',
            'El Oro', 'Esmeraldas', 'Galápagos', 'Guayas', 'Imbabura', 'Loja',
            'Los Ríos', 'Manabí', 'Morona Santiago', 'Napo', 'Orellana', 'Pastaza',
            'Pichincha', 'Santa Elena', 'Santo Domingo de los Tsáchilas',
            'Sucumbíos', 'Tungurahua', 'Zamora Chinchipe',
        ],
    ],
    'PE' => [
        'nombre' => 'Perú',
        'prioridad' => 23,
        'mapa_motor' => 'geojson',
        'mapa_src' => 'https://code.highcharts.com/mapdata/countries/pe/pe-all.geo.json',
        'admin_level' => 4,
        'areas' => [
            'Amazonas', 'Áncash', 'Apurímac', 'Arequipa', 'Ayacucho', 'Cajamarca',
            'Callao', 'Cusco', 'Huancavelica', 'Huánuco', 'Ica', 'Junín',
            'La Libertad', 'Lambayeque', 'Lima', 'Loreto', 'Madre de Dios',
            'Moquegua', 'Pasco', 'Piura', 'Puno', 'San Martín', 'Tacna', 'Tumbes', 'Ucayali',
        ],
    ],
    'BO' => [
        'nombre' => 'Bolivia',
        'prioridad' => 24,
        'mapa_motor' => 'geojson',
        'mapa_src' => 'https://code.highcharts.com/mapdata/countries/bo/bo-all.geo.json',
        'admin_level' => 4,
        'areas' => [
            'Chuquisaca', 'Cochabamba', 'El Beni', 'La Paz', 'Oruro',
            'Pando', 'Potosí', 'Santa Cruz', 'Tarija',
        ],
    ],
    'CL' => [
        'nombre' => 'Chile',
        'prioridad' => 25,
        'mapa_motor' => 'geojson',
        'mapa_src' => 'https://code.highcharts.com/mapdata/countries/cl/cl-all.geo.json',
        'admin_level' => 4,
        'areas' => [
            'Arica y Parinacota', 'Tarapacá', 'Antofagasta', 'Atacama', 'Coquimbo',
            'Valparaíso', 'Metropolitana de Santiago', "Libertador General Bernardo O'Higgins",
            'Maule', 'Ñuble', 'Biobío', 'La Araucanía', 'Los Ríos', 'Los Lagos',
            'Aysén', 'Magallanes',
        ],
    ],
    'AR' => [
        'nombre' => 'Argentina',
        'prioridad' => 26,
        'mapa_motor' => 'geojson',
        'mapa_src' => 'https://code.highcharts.com/mapdata/countries/ar/ar-all.geo.json',
        'admin_level' => 4,
        'areas' => [
            'Buenos Aires', 'Ciudad Autónoma de Buenos Aires', 'Catamarca', 'Chaco',
            'Chubut', 'Córdoba', 'Corrientes', 'Entre Ríos', 'Formosa', 'Jujuy',
            'La Pampa', 'La Rioja', 'Mendoza', 'Misiones', 'Neuquén', 'Río Negro',
            'Salta', 'San Juan', 'San Luis', 'Santa Cruz', 'Santa Fe',
            'Santiago del Estero', 'Tierra del Fuego', 'Tucumán',
        ],
    ],
    'PY' => [
        'nombre' => 'Paraguay',
        'prioridad' => 27,
        'mapa_motor' => 'geojson',
        'mapa_src' => 'https://code.highcharts.com/mapdata/countries/py/py-all.geo.json',
        'admin_level' => 4,
        'areas' => [
            'Alto Paraguay', 'Alto Paraná', 'Amambay', 'Asunción', 'Boquerón',
            'Caaguazú', 'Caazapá', 'Canindeyú', 'Central', 'Concepción',
            'Cordillera', 'Guairá', 'Itapúa', 'Misiones', 'Ñeembucú',
            'Paraguarí', 'Presidente Hayes', 'San Pedro',
        ],
    ],
    'UY' => [
        'nombre' => 'Uruguay',
        'prioridad' => 28,
        'mapa_motor' => 'geojson',
        'mapa_src' => 'https://code.highcharts.com/mapdata/countries/uy/uy-all.geo.json',
        'admin_level' => 4,
        'areas' => [
            'Artigas', 'Canelones', 'Cerro Largo', 'Colonia', 'Durazno', 'Flores',
            'Florida', 'Lavalleja', 'Maldonado', 'Montevideo', 'Paysandú',
            'Río Negro', 'Rivera', 'Rocha', 'Salto', 'San José', 'Soriano',
            'Tacuarembó', 'Treinta y Tres',
        ],
    ],
];
