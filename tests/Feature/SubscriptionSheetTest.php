<?php

use App\Actions\UpdateSubscription;
use App\Enums\SubscriptionCategory;
use App\Enums\SubscriptionCycle;
use App\Livewire\Sheets\AddSubscription;
use App\Livewire\Sheets\SubscriptionSheet;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\SubscriptionSeeder;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    $this->withoutVite();

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    app(SubscriptionSeeder::class)->run($this->user);
});

function editSheetRow(string $name): Subscription
{
    return test()->user->subscriptions()->where('name', $name)->sole();
}

function openEditSheet(Subscription $subscription): Testable
{
    return Livewire::test(SubscriptionSheet::class)->dispatch('show-subscription', id: $subscription->id);
}

function editSheetHtml(Testable $component): HTMLDocument
{
    return HTMLDocument::createFromString($component->html(), LIBXML_NOERROR);
}

/**
 * The text of the pressed chips, Billing first, then Category.
 *
 * @return list<string>
 */
function editSheetChips(Testable $component): array
{
    return array_map(
        fn (Element $chip) => trim($chip->textContent),
        iterator_to_array(editSheetHtml($component)->querySelectorAll('[role="group"] button[aria-pressed="true"]')),
    );
}

function editSheetToggle(Testable $component): Element
{
    return editSheetHtml($component)->querySelector('button[wire\\:click="$toggle(\'isTrial\')"]');
}

/**
 * Every subscription's stored attributes, in id order.
 *
 * @return list<array<string, mixed>>
 */
function editSheetSnapshot(): array
{
    return Subscription::orderBy('id')->get()->map->getAttributes()->all();
}

// Opening

it('opens on show-subscription with a labelled title, the close wrapper and the form', function () {
    $component = openEditSheet(editSheetRow('Netflix'))->assertDispatched('open-sheet', name: 'subscription');
    $page = editSheetHtml($component);
    $dialog = $page->querySelector('dialog#sheet-subscription');
    $name = $page->getElementById('editSubscriptionName');
    $price = $page->getElementById('editSubscriptionPrice');
    $date = $page->getElementById('editNextPaymentDate');
    $buttons = iterator_to_array($dialog->querySelectorAll('form > button'));

    expect(trim($page->getElementById($dialog->getAttribute('aria-labelledby'))->textContent))->toBe('Edit subscription')
        ->and($dialog->parentElement->getAttribute('x-on:close.capture'))->toBe('$wire.clear()')
        ->and($dialog->querySelector('form')->getAttribute('wire:submit'))->toBe('save')
        ->and(trim($dialog->querySelector('label[for="editSubscriptionName"]')->textContent))->toBe('Name')
        ->and([$name->getAttribute('wire:model'), $name->getAttribute('maxlength')])->toBe(['editSubscriptionName', '255'])
        ->and(trim($dialog->querySelector('label[for="editSubscriptionPrice"]')->textContent))->toBe('Price €')
        ->and([$price->getAttribute('wire:model'), $price->getAttribute('inputmode')])->toBe(['editSubscriptionPrice', 'decimal'])
        ->and($price->classList->contains('font-mono'))->toBeTrue()
        ->and($name->parentElement->parentElement->getAttribute('class'))->toBe('grid grid-cols-[minmax(0,1fr)_120px] gap-2.5')
        ->and([$date->getAttribute('wire:model'), $date->getAttribute('type')])->toBe(['editNextPaymentDate', 'date'])
        ->and(array_map(fn (Element $group) => trim($page->getElementById($group->getAttribute('aria-labelledby'))->textContent), iterator_to_array($dialog->querySelectorAll('[role="group"]'))))
        ->toBe(['Billing', 'Category'])
        ->and(array_map(fn (Element $button) => [trim($button->textContent), $button->getAttribute('type'), $button->getAttribute('class')], $buttons))->toBe([
            ['Save changes', 'submit', 'min-h-12 cursor-pointer rounded-button bg-peach text-[15px] font-bold text-on-peach'],
            ['Delete subscription', 'button', 'min-h-[46px] cursor-pointer rounded-button border border-destructive-border text-[14px] font-bold text-destructive'],
        ])
        ->and($buttons[1]->getAttribute('wire:click'))->toBe('deleteSubscription');
});

