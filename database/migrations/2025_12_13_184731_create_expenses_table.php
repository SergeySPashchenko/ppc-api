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
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->date('ExpenseDate');
            $table->decimal('Expense', 10, 2);
            $table->unsignedBigInteger('ProductID')->nullable();
            $table->unsignedBigInteger('ExpenseID')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Foreign keys через ProductID та ExpenseTypeID
            $table->foreign('ProductID')->references('ProductID')->on('products')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('ExpenseID')->references('ExpenseID')->on('expensetypes')->onDelete('set null')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
