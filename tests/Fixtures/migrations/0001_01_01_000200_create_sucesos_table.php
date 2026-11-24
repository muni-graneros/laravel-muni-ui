<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registros con DOS columnas de fecha (`created_at` y `ocurrido_en`), para
 * probar que `FiltroDeFecha` filtra por la columna que se le indica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sucesos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->dateTime('ocurrido_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucesos');
    }
};
