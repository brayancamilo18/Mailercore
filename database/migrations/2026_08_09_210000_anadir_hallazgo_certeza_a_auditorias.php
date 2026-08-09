<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditorias', function (Blueprint $tabla): void {
            // Cuánto se puede defender el hallazgo principal: 'hecho',
            // 'observado' o 'indicio'. Cada entrada del JSON hallazgos lleva
            // además la suya.
            $tabla->string('hallazgo_certeza', 20)->nullable()->after('hallazgo_codigo');
        });
    }

    public function down(): void
    {
        Schema::table('auditorias', function (Blueprint $tabla): void {
            $tabla->dropColumn('hallazgo_certeza');
        });
    }
};
