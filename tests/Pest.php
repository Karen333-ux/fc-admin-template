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

/**
 * ملفات PHP اللي ممكن تحتوي نص واجهة — نطاق فحص ADR-025.
 *
 * ⚠️ المستثنى **بحكم النطاق** مش بحكم استثناء:
 *   • `Lang/` — دي **مكان** الترجمة مش مخالفة ليها
 *   • `app/Console/` — أوامر CLI مش «واجهة». ADR-025 بيحصر الفحص في
 *     «نصوص الواجهة/القوالب/كود الواجهة»، والكونسول بره ده. النصوص دي
 *     لسه غير مترجمة وده مسجّل كبند مفتوح.
 *
 * @return list<string>
 */
function uiPhpFiles(): array
{
    return array_values(array_filter(
        projectPhpFiles(),
        static function (string $path): bool {
            $normalised = strtr($path, DIRECTORY_SEPARATOR, '/');

            return ! str_contains($normalised, '/Lang/')
                && ! str_contains($normalised, '/app/Console/');
        },
    ));
}

/**
 * بيشيل التعليقات من كود PHP قبل الفحص.
 *
 * ⚠️ التعليقات العربية موجودة في كل ملف في المشروع بالقصد — لو الفحص
 *    شافها هيفشل على كل حاجة ويتشال بعد أسبوع.
 */
function stripPhpComments(string $code): string
{
    $output = '';

    foreach (token_get_all($code) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            // بنسيب سطور فاضية مكانها عشان أرقام السطور ماتزحلقش
            $output .= str_repeat("\n", substr_count($token[1], "\n"));

            continue;
        }

        $output .= is_array($token) ? $token[1] : $token;
    }

    return $output;
}

/**
 * هل النص العربي في السطر ده استثناء مشروع؟ (ADR-025)
 *
 * التلات استثناءات المسجّلة في الـ ADR:
 *   ١. بيانات مزروعة في الهجرات — قيمة افتراضية مترجمة بتتخزّن في DB
 *   ٢. فواصل وعلامات ترقيم للعرض — حرف تنسيق مش جملة
 *   ٣. رسائل استثناءات للمطوّر — `DomainException` بيقول صراحةً إنها مش من __()
 *
 * ⚠️ الفحص بياخد **سياق** السطور اللي قبله: رسالة الاستثناء ساعات بتكون
 *    على سطر لوحدها بعد `throw new Foo(` — فحص سطر واحد بيفوّتها.
 *
 * @param  list<string>  $previousLines
 */
function isAllowedArabicLiteral(string $line, array $previousLines = [], string $path = ''): bool
{
    $normalisedPath = strtr($path, DIRECTORY_SEPARATOR, '/');

    // ٣-أ. كلاسات الاستثناءات نفسها — كل رسايلها للمطوّر
    if (str_contains($normalisedPath, '/Domain/Exceptions/')) {
        return true;
    }

    // ١. بيانات مزروعة
    if (str_contains($line, 'migrator->add(') || str_contains($line, "'ar' =>")) {
        return true;
    }

    // ٢. فواصل وعلامات ترقيم للعرض
    if (preg_match("/->separator\('[^']+'\)/u", $line)) {
        return true;
    }

    // ٣-ب. رسالة استثناء — على السطر ده أو في تعبير `throw` بدأ قبله
    $context = implode("\n", [...array_slice($previousLines, -3), $line]);

    if (preg_match('/\b(throw new|Exception\(|->setModel|report\()/', $context)) {
        return true;
    }

    return false;
}
