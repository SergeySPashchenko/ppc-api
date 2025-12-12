<?php

namespace Database\Seeders;

use App\Models\Access;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
        ]);
        $mainCompany = Company::query()
            ->where('name', 'Main')
            ->first();
        if ($mainCompany === null) {
            $mainCompany = Company::query()->create([
                'name' => 'Main',
            ]);
        }

        $access = $mainCompany->accessible()->create([
            'user_id' => $superAdmin->id,
        ]);

        // Призначаємо роль super-admin для superAdmin через Access (team_id)
        if ($access) {
            $superAdminRole = Role::query()->firstOrCreate([
                'name' => 'super-admin',
                'guard_name' => 'web',
                'team_id' => $access->id,
            ]);

            // Встановлюємо team_id для призначення ролі
            $registrar = app(PermissionRegistrar::class);
            $registrar->setPermissionsTeamId($access->id);
            $superAdmin->assignRole($superAdminRole);
            $registrar->setPermissionsTeamId(null);
        }

    }
}
