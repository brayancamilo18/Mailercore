<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Introduce el status de lead "rebotado" (procesado vía IMAP).
 * La columna status ya es string; esta migración deja constancia del despliegue.
 */
return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        // Status admitidos: ver App\Models\Lead::ESTADOS (incluye "rebotado").
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        // Sin cambios de esquema que revertir.
    }
};
