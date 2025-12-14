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
            $table->string('OrderN')->nullable();
            $table->decimal('ProductTotal');
            $table->decimal('GrandTotal');
            $table->decimal('RefundAmount');
            $table->string('refund_amount_raw', 255)->nullable();
            $table->boolean('refund_amount_is_valid')->default(true);
            $table->string('Shipping')->nullable();
            $table->string('ShippingMethod')->nullable();
            $table->boolean('Refund')->default(false);
            $table->string('refund_type', 50)->nullable();
            $table->boolean('is_refunded')->default(false);
            $table->boolean('is_partial_refund')->default(false);
            $table->boolean('is_marketplace')->default(false);
            $table->boolean('has_missing_contact_info')->default(false);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null')->onUpdate('cascade');
            $table->unsignedBigInteger('BrandID')->nullable();
            $table->foreign('BrandID')->references('ProductID')->on('products')->onDelete('set null')->onUpdate('cascade');
            $table->softDeletes();
            $table->timestamps();

            // Indexes and constraints
            $table->unique('OrderID', 'orders_orderid_unique');
            $table->index('OrderDate');
            $table->index('customer_id');
            $table->index('refund_amount_is_valid');
            $table->index('is_refunded');
            $table->index('is_partial_refund');
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
