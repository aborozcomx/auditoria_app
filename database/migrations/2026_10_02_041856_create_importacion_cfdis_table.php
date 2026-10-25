<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('importaciones_cfdi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio')->index();
            $table->string('archivo_nombre');
            $table->string('archivo_ruta');
            $table->string('estado', 30)->default('pendiente')->index();
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('filas_procesadas')->default(0);
            $table->unsignedInteger('filas_con_error')->default(0);
            $table->json('errores')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->timestamp('iniciado_a')->nullable();
            $table->timestamp('finalizado_a')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'anio']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('importaciones_cfdi');
    }
};
