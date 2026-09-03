<?php

declare(strict_types=1);

return [

    // Shared empty state for every table — docs/08 §1
    //
    // A resource overrides both strings when it distinguishes "no data at all"
    // from "the filter matched nothing". (docs/08 §7)
    'empty' => [
        'heading' => 'Nothing here yet',
        'description' => 'There are no records to show.',

        // Second state: data exists but the filter matched nothing (docs/08 §7)
        'no_results' => 'No results for this filter',
        'no_results_description' => 'Try widening the filter, or clear it.',
    ],

];
