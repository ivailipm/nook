<?php

namespace App\Enums;

/**
 * The stores, in the prototype's order. Each value is the display name.
 */
enum Store: string
{
    case Aldi = 'Aldi';
    case Lidl = 'Lidl';
    case Delhaize = 'Delhaize';
    case Colruyt = 'Colruyt';
    case Other = 'Other';
}
