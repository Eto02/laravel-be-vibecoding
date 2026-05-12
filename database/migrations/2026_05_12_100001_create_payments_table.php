<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway');                    // xendit | midtrans | wallet
            $table->string('method');                     // invoice | virtual_account | qris | ewallet | snap | wallet
            $table->string('gateway_ref')->nullable()->unique();
            $table->unsignedBigInteger('amount');         // integer cents
            $table->string('status')->default('pending'); // PaymentStatus enum
            $table->json('payment_details')->nullable();  // method-specific data
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('status');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
