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
        Schema::table('empleado_cuotas_imss', function (Blueprint $table) {
            $table->decimal('sdi_archivo', 10, 2)->default(0)->after('sdi_cotizado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empleado_cuotas_imss', function (Blueprint $table) {
            $table->dropColumn('sdi_archivo');
        });
    }
};
