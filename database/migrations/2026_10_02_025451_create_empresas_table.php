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
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150)->index();
            $table->string('rfc', 13)->unique();
            $table->string('razon_social', 255)->index();
            $table->unsignedSmallInteger('dias_aguinaldo')->default(15);
            $table->decimal('prima_vacacional', 5, 2)->default(25.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
