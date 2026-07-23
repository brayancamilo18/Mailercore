<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valores válidos de salud en dias_envio:
 * verde, ambar, rojo, parado
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dias_envio', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->date('fecha')->unique();
            $tabla->unsignedTinyInteger('escalon')->default(1);
            $tabla->unsignedSmallInteger('cuota_planificada')->default(0);
            $tabla->unsignedSmallInteger('generados')->default(0);
            $tabla->unsignedSmallInteger('enviados')->default(0);
            $tabla->unsignedSmallInteger('fallidos')->default(0);
            $tabla->unsignedSmallInteger('rebotes_duros')->default(0);
            $tabla->unsignedSmallInteger('rebotes_blandos')->default(0);
            $tabla->unsignedSmallInteger('bajas')->default(0);
            $tabla->unsignedSmallInteger('respuestas')->default(0);
            $tabla->decimal('tasa_rebote', 5, 2)->nullable();
            $tabla->string('salud')->default('verde');
            $tabla->text('nota')->nullable();
            $tabla->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dias_envio');
    }
};
