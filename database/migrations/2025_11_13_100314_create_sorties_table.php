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
        Schema::create('sorties', function (Blueprint $table) {
            $table->id();
            $table->integer('quantite');
            $table->decimal('prix_unit_vente',10,2)->nullable();
            $table->foreignId('product_id')->nullable()->constrained('produits')->nullOnDelete();
            $table->foreignId('addedBy')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->unique();
            $table->foreignId('account_id')->constrained('tresoreries');
            $table->date('date_transaction')->nullable();
            $table->boolean('deleted')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sorties');
    }
};
