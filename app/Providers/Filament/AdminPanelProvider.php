<?php

namespace App\Providers\Filament;

use App\Http\Middleware\ApplyTenantScopes;
use App\Http\Middleware\SetPermissionsTeamId;
use App\Models\Access;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->tenant(Access::class, ownershipRelationship: 'access')
            ->tenantMenu(static function (Panel $panel): bool {
                // Показуємо меню tenant тільки якщо у користувача більше одного доступу
                $user = $panel->auth()->user();

                return $user?->hasMultipleTenants() ?? false;
            })
            ->tenantMenuItems([
                // Додаємо можливість повернутися на верхній рівень (Main Company)
                Action::make('backToMain')
                    ->label('Повернутися до Main')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->visible(static function (): bool {
                        $tenant = Filament::getTenant();

                        if ($tenant === null || ! $tenant instanceof Access) {
                            return false;
                        }

                        // Показуємо тільки якщо поточний tenant не є Main Company
                        /** @var \App\Models\User|null $user */
                        $user = Filament::auth()->user();
                        $mainCompany = $user?->company();

                        return $mainCompany !== null && $tenant->accessible_id !== $mainCompany->id;
                    })
                    ->url(static function (): string {
                        /** @var \App\Models\User|null $user */
                        $user = Filament::auth()->user();
                        $mainAccess = $user?->getDefaultTenant(Filament::getPanel());

                        if ($mainAccess === null) {
                            return Filament::getPanel()->getUrl();
                        }

                        return Filament::getPanel()->getUrl($mainAccess);
                    }),
            ])
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->tenantMiddleware([
                SetPermissionsTeamId::class,
                ApplyTenantScopes::class,
            ], isPersistent: true);
    }
}
