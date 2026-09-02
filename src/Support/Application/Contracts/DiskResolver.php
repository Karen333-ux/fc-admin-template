<?php

declare(strict_types=1);

namespace Src\Support\Application\Contracts;

/**
 * المكان **الوحيد** اللي بيقرر الديسك. (docs/04 بند ٣)
 *
 * الكود مش بيعرف S3 ولا local — بيسأل هنا وخلاص. معيار القبول في `docs/04`
 * بند ١٠: مفيش `'s3'` ولا `'local'` مكتوبين في أي كود بره `config/` وهنا.
 */
interface DiskResolver
{
    /** الديسك المناسب لمجموعة وسائط معيّنة */
    public function for(string $collection): string;

    /** ديسك التحويلات (المصغّرات) */
    public function forConversions(string $collection): string;

    /** هل المجموعة دي خاصة (تحتاج رابط مؤقت)؟ */
    public function isPrivate(string $collection): bool;
}
