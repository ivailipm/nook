<?php

use App\Enums\GroceryCategory;
use App\Enums\SubscriptionCategory;
use App\Enums\SubscriptionCycle;
use App\Enums\TaskPriority;
use App\Enums\TaskTag;
use App\Models\GroceryItem;
use App\Models\Promo;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PromoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

it('seeds the local account, its grocery items, its promos, its tasks and its subscriptions once', function (string $environment) {
    $this->app['env'] = $environment;
    $this->freezeTime();

    $this->seed();
    $this->seed();

    $user = User::sole();

    expect($user->email)->toBe('ivayla@example.com')
        ->and($user->name)->toBe('Ivayla')
        ->and(Hash::check('password', $user->password))->toBeTrue();

    $promos = Promo::orderBy('id')->get();
    $day = fn (int $offset) => today()->addDays($offset)->toDateString();

    expect($promos->pluck('user_id')->unique()->all())->toBe([$user->id])
        ->and($promos->map(fn (Promo $promo) => [
            $promo->store, $promo->product, $promo->deal, $promo->note,
            $promo->valid_from->toDateString(), $promo->valid_until->toDateString(), $promo->photo_path, $promo->tone,
        ])->all())->toBe([
            ['Colruyt', 'Coffee beans 1 kg', '2+1 free', '€11.98 for three', $day(-2), $day(2), null, 0],
            ['Delhaize', 'Dish soap', '−30%', '€2.09 instead of €2.99', $day(-5), $day(1), null, 1],
            ['Lidl', 'Greek yoghurt', '2nd ½ price', null, $day(-1), $day(5), null, 2],
            ['Aldi', 'Pasta 1 kg', '€1.79', 'instead of €2.39', $day(0), $day(6), null, 3],
            ['Colruyt', 'Laundry pods', '−25%', null, $day(-3), $day(12), null, 4],
            ['Lidl', 'Avocados ×4', '€2.49', null, $day(-1), $day(2), null, 5],
            ['Aldi', 'Mandarins 2 kg', '1+1', null, $day(-8), $day(-2), null, 3],
            ['Delhaize', 'Kitchen roll', '−40%', null, $day(-10), $day(-4), null, 1],
            ['Lidl', 'Gouda slices', '€1.99', null, $day(-7), $day(-1), null, 2],
        ]);

    $items = GroceryItem::orderBy('id')->get();

    expect($items->pluck('user_id')->unique()->all())->toBe([$user->id])
        ->and($items->map(fn (GroceryItem $item) => [$item->name, $item->qty, $item->store, $item->category, $item->done, $item->promo_id])->all())->toBe([
            ['Coffee beans', '1kg', 'Colruyt', GroceryCategory::Pantry, false, $promos[0]->id],
            ['Dish soap', null, 'Delhaize', GroceryCategory::Household, false, $promos[1]->id],
            ['Oat milk', '2', 'Aldi', GroceryCategory::Dairy, false, null],
            ['Spinach', '300g', 'Lidl', GroceryCategory::FruitAndVeg, false, null],
            ['Greek yoghurt', '2', 'Lidl', GroceryCategory::Dairy, false, $promos[2]->id],
            ['Bananas', '6', 'Aldi', GroceryCategory::FruitAndVeg, true, null],
            ['Laundry pods', null, 'Colruyt', GroceryCategory::Household, false, $promos[4]->id],
        ]);

    $tasks = Task::orderBy('id')->get();
    $weekday = fn (int $offset) => today()->addDays($offset)->dayOfWeek;

    expect($tasks->pluck('user_id')->unique()->all())->toBe([$user->id])
        ->and($tasks->map(fn (Task $task) => [
            $task->title, $task->tag, $task->due_date->toDateString(), $task->priority, $task->repeat_weekday,
            $task->done, $task->completed_at?->toDateTimeString(), $task->rolled_over,
        ])->all())->toBe([
            ['Return library book', TaskTag::School, $day(-1), TaskPriority::Med, null, false, null, false],
            ['Send CV to two companies', TaskTag::Internship, $day(0), TaskPriority::High, null, false, null, false],
            ['Put out the PMD bag', TaskTag::Home, $day(0), null, $weekday(0), false, null, false],
            ['Water the plants', TaskTag::Home, $day(0), TaskPriority::Low, null, true, now()->toDateTimeString(), false],
            ['Finish cloud lab report', TaskTag::School, $day(1), TaskPriority::High, null, false, null, false],
            ['Read deep learning chapter 5', TaskTag::School, $day(4), TaskPriority::Low, null, false, null, false],
            ['Take out the trash', TaskTag::Home, $day(5), null, $weekday(5), false, null, false],
        ]);

    $subscriptions = Subscription::orderBy('id')->get();

    expect($subscriptions->pluck('user_id')->unique()->all())->toBe([$user->id])
        ->and($subscriptions->map(fn (Subscription $subscription) => [
            $subscription->name, $subscription->price, $subscription->cycle, $subscription->next_payment_date->toDateString(),
            $subscription->category, $subscription->is_trial, $subscription->trial_reminder, $subscription->cancelled_at?->toDateString(),
        ])->all())->toBe([
            ['Gym', '24.99', SubscriptionCycle::Monthly, $day(3), SubscriptionCategory::Health, false, false, null],
            ['Netflix', '13.49', SubscriptionCycle::Monthly, $day(5), SubscriptionCategory::Entertainment, false, false, null],
            ['Spotify', '6.49', SubscriptionCycle::Monthly, $day(2), SubscriptionCategory::Entertainment, false, false, null],
            ['iCloud+', '2.99', SubscriptionCycle::Monthly, $day(16), SubscriptionCategory::Storage, false, false, null],
            ['Duolingo Super', '12.99', SubscriptionCycle::Monthly, $day(4), SubscriptionCategory::Education, true, false, null],
            ['Disney+', '7.99', SubscriptionCycle::Monthly, $day(0), SubscriptionCategory::Entertainment, false, false, $day(-61)],
        ]);
})->with(['local', 'testing']);

it('repeats the seeded recurring tasks on the weekday of their due dates', function (string $today, int $first, int $second) {
    $this->travelTo(Carbon::parse("{$today} 12:00", 'Europe/Brussels'));

    $this->seed();

    expect(Task::whereNotNull('repeat_weekday')->orderBy('id')->pluck('repeat_weekday')->all())->toBe([$first, $second]);
})->with([
    'the prototype\'s Thursday' => ['2026-10-29', 4, 2],
    'a Monday' => ['2026-11-02', 1, 6],
]);

it('seeds the promos for a user without the sample items, linking the items that exist', function () {
    $user = User::factory()->create();
    $coffee = GroceryItem::factory()->for($user)->create(['name' => 'Coffee beans']);

    app(PromoSeeder::class)->run($user);

    $promos = $user->promos()->orderBy('id')->get();

    expect($promos)->toHaveCount(9)
        ->and($coffee->fresh()->promo_id)->toBe($promos[0]->id);
});

it('seeds no user outside local and testing', function (string $environment) {
    $this->app['env'] = $environment;

    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::count())->toBe(0)
        ->and(GroceryItem::count())->toBe(0)
        ->and(Promo::count())->toBe(0)
        ->and(Task::count())->toBe(0)
        ->and(Subscription::count())->toBe(0);
})->with(['production', 'staging']);
