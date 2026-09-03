<?php

declare(strict_types=1);

namespace Src\Support\Application\Contracts;

/**
 * القنوات اللي إشعار معيّن هيتبعت عليها لمستقبِل معيّن. (docs/09 بند ٣)
 *
 * العقد في `Application` والتنفيذ في `Infrastructure` — نفس نمط
 * `TenantContext` و`DiskResolver`. كلاسات الإشعارات في السياقات بتعتمد
 * على العقد ده بس، فـ `Src\Support` مابيستوردش من `Src\Contexts` (ADR-011).
 */
interface NotificationChannels
{
    /**
     * @param  object  $notifiable  المستقبِل (`Notifiable`)
     * @param  string  $key  مفتاح الإشعار في الكتالوج
     * @return list<string> أسماء قنوات Laravel
     */
    public function for(object $notifiable, string $key): array;
}
