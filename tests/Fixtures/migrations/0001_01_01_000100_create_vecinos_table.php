<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vecinos', function (Blueprint $table): void {
            $table->id();
            $table->string('padron')->nullable();
            $table->string('nombre_completo');
            $table->string('correo')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vecinos');
    }
};
