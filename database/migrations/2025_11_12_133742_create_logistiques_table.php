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
        Schema::create('logistiques', function (Blueprint $table) {
            $table->id();
            $table->date('date_transaction');
            $table->decimal('previous_quantity');
            $table->decimal('new_quantity');
            $table->text('motif')->nullable();
            $table->string('type_transaction');
            $table->foreignId('product_id')->nullable()->constrained('produits')->nullOnDelete();
            $table->foreignId('addedBy')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logistiques');
    }
};
