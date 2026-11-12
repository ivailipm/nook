<?php

namespace App\Livewire\Sheets;

use App\Actions\AddTag;
use App\Actions\RemoveTag;
use App\Enums\TaskTag;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Livewire\Component;

/**
 * The "Your tags" sheet, rendered once in the layout and opened by the dashed + after the tag chips on To-do and in
 * the add-task and edit-task sheets, on top of those sheets. It adds and removes the user's own task tags.
 */
class Tags extends Component
{
    /**
     * The new tag's name.
     */
    public string $tag = '';

    /**
     * What the last add or remove did, shown in the sheet: the toast would sit behind it.
     */
    public string $status = '';

    /**
     * Adds the tag and keeps the sheet open with an empty field, so several go in one after another.
     * An add-task or edit-task sheet open underneath picks the new tag (tag-added).
     */
    public function add(AddTag $addTag): void
    {
        $tag = $addTag->handle(auth()->user(), $this->tag);

        $this->resetForm();

        $this->status = $addTag->message($tag);
        $this->dispatch('tags-changed');
        $this->dispatch('tag-added', name: $tag->name);
    }

    /**
     * Removes the tag, moving its tasks to home. A tag already gone (a double tap) is left alone.
     */
    public function remove(int $id, RemoveTag $removeTag): void
    {
        $tag = auth()->user()->customTags()->find($id);

        if ($tag === null) {
            return;
        }

        $moved = $removeTag->handle(auth()->user(), $tag);

        $this->status = $removeTag->message($tag->name, $moved);
        $this->dispatch('tags-changed');
    }

    /**
     * Back to an empty field without errors or a status. The view calls this whenever the sheet closes.
     */
    public function resetForm(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.sheets.tags', [
            'own' => auth()->user()->customTags()->orderBy('id')->get(),
            'builtIn' => Arr::join(array_column(TaskTag::cases(), 'value'), ', ', ' and '),
        ]);
    }
}
