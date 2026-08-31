<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// القالب ده لوحة تحكم — مفيش واجهة عامة. الجذر بيروّح على اللوحة.
// (صفحة welcome بتاعة سكافولد Laravel اتشالت: نصوص مكتوبة + CSS اتجاهي
//  + ألوان hex، وكلهم مرفوضين في معايير القبول — docs/23 بند ٨.)
Route::redirect('/', '/admin');
