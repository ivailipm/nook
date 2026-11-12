<?php

namespace App\Actions;

use App\Models\CustomTag;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Adds a task tag of the user's own, which then shows after the built-in tags wherever a tag is picked or filtered,
 * and builds its message. A tag is one lower-case word, so the quick add can read it as "#gym": a leading # goes,
 * and spaces become hyphens. It can't repeat one of the user's tags, or be "all", the filter for every tag.
 */
class AddTag
{
    /**
     * @throws ValidationException under the "tag" key, the Your tags sheet's field.
     */
    public function handle(User $user, string $name): CustomTag
    {
        $name = Str::lower(Str::replace(' ', '-', Str::squish(ltrim(Str::trim($name), '#'))));

        Validator::make(['tag' => $name], [
            'tag' => ['required', 'max:20', 'regex:/^[\pL\pN_-]+$/u', function (string $attribute, string $value, Closure $fail) use ($user) {
                if ($value === 'all') {
                    $fail('All is the filter for every tag. Pick another name.');
                } elseif (in_array($value, $user->tagNames(), true)) {
                    $fail("You already have the {$value} tag.");
                }
            }],
        ], ['tag.regex' => 'A tag can only hold letters, numbers, - and _.'], ['tag' => 'tag'])->validate();

        return $user->customTags()->create(['name' => $name]);
    }

    /**
     * The message after adding: "Added the gym tag".
     */
    public function message(CustomTag $tag): string
    {
        return "Added the {$tag->name} tag";
    }
}
