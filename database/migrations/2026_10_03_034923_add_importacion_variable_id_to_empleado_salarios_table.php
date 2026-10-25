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
            $table->foreignId('importacion_variable_id')
                ->nullable()
                ->after('importacion_cfdi_id')
                ->constrained('importaciones_variables')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empleado_salarios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('importacion_variable_id');
        });
    }
};
