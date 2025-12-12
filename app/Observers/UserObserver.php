<?php

namespace App\Observers;

use App\Models\Access;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class UserObserver
{
    public function creating(User $user): void
    {
        //
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $mainCompany = Company::query()
            ->where('name', 'Main')
            ->first();

        if ($mainCompany === null) {
            $mainCompany = Company::query()->create([
                'name' => 'Main',
            ]);
        }

        $access = $mainCompany->accessible()->create([
            'user_id' => $user->id,
        ]);

        // Визначаємо роль: super-admin для superAdmin, guest для інших
        $isSuperAdmin = $user->email === 'superadmin@example.com';
        $roleName = $isSuperAdmin ? 'super-admin' : 'guest';

        // Створюємо або отримуємо роль з team_id = access->id
        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
            'team_id' => $access->id,
        ]);

        // Призначаємо роль користувачу через Access (team_id)
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($access->id);
        $user->assignRole($role);
        $registrar->setPermissionsTeamId(null);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
