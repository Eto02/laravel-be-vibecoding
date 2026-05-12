<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');          // integer cents
            $table->string('reason')->nullable();
            $table->string('status')->default('pending');  // RefundStatus enum
            $table->string('gateway_ref')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index('payment_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
