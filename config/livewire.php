<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Navigate
    |--------------------------------------------------------------------------
    |
    | wire:navigate shows a 2px progress bar at the top when a page takes
    | longer than 150ms, in the peach of the focus ring and the FAB. Only
    | this key is set here: Livewire merges its other defaults in.
    |
    */

    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => '#f0b48a',
    ],

];
