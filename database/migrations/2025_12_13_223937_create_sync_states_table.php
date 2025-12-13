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
        Schema::create('sync_states', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type')->unique(); // 'orders' or 'expenses'
            $table->date('last_order_date')->nullable(); // Last imported OrderDate
            $table->date('last_expense_date')->nullable(); // Last imported ExpenseDate
            $table->unsignedBigInteger('last_external_order_id')->nullable(); // Last imported OrderID
            $table->unsignedBigInteger('last_external_expense_id')->nullable(); // Last imported expense id
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->index('entity_type');
            $table->index('last_order_date');
            $table->index('last_expense_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_states');
    }
};
