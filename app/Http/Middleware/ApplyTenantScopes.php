<?php

namespace App\Http\Middleware;

use App\Models\Access;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyTenantScopes
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->getTenant($request);

        if ($tenant instanceof Access) {
            // Застосовуємо global scope для моделей, які мають team_id або access_id
            // Filament автоматично скопує Role через BelongsToTenant trait,
            // але для інших моделей (якщо вони будуть) потрібен додатковий scope

            // Приклад для майбутніх моделей (розкоментуйте коли буде потрібно):
            /*
            Brand::addGlobalScope(
                'tenant',
                fn (Builder $query) => $query->where('access_id', $tenant->id),
            );

            Product::addGlobalScope(
                'tenant',
                fn (Builder $query) => $query->where('access_id', $tenant->id),
            );
            */
        }

        return $next($request);
    }

    /**
     * Get current tenant from Filament or API request.
     */
    protected function getTenant(Request $request): ?Access
    {
        // Для Filament panel
        if (class_exists(Filament::class) && Filament::auth()->check()) {
            $tenant = Filament::getTenant();

            if ($tenant instanceof Access) {
                return $tenant;
            }
        }

        // Для API
        if ($request->is('api/*')) {
            /** @var \App\Models\User|null $user */
            $user = $request->user();

            if ($user !== null) {
                $teamId = $request->header('X-Access-ID') ?? $request->header('X-Tenant-ID');

                if ($teamId === null) {
                    $teamId = $request->query('access_id') ?? $request->query('tenant_id');
                }

                if ($teamId === null) {
                    $teamId = $user->getMainCompanyAccessId();
                } else {
                    $teamId = (int) $teamId;
                    $hasAccess = $user->accesses()
                        ->where('id', $teamId)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (! $hasAccess) {
                        $teamId = $user->getMainCompanyAccessId();
                    }
                }

                if ($teamId !== null) {
                    return Access::query()
                        ->where('id', $teamId)
                        ->whereNull('deleted_at')
                        ->first();
                }
            }
        }

        return null;
    }
}
