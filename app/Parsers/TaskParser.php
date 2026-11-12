<?php

namespace App\Parsers;

use App\Enums\TaskPriority;
use App\Enums\TaskTag;
use Illuminate\Support\Str;

/**
 * Reads a task quick-add line such as "Email the prof tomorrow !high #school", exactly as the
 * prototype's parseTask does, except that only real weekday words count and "!medium" is read whole.
 * The title isn't capitalised, as the prototype's addTask doesn't. The # word picks from the tags it is given,
 * so it can pick a tag of the user's own.
 */
class TaskParser
{
    private const string WEEKDAY = 'sun|sunday|mon|monday|tue|tues|tuesday|wed|weds|wednesday|thu|thur|thurs|thursday|fri|friday|sat|saturday';

    private const array WEEKDAY_NUMBERS = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];

    /**
     * @param  list<string>|null  $tags  The tags a # word can pick; null means the built-in tags.
     */
    public function parse(string $line, ?array $tags = null): ParsedTask
    {
        $tags ??= array_column(TaskTag::cases(), 'value');

        [$text, $priority] = $this->take('/!(high|medium|med|low)/iu', $line);
        [$text, $tag] = $this->take($this->tagPattern($tags), $text);
        [$text, $repeat] = $this->take('/every\s+('.self::WEEKDAY.')\b/iu', $text);
        [$text, $dateWord] = $this->take('/\b(today|tomorrow)\b/iu', $text);
        [$text, $weekday] = $this->take('/\b('.self::WEEKDAY.')\b/iu', $text);

        $repeatWeekday = $repeat === null ? null : $this->weekdayNumber($repeat);

        $dueDate = match (true) {
            $weekday !== null => today()->next($this->weekdayNumber($weekday)),
            $dateWord === 'today' => today(),
            $dateWord === 'tomorrow' => today()->addDay(),
            // Unlike a plain weekday, "every thu" on a Thursday is due today.
            $repeatWeekday !== null => today()->dayOfWeek === $repeatWeekday ? today() : today()->next($repeatWeekday),
            default => null,
        };

        return new ParsedTask(
            title: Str::squish($text),
            dueDate: $dueDate,
            priority: match ($priority) {
                null => null,
                'high' => TaskPriority::High,
                'low' => TaskPriority::Low,
                default => TaskPriority::Med,
            },
            tag: array_find($tags, fn (string $name) => Str::convertCase($name) === $tag) ?? TaskTag::Home->value,
            repeatWeekday: $repeatWeekday,
        );
    }

    /**
     * Matches "#" and one of the tags, ignoring case. A longer tag goes first, so "#homework" reads as a homework tag
     * when there is one, and as home followed by "work" when there isn't.
     *
     * @param  list<string>  $tags
     */
    private function tagPattern(array $tags): string
    {
        usort($tags, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return '/#('.implode('|', array_map(fn (string $tag) => preg_quote($tag, '/'), $tags)).')/iu';
    }

    /**
     * Removes the first match of the pattern from the text. The word is case-folded rather than
     * lower-cased, because the "iu" flags also match "ſun", which lower-casing leaves as it is.
     *
     * @return array{string, ?string} The remaining text, and the captured word or null when there is none.
     */
    private function take(string $pattern, string $text): array
    {
        if (preg_match($pattern, $text, $matches) !== 1) {
            return [$text, null];
        }

        return [(string) preg_replace($pattern, '', $text, 1), Str::convertCase($matches[1])];
    }

    /**
     * The weekday's number from its first three letters, 0 (Sunday) to 6.
     */
    private function weekdayNumber(string $word): int
    {
        return self::WEEKDAY_NUMBERS[substr($word, 0, 3)];
    }
}
