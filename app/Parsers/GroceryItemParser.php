<?php

namespace App\Parsers;

use App\Enums\GroceryCategory;
use App\Enums\Store;
use Illuminate\Support\Str;

/**
 * Reads a grocery quick-add line such as "2 coffee @lidl #pantry", exactly as the
 * prototype's parseItem does, and capitalises the name as its addItem does. The @ token
 * picks from the stores it is given, so it can pick a store of the user's own.
 */
class GroceryItemParser
{
    /**
     * @param  list<string>|null  $stores  The store names an @ token can pick, in order; null means the built-in stores.
     */
    public function parse(string $line, ?array $stores = null): ParsedGroceryItem
    {
        $stores ??= array_column(Store::cases(), 'value');
        $text = Str::trim($line);
        $qty = null;

        // A leading number with an optional unit, only when more text follows it.
        if (preg_match('/^(\d+\s*(?:x|×|kg|g|l|ml)?)\s+(.*)$/iu', $text, $matches) === 1) {
            $qty = (string) preg_replace('/\s+/u', '', $matches[1]);
            $text = $matches[2];
        }

        [$text, $storeToken] = $this->takeToken('@', $text);
        [$text, $categoryToken] = $this->takeToken('#', $text);
        $category = $this->firstStartingWith(array_column(GroceryCategory::cases(), 'value'), $categoryToken);

        return new ParsedGroceryItem(
            name: Str::ucfirst(Str::squish($text)),
            qty: $qty,
            store: $this->firstStartingWith($stores, $storeToken) ?? Store::Lidl->value,
            category: $category === null ? GroceryCategory::Pantry : GroceryCategory::from($category),
        );
    }

    /**
     * Removes the first token after the sigil from the text, whatever it says.
     *
     * @return array{string, ?string} The remaining text, and the token or null when there is none.
     */
    private function takeToken(string $sigil, string $text): array
    {
        $pattern = '/'.$sigil.'(\S+)/u';

        if (preg_match($pattern, $text, $matches) !== 1) {
            return [$text, null];
        }

        return [(string) preg_replace($pattern, '', $text, 1), $matches[1]];
    }

    /**
     * The first name, in list order, that starts with the token, ignoring case.
     *
     * @param  list<string>  $names
     */
    private function firstStartingWith(array $names, ?string $token): ?string
    {
        if ($token === null) {
            return null;
        }

        return array_find($names, fn (string $name) => str_starts_with(Str::lower($name), Str::lower($token)));
    }
}
