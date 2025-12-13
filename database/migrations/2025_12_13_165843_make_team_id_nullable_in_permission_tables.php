<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teamForeignKey = $columnNames['team_foreign_key'] ?? 'team_id';

        // Check if team_id column exists in each table before modifying
        // For model_has_roles
        if (Schema::hasTable($tableNames['model_has_roles']) && 
            Schema::hasColumn($tableNames['model_has_roles'], $teamForeignKey)) {
            
            // Update NULL values to 0 temporarily for SQLite compatibility
            DB::table($tableNames['model_has_roles'])
                ->whereNull($teamForeignKey)
                ->update([$teamForeignKey => 0]);

            // Recreate table with nullable team_id
            DB::statement("
                CREATE TABLE {$tableNames['model_has_roles']}_new (
                    role_id INTEGER NOT NULL,
                    model_type VARCHAR NOT NULL,
                    model_id INTEGER NOT NULL,
                    {$teamForeignKey} INTEGER NULL,
                    PRIMARY KEY ({$teamForeignKey}, role_id, model_id, model_type),
                    FOREIGN KEY (role_id) REFERENCES {$tableNames['roles']} (id) ON DELETE CASCADE
                )
            ");

            DB::statement("
                INSERT INTO {$tableNames['model_has_roles']}_new 
                SELECT role_id, model_type, model_id, NULLIF({$teamForeignKey}, 0) as {$teamForeignKey}
                FROM {$tableNames['model_has_roles']}
            ");

            Schema::drop($tableNames['model_has_roles']);
            DB::statement("ALTER TABLE {$tableNames['model_has_roles']}_new RENAME TO {$tableNames['model_has_roles']}");

            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamForeignKey) {
                $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
                $table->index($teamForeignKey, 'model_has_roles_team_foreign_key_index');
            });
        }

        // For model_has_permissions
        if (Schema::hasTable($tableNames['model_has_permissions']) && 
            Schema::hasColumn($tableNames['model_has_permissions'], $teamForeignKey)) {
            
            DB::table($tableNames['model_has_permissions'])
                ->whereNull($teamForeignKey)
                ->update([$teamForeignKey => 0]);

            DB::statement("
                CREATE TABLE {$tableNames['model_has_permissions']}_new (
                    permission_id INTEGER NOT NULL,
                    model_type VARCHAR NOT NULL,
                    model_id INTEGER NOT NULL,
                    {$teamForeignKey} INTEGER NULL,
                    PRIMARY KEY ({$teamForeignKey}, permission_id, model_id, model_type),
                    FOREIGN KEY (permission_id) REFERENCES {$tableNames['permissions']} (id) ON DELETE CASCADE
                )
            ");

            DB::statement("
                INSERT INTO {$tableNames['model_has_permissions']}_new 
                SELECT permission_id, model_type, model_id, NULLIF({$teamForeignKey}, 0) as {$teamForeignKey}
                FROM {$tableNames['model_has_permissions']}
            ");

            Schema::drop($tableNames['model_has_permissions']);
            DB::statement("ALTER TABLE {$tableNames['model_has_permissions']}_new RENAME TO {$tableNames['model_has_permissions']}");

            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamForeignKey) {
                $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
                $table->index($teamForeignKey, 'model_has_permissions_team_foreign_key_index');
            });
        }

        // For roles
        if (Schema::hasTable($tableNames['roles']) && 
            Schema::hasColumn($tableNames['roles'], $teamForeignKey)) {
            
            DB::table($tableNames['roles'])
                ->whereNull($teamForeignKey)
                ->update([$teamForeignKey => 0]);

            DB::statement("
                CREATE TABLE {$tableNames['roles']}_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    {$teamForeignKey} INTEGER NULL,
                    name VARCHAR NOT NULL,
                    guard_name VARCHAR NOT NULL,
                    created_at DATETIME,
                    updated_at DATETIME,
                    UNIQUE({$teamForeignKey}, name, guard_name)
                )
            ");

            DB::statement("
                INSERT INTO {$tableNames['roles']}_new (id, {$teamForeignKey}, name, guard_name, created_at, updated_at)
                SELECT id, NULLIF({$teamForeignKey}, 0) as {$teamForeignKey}, name, guard_name, created_at, updated_at
                FROM {$tableNames['roles']}
            ");

            Schema::drop($tableNames['roles']);
            DB::statement("ALTER TABLE {$tableNames['roles']}_new RENAME TO {$tableNames['roles']}");

            Schema::table($tableNames['roles'], function (Blueprint $table) use ($teamForeignKey) {
                $table->index($teamForeignKey, 'roles_team_foreign_key_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is complex to reverse, so we'll leave it as is
    }
};
