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
            $table->softDeletes();
            $table->timestamps();
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
