<?php

namespace App\Enums;

/**
 * The built-in stores, in the prototype's order. Each value is the display name. A user's own stores
 * (App\Models\CustomStore) go between Colruyt and Other, and User::storeNames() gives the full list.
 * Items and promos store the name as a plain string, so they can be at a store of the user's own.
 */
enum Store: string
{
    case Aldi = 'Aldi';
    case Lidl = 'Lidl';
    case Delhaize = 'Delhaize';
    case Colruyt = 'Colruyt';
    case Other = 'Other';
}
