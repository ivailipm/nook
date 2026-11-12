<?php

use App\Actions\CreateSubscription;
use App\Enums\SubscriptionCategory;
use App\Enums\SubscriptionCycle;
use App\Livewire\Sheets\AddSubscription;
use App\Models\Subscription;
use App\Models\User;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function addSubscriptionHtml(Testable $component): HTMLDocument
{
    return HTMLDocument::createFromString($component->html(), LIBXML_NOERROR);
}

/**
 * Each chip row's label and its chips, with the pressed ones marked.
 *
 * @return array<string, list<string>>
 */
function addSubscriptionChips(Testable $component): array
{
    $page = addSubscriptionHtml($component);
    $rows = [];

    foreach ($page->querySelectorAll('[role="group"]') as $group) {
        $label = trim($page->getElementById($group->getAttribute('aria-labelledby'))->textContent);
        $rows[$label] = array_map(
            fn (Element $chip) => trim($chip->textContent).($chip->getAttribute('aria-pressed') === 'true' ? ' (pressed)' : ''),
            iterator_to_array($group->querySelectorAll('button')),
        );
    }

    return $rows;
}

function addSubscriptionTrialToggle(Testable $component): Element
{
    return addSubscriptionHtml($component)->querySelector('button[wire\\:click="$toggle(\'isTrial\')"]');
}

function addSubscriptionForm(): Testable
{
    return Livewire::test(AddSubscription::class)
        ->set('subscriptionName', 'Figma')
        ->set('subscriptionPrice', '9.99');
}

dataset('default chips', ['Monthly and Entertainment pressed' => [[
    'Billing' => ['Monthly (pressed)', 'Yearly'],
    'Category' => ['Entertainment (pressed)', 'Health', 'Storage', 'Education', 'Other'],
]]]);

it('renders the sheet with its defaults', function (array $chips) {
    $component = Livewire::test(AddSubscription::class);
    $page = addSubscriptionHtml($component);
    $dialog = $page->querySelector('dialog#sheet-add-subscription');
    $name = $page->getElementById('subscriptionName');
    $price = $page->getElementById('subscriptionPrice');
    $date = $page->getElementById('nextPaymentDate');
    $toggle = addSubscriptionTrialToggle($component);
    $submit = $dialog->querySelector('button[type="submit"]');

    expect(trim($page->getElementById($dialog->getAttribute('aria-labelledby'))->textContent))->toBe('New subscription')
        ->and($dialog->parentElement->getAttribute('x-on:close.capture'))->toBe('$wire.resetForm()')
        ->and($dialog->querySelector('form')->getAttribute('wire:submit'))->toBe('save')
        ->and(trim($dialog->querySelector('label[for="subscriptionName"]')->textContent))->toBe('Name')
        ->and([$name->getAttribute('wire:model'), $name->getAttribute('placeholder'), $name->getAttribute('maxlength'), $name->getAttribute('value')])
        ->toBe(['subscriptionName', 'Netflix', '255', null])
        ->and(trim($dialog->querySelector('label[for="subscriptionPrice"]')->textContent))->toBe('Price €')
        ->and([$price->getAttribute('wire:model'), $price->getAttribute('inputmode'), $price->getAttribute('placeholder')])
        ->toBe(['subscriptionPrice', 'decimal', '9.99'])
        ->and($price->classList->contains('font-mono'))->toBeTrue()
        ->and(trim($dialog->querySelector('label[for="nextPaymentDate"]')->textContent))->toBe('Next payment')
        ->and([$date->getAttribute('wire:model'), $date->getAttribute('type')])->toBe(['nextPaymentDate', 'date'])
        ->and(addSubscriptionChips($component))->toBe($chips)
        ->and(trim($toggle->textContent))->toBe('Free trial?')
        ->and($toggle->getAttribute('type'))->toBe('button')
        ->and($toggle->getAttribute('aria-pressed'))->toBe('false')
        ->and($dialog->textContent)->toContain('Free trials get a reminder 3 days and 1 day before they turn paid.')
        ->and(trim($submit->textContent))->toBe('Save subscription')
        ->and($submit->getAttribute('class'))->toBe('min-h-12 cursor-pointer rounded-button bg-peach text-[15px] font-bold text-on-peach');

    $component->assertSet('subscriptionName', '')
        ->assertSet('subscriptionPrice', '')
        ->assertSet('cycle', 'monthly')
        ->assertSet('category', 'Entertainment')
        ->assertSet('nextPaymentDate', '2026-11-28')
        ->assertSet('isTrial', false);
})->with('default chips');

it('presses the chips that were picked', function () {
    $component = Livewire::test(AddSubscription::class)
        ->set('cycle', 'yearly')
        ->set('category', 'Other');

    expect(addSubscriptionChips($component))->toBe([
        'Billing' => ['Monthly', 'Yearly (pressed)'],
        'Category' => ['Entertainment', 'Health', 'Storage', 'Education', 'Other (pressed)'],
    ]);
});

