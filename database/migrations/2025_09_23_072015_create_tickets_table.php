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
            $table->foreignId('point_id')->constrained('point_eaus')->onDelete('cascade');;
            $table->text('description')->nullable();
            $table->string('statut')->nullable();
            $table->string('priorite')->nullable();
            $table->foreignId('technicien_id')->constrained('users');
            $table->date('date_ouverture')->nullable();
            $table->date('date_cloture')->nullable();
            $table->string('reference');
            $table->foreignId('addedBy')->constrained('users');
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
