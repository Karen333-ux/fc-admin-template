<?php

declare(strict_types=1);

return [

    'horizon' => 'لوحة الطوابير (Horizon)',

    'failed_jobs' => [
        'title' => 'الوظائف الفاشلة',
        'fields' => [
            'job' => 'الوظيفة',
            'connection' => 'الاتصال',
            'queue' => 'الطابور',
            'exception' => 'الاستثناء',
            'failed_at' => 'وقت الفشل',
        ],
        'actions' => [
            'retry' => 'إعادة المحاولة',
        ],
        'notifications' => [
            'retried' => 'اتبعتت الوظيفة تاني للطابور.',
        ],
        'empty' => [
            'heading' => 'مفيش وظائف فاشلة',
        ],
    ],

];
