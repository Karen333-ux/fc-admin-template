<?php

declare(strict_types=1);

use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\CreateUser;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\EditUser;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\ListUsers;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Models\Tenant;

/**
 * يحطّ اللوحة والمستخدم والمستأجر في السياق زي ما الميدلوير بيعمل في طلب حقيقي.
 *
 * الترتيب مهم: `Filament::setTenant()` بيبعت حدث `TenantSet` وبياخد المستخدم
 * الحالي، فلازم تسجيل الدخول يحصل **الأول**.
 */
function panelContext(Tenant $tenant, User $user): void
{
    Auth::login($user);

    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);

    actingWithinTenant($tenant);
}

// ────────────────────────────────────────────────────────────────
// و-١ — صفحة الإنشاء بالـ URL المباشر
// ────────────────────────────────────────────────────────────────

it('يمنع viewer من فتح صفحة الإنشاء بالـ URL المباشر', function (): void {
    // إخفاء الزرار تجربة استخدام — ده بيثبت إن الـ endpoint نفسه مقفول.
    $tenant = Tenant::factory()->create();
    $viewer = userWithRole('viewer', $tenant);

    $this->actingAs($viewer)
        ->get("/admin/{$tenant->slug}/users/create")
        ->assertForbidden();
});

it('يمنع editor من فتح صفحة الإنشاء بالـ URL المباشر', function (): void {
    $tenant = Tenant::factory()->create();
    $editor = userWithRole('editor', $tenant);

    $this->actingAs($editor)
        ->get("/admin/{$tenant->slug}/users/create")
        ->assertForbidden();
});

it('admin بيفتح صفحة الإنشاء عادي', function (): void {
    // الضابط: بيثبت إن الـ 403 فوق تفويض مش صفحة مكسورة
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    $this->actingAs($admin)
        ->get("/admin/{$tenant->slug}/users/create")
        ->assertSuccessful();
});

// ────────────────────────────────────────────────────────────────
// و-٢ — زرار الحذف
// ────────────────────────────────────────────────────────────────

it('يمنع من لا يملك التعديل من فتح صفحة التعديل أصلاً', function (string $role): void {
    // زرار الحذف عايش في هيدر صفحة التعديل. الدور اللي مامعهوش `update.users`
    // مابيوصلش للصفحة من أساسه — فالزرار مش «مخفي»، الصفحة كلها مقفولة.
    // ده أقوى من إخفاء الزرار، وهو السبب إن الاختبار اتكتب بالشكل ده.
    $tenant = Tenant::factory()->create();
    $actor = userWithRole($role, $tenant);

    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    $this->actingAs($actor)
        ->get("/admin/{$tenant->slug}/users/{$target->getRouteKey()}/edit")
        ->assertForbidden();
})->with(['editor', 'viewer']);

it('يظهر زرار الحذف لمن يملك الصلاحية', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    panelContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getRouteKey()])
        ->assertActionVisible(DeleteAction::class);
});

it('يخفي زرار الحذف عن المستخدم على نفسه', function (): void {
    // قاعدة سلامة مش صلاحية — حتى admin مابيشوفهاش على حسابه (ADR-005)
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    panelContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $admin->getRouteKey()])
        ->assertActionHidden(DeleteAction::class);
});

// ────────────────────────────────────────────────────────────────
// و-٣ — عدد الاستعلامات
// ────────────────────────────────────────────────────────────────

it('صفحة الجدول لا تتجاوز ١٠ استعلامات', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    User::factory()->count(15)->create()
        ->each(fn (User $u) => $u->tenants()->attach($tenant));

    panelContext($tenant, $admin);

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->assertOk();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect(count($queries))->toBeLessThanOrEqual(
        10,
        'استعلامات صفحة الجدول: '.count($queries)."\n".
        implode("\n", array_map(fn (array $q): string => $q['query'], $queries)),
    );
});

// ────────────────────────────────────────────────────────────────
// عبور حدود المستأجر على مستوى HTTP — ADR-005
// ────────────────────────────────────────────────────────────────