it('flips the date label and the toggle with the free trial', function () {
    $component = Livewire::test(AddSubscription::class)->toggle('isTrial')->assertSet('isTrial', true);
    $toggle = addSubscriptionTrialToggle($component);

    expect(trim(addSubscriptionHtml($component)->querySelector('label[for="nextPaymentDate"]')->textContent))->toBe('Trial ends')
        ->and(trim($toggle->textContent))->toBe('Free trial · on')
        ->and($toggle->getAttribute('aria-pressed'))->toBe('true')
        ->and($toggle->classList->contains('bg-peach'))->toBeTrue();

    $component->toggle('isTrial')->assertSet('isTrial', false);
    $toggle = addSubscriptionTrialToggle($component);

    expect(trim(addSubscriptionHtml($component)->querySelector('label[for="nextPaymentDate"]')->textContent))->toBe('Next payment')
        ->and(trim($toggle->textContent))->toBe('Free trial?')
        ->and($toggle->getAttribute('aria-pressed'))->toBe('false');
});

it('saves a subscription for the signed-in user and lands on Subscriptions with a toast', function () {
    $other = User::factory()->create();

    $component = Livewire::test(AddSubscription::class)
        ->set('subscriptionName', '  Figma  ')
        ->set('subscriptionPrice', ' 9,99 ')
        ->set('cycle', 'yearly')
        ->set('category', 'Other')
        ->set('nextPaymentDate', '2026-12-01')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect('/subscriptions');

    $subscription = $this->user->subscriptions()->sole();

    expect([$subscription->name, $subscription->price, $subscription->cycle, $subscription->category])
        ->toBe(['Figma', '9.99', SubscriptionCycle::Yearly, SubscriptionCategory::Other])
        ->and($subscription->next_payment_date->toDateString())->toBe('2026-12-01')
        ->and($subscription->is_trial)->toBeFalse()
        ->and($subscription->trial_reminder)->toBeFalse()
        ->and($subscription->cancelled_at)->toBeNull()
        ->and($other->subscriptions()->count())->toBe(0)
        ->and(session('toast'))->toBe('Subscription saved');

    $component->assertNotDispatched('close-sheet');
});

it('saves a free trial with its own toast and no reminder set', function () {
    addSubscriptionForm()
        ->toggle('isTrial')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect('/subscriptions');

    $subscription = $this->user->subscriptions()->sole();

    expect($subscription->is_trial)->toBeTrue()
        ->and($subscription->trial_reminder)->toBeFalse()
        ->and($subscription->cancelled_at)->toBeNull()
        ->and(session('toast'))->toBe('Trial saved. You will get a reminder before it turns paid');
});

it('reads the price with a comma or a dot and stores two decimals', function (string $price, string $stored) {
    addSubscriptionForm()
        ->set('subscriptionPrice', $price)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->user->subscriptions()->sole()->fresh()->price)->toBe($stored);
})->with([
    'a comma' => ['9,99', '9.99'],
    'a whole number' => ['12', '12.00'],
    'zero' => ['0', '0.00'],
    'the largest price' => ['999999.99', '999999.99'],
    'a dot' => ['9.99', '9.99'],
    'spaces around it' => [' 7 ', '7.00'],
]);

it('uses today + 30 days for a blank date', function () {
    addSubscriptionForm()
        ->set('nextPaymentDate', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->user->subscriptions()->sole()->next_payment_date->toDateString())->toBe('2026-11-28');
});

it('accepts a date in the past', function () {
    addSubscriptionForm()
        ->set('nextPaymentDate', '2026-10-01')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->user->subscriptions()->sole()->next_payment_date->toDateString())->toBe('2026-10-01');
});

it('saves a 255-character name', function () {
    addSubscriptionForm()
        ->set('subscriptionName', str_repeat('a', 255))
        ->call('save')
        ->assertHasNoErrors();

    expect($this->user->subscriptions()->sole()->name)->toBe(str_repeat('a', 255));
});

it('rejects a bad value, saves nothing and stays on the page', function (string $field, string $value) {
    $component = addSubscriptionForm()
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors($field)
        ->assertNoRedirect()
        ->assertNotDispatched('close-sheet');

    expect(Subscription::count())->toBe(0)
        ->and(session('toast'))->toBeNull()
        ->and($component->errors()->first($field))->not->toBe('')
        ->and(addSubscriptionHtml($component)->documentElement->textContent)->toContain($component->errors()->first($field));
})->with([
    'an empty name' => ['subscriptionName', ''],
    'a name of spaces' => ['subscriptionName', '   '],
    'a 256-character name' => ['subscriptionName', str_repeat('a', 256)],
    'price 999999.994' => ['subscriptionPrice', '999999.994'],
    'price 1000000' => ['subscriptionPrice', '1000000'],
    'price -1' => ['subscriptionPrice', '-1'],
    'price 1e5' => ['subscriptionPrice', '1e5'],
    'price 1e5000' => ['subscriptionPrice', '1e5000'],
    'price 9.999' => ['subscriptionPrice', '9.999'],
    'price abc' => ['subscriptionPrice', 'abc'],
    'an empty price' => ['subscriptionPrice', ''],
    'price 1.2.3' => ['subscriptionPrice', '1.2.3'],
    'price 1.299.00' => ['subscriptionPrice', '1.299.00'],
    'price 1,299.00' => ['subscriptionPrice', '1,299.00'],
    'price 0x1A' => ['subscriptionPrice', '0x1A'],
    'price NAN' => ['subscriptionPrice', 'NAN'],
    'price INF' => ['subscriptionPrice', 'INF'],
    'cycle weekly' => ['cycle', 'weekly'],
    'cycle Monthly' => ['cycle', 'Monthly'],
    'an empty cycle' => ['cycle', ''],
    'category Gaming' => ['category', 'Gaming'],
    'category health' => ['category', 'health'],
    'an empty category' => ['category', ''],
    'date not-a-date' => ['nextPaymentDate', 'not-a-date'],
    'date 2026-02-31' => ['nextPaymentDate', '2026-02-31'],
    'date 29/10/2026' => ['nextPaymentDate', '29/10/2026'],
]);

