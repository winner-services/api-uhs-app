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
        Schema::create('point_eaus', function (Blueprint $table) {
            $table->id();
            $table->string('lat')->nullable();
            $table->string('long')->nullable();
            $table->string('numero_compteur')->nullable();
            $table->string('status')->default('Actif');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_eaus');
    }
};
