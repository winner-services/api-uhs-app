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
        Schema::create('payements', function (Blueprint $table) {
            $table->id();
            $table->decimal('loan_amount', 8, 2);
            $table->decimal('paid_amount', 8, 2);
            $table->date('transaction_date');
            $table->foreignId('account_id')->nullable()->constrained('tresoreries')->nullOnDelete();
            $table->foreignId('facture_id')->nullable()->constrained('facturations')->nullOnDelete();
            $table->foreignId('addedBy')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('status')->default(true);
            $table->string('reference')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payements');
    }
};