it('كل دور بياخد 404 على مستخدم مؤسسة تانية', function (string $role): void {
    // مش 403: «ممنوع» بتأكد إن السجل موجود، وده تسريب في حد ذاته.
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $actor = userWithRole($role, $a);

    $foreign = User::factory()->create();
    $foreign->tenants()->attach($b);

    $this->actingAs($actor)
        ->get("/admin/{$a->slug}/users/{$foreign->getRouteKey()}")
        ->assertNotFound();
})->with(['super_admin', 'admin', 'editor', 'viewer']);

// ────────────────────────────────────────────────────────────────
// إنشاء مستخدم — هل بيترّبط بالمستأجر الحالي؟
// ────────────────────────────────────────────────────────────────

it('صفحة الإنشاء بتحفظ فعلاً وكلمة المرور بتتهاش', function (): void {
    // `users.password` عمود NOT NULL — قبل ما نضيف حقل كلمة المرور، الصفحة
    // كانت بتفتح وبتفشل عند الحفظ بقيد قاعدة البيانات.
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    panelContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'مستخدم جديد',
            'email' => 'new.user@example.test',
            'password' => 'correct-horse-battery-staple',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::query()->where('email', 'new.user@example.test')->firstOrFail();

    expect($created->password)->not->toBe('correct-horse-battery-staple')
        ->and(Hash::check('correct-horse-battery-staple', $created->password))->toBeTrue();
});

it('المستخدم المتعمل من اللوحة بيترّبط بالمستأجر الحالي', function (): void {
    // القرار في ADR-016: مستخدم جديد من UserResource بيترّبط **تلقائياً**
    // بمستأجر الـ TenantContext الحالي. مفيش اختيار مؤسسة في الفورم ومفيش
    // معرّف مستأجر بييجي من الطلب.

    // ١. فيه مستأجر حالي — وكمان مستأجر تاني عشان نثبت إن مفيش ربط عشوائي
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    panelContext($tenant, $admin);

    expect(app(TenantContext::class)->id())->toBe($tenant->getKey());

    // ٢. الإنشاء من مسار Filament الحقيقي — مش User::create()
    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'مستخدم جديد',
            'email' => 'new.user2@example.test',
            'password' => 'correct-horse-battery-staple',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // ٣. المستخدم اتخزّن فعلاً
    $created = User::query()->where('email', 'new.user2@example.test')->firstOrFail();

    // ٤. فيه صف عضوية في tenant_user — بنفحص الجدول نفسه مش العلاقة بس
    $memberships = DB::table('tenant_user')
        ->where('user_id', $created->getKey())
        ->get();

    expect($memberships)->toHaveCount(1);

    // ٥. العضوية للمستأجر الحالي
    expect((int) $memberships->first()->tenant_id)->toBe($tenant->getKey())
        ->and($created->tenants()->pluck('tenants.id')->all())->toBe([$tenant->getKey()]);

    // ٦. ظاهر في استعلام المورد بتاع المستأجر الحالي
    expect(UserResource::getEloquentQuery()->pluck('id')->all())
        ->toContain($created->getKey());

    // ٧. مش مترّبط بالمستأجر التاني — لا في العضوية ولا في استعلامه
    expect($created->tenants()->pluck('tenants.id')->all())
        ->not->toContain($otherTenant->getKey());

    actingWithinTenant($otherTenant);

    expect(UserResource::getEloquentQuery()->pluck('id')->all())
        ->not->toContain($created->getKey());
});

it('صفحة الإنشاء بتشتغل جوّه معاملة قاعدة بيانات', function (): void {
    // معاملات Filament **مطفية افتراضياً** على مستوى اللوحة. لو الافتراضي ده
    // رجع، إنشاء المستخدم والعضوية بيبقوا عمليتين منفصلتين، وأي فشل بعد الحفظ
    // بيسيب مستخدم يتيم. الافتراضي الصامت ده بالظبط نوع الباگ اللي ADR-015
    // و ADR-016 اتكتبوا بسببه — فالاختبار بيقفله. (ADR-016)
    $property = new ReflectionProperty(CreateUser::class, 'hasDatabaseTransactions');

    expect($property->getDefaultValue())->toBeTrue();
});
