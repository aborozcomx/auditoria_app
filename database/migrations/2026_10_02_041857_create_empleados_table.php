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
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->string('registro_patronal', 50)->nullable();
            $table->string('rfc_receptor', 13)->index();
            $table->string('nombre', 255)->index();
            $table->date('fecha_inicio_relacion_laboral')->nullable();
            $table->string('numero_empleado', 50)->index();
            $table->string('antiguedad_texto', 60)->nullable();
            $table->unsignedInteger('antiguedad_semanas')->default(0);
            $table->unsignedSmallInteger('antiguedad_anios')->default(0);
            $table->boolean('es_sindicalizado')->default(false);
            $table->string('curp', 18)->nullable()->index();
            $table->string('numero_seguro_social', 50)->nullable()->index();
            $table->decimal('salario_base', 10, 2)->default(0);
            $table->decimal('salario_diario_integrado', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['empresa_id', 'numero_empleado']);
            $table->index(['empresa_id', 'rfc_receptor']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
