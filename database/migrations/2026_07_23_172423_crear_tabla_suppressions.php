<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motivos válidos de suppressions:
 * baja, rebote_duro, manual, queja, supresion_rgpd
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('suppressions', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('email')->nullable()->unique();
            $tabla->string('email_hash')->nullable()->index();
            $tabla->string('dominio')->nullable()->index();
            $tabla->string('motivo');
            $tabla->text('detalle')->nullable();
            $tabla->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppressions');
    }
};
