<?php

use App\Enums\GroceryCategory;
use App\Parsers\GroceryItemParser;

it('parses a quick-add line', function (string $line, string $name, ?string $qty, string $store, GroceryCategory $category) {
    $item = (new GroceryItemParser)->parse($line);

    expect($item->name)->toBe($name)
        ->and($item->qty)->toBe($qty)
        ->and($item->store)->toBe($store)
        ->and($item->category)->toBe($category);
})->with([
    // The FEATURES.md and prototype examples.
    'the placeholder' => ['2 coffee @lidl #pantry', 'Coffee', '2', 'Lidl', GroceryCategory::Pantry],
    'a count with x' => ['2x oat milk', 'Oat milk', '2x', 'Lidl', GroceryCategory::Pantry],
    'kilograms and a store prefix' => ['1kg coffee beans @col', 'Coffee beans', '1kg', 'Colruyt', GroceryCategory::Pantry],
    'grams and a category prefix' => ['300g spinach #fru', 'Spinach', '300g', 'Lidl', GroceryCategory::FruitAndVeg],
    'litres, a store and a category' => ['1l oat milk @aldi #dai', 'Oat milk', '1l', 'Aldi', GroceryCategory::Dairy],
    'store and category prefixes without a quantity' => ['greek yoghurt @lid #dai', 'Greek yoghurt', null, 'Lidl', GroceryCategory::Dairy],
    'a name only' => ['dish soap', 'Dish soap', null, 'Lidl', GroceryCategory::Pantry],
    'a full store name' => ['laundry pods @colruyt #house', 'Laundry pods', null, 'Colruyt', GroceryCategory::Household],

    // The prototype's edge behaviour.
    'a spaced x' => ['2 x milk', 'Milk', '2x', 'Lidl', GroceryCategory::Pantry],
    'a multiplication sign' => ['6 × eggs', 'Eggs', '6×', 'Lidl', GroceryCategory::Pantry],
    'spaced millilitres' => ['500 ml water', 'Water', '500ml', 'Lidl', GroceryCategory::Pantry],
    'a unit keeps its case' => ['2 KG rice', 'Rice', '2KG', 'Lidl', GroceryCategory::Pantry],
    'a bare number before a word starting with a unit letter' => ['2 lemons', 'Lemons', '2', 'Lidl', GroceryCategory::Pantry],
    'a lone quantity is the name' => ['1kg', '1kg', null, 'Lidl', GroceryCategory::Pantry],
    'a number joined to a word' => ['7up', '7up', null, 'Lidl', GroceryCategory::Pantry],
    'a decimal is not a quantity' => ['1.5l water', '1.5l water', null, 'Lidl', GroceryCategory::Pantry],
    'a number after a store stays in the name' => ['@aldi 2 bananas', '2 bananas', null, 'Aldi', GroceryCategory::Pantry],
    'uppercase tokens' => ['bananas @ALDI #FRUIT', 'Bananas', null, 'Aldi', GroceryCategory::FruitAndVeg],
    'one-letter prefixes' => ['milk @d #h', 'Milk', null, 'Delhaize', GroceryCategory::Household],
    'the other store' => ['tape @oth', 'Tape', null, 'Other', GroceryCategory::Pantry],
    'an unknown store is dropped' => ['milk @carrefour', 'Milk', null, 'Lidl', GroceryCategory::Pantry],
    'a category is matched by prefix only' => ['milk #veg', 'Milk', null, 'Lidl', GroceryCategory::Pantry],
    'only the first store is read' => ['milk @aldi @lidl', 'Milk @lidl', null, 'Aldi', GroceryCategory::Pantry],
    'a store mid-line' => ['oat @aldi milk', 'Oat milk', null, 'Aldi', GroceryCategory::Pantry],
    'extra whitespace' => ['  oat   milk  ', 'Oat milk', null, 'Lidl', GroceryCategory::Pantry],
    'a multibyte first letter' => ['épinards', 'Épinards', null, 'Lidl', GroceryCategory::Pantry],
    'a quantity and a store without a name' => ['2 @aldi', '', '2', 'Aldi', GroceryCategory::Pantry],
    'an empty line' => ['', '', null, 'Lidl', GroceryCategory::Pantry],
    'only spaces' => ['   ', '', null, 'Lidl', GroceryCategory::Pantry],
]);

it('picks from the stores it is given, in their order', function (string $line, string $store) {
    $stores = ['Aldi', 'Lidl', 'Delhaize', 'Colruyt', 'Carrefour', 'Albert Heijn', 'Other'];

    expect((new GroceryItemParser)->parse($line, $stores)->store)->toBe($store);
})->with([
    'a store of the user\'s own by prefix' => ['wine @carr', 'Carrefour'],
    'a shared prefix picks the earlier store' => ['wine @c', 'Colruyt'],
    'the first word of a name with a space' => ['cheese @albert', 'Albert Heijn'],
    'uppercase' => ['cheese @ALB', 'Albert Heijn'],
    'still Other' => ['tape @oth', 'Other'],
    'a store not in the list falls back to Lidl' => ['milk @spar', 'Lidl'],
]);
