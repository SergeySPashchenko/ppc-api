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
        Schema::table('roles', function (Blueprint $table): void {
            $table->foreign('team_id')
                ->references('id')
                ->on('accesses')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        Schema::table('model_has_roles', function (Blueprint $table): void {
            $table->foreign('team_id')
                ->references('id')
                ->on('accesses')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        Schema::table('model_has_permissions', function (Blueprint $table): void {
            if (Schema::hasColumn('model_has_permissions', 'team_id')) {
                $table->foreign('team_id')
                    ->references('id')
                    ->on('accesses')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_has_permissions', function (Blueprint $table): void {
            if (Schema::hasColumn('model_has_permissions', 'team_id')) {
                $table->dropForeign(['team_id']);
            }
        });

        Schema::table('model_has_roles', function (Blueprint $table): void {
            $table->dropForeign(['team_id']);
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropForeign(['team_id']);
        });
    }
};
