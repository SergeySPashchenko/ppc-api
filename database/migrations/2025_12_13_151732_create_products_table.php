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
            $table->id('ProductID');
            $table->string('Product');
            $table->string('slug');
            $table->boolean('newSystem');
            $table->boolean('Visible');
            $table->boolean('flyer');
            $table->foreignId('main_category_id')->constrained('categories', 'category_id')->onDelete('set null');
            $table->foreignId('marketing_category_id')->constrained('categories', 'category_id')->onDelete('set null');
            $table->foreignId('gender_id')->constrained('genders', 'gender_id')->onDelete('set null');
            $table->foreignId('brand_id')->constrained('brands', 'brand_id')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();
            $table->index('slug');
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
