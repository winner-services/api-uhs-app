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
        Schema::create('abonnes', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->foreignId('categorie_id')
                ->nullable()
                ->constrained('abonnement_categories')
                ->nullOnDelete();
            $table->string('telephone')->nullable();
            $table->string('status')->nullable();
            $table->string('gender')->nullable();
            $table->string('adresse')->nullable();
            $table->string('piece_identite')->nullable();
            $table->string('num_piece_identite')->nullable();
            $table->foreignId('addedBy')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abonnes');
    }
};
