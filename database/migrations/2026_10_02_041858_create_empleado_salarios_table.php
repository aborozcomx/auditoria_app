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
        Schema::create('empleado_salarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('importacion_cfdi_id')->nullable()->constrained('importaciones_cfdi')->cascadeOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->unsignedSmallInteger('anio')->index();
            $table->string('periodo', 20)->index();
            $table->string('tipo_nomina', 100)->nullable();
            $table->decimal('salario_base', 10, 2)->default(0);
            $table->unsignedSmallInteger('dias_vacaciones')->default(0);
            $table->decimal('importe_vacaciones', 10, 2)->default(0);
            $table->decimal('aguinaldo_diario', 10, 2)->default(0);
            $table->decimal('prima_vacacional', 10, 2)->default(0);
            $table->decimal('salario_diario_integrado', 10, 2)->default(0);
            $table->decimal('salario_diario_integrado_excel', 10, 2)->nullable();
            $table->decimal('limite_salario_diario', 10, 2)->default(0);
            $table->boolean('excede_limite')->default(false);
            $table->decimal('uma_utilizada', 10, 2)->default(0);
            $table->unsignedSmallInteger('anio_uma')->nullable();
            $table->timestamps();

            $table->unique(['empleado_id', 'anio', 'periodo']);
            $table->index(['empresa_id', 'anio', 'periodo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empleado_salarios');
    }
};
