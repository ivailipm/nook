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
        $this->dispatch('nav-counts-changed');
    }

    public function toggle(int $id, bool $done, ToggleTask $toggleTask): void
    {
        $next = $toggleTask->handle(auth()->user()->tasks()->findOrFail($id), $done);

        if ($next !== null) {
            $this->dispatch('toast', message: $toggleTask->message($next));
        }

        $this->dispatch('nav-counts-changed');
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

    /**
     * The task sheet saved or deleted a task, so the page re-renders. A saved task can move to another group, which
     * rebuilds its row, so the row's button takes focus back by id once the page has changed; a deleted task has none.
     */
    #[On('task-changed')]
    public function taskChanged(int $id): void
    {
        $this->js("document.getElementById('task-open-{$id}')?.focus()");
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
