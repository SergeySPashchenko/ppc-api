<?php

namespace App\Http\Middleware;

use App\Models\AllBrandsTenant;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveAllBrandsTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if the route parameter is "all"
        $tenant = $request->route('tenant');

        if ($tenant === 'all') {
            // Set the AllBrandsTenant as the current tenant
            Filament::setTenant(new AllBrandsTenant);
        }

        return $next($request);
    }
}