it('renders no form until a subscription is shown', function () {
    $page = editSheetHtml(Livewire::test(SubscriptionSheet::class));

    expect($page->querySelector('dialog#sheet-subscription'))->not->toBeNull()
        ->and($page->querySelector('dialog#sheet-subscription form'))->toBeNull();
});

it('fills the form from the row it was opened for and changes nothing', function (string $name, array $properties, array $chips, string $label, bool $trial) {
    $this->user->subscriptions()->create(['name' => 'Figma', 'price' => 120.00, 'cycle' => SubscriptionCycle::Yearly, 'next_payment_date' => '2027-01-15', 'category' => SubscriptionCategory::Other, 'is_trial' => false, 'trial_reminder' => false, 'cancelled_at' => null]);
    Subscription::query()->update(['updated_at' => '2026-10-01 08:00:00']);
    $before = editSheetSnapshot();

    $component = openEditSheet(editSheetRow($name))
        ->assertDispatched('open-sheet', name: 'subscription')
        ->assertHasNoErrors();
    $toggle = editSheetToggle($component);

    foreach ($properties as $property => $value) {
        $component->assertSet($property, $value);
    }

    expect(editSheetChips($component))->toBe($chips)
        ->and(trim(editSheetHtml($component)->querySelector('label[for="editNextPaymentDate"]')->textContent))->toBe($label)
        ->and(trim($toggle->textContent))->toBe($trial ? 'Free trial · on' : 'Free trial?')
        ->and($toggle->getAttribute('type'))->toBe('button')
        ->and($toggle->getAttribute('aria-pressed'))->toBe($trial ? 'true' : 'false')
        ->and(editSheetSnapshot())->toBe($before);
})->with([
    'Netflix' => ['Netflix', ['editSubscriptionName' => 'Netflix', 'editSubscriptionPrice' => '13.49', 'cycle' => 'monthly', 'category' => 'Entertainment', 'editNextPaymentDate' => '2026-11-03', 'isTrial' => false], ['Monthly', 'Entertainment'], 'Next payment', false],
    'Figma, yearly' => ['Figma', ['editSubscriptionName' => 'Figma', 'editSubscriptionPrice' => '120.00', 'cycle' => 'yearly', 'category' => 'Other', 'editNextPaymentDate' => '2027-01-15', 'isTrial' => false], ['Yearly', 'Other'], 'Next payment', false],
    'Duolingo Super, a trial' => ['Duolingo Super', ['editSubscriptionName' => 'Duolingo Super', 'editSubscriptionPrice' => '12.99', 'cycle' => 'monthly', 'category' => 'Education', 'editNextPaymentDate' => '2026-11-02', 'isTrial' => true], ['Monthly', 'Education'], 'Trial ends', true],
    'Disney+, cancelled' => ['Disney+', ['editSubscriptionName' => 'Disney+', 'editSubscriptionPrice' => '7.99', 'cycle' => 'monthly', 'category' => 'Entertainment', 'editNextPaymentDate' => '2026-10-29', 'isTrial' => false], ['Monthly', 'Entertainment'], 'Next payment', false],
]);

it('flips the date label and the toggle with the free trial', function () {
    $component = openEditSheet(editSheetRow('Duolingo Super'))->toggle('isTrial')->assertSet('isTrial', false);
    $toggle = editSheetToggle($component);

    expect(trim(editSheetHtml($component)->querySelector('label[for="editNextPaymentDate"]')->textContent))->toBe('Next payment')
        ->and(trim($toggle->textContent))->toBe('Free trial?')
        ->and($toggle->getAttribute('aria-pressed'))->toBe('false');

    $component->toggle('isTrial');
    $toggle = editSheetToggle($component);

    expect(trim(editSheetHtml($component)->querySelector('label[for="editNextPaymentDate"]')->textContent))->toBe('Trial ends')
        ->and(trim($toggle->textContent))->toBe('Free trial · on')
        ->and($toggle->getAttribute('aria-pressed'))->toBe('true')
        ->and($toggle->classList->contains('bg-peach'))->toBeTrue();
});

