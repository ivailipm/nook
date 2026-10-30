<?php

use App\Enums\Section;
use App\Livewire\Pages\Groceries;
use App\Livewire\Pages\Home;
use App\Livewire\Pages\PromoBoard;
use App\Livewire\Pages\Subscriptions;
use App\Livewire\Pages\Todo;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Home::class)->name(Section::Home->value);
Route::livewire('/groceries', Groceries::class)->name(Section::Groceries->value);
Route::livewire('/promos', PromoBoard::class)->name(Section::Promos->value);
Route::livewire('/todo', Todo::class)->name(Section::Todo->value);
Route::livewire('/subscriptions', Subscriptions::class)->name(Section::Subscriptions->value);
