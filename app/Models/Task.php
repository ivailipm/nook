<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskTag;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'tag', 'due_date', 'priority', 'repeat_weekday', 'done', 'completed_at', 'rolled_over'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tag' => TaskTag::class,
            'priority' => TaskPriority::class,
            'due_date' => 'date',
            'repeat_weekday' => 'integer',
            'done' => 'boolean',
            'completed_at' => 'datetime',
            'rolled_over' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
