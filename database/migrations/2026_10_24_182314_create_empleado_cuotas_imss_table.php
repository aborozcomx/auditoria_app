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
        Schema::create('empleado_cuotas_imss', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importacion_cuota_imss_id')->constrained('importaciones_cuotas_imss')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('empleado_id')->nullable()->constrained('empleados')->nullOnDelete();
            $table->foreignId('empleado_salario_id')->nullable()->constrained('empleado_salarios')->nullOnDelete();
            $table->unsignedSmallInteger('anio')->index();
            $table->string('periodo', 20)->index();
            $table->string('nss', 30)->index();
            $table->string('nombre')->nullable();
            $table->string('clave', 50)->nullable();
            $table->string('registro_patronal', 50)->nullable();
            $table->integer('dias')->default(0);
            $table->integer('ausencias')->default(0);
            $table->integer('incapacidades')->default(0);
            $table->integer('total_dias')->default(0);
            $table->integer('dias_incapacidad')->default(0);
            $table->decimal('sdi_cotizado', 10, 2)->default(0);
            $table->decimal('salario_base', 10, 2)->default(0);
            $table->decimal('salario_incapacidad', 10, 2)->default(0);
            $table->decimal('salario_uma_tope', 10, 2)->default(0);
            $table->decimal('base_price_em', 10, 2)->default(0);
            $table->decimal('base_price_rt', 10, 2)->default(0);
            $table->decimal('base_price_iv', 10, 2)->default(0);
            $table->decimal('salario_uma', 10, 2)->default(0);
            $table->decimal('fixed_price', 10, 2)->default(0);
            $table->decimal('sdmg', 10, 2)->default(0);
            $table->decimal('in_cash', 10, 2)->default(0);
            $table->decimal('disability_health', 10, 2)->default(0);
            $table->decimal('pensioners', 10, 2)->default(0);
            $table->decimal('risk', 8, 5)->default(0);
            $table->decimal('risk_price', 10, 2)->default(0);
            $table->decimal('nurseries', 10, 2)->default(0);
            $table->decimal('total_audit', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('difference', 10, 2)->default(0);
            $table->decimal('uma_utilizada', 10, 2)->default(0);
            $table->unsignedSmallInteger('anio_uma')->default(0);
            $table->timestamps();

            $table->index(['empresa_id', 'anio', 'periodo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empleado_cuotas_imss');
    }
};
