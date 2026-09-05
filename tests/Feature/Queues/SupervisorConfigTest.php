<?php

declare(strict_types=1);

/**
 * إعداد Supervisor بتاع Horizon في الإنتاج. (docs/13 بند ٤)
 *
 * ⚠️ الملف بصيغة Supervisor/configparser (Python) مش PHP ini — قيمة
 *    زي `%(program_name)s` بتكسر parse_ini_string (بيرفض `(` من غير
 *    quotes)، والتزويد بـ quotes هيغيّر السلوك الحقيقي عند Supervisor
 *    نفسه. فالفحص هنا نصّي (سطر بسطر) مش ini parsing.
 */
function fcHorizonSupervisorConfigLines(): array
{
    $path = base_path('deploy/supervisor/fc-horizon.conf');

    expect(file_exists($path))->toBeTrue();

    return array_map('trim', file($path));
}

it('بتشغّل Horizon نفسه ومن غير إعادة تشغيل يدوية', function (): void {
    $lines = fcHorizonSupervisorConfigLines();

    expect($lines)->toContain('[program:fc-horizon]')
        ->and($lines)->toContain('command=php /var/www/app/artisan horizon')
        ->and($lines)->toContain('autostart=true')
        ->and($lines)->toContain('autorestart=true');
});

it('stopwaitsecs بتاعتها كافية عشان الـ jobs الجارية تخلص قبل الإقفال', function (): void {
    $lines = fcHorizonSupervisorConfigLines();

    expect($lines)->toContain('stopwaitsecs=3600');
});
