<?php

declare(strict_types=1);

use Src\Support\Infrastructure\Logging\Redactor;

/**
 * `Redactor` مستخرجة من `ContextProcessor` عشان اللوج البنيوي (Slice 4.2)
 * وسجل النشاط (Slice 4.3) يقروا من نفس `config('logging.redact')`. (docs/11 بند ٦)
 */
it('بتشيل الحقول الحسّاسة وبتسيب الباقي', function (): void {
    $redacted = app(Redactor::class)->redact([
        'password' => 'secret',
        'email' => 'keep@example.test',
    ]);

    expect($redacted['password'])->toBe('[REDACTED]')
        ->and($redacted['email'])->toBe('keep@example.test');
});

it('بتمشي على المصفوفات المتداخلة على أي عمق', function (): void {
    $redacted = app(Redactor::class)->redact([
        'a' => ['b' => ['c' => ['token' => 'leak', 'name' => 'كريم']]],
    ]);

    expect($redacted['a']['b']['c']['token'])->toBe('[REDACTED]')
        ->and($redacted['a']['b']['c']['name'])->toBe('كريم');
});

it('مطابقة اسم المفتاح case-insensitive', function (): void {
    $redacted = app(Redactor::class)->redact(['PASSWORD' => 'secret']);

    expect($redacted['PASSWORD'])->toBe('[REDACTED]');
});
