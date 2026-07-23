<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estados válidos de mensajes:
 * pendiente, enviando, enviado, fallido, cancelado
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mensajes', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $tabla->foreignId('lead_email_id')->nullable()->constrained('lead_emails')->nullOnDelete();
            $tabla->string('destinatario');
            $tabla->string('plantilla');
            $tabla->unsignedTinyInteger('paso')->default(1);
            $tabla->string('asunto');
            $tabla->text('cuerpo_texto');
            $tabla->text('cuerpo_html')->nullable();
            $tabla->timestamp('programado_para')->index();
            $tabla->string('estado')->default('pendiente')->index();
            $tabla->unsignedTinyInteger('intentos')->default(0);
            $tabla->text('ultimo_error')->nullable();
            $tabla->string('message_id')->nullable()->index();
            $tabla->timestamp('bloqueado_at')->nullable();
            $tabla->timestamp('enviado_at')->nullable()->index();
            $tabla->timestamps();

            $tabla->unique(['lead_id', 'plantilla', 'paso'], 'mensajes_lead_plantilla_paso_unico');
            $tabla->index(['estado', 'programado_para']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mensajes');
    }
};
