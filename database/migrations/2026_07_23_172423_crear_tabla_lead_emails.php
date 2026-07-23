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
        Schema::create('lead_emails', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $tabla->string('email')->unique();
            $tabla->string('tipo')->default('rol');
            $tabla->string('prefijo')->nullable();
            $tabla->string('origen');
            $tabla->text('url_origen')->nullable();
            $tabla->boolean('es_principal')->default(false);
            $tabla->unsignedTinyInteger('prioridad')->default(9);
            $tabla->boolean('mx_ok')->nullable();
            $tabla->boolean('es_catch_all')->nullable();
            $tabla->string('estado_verificacion')->nullable();
            $tabla->timestamp('verificado_at')->nullable()->index();
            $tabla->timestamps();

            $tabla->index(['lead_id', 'es_principal']);
            $tabla->index(['estado_verificacion', 'verificado_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_emails');
    }
};
