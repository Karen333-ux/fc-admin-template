<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource;
use Src\Support\Domain\Models\Tenant;

/** نفس ترتيب طلب حقيقي: تسجيل دخول ← لوحة ← مستأجر */
function searchContext(Tenant $tenant, User $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

/** عضو باسم معروف داخل مستأجر */
function memberNamed(Tenant $tenant, string $name): User
{
    $user = User::factory()->create(['name' => $name]);
    $user->tenants()->attach($tenant);

    return $user;
}

// ────────────────────────────────────────────────────────────────
// الاختبار السلبي — الحد الأمني للشريحة
// ────────────────────────────────────────────────────────────────

it('مستأجر ب مش بيلاقي مستخدم مستأجر أ في البحث الشامل', function (): void {
    // ⚠️⚠️ الاختبار ده هو **سبب وجود البحث الشامل في المراجعة**.
    //
    // `User` مالوش `tenant_id` ومالوش global scope (ADR-002/ADR-016)، فالعزل
    // الوحيد هو الفلتر اليدوي في `UserResource::getEloquentQuery()`. البحث
    // الشامل بيعدّي على نفس الاستعلام عن طريق `parent::getGlobalSearchEloquentQuery()`.
    //
    // لو حد كتب `User::query()` هناك، البحث بيبقى نافذة على مستخدمين كل
    // المستأجرين — والاختبار ده هو اللي بيمسكها.
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    memberNamed($tenantA, 'سرّية جداً');

    $intruder = userWithRole('admin', $tenantB);
    searchContext($tenantB, $intruder);

    expect(UserResource::getGlobalSearchResults('سرّية'))->toBeEmpty();
});

it('البحث بالبريد كمان معزول بين المستأجرين', function (): void {
    // نفس الحد من المدخل التاني القابل للبحث
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $foreign = memberNamed($tenantA, 'صاحب البريد');

    $intruder = userWithRole('admin', $tenantB);
    searchContext($tenantB, $intruder);

    expect(UserResource::getGlobalSearchResults($foreign->email))->toBeEmpty();
});

it('استعلام البحث الشامل هو نفسه استعلام المورد المفلتر', function (): void {
    // العقد نفسه: نفس الـ SQL، وفيه شرط العضوية
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    searchContext($tenant, $admin);

    $resourceSql = UserResource::getEloquentQuery()->toSql();
    $searchSql = UserResource::getGlobalSearchEloquentQuery()->toSql();

    expect($searchSql)->toBe($resourceSql)
        ->and($searchSql)->toContain('exists');
});

// ────────────────────────────────────────────────────────────────
// الحالة الإيجابية — البحث شغّال جوّه المستأجر
// ────────────────────────────────────────────────────────────────

it('المستخدم بيلاقي أعضاء مستأجره', function (): void {
    $tenant = Tenant::factory()->create();
    memberNamed($tenant, 'زميل ظاهر');

    $admin = userWithRole('admin', $tenant);
    searchContext($tenant, $admin);

    $results = UserResource::getGlobalSearchResults('زميل');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('زميل ظاهر');
});

it('نتيجة البحث بتوصّل لصفحة التعديل', function (): void {
    $tenant = Tenant::factory()->create();
    $member = memberNamed($tenant, 'هدف التعديل');

    $admin = userWithRole('admin', $tenant);
    searchContext($tenant, $admin);

    expect(UserResource::getGlobalSearchResultUrl($member))
        ->toBe(UserResource::getUrl('edit', ['record' => $member]));
});

// ────────────────────────────────────────────────────────────────
// التفويض — البحث مش باب خلفي
// ────────────────────────────────────────────────────────────────

it('من مالوش صلاحية عرض المستخدمين مابيقدرش يبحث فيهم', function (): void {
    // `canGloballySearch()` بينتهي بـ `&& static::canAccess()` في Filament،
    // يعني المورد كله بيتحجب عن البحث — مش بيرجّع نتايج فاضية بس.
    //
    // كل الأدوار الافتراضية عندها `view_any.users`، فالحالة السلبية الحقيقية
    // هي مستخدم من غير أي دور.
    $tenant = Tenant::factory()->create();

    $roleless = User::factory()->create();
    $roleless->tenants()->attach($tenant);

    searchContext($tenant, $roleless);

    expect(UserResource::canGloballySearch())->toBeFalse();
});

it('من معاه الصلاحية بيقدر يبحث', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    searchContext($tenant, $admin);

    expect(UserResource::canGloballySearch())->toBeTrue();
});

it('الأعمدة القابلة للبحث موجودة فعلاً في جدول users', function (): void {
    // ⚠️ `docs/07` بند ٥ بيحط `phone` — العمود ده مش موجود في الهجرة، وكان
    // هيرمي خطأ SQL أول بحث. الاختبار ده بيمنع رجوعه.
    foreach (UserResource::getGloballySearchableAttributes() as $column) {
        expect(Schema::hasColumn('users', $column))->toBeTrue("العمود {$column}");
    }
});
