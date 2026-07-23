<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('place_id')->nullable()->unique();
            $tabla->string('nombre');
            $tabla->string('website')->nullable();
            $tabla->string('website_dominio')->nullable()->index();
            $tabla->string('osm_tag')->nullable();
            $tabla->string('osm_valor')->nullable();
            $tabla->jsonb('osm_tags_raw')->nullable();
            $tabla->string('sector')->nullable()->index();
            $tabla->string('subsector')->nullable();
            $tabla->string('clasificacion_metodo')->nullable();
            $tabla->unsignedTinyInteger('clasificacion_confianza')->nullable();
            $tabla->string('telefono')->nullable();
            $tabla->string('direccion')->nullable();
            $tabla->string('ciudad')->nullable();
            $tabla->string('provincia')->nullable()->index();
            $tabla->string('codigo_postal', 10)->nullable();
            $tabla->decimal('latitud', 10, 7)->nullable();
            $tabla->decimal('longitud', 10, 7)->nullable();
            $tabla->string('fuente')->default('overpass');
            $tabla->string('estado')->default('nuevo')->index();
            $tabla->timestamp('capturado_at')->nullable();
            $tabla->timestamp('contactado_at')->nullable()->index();
            $tabla->timestamp('rastreado_at')->nullable()->index();
            $tabla->text('notas')->nullable();
            $tabla->timestamps();

            $tabla->index(['estado', 'sector']);
            $tabla->index(['sector', 'rastreado_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
