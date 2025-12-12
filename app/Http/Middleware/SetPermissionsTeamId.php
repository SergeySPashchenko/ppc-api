<?php

namespace App\Http\Middleware;

use App\Models\Access;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetPermissionsTeamId
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $teamId = null;

        // Для Filament panel - отримуємо tenant з Filament
        if (class_exists(Filament::class) && Filament::auth()->check()) {
            $tenant = Filament::getTenant();

            if ($tenant instanceof Access) {
                $teamId = $tenant->id;
            }
        }

        // Для API - отримуємо team_id з заголовка або query параметра
        if ($teamId === null && $request->is('api/*')) {
            /** @var \App\Models\User|null $user */
            $user = $request->user();

            if ($user !== null) {
                // Спробуємо отримати з заголовка X-Access-ID або X-Tenant-ID
                $teamId = $request->header('X-Access-ID') ?? $request->header('X-Tenant-ID');

                // Якщо немає в заголовку, спробуємо з query параметра
                if ($teamId === null) {
                    $teamId = $request->query('access_id') ?? $request->query('tenant_id');
                }

                // Якщо все ще немає, використовуємо default tenant (Main Company)
                if ($teamId === null) {
                    $teamId = $user->getMainCompanyAccessId();
                } else {
                    // Перевіряємо, чи користувач має доступ до цього tenant
                    $teamId = (int) $teamId;
                    $hasAccess = $user->accesses()
                        ->where('id', $teamId)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (! $hasAccess) {
                        // Якщо немає доступу, використовуємо default tenant
                        $teamId = $user->getMainCompanyAccessId();
                    }
                }
            }
        }

        // Встановлюємо team_id для Spatie Permission
        if ($teamId !== null) {
            $registrar = app(PermissionRegistrar::class);
            $registrar->setPermissionsTeamId($teamId);
        }

        return $next($request);
    }
}
