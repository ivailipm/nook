<?php

namespace Database\Seeders;

use App\Enums\TaskPriority;
use App\Enums\TaskTag;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    /**
     * Seed the prototype's sample tasks for the user, unless they already have tasks.
     * A recurring task repeats on the weekday of its due date, which on the prototype's Thursday gives its 4 and 2.
     */
    public function run(User $user): void
    {
        if ($user->tasks()->exists()) {
            return;
        }

        $day = fn (int $offset) => today()->addDays($offset);

        $user->tasks()->createMany([
            ['title' => 'Return library book', 'tag' => TaskTag::School, 'due_date' => $day(-1), 'priority' => TaskPriority::Med, 'repeat_weekday' => null, 'done' => false, 'completed_at' => null],
            ['title' => 'Send CV to two companies', 'tag' => TaskTag::Internship, 'due_date' => $day(0), 'priority' => TaskPriority::High, 'repeat_weekday' => null, 'done' => false, 'completed_at' => null],
            ['title' => 'Put out the PMD bag', 'tag' => TaskTag::Home, 'due_date' => $day(0), 'priority' => null, 'repeat_weekday' => $day(0)->dayOfWeek, 'done' => false, 'completed_at' => null],
            ['title' => 'Water the plants', 'tag' => TaskTag::Home, 'due_date' => $day(0), 'priority' => TaskPriority::Low, 'repeat_weekday' => null, 'done' => true, 'completed_at' => now()],
            ['title' => 'Finish cloud lab report', 'tag' => TaskTag::School, 'due_date' => $day(1), 'priority' => TaskPriority::High, 'repeat_weekday' => null, 'done' => false, 'completed_at' => null],
            ['title' => 'Read deep learning chapter 5', 'tag' => TaskTag::School, 'due_date' => $day(4), 'priority' => TaskPriority::Low, 'repeat_weekday' => null, 'done' => false, 'completed_at' => null],
            ['title' => 'Take out the trash', 'tag' => TaskTag::Home, 'due_date' => $day(5), 'priority' => null, 'repeat_weekday' => $day(5)->dayOfWeek, 'done' => false, 'completed_at' => null],
        ]);
    }
}
