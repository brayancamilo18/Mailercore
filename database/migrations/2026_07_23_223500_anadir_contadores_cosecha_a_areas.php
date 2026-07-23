<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas_cosecha', function (Blueprint $tabla) {
            $tabla->unsignedInteger('candidatos_vistos')->default(0)->after('emails_encontrados');
            $tabla->unsignedInteger('omitidos')->default(0)->after('candidatos_vistos');
            $tabla->unsignedInteger('ciclos_completados')->default(0)->after('omitidos');
        });
    }

    public function down(): void
    {
        Schema::table('areas_cosecha', function (Blueprint $tabla) {
            $tabla->dropColumn(['candidatos_vistos', 'omitidos', 'ciclos_completados']);
        });
    }
};
