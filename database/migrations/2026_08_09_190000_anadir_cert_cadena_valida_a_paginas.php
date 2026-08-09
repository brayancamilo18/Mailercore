<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paginas', function (Blueprint $tabla): void {
            $tabla->boolean('cert_cadena_valida')->nullable()->after('cert_valido');
        });
    }

    public function down(): void
    {
        Schema::table('paginas', function (Blueprint $tabla): void {
            $tabla->dropColumn('cert_cadena_valida');
        });
    }
};
