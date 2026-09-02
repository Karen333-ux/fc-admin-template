<?php

declare(strict_types=1);
use Illuminate\Auth\Access\Response;
use Src\Contexts\Identity\Infrastructure\Policies\UserPolicy;

/**
 * القواعد دي بتتفرض على الكود كنص، مش بالتحليل الساكن — عشان تمسك
 * أي استخدام حتى لو جوه string أو تعليق منسوخ. (docs/19 بند ٩)
 */

/** @return list<string> */
function filesContaining(string $needle, array $allowedBasenames = []): array
{
    $offenders = [];

    foreach (projectPhpFiles() as $file) {
        if (in_array(basename($file), $allowedBasenames, true)) {
            continue;
        }

        if (str_contains((string) file_get_contents($file), $needle)) {
            $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
        }
    }

    return $offenders;
}

/** @return list<string> قوالب Blade بتاعتنا — من غير vendor */
function bladeFiles(): array
{
    $files = [];
    $roots = array_filter(
        [base_path('resources/views'), base_path('src')],
        fn (string $dir): bool => is_dir($dir),
    );

    foreach ($roots as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

it('صفر hasPermissionTo خارج Decision و AuthorizationServiceProvider', function (): void {
    // المكانين الوحيدين المسموح فيهم (CLAUDE.md بند ٢)
    expect(filesContaining('hasPermissionTo(', [
        'Decision.php',
        'AuthorizationServiceProvider.php',
    ]))->toBeEmpty();
});

it('صفر hasRole خارج AuthorizationServiceProvider', function (): void {
    // Gate::before بيسأل عن الدور الخارق — ده الاستخدام الوحيد هنا
    expect(filesContaining('hasRole(', [
        'AuthorizationServiceProvider.php',
    ]))->toBeEmpty();
});

it('صفر skipAuthorization في أي مكان', function (): void {
    expect(filesContaining('skipAuthorization('))->toBeEmpty();
});

it('صفر can() باسم صلاحية بدل قدرة', function (): void {
    $offenders = [];

    foreach (projectPhpFiles() as $file) {
        $contents = (string) file_get_contents($file);

        // ->can('view_any.users')  ❌  — ده اسم صلاحية، مش قدرة
        if (preg_match("/->can\(\s*'[a-z_]+\.[a-z_.]+'\s*\)/", $contents) === 1) {
            $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
        }
    }

    expect($offenders)->toBeEmpty();
});

it('كل دالة Policy بتاخد سجل بتستخدم decideFor', function (): void {
    // ADR-007: decide() بتاخد كلاس أو ولا حاجة. decideFor() بتاخد سجل.
    $policyFiles = array_filter(
        projectPhpFiles(),
        fn (string $file): bool => str_ends_with($file, 'Policy.php')
            && ! str_ends_with($file, DIRECTORY_SEPARATOR.'Policy.php'),
    );

    expect($policyFiles)->not->toBeEmpty();

    $inspected = 0;

    foreach ($policyFiles as $file) {
        $contents = (string) file_get_contents($file);

        preg_match_all(
            '/public function (\w+)\([^)]*\$\w+,\s*\??[\w\\\\]+\s+\$\w+[^)]*\)[^{]*\{(.*?)\n    \}/s',
            $contents,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as [, $method, $body]) {
            $inspected++;

            // ⚠️ `toContain()` بتاخد needles بس — مافيش معامل رسالة.
            // الشكل القديم كان بيبعت الرسالة كـ needle تانية لازم تتلاقى
            // في الجسم، وده مستحيل — فالتأكيد كان بيفشل لأي دالة يلاقيها.
            // الـ pattern المكسور كان بيخفي ده لأنه مالقاش ولا دالة أصلاً.
            expect(str_contains($body, 'decideFor('))->toBeTrue(
                "الدالة {$method} في ".basename($file).' بتاخد سجل ومابتستخدمش decideFor()',
            );
        }
    }

    // ⚠️ حارس ضد الاختبار الفاضي.
    // الـ pattern ده اتكسر مرة: في نص PHP بين علامتين مفردتين، `\\` بتتحوّل لـ `\`،
    // فـ `[\w\\]` المكتوبة في الملف بتوصل لـ PCRE كـ `[\w\]` —
    // و`\]` بتهرّب القوس، فالـ character class مابيتقفلش والـ pattern مابيطابقش
    // حاجة — والاختبار كان بيعدّي وهو مابيفحصش ولا دالة. من غير التأكيد ده،
    // أي كسر في الـ pattern بيرجّع الاختبار فاضي بصمت تاني.
    expect($inspected)->toBeGreaterThan(0, 'الـ pattern مالقاش أي دالة Policy بتاخد سجل');
});

it('صفر @can باسم صلاحية في Blade', function (): void {
    // docs/23 بند ٦-أ — نفس قاعدة can('x.y') بس في القوالب
    $offenders = [];

    foreach (bladeFiles() as $file) {
        if (preg_match("/@can\(\s*'[a-z_]+\.[a-z_.]+'\s*\)/", (string) file_get_contents($file)) === 1) {
            $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
        }
    }

    expect($offenders)->toBeEmpty();
});

it('كل دالة عامة في Policy بترجّع Response مش bool', function (): void {
    // docs/19 بند ٣ — bool بيضيّع سبب الرفض
    $policies = [UserPolicy::class];

    foreach ($policies as $policy) {
        $reflection = new ReflectionClass($policy);

        $methods = array_filter(
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
            fn (ReflectionMethod $m): bool => $m->getDeclaringClass()->getName() === $policy
                && ! $m->isStatic()
                && ! in_array($m->getName(), ['invariants', 'isInvariant', 'permissionFor'], true),
        );

        expect($methods)->not->toBeEmpty();

        foreach ($methods as $method) {
            expect((string) $method->getReturnType())->toBe(
                Response::class,
                "{$policy}::{$method->getName()}() مابترجّعش Response",
            );
        }
    }
});

it('صفر CSS اتجاهي في قوالبنا', function (): void {
    // docs/18 — start/end مش left/right، عشان العربي
    $offenders = [];

    foreach (bladeFiles() as $file) {
        if (preg_match('/\b(pl-|pr-|ml-|mr-|text-left|text-right)/', (string) file_get_contents($file)) === 1) {
            $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
        }
    }

    expect($offenders)->toBeEmpty();
});

it('صفر ألوان hex في كود PHP', function (): void {
    // الألوان مصدرها الثيم/الكونفيج — مش مكتوبة في الكود
    $offenders = [];

    foreach (projectPhpFiles() as $file) {
        // استثناء موثّق وضيّق: هجرتين بس بيزرعوا **قيمة بيانات** افتراضية
        // للون العلامة — مش لون مكتوب في واجهة. أي ملف تاني فيه hex بيفشل.
        $seedsBrandColourDefault = str_contains($file, 'create_tenants_table')
            || str_contains($file, 'create_appearance_settings');

        if ($seedsBrandColourDefault) {
            continue;
        }

        if (preg_match('/#[0-9A-Fa-f]{6}\b/', (string) file_get_contents($file)) === 1) {
            $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
        }
    }

    expect($offenders)->toBeEmpty();
});
