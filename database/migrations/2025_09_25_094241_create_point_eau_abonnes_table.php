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
        Schema::create('point_eau_abonnes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abonne_id')->constrained('abonnes');
            $table->foreignId('point_eau_id')->constrained('point_eaus');
            $table->date('date_operation');
            $table->foreignId('addedBy')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_eau_abonnes');
    }
};
