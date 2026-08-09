<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $tabla): void {
            $tabla->string('pais_codigo', 2)->nullable()->after('provincia')->index();
            $tabla->index(['pais_codigo', 'provincia']);
        });

        // Relleno: provincia → país solo si el nombre de área es único entre países.
        if (Schema::hasTable('areas_cosecha')) {
            DB::statement(<<<'SQL'
                UPDATE leads AS l
                SET pais_codigo = a.pais_codigo
                FROM (
                    SELECT nombre, MIN(pais_codigo) AS pais_codigo
                    FROM areas_cosecha
                    WHERE pais_codigo IS NOT NULL
                    GROUP BY nombre
                    HAVING COUNT(DISTINCT pais_codigo) = 1
                ) AS a
                WHERE l.pais_codigo IS NULL
                  AND l.provincia IS NOT NULL
                  AND l.provincia = a.nombre
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $tabla): void {
            $tabla->dropIndex(['pais_codigo', 'provincia']);
            $tabla->dropIndex(['pais_codigo']);
            $tabla->dropColumn('pais_codigo');
        });
    }
};
