<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qué señales enlazaba la home cuando se rastreó. El HTML no se guarda, así
     * que sin esta columna la auditoría no puede saber si el sitio prometía
     * reservas o carrito en una página que nadie llegó a visitar.
     */
    public function up(): void
    {
        Schema::table('paginas', function (Blueprint $tabla): void {
            $tabla->jsonb('enlaces_senal')->nullable()->after('enlaces_externos');
        });
    }

    public function down(): void
    {
        Schema::table('paginas', function (Blueprint $tabla): void {
            $tabla->dropColumn('enlaces_senal');
        });
    }
};