// Saving

it('saves every field of the form, with a toast, and closes', function () {
    $netflix = editSheetRow('Netflix');

    openEditSheet($netflix)
        ->set('editSubscriptionName', '  Netflix Premium  ')
        ->set('editSubscriptionPrice', '15,99')
        ->set('cycle', 'yearly')
        ->set('category', 'Other')
        ->set('editNextPaymentDate', '2026-12-01')
        ->toggle('isTrial')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Netflix Premium saved')
        ->assertDispatched('close-sheet', name: 'subscription')
        ->assertDispatched('subscription-changed', id: $netflix->id)
        ->assertNotDispatched('nav-counts-changed')
        ->assertSet('subscriptionId', null);

    $fresh = $netflix->fresh();

    expect([$fresh->name, $fresh->price, $fresh->cycle, $fresh->category])
        ->toBe(['Netflix Premium', '15.99', SubscriptionCycle::Yearly, SubscriptionCategory::Other])
        ->and($fresh->next_payment_date->toDateString())->toBe('2026-12-01')
        ->and($fresh->is_trial)->toBeTrue()
        ->and($fresh->trial_reminder)->toBeFalse()
        ->and($fresh->cancelled_at)->toBeNull()
        ->and($fresh->user_id)->toBe($this->user->id);
});

it('keeps the reminder through a save that turns the trial off', function () {
    $trial = editSheetRow('Duolingo Super');
    $trial->update(['trial_reminder' => true]);

    openEditSheet($trial)
        ->toggle('isTrial')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Duolingo Super saved');

    expect($trial->fresh()->is_trial)->toBeFalse()
        ->and($trial->fresh()->trial_reminder)->toBeTrue();
});

it('keeps a cancelled row cancelled and its owner', function () {
    $disney = editSheetRow('Disney+');

    openEditSheet($disney)
        ->set('editSubscriptionPrice', '9.99')
        ->call('save')
        ->assertHasNoErrors()
        ->assertNotDispatched('nav-counts-changed');

    expect($disney->fresh()->price)->toBe('9.99')
        ->and($disney->fresh()->cancelled_at->toDateString())->toBe('2026-08-29')
        ->and($disney->fresh()->user_id)->toBe($this->user->id);
});

it('leaves the user\'s other subscriptions alone', function () {
    $netflix = editSheetRow('Netflix');
    Subscription::query()->update(['updated_at' => '2026-10-01 08:00:00']);
    $others = fn () => Subscription::whereKeyNot($netflix->id)->orderBy('id')->get()->map->getAttributes()->all();
    $before = $others();

    openEditSheet($netflix)
        ->set('editSubscriptionName', 'Netflix Premium')
        ->set('editNextPaymentDate', '2026-11-01')
        ->call('save')
        ->assertHasNoErrors();

    expect($others())->toBe($before)
        ->and($netflix->fresh()->name)->toBe('Netflix Premium');
});

it('reads the price with a comma or a dot and stores two decimals', function (string $price, string $stored) {
    $netflix = editSheetRow('Netflix');

    openEditSheet($netflix)
        ->set('editSubscriptionPrice', $price)
        ->call('save')
        ->assertHasNoErrors();

    expect($netflix->fresh()->price)->toBe($stored);
})->with([
    'a comma' => ['9,99', '9.99'],
    'a whole number' => ['12', '12.00'],
    'zero' => ['0', '0.00'],
    'the largest price' => ['999999.99', '999999.99'],
    'spaces around it' => [' 7 ', '7.00'],
]);

it('saves a 255-character name', function () {
    $netflix = editSheetRow('Netflix');

    openEditSheet($netflix)
        ->set('editSubscriptionName', str_repeat('a', 255))
        ->call('save')
        ->assertHasNoErrors();

    expect($netflix->fresh()->name)->toBe(str_repeat('a', 255));
});

