<?php

use App\Enums\GroceryCategory;
use App\Enums\Store;
use App\Parsers\GroceryItemParser;

it('parses a quick-add line', function (string $line, string $name, ?string $qty, Store $store, GroceryCategory $category) {
    $item = (new GroceryItemParser)->parse($line);

    expect($item->name)->toBe($name)
        ->and($item->qty)->toBe($qty)
        ->and($item->store)->toBe($store)
        ->and($item->category)->toBe($category);
})->with([
    // The FEATURES.md and prototype examples.
    'the placeholder' => ['2 coffee @lidl #pantry', 'Coffee', '2', Store::Lidl, GroceryCategory::Pantry],
    'a count with x' => ['2x oat milk', 'Oat milk', '2x', Store::Lidl, GroceryCategory::Pantry],
    'kilograms and a store prefix' => ['1kg coffee beans @col', 'Coffee beans', '1kg', Store::Colruyt, GroceryCategory::Pantry],
    'grams and a category prefix' => ['300g spinach #fru', 'Spinach', '300g', Store::Lidl, GroceryCategory::FruitAndVeg],
    'litres, a store and a category' => ['1l oat milk @aldi #dai', 'Oat milk', '1l', Store::Aldi, GroceryCategory::Dairy],
    'store and category prefixes without a quantity' => ['greek yoghurt @lid #dai', 'Greek yoghurt', null, Store::Lidl, GroceryCategory::Dairy],
    'a name only' => ['dish soap', 'Dish soap', null, Store::Lidl, GroceryCategory::Pantry],
    'a full store name' => ['laundry pods @colruyt #house', 'Laundry pods', null, Store::Colruyt, GroceryCategory::Household],

    // The prototype's edge behaviour.
    'a spaced x' => ['2 x milk', 'Milk', '2x', Store::Lidl, GroceryCategory::Pantry],
    'a multiplication sign' => ['6 × eggs', 'Eggs', '6×', Store::Lidl, GroceryCategory::Pantry],
    'spaced millilitres' => ['500 ml water', 'Water', '500ml', Store::Lidl, GroceryCategory::Pantry],
    'a unit keeps its case' => ['2 KG rice', 'Rice', '2KG', Store::Lidl, GroceryCategory::Pantry],
    'a bare number before a word starting with a unit letter' => ['2 lemons', 'Lemons', '2', Store::Lidl, GroceryCategory::Pantry],
    'a lone quantity is the name' => ['1kg', '1kg', null, Store::Lidl, GroceryCategory::Pantry],
    'a number joined to a word' => ['7up', '7up', null, Store::Lidl, GroceryCategory::Pantry],
    'a decimal is not a quantity' => ['1.5l water', '1.5l water', null, Store::Lidl, GroceryCategory::Pantry],
    'a number after a store stays in the name' => ['@aldi 2 bananas', '2 bananas', null, Store::Aldi, GroceryCategory::Pantry],
    'uppercase tokens' => ['bananas @ALDI #FRUIT', 'Bananas', null, Store::Aldi, GroceryCategory::FruitAndVeg],
    'one-letter prefixes' => ['milk @d #h', 'Milk', null, Store::Delhaize, GroceryCategory::Household],
    'the other store' => ['tape @oth', 'Tape', null, Store::Other, GroceryCategory::Pantry],
    'an unknown store is dropped' => ['milk @carrefour', 'Milk', null, Store::Lidl, GroceryCategory::Pantry],
    'a category is matched by prefix only' => ['milk #veg', 'Milk', null, Store::Lidl, GroceryCategory::Pantry],
    'only the first store is read' => ['milk @aldi @lidl', 'Milk @lidl', null, Store::Aldi, GroceryCategory::Pantry],
    'a store mid-line' => ['oat @aldi milk', 'Oat milk', null, Store::Aldi, GroceryCategory::Pantry],
    'extra whitespace' => ['  oat   milk  ', 'Oat milk', null, Store::Lidl, GroceryCategory::Pantry],
    'a multibyte first letter' => ['épinards', 'Épinards', null, Store::Lidl, GroceryCategory::Pantry],
    'a quantity and a store without a name' => ['2 @aldi', '', '2', Store::Aldi, GroceryCategory::Pantry],
    'an empty line' => ['', '', null, Store::Lidl, GroceryCategory::Pantry],
    'only spaces' => ['   ', '', null, Store::Lidl, GroceryCategory::Pantry],
]);
