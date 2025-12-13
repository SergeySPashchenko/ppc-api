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
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('OrderID');
            $table->string('Agent');
            $table->dateTime('Created');
            $table->date('OrderDate');
            $table->string('OrderNum');
            $table->decimal('ProductTotal');
            $table->decimal('GrandTotal');
            $table->decimal('RefundAmount');
            $table->string('Shipping')->nullable();
            $table->string('ShippingMethod')->nullable();
            $table->boolean('Refund')->default(false);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null')->onUpdate('cascade');
            $table->unsignedBigInteger('BrandID')->nullable();
            $table->foreign('BrandID')->references('ProductID')->on('products')->onDelete('set null')->onUpdate('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
