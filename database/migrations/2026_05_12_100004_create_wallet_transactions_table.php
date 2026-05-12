<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_balance_id')->constrained()->cascadeOnDelete();
            $table->string('type');                        // credit | debit
            $table->unsignedBigInteger('amount');          // integer cents
            $table->string('description')->nullable();
            $table->string('reference_type')->nullable();  // App\Models\Payment, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();

            $table->index('wallet_balance_id');
            $table->index('type');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
