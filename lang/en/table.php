<?php

declare(strict_types=1);

return [

    // Shared empty state for every table — docs/08 §1
    //
    // Note: telling "no data at all" apart from "the filter matched nothing"
    // (docs/08 §7) is out of scope for this slice.
    'empty' => [
        'heading' => 'Nothing here yet',
        'description' => 'There are no records to show.',
    ],

];
