<?php

declare(strict_types=1);

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
        Schema::create('product_items', function (Blueprint $table): void {
            $table->id('ItemID');
            $table->unsignedBigInteger('ProductID')->nullable();
            $table->foreign('ProductID')->references('ProductID')->on('products')->onDelete('set null')->onUpdate('cascade');
            $table->string('ProductName');
            $table->string('slug');
            $table->string('SKU');
            $table->string('sku_normalized', 255)->nullable();
            $table->integer('Quantity');
            $table->integer('quantity_raw')->nullable();
            $table->boolean('upSell')->default(false);
            $table->boolean('active')->default(true);
            $table->boolean('deleted')->default(false);
            $table->boolean('is_valid')->default(true);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_discount_item')->default(false);
            $table->boolean('is_bundle')->default(false);
            $table->string('offerProducts')->nullable();
            $table->boolean('extraProduct')->default(false);
            $table->text('validation_errors')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Indexes for performance
            $table->index('is_valid', 'product_items_is_valid_index');
            $table->index('is_available', 'product_items_is_available_index');
            $table->index('sku_normalized', 'product_items_sku_normalized_index');
            $table->index(['ProductID', 'is_available'], 'product_items_product_available_index');
            $table->index(['is_available', 'is_valid'], 'product_items_available_valid_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_items');
    }
};