it('saves a past date as typed', function () {
    $netflix = editSheetRow('Netflix');

    openEditSheet($netflix)
        ->set('editNextPaymentDate', '2026-10-01')
        ->call('save')
        ->assertHasNoErrors();

    expect($netflix->fresh()->next_payment_date->toDateString())->toBe('2026-10-01');
});

it('updates the row through the action with only the form\'s fields', function () {
    $netflix = editSheetRow('Netflix');
    $netflix->update(['trial_reminder' => true, 'cancelled_at' => '2026-10-20']);
    $updateSubscription = new UpdateSubscription;

    $saved = $updateSubscription->handle($netflix, 'Netflix Premium', '15.99', SubscriptionCycle::Yearly, SubscriptionCategory::Other, '2026-12-01', true);
    $fresh = $netflix->fresh();

    expect($saved->is($netflix))->toBeTrue()
        ->and([$fresh->name, $fresh->price, $fresh->cycle, $fresh->category])
        ->toBe(['Netflix Premium', '15.99', SubscriptionCycle::Yearly, SubscriptionCategory::Other])
        ->and($fresh->next_payment_date->toDateString())->toBe('2026-12-01')
        ->and($fresh->is_trial)->toBeTrue()
        ->and($fresh->trial_reminder)->toBeTrue()
        ->and($fresh->cancelled_at->toDateString())->toBe('2026-10-20')
        ->and($fresh->user_id)->toBe($this->user->id)
        ->and($updateSubscription->message($saved))->toBe('Netflix Premium saved');
});

// Bad input

it('rejects a bad value, saves nothing and stays open', function (string $field, string $value) {
    $netflix = editSheetRow('Netflix');
    Subscription::query()->update(['updated_at' => '2026-10-01 08:00:00']);
    $before = editSheetSnapshot();

    $component = openEditSheet($netflix)
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors($field)
        ->assertNotDispatched('toast')
        ->assertNotDispatched('subscription-changed')
        ->assertNotDispatched('close-sheet')
        ->assertSet('subscriptionId', $netflix->id);

    expect(editSheetSnapshot())->toBe($before)
        ->and($component->errors()->first($field))->not->toBe('')
        ->and(editSheetHtml($component)->documentElement->textContent)->toContain($component->errors()->first($field));
})->with([
    'an empty name' => ['editSubscriptionName', ''],
    'a name of spaces' => ['editSubscriptionName', '   '],
    'a 256-character name' => ['editSubscriptionName', str_repeat('a', 256)],
    'price 999999.994' => ['editSubscriptionPrice', '999999.994'],
    'price 1000000' => ['editSubscriptionPrice', '1000000'],
    'price -1' => ['editSubscriptionPrice', '-1'],
    'price 1e5' => ['editSubscriptionPrice', '1e5'],
    'price 1e5000' => ['editSubscriptionPrice', '1e5000'],
    'price 9.999' => ['editSubscriptionPrice', '9.999'],
    'price abc' => ['editSubscriptionPrice', 'abc'],
    'an empty price' => ['editSubscriptionPrice', ''],
    'price 1.2.3' => ['editSubscriptionPrice', '1.2.3'],
    'price 1.299.00' => ['editSubscriptionPrice', '1.299.00'],
    'price 1,299.00' => ['editSubscriptionPrice', '1,299.00'],
    'price 0x1A' => ['editSubscriptionPrice', '0x1A'],
    'price NAN' => ['editSubscriptionPrice', 'NAN'],
    'price INF' => ['editSubscriptionPrice', 'INF'],
    'cycle weekly' => ['cycle', 'weekly'],
    'cycle Monthly' => ['cycle', 'Monthly'],
    'an empty cycle' => ['cycle', ''],
    'category Gaming' => ['category', 'Gaming'],
    'category health' => ['category', 'health'],
    'an empty category' => ['category', ''],
    'date not-a-date' => ['editNextPaymentDate', 'not-a-date'],
    'date 2026-02-31' => ['editNextPaymentDate', '2026-02-31'],
    'date 29/10/2026' => ['editNextPaymentDate', '29/10/2026'],
    'an empty date' => ['editNextPaymentDate', ''],
]);

