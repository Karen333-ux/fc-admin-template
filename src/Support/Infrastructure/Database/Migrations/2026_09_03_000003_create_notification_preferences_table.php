<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تفضيلات الإشعارات لكل مستخدم. (docs/09 بند ٣)
 *
 * ⚠️ `tenant_id` **nullable** بالقصد: الصف الفاضي معناه «تفضيل عام للمستخدم
 *    في كل المؤسسات»، والصف اللي فيه مستأجر بيدهسه جوّه المستأجر ده بس.
 *
 * ⚠️ عشان كده الجدول ده **مابيستخدمش** `BelongsToTenant`: الـ trait بيرمي
 *    لما مفيش سياق مستأجر (`MissingTenantContextException`)، وهنا «مفيش
 *    مستأجر» قيمة صالحة مش خطأ. التنطيق صريح في `NotificationChannelResolver`.
 *    (قرار المراجعة هـ)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('notification_key');
            $table->json('channels');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            // ⚠️ على PostgreSQL الـ unique مابيمنعش تكرار الصفوف اللي
            //    tenant_id فيها NULL (كل NULL مختلف عن التاني). الفهرس
            //    الجزئي تحت هو اللي بيقفل الثغرة دي.
            $table->unique(['user_id', 'tenant_id', 'notification_key'], 'notif_pref_unique');

            // الاستعلام الوحيد اللي الحلّال بيعمله
            $table->index(['user_id', 'notification_key']);
        });

        // صف عام واحد بس لكل (مستخدم، مفتاح)
        DB::statement(
            'CREATE UNIQUE INDEX notif_pref_global_unique ON notification_preferences '
            .'(user_id, notification_key) WHERE tenant_id IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
