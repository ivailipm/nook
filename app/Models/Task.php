<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Support\RelativeDate;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['title', 'tag', 'due_date', 'priority', 'repeat_weekday', 'done', 'completed_at', 'rolled_over'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    private const array WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'due_date' => 'date',
            'repeat_weekday' => 'integer',
            'done' => 'boolean',
            'completed_at' => 'datetime',
            'rolled_over' => 'boolean',
        ];
    }

    /**
     * Whole calendar days to the due date: 0 today, 1 tomorrow, negative once past, or null without a due date.
     */
    public function daysUntilDue(): ?int
    {
        return $this->due_date === null ? null : RelativeDate::daysFromToday($this->due_date);
    }

    /**
     * Open and due before today. A done task is never overdue.
     */
    public function isOverdue(): bool
    {
        return ! $this->done && $this->due_date !== null && $this->daysUntilDue() < 0;
    }

    /**
     * The row's due label: "no date", "overdue · was yesterday", "due today", "due Sat" or "due 5 Nov".
     */
    public function dueLabel(): string
    {
        if ($this->due_date === null) {
            return 'no date';
        }

        return ($this->isOverdue() ? 'overdue · was ' : 'due ').RelativeDate::format($this->due_date);
    }

    /**
     * The Home card's meta: "School · overdue", "Internship · due today" or "Home · done".
     */
    public function homeMeta(): string
    {
        $state = match (true) {
            $this->done => 'done',
            $this->isOverdue() => 'overdue',
            default => $this->dueLabel(),
        };

        return Str::ucfirst($this->tag)." · {$state}";
    }

    /**
     * The row's repeat label: "every Thursday", or null for a one-off.
     */
    public function repeatLabel(): ?string
    {
        return $this->repeat_weekday === null ? null : 'every '.self::WEEKDAYS[$this->repeat_weekday];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
