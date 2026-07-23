<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos válidos de eventos_inbox:
 * rebote_duro, rebote_blando, baja, respuesta, queja, ignorado
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('eventos_inbox', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('mensaje_id')->nullable()->constrained('mensajes')->nullOnDelete();
            $tabla->string('email')->index();
            $tabla->string('tipo')->index();
            $tabla->string('codigo_smtp', 20)->nullable();
            $tabla->text('asunto')->nullable();
            $tabla->text('extracto')->nullable();
            $tabla->string('raw_hash', 40)->unique();
            $tabla->timestamp('recibido_at')->index();
            $tabla->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos_inbox');
    }
};
