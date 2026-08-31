<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Models\Tenant;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

// الاختبارات المعمارية مش محتاجة قاعدة بيانات — بس محتاجة تطبيق مُقلِع
// عشان base_path() و config().
uses(TestCase::class)->in('Architecture');

/**
 * ⚠️ TenantContext singleton بحالة قابلة للتغيير — اختبار سايب سياق مضبوط
 * بيلوّث اللي بعده، والفشل بيظهر في اختبار تاني خالص. (docs/23 بند ٧-٢)
 */
uses()->beforeEach(function (): void {
    app(TenantContext::class)->forget();

    // الكتالوج مصدره config/authorization.php — الاختبارات بتزامنه بدل ما
    // تعرّف صلاحيات بإيدها، عشان تفضل متمسّكة بالكونفيج الحقيقي. (docs/02 بند ٣)
    Artisan::call('authorization:sync');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
})->in('Feature');

/**
 * مستخدم بدور داخل مستأجر.
 *
 * الأدوار معزولة بالـ teams — لازم نضبط الـ team id قبل الإسناد،
 * وإلا الدور بيتسند عالمياً وبيتسرّب بين المستأجرين. (docs/03 بند ٤)
 */
function userWithRole(string $role, ?Tenant $tenant = null): User
{
    $tenant ??= Tenant::factory()->create();

    $user = User::factory()->create();
    $user->tenants()->attach($tenant);

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->getKey());
    $user->assignRole($role);
    $registrar->forgetCachedPermissions();

    return $user->fresh();
}

/** يضبط سياق المستأجر (وبالتالي team id للصلاحيات) */
function actingWithinTenant(Tenant $tenant): void
{
    app(TenantContext::class)->set($tenant->getKey());
}

/** @return list<string> كل ملفات PHP بتاعتنا — من غير vendor */
function projectPhpFiles(): array
{
    $files = [];

    foreach (['src', 'app'] as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path($root), FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}