it('shows the errors under the cleared fields, with their names in plain words', function () {
    $component = openEditSheet(editSheetRow('Netflix'))
        ->set('editSubscriptionName', '')
        ->set('editSubscriptionPrice', '')
        ->set('editNextPaymentDate', '')
        ->call('save');
    $page = editSheetHtml($component);
    $errorOf = function (string $field) use ($page) {
        $input = $page->getElementById($field);

        expect($input->getAttribute('aria-invalid'))->toBe('true');

        return trim($page->getElementById($input->getAttribute('aria-describedby'))->textContent);
    };

    expect($errorOf('editSubscriptionName'))->toBe('The name field is required.')
        ->and($errorOf('editSubscriptionPrice'))->toBe('The price field is required.')
        ->and($errorOf('editNextPaymentDate'))->toBe('The next payment date field is required.');
});

// Closing

it('forgets the subscription, the changes and the errors when the sheet closes', function () {
    $component = openEditSheet(editSheetRow('Netflix'))
        ->set('editSubscriptionName', '')
        ->set('cycle', 'yearly')
        ->call('save')
        ->assertHasErrors('editSubscriptionName')
        ->call('clear')
        ->assertHasNoErrors()
        ->assertSet('subscriptionId', null)
        ->assertSet('editSubscriptionName', '')
        ->assertSet('editSubscriptionPrice', '')
        ->assertSet('cycle', '')
        ->assertSet('category', '')
        ->assertSet('editNextPaymentDate', '')
        ->assertSet('isTrial', false);

    expect(editSheetHtml($component)->querySelector('dialog#sheet-subscription form'))->toBeNull()
        ->and(editSheetRow('Netflix')->cycle)->toBe(SubscriptionCycle::Monthly);
});

it('shows the next row\'s values without the last one\'s errors', function () {
    $component = openEditSheet(editSheetRow('Netflix'))
        ->set('editSubscriptionName', '')
        ->call('save')
        ->assertHasErrors('editSubscriptionName')
        ->dispatch('show-subscription', id: editSheetRow('Spotify')->id)
        ->assertHasNoErrors()
        ->assertSet('editSubscriptionName', 'Spotify')
        ->assertSet('editSubscriptionPrice', '6.49')
        ->assertSet('editNextPaymentDate', '2026-10-31');

    expect(editSheetHtml($component)->getElementById('editSubscriptionName')->hasAttribute('aria-invalid'))->toBeFalse();
});

// Deleting

it('deletes only the row, with a toast, and closes', function () {
    $gym = editSheetRow('Gym');

    openEditSheet($gym)
        ->call('deleteSubscription')
        ->assertDispatched('toast', message: 'Gym deleted')
        ->assertDispatched('close-sheet', name: 'subscription')
        ->assertDispatched('subscription-changed', id: $gym->id)
        ->assertDispatched('nav-counts-changed')
        ->assertSet('subscriptionId', null);

    expect(Subscription::find($gym->id))->toBeNull()
        ->and($this->user->subscriptions()->orderBy('name')->pluck('name')->all())->toBe(['Disney+', 'Duolingo Super', 'iCloud+', 'Netflix', 'Spotify']);
});

// Other users and stale sheets

it('gives a 404 and does not open for another user\'s subscription', function () {
    Livewire::test(SubscriptionSheet::class)
        ->dispatch('show-subscription', id: Subscription::factory()->create()->id)
        ->assertNotFound()
        ->assertNotDispatched('open-sheet');
});

