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
            $table->string('village')->nullable();
            $table->string('quartier')->nullable();
            $table->string('num_avenue')->nullable();
            $table->string('num_parcelle')->nullable();
            $table->string('nom_chef')->nullable();
            $table->string('contact')->nullable();
            $table->string('numero_compteur')->nullable();
            $table->string('status')->default('Actif');
            $table->string('matricule')->nullable();
            $table->text('entite')->nullable();
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
