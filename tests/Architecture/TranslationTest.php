<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

/** @return list<string> ملفات الترجمة المشتركة + بتاعة السياقات */
function translationDirs(): array
{
    $dirs = [lang_path()];

    foreach (File::directories(base_path('src/Contexts')) as $context) {
        if (File::isDirectory($context.'/Lang')) {
            $dirs[] = $context.'/Lang';
        }
    }

    return $dirs;
}

// ────────────────────────────────────────────────────────────────
// تطابق المفاتيح — docs/10 بند ٨
// ────────────────────────────────────────────────────────────────

it('كل مفاتيح الترجمة العربية لها مقابل إنجليزي', function (): void {
    $checked = 0;

    foreach (translationDirs() as $dir) {
        foreach (File::files($dir.'/ar') as $file) {
            $enPath = $dir.'/en/'.$file->getFilename();

            expect(File::exists($enPath))
                ->toBeTrue("ناقص بالإنجليزي: {$file->getFilename()} في {$dir}");

            $arKeys = Arr::dot(require $file->getPathname());
            $enKeys = Arr::dot(require $enPath);

            $missing = array_diff_key($arKeys, $enKeys);

            expect($missing)->toBeEmpty(
                "مفاتيح ناقصة في en/{$file->getFilename()}: ".implode(', ', array_keys($missing))
            );

            $checked++;
        }
    }

    // حارس ضد الاختبار الفاضي: لو الاكتشاف اتكسر، الاختبار مايعديش بالسكوت
    expect($checked)->toBeGreaterThan(5);
});

it('كل مفاتيح الترجمة الإنجليزية لها مقابل عربي', function (): void {
    // الاتجاه التاني — مفتاح اتضاف بالإنجليزي بس بيفضل مخفي من غير ده
    foreach (translationDirs() as $dir) {
        foreach (File::files($dir.'/en') as $file) {
            $arPath = $dir.'/ar/'.$file->getFilename();

            expect(File::exists($arPath))
                ->toBeTrue("ناقص بالعربي: {$file->getFilename()} في {$dir}");

            $enKeys = Arr::dot(require $file->getPathname());
            $arKeys = Arr::dot(require $arPath);

            $missing = array_diff_key($enKeys, $arKeys);

            expect($missing)->toBeEmpty(
                "مفاتيح ناقصة في ar/{$file->getFilename()}: ".implode(', ', array_keys($missing))
            );
        }
    }
});

// ────────────────────────────────────────────────────────────────
// نصوص الواجهة — النطاق المصحّح في ADR-025
// ────────────────────────────────────────────────────────────────

it('مفيش نص عربي مكتوب في كود الواجهة', function (): void {
    // ⚠️ النطاق هنا **نصوص الواجهة بس** (ADR-025). الاستثناءات المشروعة:
    //    البيانات المزروعة في الهجرات، وفواصل العرض، ورسائل الاستثناءات
    //    للمطوّر. المعيار الأصلي في `docs/10` بند ٩ كان بيفحص كل حرف عربي
    //    في `src/` و`app/` — وده كان بيفشل على تلات أعراف قائمة.
    $violations = [];

    foreach (uiPhpFiles() as $path) {
        // التعليقات بتتشال الأول — المشروع كله معلّق بالعربي بالقصد
        $lines = explode("\n", stripPhpComments((string) file_get_contents($path)));

        foreach ($lines as $number => $line) {
            if (! preg_match("/'[^']*[\x{0600}-\x{06FF}]{3,}[^']*'/u", $line)) {
                continue;
            }

            if (isAllowedArabicLiteral($line, array_slice($lines, 0, $number), $path)) {
                continue;
            }

            $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path).':'.($number + 1);
        }
    }

    expect($violations)->toBeEmpty(
        'نص عربي في كود الواجهة بدل ملف ترجمة: '.implode(', ', $violations)
    );
});

it('مفيش نص عربي مكتوب مباشرة في قوالب Blade', function (): void {
    $violations = [];

    foreach (File::allFiles(resource_path('views')) as $file) {
        // ⚠️ التعليقات بتتشال الأول: تعليق Blade عربي بيتكلم عن `<head>`
        //    كان بيتحسب مخالفة — الرمز `>` جاي من التعليق نفسه.
        $contents = (string) preg_replace(
            ['/\{\{--.*?--\}\}/s', '/<!--.*?-->/s'],
            '',
            $file->getContents(),
        );

        // نص عربي بعد `>` يعني محتوى معروض مش داخل {{ }} ولا __()
        if (preg_match('/>\s*[\x{0600}-\x{06FF}]{3,}/u', $contents)) {
            $violations[] = $file->getRelativePathname();
        }
    }

    expect($violations)->toBeEmpty('نصوص عربية مكتوبة مباشرة في: '.implode(', ', $violations));
});

// ────────────────────────────────────────────────────────────────
// الاختبارات السلبية — بتثبت إن الفحص بيفرّق فعلاً (ADR-025)
// ────────────────────────────────────────────────────────────────

