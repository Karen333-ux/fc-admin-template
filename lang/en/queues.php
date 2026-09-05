<?php

declare(strict_types=1);

return [

    'horizon' => 'Queue Dashboard (Horizon)',

    'failed_jobs' => [
        'title' => 'Failed Jobs',
        'fields' => [
            'job' => 'Job',
            'connection' => 'Connection',
            'queue' => 'Queue',
            'exception' => 'Exception',
            'failed_at' => 'Failed at',
        ],
        'actions' => [
            'retry' => 'Retry',
        ],
        'notifications' => [
            'retried' => 'Job pushed back onto the queue.',
        ],
        'empty' => [
            'heading' => 'No failed jobs',
        ],
    ],

];
