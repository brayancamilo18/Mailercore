<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::create('harvest_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('admin_level');
            $table->string('status')->default('pendiente'); // pendiente | en_proceso | hecho | error
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedInteger('leads_found')->default(0);
            $table->unsignedInteger('emails_found')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('priority');
            $table->unique(['name', 'admin_level']);
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('harvest_areas');
    }
};
