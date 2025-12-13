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
        Schema::create('accesses', function (Blueprint $table) {
            $table->id('access_id');
            $table->tinyInteger('level')->default(1);
            $table->boolean('is_guest')->default(false);
            $table->foreignId('user_id')->nullable()->index()->constrained('users')->onDelete('cascade')->onUpdate('cascade');
            $table->nullableMorphs('accessible', 'accessible');
            $table->unique(['user_id', 'accessible_type', 'accessible_id'], 'accesses_user_accessible_unique');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accesses');
    }
};
