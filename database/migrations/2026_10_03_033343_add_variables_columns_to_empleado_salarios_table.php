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
        Schema::table('empleado_salarios', function (Blueprint $table) {
            $table->decimal('cantidad_dias', 10, 2)->default(0)->after('salario_diario_integrado_excel');
            $table->decimal('importe_suma', 12, 2)->default(0)->after('cantidad_dias');
            $table->decimal('sdi_variable', 10, 2)->default(0)->after('importe_suma');
            $table->decimal('sdi_total', 10, 2)->default(0)->after('sdi_variable');
            $table->decimal('sdi_auditado', 10, 2)->default(0)->after('sdi_total');
            $table->decimal('imss_cia', 10, 2)->default(0)->after('sdi_auditado');
            $table->decimal('diferencia', 10, 2)->default(0)->after('imss_cia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empleado_salarios', function (Blueprint $table) {
            $table->dropColumn([
                'cantidad_dias',
                'importe_suma',
                'sdi_variable',
                'sdi_total',
                'sdi_auditado',
                'imss_cia',
                'diferencia',
            ]);
        });
    }
};
