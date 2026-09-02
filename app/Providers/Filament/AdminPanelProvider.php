<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Src\Contexts\Settings\Application\AppearanceResolver;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\Theming\BrandPalette;
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
            // ── الثيم: كل القيم دي بتتقرا من AppearanceSettings عبر
            //    AppearanceResolver (مع دهس المستأجر). مفيش قيمة مكرّرة هنا.
            //
            //    ⚠️ كلوجرات مش قيم ثابتة: Filament بيقيّمها وقت العرض
            //    (`evaluate()`)، يعني **بعد** ميدلوير المستأجر — فالقيمة
            //    بتطلع بتاعت المستأجر الحالي. وكمان بتمنع قراءة الإعدادات
            //    وقت تسجيل اللوحة، وده كان هيكسر `artisan` قبل ما هجرة
            //    الإعدادات تشتغل أصلاً.
            ->colors(fn (): array => $this->brandColors())
            ->font(
                fn (): string => app(AppearanceResolver::class)->fontFamily(),
                provider: GoogleFontProvider::class,
            )
            ->monoFont(config('theme.fonts.mono'), provider: GoogleFontProvider::class)
            ->brandLogo(fn (): ?string => $this->assetUrl(app(AppearanceResolver::class)->logoPath()))
            ->darkModeBrandLogo(fn (): ?string => $this->assetUrl(app(AppearanceResolver::class)->darkLogoPath()))
            ->brandLogoHeight(config('theme.brand_logo_height'))
            ->favicon(fn (): ?string => $this->assetUrl(app(AppearanceResolver::class)->faviconPath()))
            ->darkMode(fn (): bool => app(AppearanceResolver::class)->allowsThemeSwitch())
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('theme.brand-variables', [
                    'scale' => app(BrandPalette::class)->scaleFor(
                        app(AppearanceResolver::class)->primaryColor(),
                    ),
                ])->render(),
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): string => view('theme.footer', [
                    'text' => app(AppearanceResolver::class)->footerText(
                        app()->getLocale(),
                        config('app.fallback_locale'),
                    ),
                ])->render(),
            )
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

    /**
     * لوحة الألوان: العلامة من الإعدادات، وألوان الحالة ثابتة من الكونفيج.
     *
     * ألوان الحالة **مش** إعدادات — المستأجر مايغيّرهاش (docs/06 بند ٣)،
     * فمكانها كونفيج. اللون الوحيد اللي بيتغيّر هو العلامة.
     *
     * @return array<string, array<int|string, string>>
     */
    private function brandColors(): array
    {
        $palette = app(BrandPalette::class);

        $colors = [
            'primary' => $palette->scaleFor(app(AppearanceResolver::class)->primaryColor()),
        ];

        /** @var array<string, string> $status */
        $status = config('theme.status_colors', []);

        foreach ($status as $name => $hex) {
            $colors[$name] = $palette->scaleFor($hex);
        }

        return $colors;
    }

    /**
     * مسار أصل → رابط، أو `null` لو مفيش.
     *
     * ⚠️ رفع الملفات (Media Library / S3 / DiskResolver) **بره الشريحة دي**،
     * فالمسارات دي بتفضل `null` دلوقتي. لما يتعمل، حل الرابط هيعدّي على
     * `DiskResolver` بدل `asset()`. لحد ساعتها الاحتياطي آمن: `null` بيخلّي
     * Filament يعرض اسم العلامة النصي بدل اللوجو.
     */
    private function assetUrl(?string $path): ?string
    {
        return $path === null ? null : asset($path);
    }
}
