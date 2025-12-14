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
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id('idOrderItem');
            $table->unsignedBigInteger('OrderID')->nullable();
            $table->foreign('OrderID')->references('id')->on('orders')->onDelete('set null')->onUpdate('cascade');
            $table->unsignedBigInteger('ItemID')->nullable();
            $table->foreign('ItemID')->references('ItemID')->on('product_items')->onDelete('set null')->onUpdate('cascade');
            $table->decimal('Price', 10, 2);
            $table->integer('Qty');
            $table->decimal('line_total', 12, 2)->default(0);
            $table->boolean('is_valid')->default(true);
            $table->decimal('price_raw', 12, 2)->nullable();
            $table->integer('qty_raw')->nullable();
            $table->text('validation_errors')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Indexes and constraints
            $table->index('OrderID');
            $table->index('ItemID');
            $table->unique(['OrderID', 'ItemID'], 'order_items_order_item_unique');
            $table->index('line_total', 'order_items_line_total_index');
            $table->index('is_valid', 'order_items_is_valid_index');
            $table->index(['OrderID', 'is_valid'], 'order_items_order_valid_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
