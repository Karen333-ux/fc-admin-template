<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول إشعارات Laravel — **معدّل عن الستاب**. (docs/09 بند ١)
 *
 * ⚠️ الفرق الوحيد عن ستاب `make:notifications-table` إن `data` هنا `json`
 *    مش `text`. السبب مش تفضيل: كل الاستعلامات اللي بنعتمد عليها بتستخدم
 *    مسارات JSON، وعلى PostgreSQL المعامل `->` مابيشتغلش على عمود `text`
 *    (بيرمي «operator does not exist: text -> unknown»):
 *
 *      • Filament نفسه بيفلتر بـ `where('data->format', 'filament')`
 *        (`vendor/filament/notifications/src/Livewire/DatabaseNotifications.php` سطر ١١٢)
 *      • وحدّ المستأجر بتاعنا بيفلتر بـ `data->tenant_id` (ADR-024)
 *
 * ⚠️ **مفيش عمود `tenant_id` هنا بالقصد.** الجدول ده عقد إطار (Laravel
 *    بيكتب فيه عبر `Notifiable`)، وإضافة عمود ليه معناها إن أي إشعار
 *    مايعديش على مسارنا بيسيب العمود فاضي — يعني حد أمني بثقب. الملكية
 *    متسجّلة جوّه `data` اللي **كل** إشعار بيكتبه. نفس منطق
 *    `MediaOwnership` مع جدول `media`. (ADR-024)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // الجرس بيقرا «غير المقروء لهذا المستخدم» في كل طلب
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
