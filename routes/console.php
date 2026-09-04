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
Schedule::command('activitylog:prune')->monthly();
