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
            $table->foreignId('account_id')
                ->nullable()
                ->constrained('tresoreries')
                ->onDelete('set null');
            $table->decimal('amount', 8, 2);
            $table->string('transaction_type');
            $table->foreignId('facturation_id')->nullable()->constrained('facturations')->onDelete('set null');
            $table->float('solde');
            $table->boolean('status')->default(true);
            $table->string('reference')->nullable();
            $table->foreignId('addedBy')->constrained('users');
            $table->string('beneficiaire')->nullable();
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
