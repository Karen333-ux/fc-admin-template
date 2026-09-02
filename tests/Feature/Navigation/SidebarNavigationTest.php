<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup as FilamentNavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\NavigationManager;
use Illuminate\Support\Facades\Auth;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Presentation\Filament\Navigation\NavigationGroup;

/** نفس ترتيب طلب حقيقي: تسجيل دخول ← لوحة ← مستأجر */
function navContext(Tenant $tenant, User $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

/** @return array<int, FilamentNavigationGroup> */
function navigationFor(string $role, ?Tenant $tenant = null): array
{
    $tenant ??= Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    navContext($tenant, $user);

    return currentNavigation();
}

/**
 * ⚠️ `NavigationManager` مربوط بـ `scoped()` — يعني نسخة واحدة للطلب كله،
 * وبتفضل محتفظة بالقايمة اللي اتبنت لأول مستخدم. في اختبار بيقارن أدوار
 * جوّه نفس الطلب لازم نفضّيها، وإلا بنقيس نفس القايمة مرتين.
 *
 * @return array<int, FilamentNavigationGroup>
 */
function currentNavigation(): array
{
    app()->forgetInstance(NavigationManager::class);

    return Filament::getCurrentPanel()->getNavigation();
}

/** @return array<int, NavigationItem> */
function flattenItems(array $groups): array
{
    $items = [];

    foreach ($groups as $group) {
        foreach ($group->getItems() as $item) {
            $items[] = $item;
        }
    }

    return $items;
}

// ────────────────────────────────────────────────────────────────
// المجموعات الفاضية — قرار المراجعة بند ٤
// ────────────────────────────────────────────────────────────────

it('مفيش مجموعة فاضية بتتعرض للمستخدم', function (): void {
    // ⚠️ الـ enum فيه ٧ مجموعات، لكن التثبيت الحالي فيه عناصر في
    // Identity و System بس. Content/Operations/Reports/Tenancy لسه
    // مافيهاش أي مورد — وعنصر ميت بيخالف مبدأ ٥ في docs/07.
    //
    // Filament بيشيلها لوحده (`filter(fn ($g) => filled($g->getItems()))`
    // في NavigationManager) — الاختبار ده بيثبت السلوك ده بدل ما نفترضه.
    $groups = navigationFor('super_admin');

    foreach ($groups as $group) {
        expect($group->getItems())->not->toBeEmpty(
            'المجموعة «'.($group->getLabel() ?? '—').'» ظاهرة وهي فاضية',
        );
    }
});

it('المجموعات اللي مالهاش موارد لسه مش ظاهرة', function (): void {
    $labels = array_map(
        fn (FilamentNavigationGroup $group): ?string => $group->getLabel(),
        navigationFor('super_admin'),
    );

    foreach ([NavigationGroup::Content, NavigationGroup::Operations,
        NavigationGroup::Reports, NavigationGroup::Tenancy] as $empty) {
        expect($labels)->not->toContain($empty->getLabel());
    }
});

// ────────────────────────────────────────────────────────────────
// العدد والترتيب
// ────────────────────────────────────────────────────────────────

it('عدد المجموعات الظاهرة ≤ ٧ لأي دور', function (string $role): void {
    // معيار قبول docs/07 بند ١٠ — مبدأ ٧±٢
    expect(count(navigationFor($role)))->toBeLessThanOrEqual(7);
})->with(['super_admin', 'admin', 'editor', 'viewer']);

it('الترتيب بييجي من ترتيب تعريف حالات الـ enum', function (): void {
    // ⚠️ `NavigationManager` بيرتّب بـ array_search($case, $case::cases())،
    // يعني دالة sort() في docs/07 بند ٢ مابتتقريش. الترتيب = ترتيب التعريف.
    $labels = array_values(array_filter(array_map(
        fn (FilamentNavigationGroup $group): ?string => $group->getLabel(),
        navigationFor('super_admin'),
    )));

    $identity = array_search(NavigationGroup::Identity->getLabel(), $labels, true);
    $system = array_search(NavigationGroup::System->getLabel(), $labels, true);

    expect($identity)->not->toBeFalse()
        ->and($system)->not->toBeFalse()
        // Identity معرّفة قبل System في الـ enum
        ->and($identity)->toBeLessThan($system);
});

it('صفحات الإعدادات مرتّبة جوّه مجموعة النظام', function (): void {
    $groups = navigationFor('super_admin');

    $system = null;

    foreach ($groups as $group) {
        if ($group->getLabel() === NavigationGroup::System->getLabel()) {
            $system = $group;
        }
    }

    expect($system)->not->toBeNull();

    // getItems() توقيعها `array | Arrayable` — بترجّع Collection هنا
    $sorts = collect($system->getItems())
        ->map(fn (NavigationItem $item): int => $item->getSort())
        ->values()
        ->all();
    $sorted = $sorts;
    sort($sorted);

    expect($sorts)->toBe($sorted)
        ->and(count($sorts))->toBe(4);
});

// ────────────────────────────────────────────────────────────────
// مفيش عنصر ميت — معيار قبول docs/07 بند ١٠
// ────────────────────────────────────────────────────────────────

it('كل عنصر ظاهر المستخدم يقدر يفتحه فعلاً', function (string $role): void {
    // ⚠️ الاختبار ده بيمشي على **كل** لينك في السايدبار وبيفتحه بالـ HTTP.
    // أي عنصر ظاهر وبيرجّع 403 معناه إن الإخفاء والتفويض مختلفين.
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    navContext($tenant, $user);

    $items = flattenItems(currentNavigation());

    expect($items)->not->toBeEmpty("الدور {$role} مالوش أي عنصر");

    foreach ($items as $item) {
        $url = $item->getUrl();

        if (blank($url)) {
            continue;
        }

        $this->actingAs($user)
            ->get($url)
            ->assertSuccessful();
    }
})->with(['super_admin', 'admin', 'editor', 'viewer']);

it('الدور الأقل بيشوف عناصر أقل من الدور الأعلى', function (): void {
    // بيثبت إن القايمة بتتبع التفويض فعلاً مش ثابتة للكل
    $adminItems = count(flattenItems(navigationFor('admin')));
    $viewerItems = count(flattenItems(navigationFor('viewer')));

    expect($viewerItems)->toBeLessThan($adminItems);
});

it('صفحات الإعدادات مابتظهرش لمن لا يملك صلاحياتها', function (): void {
    $labels = array_map(
        fn (NavigationItem $item): string => $item->getLabel(),
        flattenItems(navigationFor('viewer')),
    );

    expect($labels)->not->toContain(__('settings::settings.pages.mail'))
        ->and($labels)->not->toContain(__('settings::settings.pages.storage'));
});

// ────────────────────────────────────────────────────────────────
// الترجمة — RTL و LTR
// ────────────────────────────────────────────────────────────────

it('أسماء المجموعات مترجمة في اللغتين ومختلفة', function (): void {
    foreach (NavigationGroup::cases() as $case) {
        $key = 'navigation.groups.'.$case->key();

        expect(trans($key, [], 'ar'))->not->toBe($key)
            ->and(trans($key, [], 'en'))->not->toBe($key);
    }

    // عيّنة بتفرق فعلاً بين اللغتين
    expect(trans('navigation.groups.identity', [], 'ar'))
        ->not->toBe(trans('navigation.groups.identity', [], 'en'));
});

it('اسم المجموعة بيتبع لغة الطلب', function (): void {
    app()->setLocale('en');
    expect(NavigationGroup::System->getLabel())->toBe('System');

    app()->setLocale('ar');
    expect(NavigationGroup::System->getLabel())->toBe('النظام');
});

it('كل مجموعة عندها أيقونة وقابلة للطيّ', function (): void {
    foreach (NavigationGroup::cases() as $case) {
        expect($case->getIcon())->not->toBeNull()
            ->and($case->isCollapsible())->toBeTrue()
            // مفيش مجموعة بتبدأ مطويّة
            ->and($case->isCollapsed())->toBeFalse();
    }
});

// ────────────────────────────────────────────────────────────────
// إعدادات السايدبار
// ────────────────────────────────────────────────────────────────

it('السايدبار متظبّط زي docs/07 بند ٣ — من غير spa', function (): void {
    Filament::setCurrentPanel('admin');
    $panel = Filament::getCurrentPanel();

    expect($panel->isSidebarCollapsibleOnDesktop())->toBeTrue()
        // الطيّ الكامل مقفول: بنسيب الأيقونات ظاهرة
        ->and($panel->isSidebarFullyCollapsibleOnDesktop())->toBeFalse()
        ->and($panel->hasCollapsibleNavigationGroups())->toBeTrue()
        ->and($panel->getSidebarWidth())->toBe(config('theme.sidebar.width'))
        ->and($panel->getCollapsedSidebarWidth())->toBe(config('theme.sidebar.collapsed_width'))
        // ⚠️ spa() مؤجّل عن قصد — بيتعارض مع render hook بتاع الثيم في HEAD
        ->and($panel->hasSpaMode())->toBeFalse();
});

it('البحث الشامل مفعّل ومربوط بـ Ctrl+K', function (): void {
    Filament::setCurrentPanel('admin');
    $panel = Filament::getCurrentPanel();

    expect($panel->getGlobalSearchProvider())->not->toBeNull()
        ->and($panel->getGlobalSearchKeyBindings())->toBe(['command+k', 'ctrl+k'])
        ->and($panel->getGlobalSearchDebounce())->toBe('400ms');
});
