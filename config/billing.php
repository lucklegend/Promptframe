<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Markup
    |--------------------------------------------------------------------------
    |
    | Users pay Pixelforge's cost plus this percentage. Credits are US dollars.
    |
    */

    'markup_percent' => (int) env('BILLING_MARKUP_PERCENT', 25),

];
