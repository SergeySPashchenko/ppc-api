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
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();

            $table
                ->enum('type', ['billing', 'shipping', 'both'])
                ->default('both');

            $table->string('name')->nullable();

            $table->string('address')->nullable();

            $table->string('address2')->nullable();

            $table->string('city')->nullable();

            $table->string('state')->nullable();

            $table->string('zip')->nullable();

            $table->string('country')->nullable();

            $table->string('phone')->nullable();

            $table->string('address_hash')->nullable();

            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null')->onUpdate('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
