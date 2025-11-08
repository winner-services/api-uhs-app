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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_id')->nullable()->constrained('point_eaus')->nullOnDelete();
            $table->text('description')->nullable();
            // $table->string('statut')->nullable();
            $table->enum('statut', ['OUVERT', 'EN_COURS','payé','acompte', 'CLOTURE'])->default('EN_COURS');    
            $table->enum('priorite', ['URGENTE', 'NORMALE'])->default('URGENTE');
            // $table->string('priorite')->nullable();
            $table->foreignId('technicien_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_ouverture')->nullable();
            $table->date('date_cloture')->nullable();
            $table->string('reference');
            $table->foreignId('addedBy')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
