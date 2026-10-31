<?php

namespace App\Parsers;

use App\Enums\GroceryCategory;
use App\Enums\Store;
use Illuminate\Support\Str;

/**
 * Reads a grocery quick-add line such as "2 coffee @lidl #pantry", exactly as the
 * prototype's parseItem does, and capitalises the name as its addItem does.
 */
class GroceryItemParser
{
    public function parse(string $line): ParsedGroceryItem
    {
        $text = Str::trim($line);
        $qty = null;

        // A leading number with an optional unit, only when more text follows it.
        if (preg_match('/^(\d+\s*(?:x|×|kg|g|l|ml)?)\s+(.*)$/iu', $text, $matches) === 1) {
            $qty = (string) preg_replace('/\s+/u', '', $matches[1]);
            $text = $matches[2];
        }

        [$text, $storeToken] = $this->takeToken('@', $text);
        [$text, $categoryToken] = $this->takeToken('#', $text);

        return new ParsedGroceryItem(
            name: Str::ucfirst(Str::squish($text)),
            qty: $qty,
            store: $this->caseStartingWith(Store::cases(), $storeToken) ?? Store::Lidl,
            category: $this->caseStartingWith(GroceryCategory::cases(), $categoryToken) ?? GroceryCategory::Pantry,
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
     * The first case, in case order, whose value starts with the token, ignoring case.
     *
     * @template TCase of Store|GroceryCategory
     *
     * @param  list<TCase>  $cases
     * @return TCase|null
     */
    private function caseStartingWith(array $cases, ?string $token): Store|GroceryCategory|null
    {
        if ($token === null) {
            return null;
        }

        return array_find($cases, fn (Store|GroceryCategory $case) => str_starts_with(Str::lower($case->value), Str::lower($token)));
    }
}