it('locks the subscription id against the browser', function () {
    expect(fn () => openEditSheet(editSheetRow('Netflix'))->set('subscriptionId', Subscription::factory()->create()->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('does nothing when no subscription is loaded', function (string $action) {
    $before = editSheetSnapshot();

    Livewire::test(SubscriptionSheet::class)
        ->call($action)
        ->assertHasNoErrors()
        ->assertNotDispatched('toast')
        ->assertNotDispatched('close-sheet')
        ->assertNotDispatched('subscription-changed')
        ->assertNotDispatched('nav-counts-changed');

    expect(editSheetSnapshot())->toBe($before);
})->with(['save', 'deleteSubscription']);

it('does nothing on a second delete', function () {
    $count = Subscription::count();

    openEditSheet(editSheetRow('Gym'))
        ->call('deleteSubscription')
        ->call('deleteSubscription')
        ->assertNotDispatched('toast')
        ->assertNotDispatched('close-sheet')
        ->assertNotDispatched('subscription-changed')
        ->assertNotDispatched('nav-counts-changed');

    expect(Subscription::count())->toBe($count - 1);
});

it('does not bring back a row deleted while the sheet was open', function () {
    $netflix = editSheetRow('Netflix');
    $component = openEditSheet($netflix);

    $netflix->delete();

    $component->set('editSubscriptionName', 'Netflix Premium')
        ->call('save')
        ->assertHasNoErrors()
        ->assertNotDispatched('toast')
        ->assertNotDispatched('close-sheet')
        ->assertNotDispatched('subscription-changed');

    expect(Subscription::find($netflix->id))->toBeNull()
        ->and($this->user->subscriptions()->where('name', 'like', 'Netflix%')->exists())->toBeFalse()
        ->and(editSheetHtml($component)->querySelector('dialog#sheet-subscription form'))->toBeNull();
});

it('has no public property for the cancelled date, the reminder or the owner', function () {
    $properties = array_map(
        fn (ReflectionProperty $property) => $property->getName(),
        array_filter(
            (new ReflectionClass(SubscriptionSheet::class))->getProperties(ReflectionProperty::IS_PUBLIC),
            fn (ReflectionProperty $property) => $property->class === SubscriptionSheet::class,
        ),
    );

    expect(array_values($properties))->toBe(['subscriptionId', 'editSubscriptionName', 'editSubscriptionPrice', 'cycle', 'category', 'editNextPaymentDate', 'isTrial']);
});

// Markup

it('shows a name with markup as text, in the list and in the sheet', function () {
    $raw = '<img src=x onerror=alert(1)>';
    $subscription = Subscription::factory()->for($this->user)->create(['name' => $raw]);

    $page = HTMLDocument::createFromString($this->get('/subscriptions')->assertOk()->getContent(), LIBXML_NOERROR);
    $button = $page->getElementById("subscription-open-{$subscription->id}");
    $component = openEditSheet($subscription)->assertSet('editSubscriptionName', $raw);

    expect($page->querySelector('img'))->toBeNull()
        ->and($button->firstElementChild->textContent)->toBe($raw)
        ->and($button->getAttribute('x-on:click'))->toBe("\$dispatch('show-subscription', { id: {$subscription->id} })")
        ->and(editSheetHtml($component)->querySelector('img'))->toBeNull();
});

it('shares no id with the add-subscription sheet', function () {
    $ids = fn (Testable $component) => array_map(
        fn (Element $element) => $element->getAttribute('id'),
        iterator_to_array(editSheetHtml($component)->querySelectorAll('[id]')),
    );

    // With every field in error, so the error messages' ids count too.
    $edit = $ids(openEditSheet(editSheetRow('Netflix'))
        ->set('editSubscriptionName', '')
        ->set('editSubscriptionPrice', '')
        ->set('cycle', '')
        ->set('category', '')
        ->set('editNextPaymentDate', '')
        ->call('save'));
    $add = $ids(Livewire::test(AddSubscription::class)
        ->set('cycle', '')
        ->set('category', '')
        ->set('nextPaymentDate', 'not-a-date')
        ->call('save'));

    expect($edit)->toContain('sheet-subscription', 'editSubscriptionName', 'editSubscriptionName-error', 'edit-subscription-billing', 'edit-subscription-category', 'editNextPaymentDate-error')
        ->and($add)->toContain('sheet-add-subscription', 'subscriptionName', 'subscriptionName-error', 'add-subscription-billing', 'add-subscription-category')
        ->and(array_intersect($edit, $add))->toBe([]);
});
