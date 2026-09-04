<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Platform;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Src\Contexts\Identity\Presentation\Http\Middleware\EnforceImpersonationTimeout;
use Src\Contexts\Identity\Presentation\Http\Middleware\RequireTwoFactorAuthentication;
use Src\Contexts\Settings\Application\AppearanceResolver;
use Src\Contexts\Settings\Application\GeneralResolver;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\Theming\BrandPalette;
use Src\Support\Presentation\Filament\Navigation\NavigationGroup;
use Src\Support\Presentation\Filament\Notifications\TenantAwareDatabaseNotifications;
use Src\Support\Presentation\Http\Middleware\AssignRequestContext;
use Src\Support\Presentation\Http\Middleware\InitializeTenantContext;
use Src\Support\Presentation\Http\Middleware\SetLocale;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            // ── المصادقة الثنائية — Filament بيشحن نظامها بالكامل جاهز
            //    (`Filament\Auth\MultiFactor\App`)، فمفيش Fortify ولا شاشة
            //    مبنية بإيدينا. `pragmarx/google2fa` و`chillerlan/php-qrcode`
            //    متاحين خلاص كاعتماديات لـ filament/filament نفسه — صفر حزم
            //    جديدة. (docs/12 بند ١ — اتفحص السورس المُثبَّت، مش من الذاكرة)
            //
            // ⚠️ `isRequired: true` هنا **إلزامي** عشان صفحة/مسار الإعداد
            //    الإجباري يتسجّلوا أصلاً (`HasComponents`/`routes/web.php`
            //    بيشرطوهم بالعلم ده) — الفرض الفعلي حسب الدور والمهلة بيحصل
            //    في `RequireTwoFactorAuthentication` بدل الميدلوير الافتراضية.
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: true,
            )
            ->multiFactorAuthenticationRequiredMiddlewareName(RequireTwoFactorAuthentication::class)
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
            // ⚠️ الاسم مصدره GeneralSettings **العام** مش AppearanceResolver:
            //    المظهر بيتدهس لكل مستأجر، أما اسم التطبيق فإعداد واحد
            //    للتثبيت كله (docs/05 بند ٤ · ADR-021).
            ->brandName(fn (): string => app(GeneralResolver::class)->appName(
                app()->getLocale(),
                (string) config('app.fallback_locale'),
            ))
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
            // ── السايدبار (docs/07 بند ٢ و ٣)
            //
            // ⚠️ الشكل النصي للـ enum بالقصد: `docs/07` بند ٢ بيبني القايمة
            //    بـ `collect()->filter(fn () => Gate::allows(...))` — و
            //    `navigationGroups()` توقيعها `array|string` **مابتاخدش
            //    closure**، يعني الـ Gate كان هيتقيّم وقت تسجيل اللوحة،
            //    قبل ميدلوير المصادقة والمستأجر. نفس عيب ADR-015/ADR-021.
            //
            //    مفيش بوابة على مستوى المجموعة: العنصر بيحمي نفسه، و
            //    Filament بيشيل المجموعة الفاضية لوحده.
            ->navigationGroups(NavigationGroup::class)
            ->sidebarCollapsibleOnDesktop()
            // بنسيب الأيقونات ظاهرة لما نطوي — مش طيّ كامل
            ->sidebarFullyCollapsibleOnDesktop(false)
            ->collapsibleNavigationGroups()
            ->sidebarWidth(config('theme.sidebar.width'))
            ->collapsedSidebarWidth(config('theme.sidebar.collapsed_width'))
            ->maxContentWidth(Width::Full)
            ->unsavedChangesAlerts()
            // ── البحث الشامل (Ctrl+K) — docs/07 بند ٥
            ->globalSearch()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldSuffix(
                fn (): string => Platform::detect() === Platform::Mac ? '⌘K' : 'Ctrl+K',
            )
            ->globalSearchDebounce('400ms')
            // ── جرس الإشعارات (docs/09 بند ١)
            //
            // ⚠️ مكوّن مخصص مش الافتراضي: الأصل بيرجّع كل إشعارات
            //    المستخدم من غير تقييد مستأجر، فمستخدم في مؤسستين كان
            //    هيشوف الاتنين في الاتنين. (ADR-024)
            //
            // ⚠️ الاستطلاع كل ٣٠ ثانية مؤقت: أول ما البث يشتغل
            //    (Reverb — بره الشريحة دي) يبقى `null`.
            ->databaseNotifications(livewireComponent: TenantAwareDatabaseNotifications::class)
            ->databaseNotificationsPolling('30s')
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
            // شاشة «أجهزتي» الذاتية — docs/12 بند ٢
            ->discoverPages(
                in: base_path('src/Contexts/Identity/Presentation/Filament/Pages'),
                for: 'Src\Contexts\Identity\Presentation\Filament\Pages',
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
                // ⚠️ بدري بالقصد: بيولّد request_id قبل أي سطر لوج في الطلب.
                //    المستأجر والمستخدم مش بيتقروا هنا — `ContextProcessor`
                //    بيقراهم وقت كتابة السطر عشان يعدّوا بعد
                //    `InitializeTenantContext`. (docs/11 بند ٢)
                AssignRequestContext::class,
                // ⚠️ بعد StartSession — بيقرا من الجلسة. ومش في authMiddleware:
                //    الميدلوير ده على اللوحة كلها عشان صفحة الدخول نفسها
                //    تطلع باللغة الصح قبل ما يبقى فيه مستخدم. (docs/10 بند ٤)
                SetLocale::class,
            ], isPersistent: true)
            // ⚠️ الأول في الترتيب — كل ميدلوير بعده بيعتمد على سياق المستأجر.
            //    (docs/22 بند ٨)
            ->tenantMiddleware([
                InitializeTenantContext::class,
            ], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
                // فرض المدة القصوى للانتحال — سيرفري، على كل طلب. (docs/12 بند ٣ قاعدة ٥)
                EnforceImpersonationTimeout::class,
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
