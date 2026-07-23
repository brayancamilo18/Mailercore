<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estados válidos de areas_cosecha:
 * pendiente, en_proceso, hecho, error
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('areas_cosecha', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre');
            $tabla->unsignedTinyInteger('admin_level');
            $tabla->string('estado')->default('pendiente')->index();
            $tabla->unsignedInteger('prioridad')->default(100)->index();
            $tabla->unsignedInteger('leads_encontrados')->default(0);
            $tabla->unsignedInteger('emails_encontrados')->default(0);
            $tabla->text('ultimo_error')->nullable();
            $tabla->timestamp('iniciada_at')->nullable();
            $tabla->timestamp('finalizada_at')->nullable();
            $tabla->timestamps();
            $tabla->unique(['nombre', 'admin_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('areas_cosecha');
    }
};
