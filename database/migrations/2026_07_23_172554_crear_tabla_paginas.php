<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('paginas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $tabla->text('url');
            $tabla->string('ruta')->nullable();
            $tabla->unsignedSmallInteger('http_status')->nullable();
            $tabla->string('content_type')->nullable();
            $tabla->unsignedInteger('bytes')->nullable();
            $tabla->unsignedInteger('respuesta_ms')->nullable();
            $tabla->text('redirigida_a')->nullable();

            $tabla->text('title')->nullable();
            $tabla->unsignedSmallInteger('title_longitud')->nullable();
            $tabla->text('meta_description')->nullable();
            $tabla->unsignedSmallInteger('meta_desc_longitud')->nullable();
            $tabla->text('h1_texto')->nullable();
            $tabla->unsignedSmallInteger('h1_total')->nullable();
            $tabla->unsignedSmallInteger('h2_total')->nullable();
            $tabla->string('idioma', 10)->nullable();
            $tabla->text('canonical')->nullable();
            $tabla->string('generador')->nullable();
            $tabla->string('charset', 30)->nullable();
            $tabla->boolean('tiene_viewport')->nullable();
            $tabla->boolean('tiene_favicon')->nullable();
            $tabla->boolean('tiene_og')->nullable();
            $tabla->boolean('tiene_jsonld')->nullable();
            $tabla->jsonb('jsonld_tipos')->nullable();

            $tabla->unsignedSmallInteger('imagenes_total')->nullable();
            $tabla->unsignedSmallInteger('imagenes_sin_alt')->nullable();
            $tabla->unsignedSmallInteger('enlaces_internos')->nullable();
            $tabla->unsignedSmallInteger('enlaces_externos')->nullable();

            $tabla->jsonb('redes_sociales')->nullable();
            $tabla->jsonb('telefonos')->nullable();
            $tabla->jsonb('emails_encontrados')->nullable();
            $tabla->boolean('tiene_formulario')->nullable();
            $tabla->boolean('tiene_whatsapp')->nullable();
            $tabla->boolean('tiene_reservas')->nullable();
            $tabla->boolean('tiene_carrito')->nullable();

            $tabla->boolean('tiene_aviso_legal')->nullable();
            $tabla->boolean('tiene_privacidad')->nullable();
            $tabla->boolean('tiene_cookies')->nullable();
            $tabla->unsignedSmallInteger('anio_copyright')->nullable();

            $tabla->boolean('es_https')->nullable();
            $tabla->boolean('cert_valido')->nullable();
            $tabla->timestamp('cert_expira_at')->nullable();

            $tabla->string('html_hash', 40)->nullable();
            $tabla->text('error')->nullable();
            $tabla->timestamp('capturada_at');
            $tabla->timestamps();

            $tabla->index(['lead_id', 'capturada_at']);
            $tabla->index(['lead_id', 'ruta']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX paginas_lead_url_unico ON paginas (lead_id, md5(url))');
        } else {
            // SQLite (tests): no tiene md5() nativo; unique sobre lead_id + url.
            Schema::table('paginas', function (Blueprint $tabla) {
                $tabla->unique(['lead_id', 'url'], 'paginas_lead_url_unico');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS paginas_lead_url_unico');
        Schema::dropIfExists('paginas');
    }
};
