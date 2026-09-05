<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// أول Schedule:: في المشروع — تقليم سجل النشاط الشهري. (docs/11 بند ١٠)
// شهرياً بيكفي: مدة الاحتفاظ بالشهور، فمفيش داعي لتردد أعلى.
//
// ⚠️ onOneServer() على كل مهمة — قاعدة إلزامية في docs/13 بند ٣، مش
//    اختيارية للمهام الجديدة بس.
Schedule::command('activitylog:prune')->monthly()->onOneServer();

// نبضة فحص الجدولة — ScheduleCheck بتفشل لو النبضة دي ماوصلتش خلال دقيقة.
// (docs/11 بند ٧)
Schedule::command('health:schedule-check-heartbeat')->everyMinute()->onOneServer();

// نتيجة snapshot دورية لمقاييس Horizon (يستخدمها الرسم البياني في لوحته).
// أمر Horizon نفسه، مفيش منطق جديد. (docs/13 بند ٣)
Schedule::command('horizon:snapshot')->everyFiveMinutes()->onOneServer();

// تشغيل كل فحوصات صحة النظام المسجّلة في AppServiceProvider::configureHealthChecks()
// وتخزين نتيجتها — نفس الأمر اللي HealthPage بتستخدمه، بس مجدول بدل ما
// يستنى فتح الصفحة. (docs/13 بند ٣)
//
// ⚠️ withoutOverlapping() هنا تحديداً: فحوصات القرص وعدّ اتصالات قاعدة
//    البيانات ممكن تاخد وقت متغيّر، ومفيش داعي لتراكم تشغيلات فوق بعض.
Schedule::command('health:check')->everyFifteenMinutes()->onOneServer()->withoutOverlapping();

// تقليم failed_jobs الأقدم من أسبوع — نفس أمر Laravel الجاهز، مفيش
// جدول تنظيف مخصص. (docs/13 بند ٥)
Schedule::command('queue:prune-failed --hours=168')->weekly()->onOneServer();

// النسخ الاحتياطي التلقائي — الوجهة s3-private الموجودة أصلاً، مضبوطة
// في config/backup.php. (docs/11 بند ٩)
//
// ⚠️ runInBackground() على backup:run تحديداً — ضغط التطبيق كله وقاعدة
//    البيانات مهمة طويلة، ونفس القاعدة العامة في docs/13 بند ٣
//    ("runInBackground() للمهام الطويلة عشان ماتعطّلش الجدول").
Schedule::command('backup:clean')->dailyAt('01:00')->onOneServer();
Schedule::command('backup:run')->dailyAt('01:30')->onOneServer()->runInBackground();
Schedule::command('backup:monitor')->dailyAt('02:00')->onOneServer();

// ⚠️ باقي أمثلة docs/13 بند ٣ مؤجّلة بالقصد — مفيش بنية تحتية حقيقية
//    ليها في المشروع لسه، وبناء أوامر/مهام وهمية بس عشان الجدول يتظبط
//    مخالف لتعليمات النطاق:
//    - notifications:prune → مفيش أمر بهذا الاسم في المشروع ولا في
//      Laravel نفسها؛ بناء واحد دلوقتي هيكون اختراع منطق تنظيف غير موصوف.
//    - telescope:prune → laravel/telescope مش متثبّت، وغير مذكور في أي
//      بند من خطة الأسابيع ٤-٦.
//    - GenerateWeeklyDigestJob / tenants:check-trials → نظام تقارير
//      أسبوعية ونظام تجربة/اشتراك مالهمش أي وجود في المشروع خالص.
