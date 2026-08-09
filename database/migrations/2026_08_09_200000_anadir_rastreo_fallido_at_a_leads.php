<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $tabla): void {
            // Marca los leads cuya home no se pudo obtener: sin esto, un rastreo
            // fallido entero es indistinguible de una web sana sin señales.
            $tabla->timestamp('rastreo_fallido_at')->nullable()->after('rastreado_at');
            $tabla->index('rastreo_fallido_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $tabla): void {
            $tabla->dropIndex(['rastreo_fallido_at']);
            $tabla->dropColumn('rastreo_fallido_at');
        });
    }
};
