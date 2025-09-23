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
        Schema::create('facturations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abonne_id')->constrained('abonnes')->onDelete('cascade');
            $table->string('mois'); // 09-2025
            $table->decimal('montant', 8, 2);
            $table->decimal('dette', 8, 2)->default(0);
            $table->string('status', []);
            $table->date('date_emission');
            $table->date('date_paiement')->nullable();
            $table->foreignId('addedBy')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturations');
    }
};
