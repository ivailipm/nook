<?php

namespace App\Livewire\Pages;

use App\Actions\CreateTask;
use App\Actions\ToggleMorningNudge;
use App\Actions\ToggleTask;
use App\Enums\TaskTag;
use App\Parsers\TaskParser;
use App\Support\TaskGroups;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('To-do')]
#[On('task-added')]
class Todo extends Component
{
    public string $quickTask = '';

    /**
     * A tag's value shows only that tag; anything else shows every tag.
     */
    public string $tagFilter = 'All';

    public function add(TaskParser $parser, CreateTask $createTask): void
    {
        $this->validate(['quickTask' => ['string', 'max:255']], attributes: ['quickTask' => 'task']);

        $parsed = $parser->parse($this->quickTask);

        if ($parsed->title === '') {
            return;
        }

        $task = $createTask->handle(auth()->user(), $parsed->title, $parsed->dueDate, $parsed->priority, $parsed->tag, $parsed->repeatWeekday);

        $this->reset('quickTask');

        $this->dispatch('toast', message: $createTask->message($task));
    }

    public function toggle(int $id, ToggleTask $toggleTask): void
    {
        $next = $toggleTask->handle(auth()->user()->tasks()->findOrFail($id));

        if ($next !== null) {
            $this->dispatch('toast', message: $toggleTask->message($next));
        }
    }

    /**
     * Saves the setting only. Nothing sends the nudge yet.
     */
    public function toggleNudge(ToggleMorningNudge $toggle): void
    {
        $user = auth()->user();

        $toggle->handle($user);

        $this->dispatch('toast', message: $toggle->message($user));
    }

    public function render(): View
    {
        $user = auth()->user();
        $tag = TaskTag::tryFrom($this->tagFilter);

        return view('livewire.pages.todo', [
            'groups' => TaskGroups::for($user, $tag),
            'tag' => $tag,
            'nudge' => $user->morning_nudge,
        ]);
    }
}
