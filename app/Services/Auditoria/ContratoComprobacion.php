<?php

namespace App\Services\Auditoria;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Models\Pagina;
use Illuminate\Support\Collection;

interface ContratoComprobacion
{
    public function codigo(): string;

    public function peso(): int;

    /**
     * @return list<string>|null null = aplica a todos los sectores
     */
    public function sectores(): ?array;

    /**
     * @param  Collection<int, Pagina>  $paginas
     */
    public function evaluar(Lead $lead, Collection $paginas, ?Auditoria $auditoria): ?Hallazgo;
}
