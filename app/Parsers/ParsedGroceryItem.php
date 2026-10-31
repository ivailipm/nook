<?php

namespace App\Parsers;

use App\Enums\GroceryCategory;
use App\Enums\Store;

/**
 * A grocery quick-add line, as read by GroceryItemParser. A missing quantity is null.
 */
final readonly class ParsedGroceryItem
{
    public function __construct(
        public string $name,
        public ?string $qty,
        public Store $store,
        public GroceryCategory $category,
    ) {}
}
