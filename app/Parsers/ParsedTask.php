<?php

namespace App\Parsers;

use App\Enums\TaskPriority;
use App\Enums\TaskTag;
use Carbon\CarbonInterface;

/**
 * A task quick-add line, as read by TaskParser. A missing due date, priority or repeat is null.
 * The repeat weekday runs from 0 (Sunday) to 6, and the title can be empty.
 */
final readonly class ParsedTask
{
    public function __construct(
        public string $title,
        public ?CarbonInterface $dueDate,
        public ?TaskPriority $priority,
        public TaskTag $tag,
        public ?int $repeatWeekday,
    ) {}
}
