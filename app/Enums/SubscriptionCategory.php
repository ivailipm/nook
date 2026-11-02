<?php

namespace App\Enums;

/**
 * The subscription categories, in the prototype's chip order. Each value is the display name.
 */
enum SubscriptionCategory: string
{
    case Entertainment = 'Entertainment';
    case Health = 'Health';
    case Storage = 'Storage';
    case Education = 'Education';
    case Other = 'Other';
}
