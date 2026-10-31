<?php

namespace App\Enums;

/**
 * The grocery categories, in the prototype's order. Each value is the display name.
 */
enum GroceryCategory: string
{
    case FruitAndVeg = 'Fruit & veg';
    case Dairy = 'Dairy';
    case Pantry = 'Pantry';
    case Household = 'Household';
}
