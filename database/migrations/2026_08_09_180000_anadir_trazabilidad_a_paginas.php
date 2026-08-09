<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paginas', function (Blueprint $tabla): void {
            $tabla->string('proposito', 20)->nullable()->after('capturada_at');
            $tabla->string('origen_ruta', 20)->nullable()->after('proposito');
            $tabla->boolean('contenido_truncado')->nullable()->after('origen_ruta');
            $tabla->unsignedTinyInteger('intentos')->default(1)->after('contenido_truncado');
            $tabla->string('fallo_tipo', 30)->nullable()->after('intentos');

            $tabla->index(['lead_id', 'proposito']);
        });
    }

    public function down(): void
    {
        Schema::table('paginas', function (Blueprint $tabla): void {
            $tabla->dropIndex(['lead_id', 'proposito']);
            $tabla->dropColumn([
                'proposito',
                'origen_ruta',
                'contenido_truncado',
                'intentos',
                'fallo_tipo',
            ]);
        });
    }
};
