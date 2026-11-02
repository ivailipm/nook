<?php

namespace App\Enums;

/**
 * How often a subscription is charged. Each value is the stored lower-case word.
 */
enum SubscriptionCycle: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
