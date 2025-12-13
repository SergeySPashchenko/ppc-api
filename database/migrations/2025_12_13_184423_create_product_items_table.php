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
            $table->integer('Quantity');
            $table->boolean('upSell')->default(false);
            $table->boolean('active')->default(true);
            $table->boolean('deleted')->default(false);
            $table->string('offerProducts')->nullable();
            $table->boolean('extraProduct')->default(false);
            $table->softDeletes();
            $table->timestamps();
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
