<?php

namespace Database\Factories;

use App\Enums\TaskTag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state: an open, one-off task.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => Str::ucfirst(fake()->words(3, true)),
            'tag' => fake()->randomElement(TaskTag::cases())->value,
            'due_date' => null,
            'priority' => null,
            'repeat_weekday' => null,
            'done' => false,
            'completed_at' => null,
            'rolled_over' => false,
        ];
    }
}
