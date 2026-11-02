<?php

namespace App\Support;

/**
 * The app's money format, following the prototype's eur(): "€3.49", "€1234.50".
 */
class Money
{
    public static function format(float $amount): string
    {
        return '€'.number_format($amount, 2, '.', '');
    }
}
