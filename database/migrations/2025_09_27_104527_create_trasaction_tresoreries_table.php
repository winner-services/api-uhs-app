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
        Schema::create('trasaction_tresoreries', function (Blueprint $table) {
            $table->id();
            $table->string('motif')->nullable();
            $table->date('transaction_date');
            $table->foreignId('account_id')->constrained('tresoreries');
            $table->decimal('amount', 8, 2);
            $table->string('transaction_type');
            $table->foreignId('facturation_id')->nullable()->constrained('facturations');
            $table->float('solde');
            $table->boolean('status')->default(true);
            $table->string('reference')->nullable();
            $table->foreignId('addedBy')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trasaction_tresoreries');
    }
};
