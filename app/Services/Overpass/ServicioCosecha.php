<?php

namespace App\Services\Overpass;

use App\Excepciones\OverpassNoDisponible;
use App\Jobs\RastrearSitioJob;
use App\Models\AreaCosecha;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\Suppression;
use App\Services\Clasificacion\CatalogoSectores;
use App\Services\Soporte\Latido;
use App\Services\Web\ClasificadorEmail;
use Illuminate\Support\Facades\DB;

class ServicioCosecha
{
    private ClasificadorEmail $clasificadorEmail;

    public function __construct(
        private OverpassClient $overpass,
        private CatalogoSectores $catalogo,
        ?ClasificadorEmail $clasificadorEmail = null,
    ) {
        $this->clasificadorEmail = $clasificadorEmail ?? new ClasificadorEmail;
    }

    /**
     * Cosecha un área: consulta Overpass, crea leads base y encola el rastreo.
     *
     * @return array{creados: int, omitidos: int, encolados: int}
     */
    public function cosechar(AreaCosecha $area, bool $dryRun = false): array
    {
        $area->forceFill([
            'estado' => 'en_proceso',
            'iniciada_at' => now(),
            'ultimo_error' => null,
            'finalizada_at' => null,
        ])->save();

        // Latido inicial: el vigilante lo usa para saber que la cosecha vive.
        Latido::marcar('cosecha', $area->nombre);
        $ultimoLatido = time();

        $filtros = [];
        $familiaPorFiltro = [];

        foreach (config('sectores') as $familia => $datos) {
            foreach ($datos['tags'] as [$tag, $valor]) {
                $filtros[] = [$tag, $valor];
                $familiaPorFiltro[$tag.'|'.$valor] = $familia;
            }
        }

        $creados = 0;
        $omitidos = 0;
        $encolados = 0;
        $emailsEncontrados = 0;

        // Punto de partida para que los contadores reflejen el progreso en vivo
        // (el panel los lee mientras el área sigue «en_proceso»).
        $baseLeads = (int) $area->leads_encontrados;
        $baseEmails = (int) $area->emails_encontrados;

        try {
            $stream = $this->overpass->buscarStream(
                [['nombre' => $area->nombre, 'admin_level' => (int) $area->admin_level]],
                $filtros
            );

            foreach ($stream as $candidato) {
                // Latido periódico por tiempo: cubre también áreas con muchos
                // «omitidos» y pocos «creados», donde el latido por lotes de 10
                // apenas se dispararía.
                if (time() - $ultimoLatido >= 60) {
                    Latido::marcar('cosecha', $area->nombre);
                    $ultimoLatido = time();
                }

                $placeId = (string) ($candidato['place_id'] ?? '');
                $website = $candidato['website'] ?? null;

                if ($placeId === '' || Lead::query()->where('place_id', $placeId)->exists()) {
                    $omitidos++;

                    continue;
                }

                if ($website === null || trim((string) $website) === '') {
                    $omitidos++;

                    continue;
                }

                $dominio = Suppression::dominioDeWeb((string) $website);
                if ($dominio === null) {
                    $omitidos++;

                    continue;
                }

                if (Lead::query()->where('website_dominio', $dominio)->exists()) {
                    $omitidos++;

                    continue;
                }

                if (Suppression::dominioExcluido($dominio)) {
                    $omitidos++;

                    continue;
                }

                $claveFiltro = ($candidato['osm_tag'] ?? '').'|'.($candidato['osm_valor'] ?? '');
                $sector = $familiaPorFiltro[$claveFiltro]
                    ?? $this->catalogo->porTag(
                        (string) ($candidato['osm_tag'] ?? ''),
                        (string) ($candidato['osm_valor'] ?? '')
                    );
                $subsector = null;
                if ($candidato['osm_tag'] && $candidato['osm_valor']) {
                    $subsector = $this->catalogo->subsector(
                        (string) $candidato['osm_tag'],
                        (string) $candidato['osm_valor']
                    );
                }

                if ($dryRun) {
                    $creados++;

                    continue;
                }

                $lead = DB::transaction(function () use ($candidato, $dominio, $sector, $subsector, &$emailsEncontrados): Lead {
                    $lead = Lead::query()->create([
                        'place_id' => $candidato['place_id'],
                        'nombre' => $candidato['nombre'],
                        'website' => $candidato['website'],
                        'website_dominio' => $dominio,
                        'osm_tag' => $candidato['osm_tag'] ?? null,
                        'osm_valor' => $candidato['osm_valor'] ?? null,
                        'osm_tags_raw' => $candidato['osm_tags_raw'] ?? null,
                        'sector' => $sector,
                        'subsector' => $subsector,
                        'clasificacion_metodo' => $sector !== null ? 'osm_filtro' : null,
                        'clasificacion_confianza' => $sector !== null ? 100 : null,
                        'telefono' => $candidato['telefono'] ?? null,
                        'direccion' => $candidato['direccion'] ?? null,
                        'ciudad' => $candidato['ciudad'] ?? null,
                        'codigo_postal' => $candidato['codigo_postal'] ?? null,
                        'latitud' => $candidato['latitud'] ?? null,
                        'longitud' => $candidato['longitud'] ?? null,
                        'fuente' => 'overpass',
                        'estado' => 'nuevo',
                        'capturado_at' => now(),
                    ]);

                    $emailOsm = $candidato['email'] ?? null;
                    if (is_string($emailOsm) && $emailOsm !== '') {
                        if ($this->clasificadorEmail->clasificar($emailOsm, $dominio) === ClasificadorEmail::ROL) {
                            LeadEmail::query()->create([
                                'lead_id' => $lead->id,
                                'email' => Suppression::normalizarEmail($emailOsm),
                                'tipo' => ClasificadorEmail::ROL,
                                'prefijo' => $this->clasificadorEmail->prefijo($emailOsm),
                                'origen' => 'osm',
                                'es_principal' => true,
                                'prioridad' => $this->clasificadorEmail->prioridad($emailOsm),
                            ]);
                            $emailsEncontrados++;
                        }
                    }

                    return $lead;
                });

                RastrearSitioJob::dispatch($lead->id)->onQueue('scraping');
                $creados++;
                $encolados++;

                // Persistimos el avance cada pocos leads para que el panel lo vea
                // sin esperar a que termine toda el área, y refrescamos el latido
                // para que el vigilante sepa que el proceso sigue vivo.
                if ($creados % 10 === 0) {
                    $area->forceFill([
                        'leads_encontrados' => $baseLeads + $creados,
                        'emails_encontrados' => $baseEmails + $emailsEncontrados,
                    ])->save();
                    Latido::marcar('cosecha', $area->nombre);
                }
            }

            $area->forceFill([
                'estado' => 'hecho',
                'finalizada_at' => now(),
                'leads_encontrados' => $baseLeads + $creados,
                'emails_encontrados' => $baseEmails + $emailsEncontrados,
                'ultimo_error' => null,
            ])->save();

            \Illuminate\Support\Facades\Cache::forget("cosecha:reintentos:{$area->id}");
        } catch (OverpassNoDisponible $e) {
            $area->forceFill([
                'estado' => 'error',
                'ultimo_error' => mb_substr($e->getMessage(), 0, 2000),
                'finalizada_at' => now(),
                'leads_encontrados' => $baseLeads + $creados,
                'emails_encontrados' => $baseEmails + $emailsEncontrados,
            ])->save();

            throw $e;
        }

        return [
            'creados' => $creados,
            'omitidos' => $omitidos,
            'encolados' => $encolados,
        ];
    }
}
