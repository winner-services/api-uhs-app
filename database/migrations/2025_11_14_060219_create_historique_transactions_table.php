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
        Schema::create('historique_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_from_id')->constrained('tresoreries')->cascadeOnDelete();
            $table->foreignId('account_to_id')->constrained('tresoreries')->cascadeOnDelete();
            $table->decimal('montant', 15, 2);
            $table->string('type_transaction');
            $table->text('description')->nullable();
            $table->foreignId('addedBy')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('date_transaction')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historique_transactions');
    }
};
