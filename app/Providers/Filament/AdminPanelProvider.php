<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Presentation\Http\Middleware\InitializeTenantContext;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            // تعدد المستأجرين — العضوية عبر tenant_user (ADR-002)
            ->tenant(Tenant::class, slugAttribute: 'slug')
            ->discoverResources(
                in: base_path('src/Contexts/Identity/Presentation/Filament/Resources'),
                for: 'Src\Contexts\Identity\Presentation\Filament\Resources',
            )
            ->discoverPages(
                in: base_path('src/Contexts/Settings/Presentation/Filament/Pages'),
                for: 'Src\Contexts\Settings\Presentation\Filament\Pages',
            )
            ->pages([
                Dashboard::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ], isPersistent: true)
            // ⚠️ الأول في الترتيب — كل ميدلوير بعده بيعتمد على سياق المستأجر.
            //    (docs/22 بند ٨)
            ->tenantMiddleware([
                InitializeTenantContext::class,
            ], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
