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
        Schema::create('payement_panes', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date');
            $table->string('reference')->unique();
            $table->decimal('loan_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2);
            $table->foreignId('point_eau_abonnes_id')->nullable()->constrained('point_eau_abonnes')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('tresoreries')->nullOnDelete();
            $table->foreignId('addedBy')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payement_panes');
    }
};
