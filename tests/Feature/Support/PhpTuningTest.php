<?php

declare(strict_types=1);

/**
 * قوالب PHP-FPM وOPcache للإنتاج. (docs/14 بند ٥)
 *
 * ⚠️ فحص نصّي بس — نفس أسلوب SupervisorConfigTest وDeployScriptTest.
 *    مفيش تشغيل فعلي لـ FPM ولا تحميل الملفات في عملية PHP حقيقية.
 */
function phpTuningFileContents(string $relativePath): string
{
    $path = base_path($relativePath);

    expect(file_exists($path))->toBeTrue();

    return (string) file_get_contents($path);
}

it('deploy/php/www.conf موجود وفيه قيم pool الإنتاج', function (): void {
    $contents = phpTuningFileContents('deploy/php/www.conf');

    expect($contents)->toContain('[www]')
        ->and($contents)->toContain('pm = dynamic')
        ->and($contents)->toContain('pm.max_children = 40')
        ->and($contents)->toContain('pm.start_servers = 8')
        ->and($contents)->toContain('pm.min_spare_servers = 4')
        ->and($contents)->toContain('pm.max_spare_servers = 12')
        ->and($contents)->toContain('pm.max_requests = 500');
});

it('deploy/php/opcache.ini موجود وفيه قيم الإنتاج', function (): void {
    $contents = phpTuningFileContents('deploy/php/opcache.ini');

    expect($contents)->toContain('opcache.enable=1')
        ->and($contents)->toContain('opcache.memory_consumption=256')
        ->and($contents)->toContain('opcache.max_accelerated_files=20000')
        ->and($contents)->toContain('opcache.validate_timestamps=0')
        ->and($contents)->toContain('opcache.jit=tracing')
        ->and($contents)->toContain('opcache.jit_buffer_size=64M');
});

it('opcache.ini بيوثّق تحذير validate_timestamps=0', function (): void {
    // ⚠️ القيمة دي معناها إن الكود الجديد مش هيظهر غير بعد opcache_reset()
    // أو إعادة تشغيل FPM — تحذير الوثيقة نفسه (docs/14 بند ٥)، مش مجرد
    // القيمة من غير سياق.
    $contents = phpTuningFileContents('deploy/php/opcache.ini');

    expect($contents)->toContain('opcache_reset()');
});
