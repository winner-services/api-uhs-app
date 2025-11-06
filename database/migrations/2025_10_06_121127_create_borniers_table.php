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
        Schema::create('borniers', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('phone')->nullable();
            $table->string('adresse')->nullable();
            $table->foreignId('borne_id')->nullable()->constrained('point_eaus')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('borniers');
    }
};