it('shows the errors under their fields, with their names in plain words', function () {
    $component = Livewire::test(AddSubscription::class)
        ->set('nextPaymentDate', 'not-a-date')
        ->call('save');
    $page = addSubscriptionHtml($component);
    $errorOf = function (string $field) use ($page) {
        $input = $page->getElementById($field);

        expect($input->getAttribute('aria-invalid'))->toBe('true');

        return trim($page->getElementById($input->getAttribute('aria-describedby'))->textContent);
    };

    expect($errorOf('subscriptionName'))->toBe('The name field is required.')
        ->and($errorOf('subscriptionPrice'))->toBe('The price field is required.')
        ->and($errorOf('nextPaymentDate'))->toBe('The next payment date field must match the format Y-m-d.');
});

it('has no public property for the cancelled date or the reminder', function () {
    $properties = array_map(
        fn (ReflectionProperty $property) => $property->getName(),
        array_filter(
            (new ReflectionClass(AddSubscription::class))->getProperties(ReflectionProperty::IS_PUBLIC),
            fn (ReflectionProperty $property) => $property->class === AddSubscription::class,
        ),
    );

    expect(array_values($properties))->toBe(['subscriptionName', 'subscriptionPrice', 'cycle', 'category', 'nextPaymentDate', 'isTrial']);
});

it('goes back to the defaults on resetForm', function (array $chips) {
    $component = Livewire::test(AddSubscription::class)
        ->set('subscriptionName', str_repeat('a', 256))
        ->set('subscriptionPrice', 'abc')
        ->set('cycle', 'yearly')
        ->set('category', 'Other')
        ->set('nextPaymentDate', '2026-12-01')
        ->set('isTrial', true)
        ->call('save')
        ->assertHasErrors(['subscriptionName', 'subscriptionPrice'])
        ->call('resetForm')
        ->assertHasNoErrors()
        ->assertSet('subscriptionName', '')
        ->assertSet('subscriptionPrice', '')
        ->assertSet('cycle', 'monthly')
        ->assertSet('category', 'Entertainment')
        ->assertSet('nextPaymentDate', '2026-11-28')
        ->assertSet('isTrial', false);

    expect(addSubscriptionChips($component))->toBe($chips)
        ->and(addSubscriptionHtml($component)->getElementById('subscriptionName')->hasAttribute('aria-invalid'))->toBeFalse()
        ->and(addSubscriptionHtml($component)->getElementById('subscriptionPrice')->hasAttribute('aria-invalid'))->toBeFalse();
})->with('default chips');

it('resets on open and on close', function () {
    $wrapper = addSubscriptionHtml(Livewire::test(AddSubscription::class))->querySelector('body > div');

    expect($wrapper->getAttribute('x-on:open-sheet.window'))->toBe("\$event.detail.name === 'add-subscription' && \$wire.resetForm()")
        ->and($wrapper->getAttribute('x-on:close.capture'))->toBe('$wire.resetForm()');
});

it('works the date out from today on resetForm, for a sheet mounted on an earlier day', function () {
    $component = Livewire::test(AddSubscription::class)->assertSet('nextPaymentDate', '2026-11-28');

    $this->travelTo(Carbon::parse('2026-10-30 12:00', 'Europe/Brussels'));

    $component->call('resetForm')->assertSet('nextPaymentDate', '2026-11-29');
});

it('creates the row with every attribute spelled out', function () {
    $createSubscription = new CreateSubscription;

    $subscription = $createSubscription->handle($this->user, 'Figma', '9.99', SubscriptionCycle::Yearly, SubscriptionCategory::Other, '2026-12-01', true)->fresh();

    expect($subscription->user->is($this->user))->toBeTrue()
        ->and([$subscription->name, $subscription->price, $subscription->cycle, $subscription->category])
        ->toBe(['Figma', '9.99', SubscriptionCycle::Yearly, SubscriptionCategory::Other])
        ->and($subscription->next_payment_date->toDateString())->toBe('2026-12-01')
        ->and($subscription->is_trial)->toBeTrue()
        ->and($subscription->trial_reminder)->toBeFalse()
        ->and($subscription->cancelled_at)->toBeNull()
        ->and($createSubscription->message($subscription))->toBe('Trial saved. You will get a reminder before it turns paid');
});
