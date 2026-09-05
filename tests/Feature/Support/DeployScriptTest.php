<?php

declare(strict_types=1);

/**
 * سكربت النشر للإنتاج. (docs/14 بند ٣)
 *
 * ⚠️ فحص نصّي (سطر بسطر) مش تنفيذ فعلي للسكربت — نفس أسلوب
 *    SupervisorConfigTest بالظبط. تنفيذ deploy.sh حقيقي هيحاول يعمل
 *    down/migrate/git pull... إلخ على بيئة الاختبار، وده مش المقصود.
 */
function deployScriptLines(): array
{
    $path = base_path('deploy/deploy.sh');

    expect(file_exists($path))->toBeTrue();

    return array_map('trim', file($path));
}

/**
 * ترتيب أول سطر **تنفيذي** يحتوي $needle كـ substring — مش مطابقة كاملة
 * للسطر، وبيتخطى سطور التعليقات (#) عمداً. من غيره، ذِكر أمر زي
 * "php artisan up" داخل تعليق الشرح في أول الملف بيتلقّط قبل السطر
 * الحقيقي في آخره.
 */
function deployScriptLineIndex(array $lines, string $needle): int
{
    foreach ($lines as $index => $line) {
        if (str_starts_with($line, '#')) {
            continue;
        }

        if (str_contains($line, $needle)) {
            return $index;
        }
    }

    expect(false)->toBeTrue("السطر [{$needle}] مش موجود في deploy.sh");

    return -1;
}

it('deploy/deploy.sh موجود', function (): void {
    expect(file_exists(base_path('deploy/deploy.sh')))->toBeTrue();
});

it('shebang وset -euo pipefail موجودين', function (): void {
    $lines = deployScriptLines();

    expect($lines[0])->toBe('#!/usr/bin/env bash')
        ->and($lines)->toContain('set -euo pipefail');
});

it('كل أوامر النشر المطلوبة موجودة', function (): void {
    $lines = deployScriptLines();
    $script = implode("\n", $lines);

    expect($script)->toContain('php artisan down')
        ->and($script)->toContain('--secret="$DEPLOY_SECRET"')
        ->and($script)->toContain('git pull origin main')
        ->and($script)->toContain('composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist')
        ->and($script)->toContain('npm ci')
        ->and($script)->toContain('npm run build')
        ->and($script)->toContain('php artisan migrate --force')
        ->and($script)->toContain('php artisan authorization:sync')
        ->and($script)->toContain('php artisan optimize:clear')
        ->and($script)->toContain('php artisan config:cache')
        ->and($script)->toContain('php artisan route:cache')
        ->and($script)->toContain('php artisan view:cache')
        ->and($script)->toContain('php artisan event:cache')
        ->and($script)->toContain('php artisan filament:optimize')
        ->and($script)->toContain('php artisan icons:cache')
        ->and($script)->toContain('php artisan horizon:terminate')
        ->and($script)->toContain('php artisan queue:restart')
        ->and($script)->toContain('php artisan up');
});

it('مفيش أوامر خطيرة أو غير موثّقة — git checkout/reset/stash/push أو Docker/SSH', function (): void {
    // ⚠️ مش فحص "--force" عام: migrate --force مطلوبة وموثّقة بالنص —
    // الفحص هنا على أوامر git الهدّامة والبنية التحتية غير الموصوفة بس.
    $script = implode("\n", deployScriptLines());

    expect($script)->not->toContain('git checkout')
        ->and($script)->not->toContain('git reset')
        ->and($script)->not->toContain('git stash')
        ->and($script)->not->toContain('git push')
        ->and($script)->not->toContain('docker')
        ->and($script)->not->toContain('ssh ');
});

it('ترتيب المراحل الكبرى زي docs/14 بند ٣ بالظبط', function (): void {
    $lines = deployScriptLines();

    $maintenance = deployScriptLineIndex($lines, 'php artisan down');
    $pull = deployScriptLineIndex($lines, 'git pull origin main');
    $composer = deployScriptLineIndex($lines, 'composer install');
    $npmCi = deployScriptLineIndex($lines, 'npm ci');
    $npmBuild = deployScriptLineIndex($lines, 'npm run build');
    $migrate = deployScriptLineIndex($lines, 'php artisan migrate --force');
    $authSync = deployScriptLineIndex($lines, 'php artisan authorization:sync');
    $optimizeClear = deployScriptLineIndex($lines, 'php artisan optimize:clear');
    $configCache = deployScriptLineIndex($lines, 'php artisan config:cache');
    $horizonTerminate = deployScriptLineIndex($lines, 'php artisan horizon:terminate');
    $queueRestart = deployScriptLineIndex($lines, 'php artisan queue:restart');
    $up = deployScriptLineIndex($lines, 'php artisan up');

    expect($maintenance)->toBeLessThan($pull)
        ->and($pull)->toBeLessThan($composer)
        ->and($composer)->toBeLessThan($npmCi)
        ->and($npmCi)->toBeLessThan($npmBuild)
        ->and($npmBuild)->toBeLessThan($migrate)
        ->and($migrate)->toBeLessThan($authSync)
        ->and($authSync)->toBeLessThan($optimizeClear)
        ->and($optimizeClear)->toBeLessThan($configCache)
        ->and($configCache)->toBeLessThan($horizonTerminate)
        ->and($horizonTerminate)->toBeLessThan($queueRestart)
        ->and($queueRestart)->toBeLessThan($up);
});

it('أوامر بناء الكاش كلها قبل horizon:terminate', function (): void {
    $lines = deployScriptLines();

    $cacheCommands = [
        'php artisan config:cache',
        'php artisan route:cache',
        'php artisan view:cache',
        'php artisan event:cache',
        'php artisan filament:optimize',
        'php artisan icons:cache',
    ];

    $horizonTerminate = deployScriptLineIndex($lines, 'php artisan horizon:terminate');

    foreach ($cacheCommands as $command) {
        expect(deployScriptLineIndex($lines, $command))->toBeLessThan($horizonTerminate);
    }
});

it('php artisan up آخر نداء artisan في السكربت', function (): void {
    $lines = deployScriptLines();

    $artisanCommandLines = array_values(array_filter(
        $lines,
        fn (string $line): bool => str_starts_with($line, 'php artisan'),
    ));

    expect(end($artisanCommandLines))->toBe('php artisan up');
});
