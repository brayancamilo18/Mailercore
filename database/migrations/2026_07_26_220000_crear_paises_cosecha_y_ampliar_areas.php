<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-país: la cosecha deja de ser solo España.
 * Cada país tiene un tope de ciclos; al llegar al máximo queda marcado como hecho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paises_cosecha', function (Blueprint $tabla) {
            $tabla->string('codigo', 2)->primary(); // ISO 3166-1 alpha-2
            $tabla->string('nombre');
            $tabla->unsignedInteger('prioridad')->default(100)->index();
            $tabla->string('estado')->default('pendiente')->index(); // pendiente|en_proceso|hecho
            $tabla->unsignedTinyInteger('ciclos_completados')->default(0);
            $tabla->unsignedTinyInteger('max_ciclos')->default(3);
            $tabla->string('mapa_motor')->default('geojson'); // spain|geojson|ninguno
            $tabla->string('mapa_src')->nullable();
            $tabla->timestamps();
        });

        DB::table('paises_cosecha')->insert([
            'codigo' => 'ES',
            'nombre' => 'España',
            'prioridad' => 1,
            'estado' => 'pendiente',
            'ciclos_completados' => 0,
            'max_ciclos' => 3,
            'mapa_motor' => 'spain',
            'mapa_src' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('areas_cosecha', function (Blueprint $tabla) {
            $tabla->string('pais_codigo', 2)->default('ES')->after('id');
            $tabla->string('codigo_mapa', 32)->nullable()->after('nombre');
        });

        DB::table('areas_cosecha')->update(['pais_codigo' => 'ES']);

        Schema::table('areas_cosecha', function (Blueprint $tabla) {
            $tabla->dropUnique(['nombre', 'admin_level']);
            $tabla->unique(['pais_codigo', 'nombre', 'admin_level']);
            $tabla->index('pais_codigo');
            $tabla->foreign('pais_codigo')
                ->references('codigo')
                ->on('paises_cosecha')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('areas_cosecha', function (Blueprint $tabla) {
            $tabla->dropForeign(['pais_codigo']);
            $tabla->dropUnique(['pais_codigo', 'nombre', 'admin_level']);
            $tabla->dropIndex(['pais_codigo']);
            $tabla->unique(['nombre', 'admin_level']);
            $tabla->dropColumn(['pais_codigo', 'codigo_mapa']);
        });

        Schema::dropIfExists('paises_cosecha');
    }
};
