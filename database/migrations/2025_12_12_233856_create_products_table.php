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
            $table->string('slug')->nullable()->unique();
            $table->boolean('newSystem')->default(true);
            $table->boolean('Visible')->default(true);
            $table->boolean('flyer')->default(false);
            $table->unsignedBigInteger('main_category_id')->nullable();
            $table->unsignedBigInteger('marketing_category_id')->nullable();
            $table->unsignedBigInteger('gender_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
           
            $table->foreign('main_category_id')->references('category_id')->on('categories')->nullOnDelete()->onUpdate('cascade');
            $table->foreign('marketing_category_id')->references('category_id')->on('categories')->nullOnDelete()->onUpdate('cascade');
            $table->foreign('gender_id')->references('gender_id')->on('genders')->nullOnDelete()->onUpdate('cascade');
            $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete()->onUpdate('cascade');
            $table->softDeletes();
            $table->timestamps();
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