it('الفحص بيسمح بالبيانات المزروعة وفواصل العرض ورسائل المطوّر', function (): void {
    $allowed = [
        "        \$this->migrator->add('general.app_name', ['ar' => 'كود المستقبل']);",
        "                    ->separator('،')",
        "            throw new RuntimeException('صفحة الإنشاء اشتغلت على موديل غلط');",
    ];

    foreach ($allowed as $line) {
        expect(isAllowedArabicLiteral($line))->toBeTrue("اتحسب مخالفة بالغلط: {$line}");
    }
});

it('رسالة الاستثناء على سطر لوحدها بعد throw بتتقبل كمان', function (): void {
    // الشكل ده موجود فعلاً في `CreateUser` و`MediaUrlResolver` — فحص سطر
    // واحد كان بيفوّته لأن `throw new` على السطر اللي قبله.
    $previous = ['            throw new LogicException('];
    $line = "                'صفحة إنشاء المستخدم اشتغلت على موديل مش User: '";

    expect(isAllowedArabicLiteral($line, $previous))->toBeTrue()
        // ومن غير السياق بتبقى مخالفة — بيثبت إن السياق هو اللي بيفرق
        ->and(isAllowedArabicLiteral($line))->toBeFalse();
});

it('التعليقات العربية بتتشال قبل الفحص', function (): void {
    // ⚠️ المشروع كله معلّق بالعربي بالقصد. من غير الشيل ده الفحص بيفشل
    //    على كل ملف ويتشال بعد أسبوع.
    $code = "<?php\n// تعليق عربي فيه كلام كتير\n\$x = 1;\n";

    expect(stripPhpComments($code))->not->toContain('تعليق عربي')
        // والسطور مابتزحلقش — رقم السطر في رسالة المخالفة لازم يفضل صح
        ->and(substr_count(stripPhpComments($code), "\n"))->toBe(substr_count($code, "\n"));
});

it('الفحص بيمسك نص واجهة عربي حقيقي', function (): void {
    // ⚠️ من غير الاختبار ده، الاستثناءات ممكن تتوسّع لحد ما الفحص يبقى
    //    بيسمح بكل حاجة — يعدّي دايماً ومايحرسش حاجة.
    $violations = [
        "                TextInput::make('name')->label('الاسم'),",
        "            ->title('تم الحفظ بنجاح')",
        "        return 'المستخدمين';",
    ];

    foreach ($violations as $line) {
        expect(isAllowedArabicLiteral($line))->toBeFalse("عدّى وهو مخالفة: {$line}");
    }
});

// ────────────────────────────────────────────────────────────────
// الاتجاه — RTL و LTR
// ────────────────────────────────────────────────────────────────

it('الاتجاه بيتقلب مع اللغة', function (): void {
    // ⚠️ مفيش `->direction()` على الـ Panel — الاتجاه بييجي من ملف ترجمة
    //    Filament نفسه. (docs/10 بند ٥)
    expect(trans('filament-panels::layout.direction', [], 'ar'))->toBe('rtl')
        ->and(trans('filament-panels::layout.direction', [], 'en'))->toBe('ltr');
});

it('كل اللغات المدعومة ليها اتجاه معرّف', function (): void {
    foreach (config('app.supported_locales') as $locale) {
        $direction = trans('filament-panels::layout.direction', [], $locale);

        expect($direction)->toBeIn(['rtl', 'ltr'], "اتجاه غير معرّف للغة {$locale}");
    }
});

// ────────────────────────────────────────────────────────────────
// تغطية تسميات الصلاحيات
// ────────────────────────────────────────────────────────────────

it('كل صلاحية في الكتالوج ليها تسمية بالعربي والإنجليزي', function (): void {
    $missing = [];

    /** @var array<string, array{group: string, actions: ?array<int, string>, extra?: array<int, string>}> $resources */
    $resources = config('authorization.resources', []);

    foreach ($resources as $resource => $definition) {
        $actions = $definition['actions'] ?? config('authorization.default_actions');
        $actions = [...$actions, ...($definition['extra'] ?? [])];

        foreach ($actions as $action) {
            foreach (['ar', 'en'] as $locale) {
                if (trans("authorization.actions.{$action}", [], $locale) === "authorization.actions.{$action}") {
                    $missing[] = "{$locale}: actions.{$action}";
                }
            }
        }

        foreach (['ar', 'en'] as $locale) {
            if (trans("authorization.resources.{$resource}", [], $locale) === "authorization.resources.{$resource}") {
                $missing[] = "{$locale}: resources.{$resource}";
            }
        }
    }

    /** @var array<string, string> $pages */
    $pages = config('authorization.pages', []);

    // ⚠️ مفاتيح الصفحات فيها نقط (`access.panel.admin`)، و`trans()` بيقرا
    //    النقطة كتداخل — فالبحث بيفشل حتى والمفتاح موجود. بنقرا المصفوفة
    //    نفسها، وده نفس اللي `PermissionBuilder` بيعمله. (ADR-003)
    foreach (['ar', 'en'] as $locale) {
        /** @var array<string, string> $labels */
        $labels = trans('authorization.pages', [], $locale);

        foreach (array_keys($pages) as $page) {
            if (! is_array($labels) || ! array_key_exists($page, $labels)) {
                $missing[] = "{$locale}: pages.{$page}";
            }
        }
    }

    expect(array_unique($missing))->toBeEmpty('تسميات صلاحيات ناقصة: '.implode(', ', array_unique($missing)));
});
