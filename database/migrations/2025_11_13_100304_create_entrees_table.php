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
        Schema::create('entrees', function (Blueprint $table) {
            $table->id();
            $table->integer('quantite');
            $table->decimal('prix_unit_achat',10,2)->nullable();
            $table->foreignId('product_id')->nullable()->constrained('produits')->nullOnDelete();
            $table->foreignId('addedBy')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entrees');
    }
};
