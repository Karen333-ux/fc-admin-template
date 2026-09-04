<?php

declare(strict_types=1);

use Src\Contexts\Identity\Infrastructure\Http\UserAgentParser;

/**
 * تحليل User-Agent — أفضل مجهود، مش حزمة كاملة (docs/12 بند ٢ مابيطلبهاش).
 */
it('بيتعرّف على Chrome وWindows', function (): void {
    $result = app(UserAgentParser::class)->parse(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    );

    expect($result['browser'])->toBe('Chrome')
        ->and($result['platform'])->toBe('Windows')
        ->and($result['device_name'])->toBe('Chrome — Windows');
});

it('بيتعرّف على Firefox وmacOS', function (): void {
    $result = app(UserAgentParser::class)->parse(
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:120.0) Gecko/20100101 Firefox/120.0',
    );

    expect($result['browser'])->toBe('Firefox')
        ->and($result['platform'])->toBe('macOS');
});

it('بيتعرّف على Safari على iPhone', function (): void {
    $result = app(UserAgentParser::class)->parse(
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
    );

    expect($result['browser'])->toBe('Safari')
        ->and($result['platform'])->toBe('iOS');
});

it('بيتعرّف على Edge مش Chrome لأن Edge بيحتوي كلمة Chrome برضه', function (): void {
    $result = app(UserAgentParser::class)->parse(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
    );

    expect($result['browser'])->toBe('Edge');
});

it('بيتعرّف على Android', function (): void {
    $result = app(UserAgentParser::class)->parse(
        'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
    );

    expect($result['platform'])->toBe('Android');
});

it('من غير User-Agent بيرجّع null من غير استثناء', function (): void {
    $result = app(UserAgentParser::class)->parse(null);

    expect($result['browser'])->toBeNull()
        ->and($result['platform'])->toBeNull()
        ->and($result['device_name'])->toBeNull();
});

it('يوزر-إيجنت مش معروف بيرجّع null من غير ما يفشل', function (): void {
    $result = app(UserAgentParser::class)->parse('SomeRandomBot/1.0');

    expect($result['browser'])->toBeNull()
        ->and($result['platform'])->toBeNull();
});
