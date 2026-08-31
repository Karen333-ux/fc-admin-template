<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use LogicException;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Exceptions\MissingTenantContextException;

final class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * ⚠️ إلزامي هنا — مش تحسين.
     *
     * معاملات قاعدة البيانات في Filament **مطفية افتراضياً** على مستوى اللوحة
     * (`Panel::$hasDatabaseTransactions = false`). من غير السطر ده، لو ربط
     * العضوية فشل بعد ما صف المستخدم اتحفظ، الصف بيفضل **متثبّت** والنتيجة
     * مستخدم يتيم: مش ظاهر في الجدول (فلتر العضوية بيستبعده) ومش قادر يدخل
     * أي لوحة.
     *
     * `CreateRecord::create()` بيفتح المعاملة قبل `handleRecordCreation()`
     * وبيعمل rollback على أي `Throwable`، و`afterCreate()` بيتنفّذ **جوّه**
     * المعاملة — يعني إنشاء المستخدم + العضوية بيبقوا وحدة واحدة. (ADR-016)
     */
    protected ?bool $hasDatabaseTransactions = true;

    /**
     * ربط المستخدم الجديد بالمستأجر الحالي.
     *
     * القرار في [ADR-016]: مستخدم جديد من `UserResource` بيترّبط **تلقائياً**
     * بالمستأجر الحالي. المصدر الوحيد للمستأجر هو `TenantContext` — **مش**
     * حقل في الفورم ومش قيمة جاية من الطلب، عشان المُنشئ مايقدرش يختار
     * مؤسسة تانية. (ADR-002: العضوية many-to-many ومفيش `users.tenant_id`)
     *
     * Filament مابيربطش لوحده: `observeTenancyModelCreation` مش نشط على المورد
     * ده — نفس سبب غياب الـ global scope الموصوف في ADR-016.
     */
    protected function afterCreate(): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            // بنرمي بدل ما نسيب مستخدم بلا عضوية. الاستثناء جوّه المعاملة،
            // فبيعمل rollback لصف المستخدم كمان — مفيش سجل يتيم بيتساب ورانا.
            throw new MissingTenantContextException(User::class);
        }

        // syncWithoutDetaching مش attach: لو إصدار Filament جاي فعّل الربط
        // التلقائي، `attach` هتضرب في المفتاح الأساسي المركّب (tenant_id, user_id).
        $this->createdUser()->tenants()->syncWithoutDetaching([$tenantId]);
    }

    /**
     * السجل بنوعه الحقيقي.
     *
     * `CreateRecord::$record` معرّف كـ `?Model` في الإطار، والخاصية مينفعش
     * يتضيّق نوعها في كلاس وارث (PHP بيمنع ده). فبدل ما نكتم الخطأ، بنتحقق
     * وقت التنفيذ ونرمي — مفيش مسار صامت بيعدّي من غير ربط.
     */
    private function createdUser(): User
    {
        $record = $this->getRecord();

        if (! $record instanceof User) {
            throw new LogicException(
                'صفحة إنشاء المستخدم اشتغلت على موديل مش User: '
                .($record === null ? 'null' : $record::class),
            );
        }

        return $record;
    }
}
