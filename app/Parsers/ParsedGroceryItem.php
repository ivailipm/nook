<?php

namespace App\Parsers;

use App\Enums\GroceryCategory;

/**
 * A grocery quick-add line, as read by GroceryItemParser. A missing quantity is null.
 */
final readonly class ParsedGroceryItem
{
    public function __construct(
        public string $name,
        public ?string $qty,
        public string $store,
        public GroceryCategory $category,
    ) {}
}
