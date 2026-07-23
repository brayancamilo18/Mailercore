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
        Schema::create('auditorias', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('lead_id')->unique()->constrained('leads')->cascadeOnDelete();
            $tabla->unsignedTinyInteger('puntuacion')->default(0);
            $tabla->string('hallazgo_codigo')->nullable();
            $tabla->string('hallazgo_principal')->nullable();
            $tabla->string('hallazgo_secundario_codigo')->nullable();
            $tabla->string('hallazgo_secundario')->nullable();
            $tabla->jsonb('hallazgos')->nullable();

            $tabla->unsignedTinyInteger('psi_rendimiento')->nullable();
            $tabla->unsignedTinyInteger('psi_seo')->nullable();
            $tabla->unsignedTinyInteger('psi_accesibilidad')->nullable();
            $tabla->unsignedTinyInteger('psi_buenas_practicas')->nullable();
            $tabla->unsignedInteger('psi_lcp_ms')->nullable();
            $tabla->decimal('psi_cls', 5, 3)->nullable();
            $tabla->unsignedInteger('psi_tbt_ms')->nullable();
            $tabla->unsignedInteger('psi_peso_kb')->nullable();
            $tabla->timestamp('psi_solicitado_at')->nullable()->index();
            $tabla->text('psi_error')->nullable();

            $tabla->timestamp('auditada_at')->nullable()->index();
            $tabla->timestamps();

            $tabla->index(['puntuacion', 'auditada_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
