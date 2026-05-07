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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('min_price')->default(0);
            $table->unsignedBigInteger('max_price')->default(0);
            $table->unsignedInteger('total_stock')->default(0);
            $table->unsignedInteger('sold_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('weight_gram')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('store_id');
            $table->index('category_id');
            $table->index('status');
            $table->index('min_price');
            $table->index('sold_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
