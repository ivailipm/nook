<?php

use App\Enums\Section;
use App\Http\Controllers\PromoPhotoController;
use App\Http\Controllers\ServiceWorkerController;
use App\Livewire\Pages\Groceries;
use App\Livewire\Pages\Home;
use App\Livewire\Pages\PromoBoard;
use App\Livewire\Pages\Subscriptions;
use App\Livewire\Pages\Todo;
use Illuminate\Support\Facades\Route;

// The service worker and the page it shows when nook can't be reached, both cached by the worker for whoever signs in.
Route::get('/sw.js', ServiceWorkerController::class)->name('service-worker');
Route::view('/offline', 'offline')->name('offline');

Route::middleware('auth')->group(function () {
    Route::livewire('/', Home::class)->name(Section::Home->value);
    Route::livewire('/groceries', Groceries::class)->name(Section::Groceries->value);
    Route::livewire('/promos', PromoBoard::class)->name(Section::Promos->value);
    Route::get('/promos/{promo}/photo', PromoPhotoController::class)->whereNumber('promo')->name('promos.photo');
    Route::livewire('/todo', Todo::class)->name(Section::Todo->value);
    Route::livewire('/subscriptions', Subscriptions::class)->name(Section::Subscriptions->value);
});
