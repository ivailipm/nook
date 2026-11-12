<?php

use App\Enums\Store;
use App\Livewire\Sheets\AddPromo;
use App\Models\GroceryItem;
use App\Models\Promo;
use App\Models\User;
use Database\Seeders\GroceryItemSeeder;
use Database\Seeders\PromoSeeder;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    Storage::fake(Promo::PHOTO_DISK);
    Storage::fake('tmp-for-tests');

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function addPromoHtml(Testable $component): HTMLDocument
{
    return HTMLDocument::createFromString($component->html(), LIBXML_NOERROR);
}

/**
 * Each chip row's label and its chips, with the pressed ones marked.
 *
 * @return array<string, list<string>>
 */
function addPromoChips(Testable $component): array
{
    $page = addPromoHtml($component);
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

function jpegUpload(string $name = 'deal.jpg', int $width = 40, int $height = 30): UploadedFile
{
    ob_start();
    imagejpeg(imagecreatetruecolor($width, $height));

    return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean())->mimeType('image/jpeg');
}

function addPromoForm(): Testable
{
    return Livewire::test(AddPromo::class);
}

/**
 * Every file in Livewire's temporary upload folder, its .json files included.
 *
 * @return list<string>
 */
function temporaryUploads(): array
{
    return FileUploadConfiguration::storage()->allFiles(FileUploadConfiguration::path());
}

it('opens with Lidl, today, today + 5 and None', function () {
    addPromoForm()
        ->assertSet('store', 'Lidl')
        ->assertSet('validFrom', '2026-10-29')
        ->assertSet('validUntil', '2026-11-03')
        ->assertSet('linkItem', null)
        ->assertSet('photo', null)
        ->assertSet('product', '')
        ->assertSet('deal', '')
        ->assertSet('note', '');
});

it('renders the form from the prototype', function () {
    $page = addPromoHtml(addPromoForm());
    $dialog = $page->querySelector('dialog#sheet-add-promo');
    $form = $dialog->querySelector('form');
    $field = fn (string $id) => $page->getElementById($id);

    expect(trim($page->getElementById($dialog->getAttribute('aria-labelledby'))->textContent))->toBe('Pin a deal')
        ->and($form->getAttribute('wire:submit'))->toBe('save')
        ->and($field('validFrom')->getAttribute('type'))->toBe('date')
        ->and($field('validUntil')->getAttribute('type'))->toBe('date')
        ->and(trim($page->querySelector('label[for="validUntil"]')->textContent))->toBe('Valid until *')
        ->and($field('product')->getAttribute('placeholder'))->toBe('Coffee beans 1kg')
        ->and($field('deal')->getAttribute('placeholder'))->toBe('2+1 free · €3.49')
        ->and($field('note')->getAttribute('placeholder'))->toBe('€2.09 instead of €2.99')
        ->and($page->querySelector('[name="tone"]'))->toBeNull();
});

it('styles the date fields and keeps the gold border for Valid until', function () {
    $page = addPromoHtml(addPromoForm());

    foreach (['validFrom', 'validUntil'] as $id) {
        expect(iterator_to_array($page->getElementById($id)->classList))->toContain('font-mono', 'text-[15px]', '[color-scheme:dark]', 'px-3')
            ->and(iterator_to_array($page->getElementById($id)->classList))->not->toContain('px-3.5', 'text-[16px]');
    }

    expect($page->getElementById('validUntil')->classList->contains('border-gold'))->toBeTrue()
        ->and($page->getElementById('validUntil')->classList->contains('border-input-border'))->toBeFalse()
        ->and($page->getElementById('validFrom')->classList->contains('border-input-border'))->toBeTrue()
        ->and($page->getElementById('product')->getAttribute('class'))->toBe('min-h-[46px] w-full rounded-[12px] border bg-black/30 px-3.5 text-[16px] border-input-border');
});

it('lets an error border win over the gold one', function () {
    $input = addPromoHtml(addPromoForm()->set('validUntil', '')->call('save'))->getElementById('validUntil');

    expect($input->classList->contains('border-destructive-border'))->toBeTrue()
        ->and($input->classList->contains('border-gold'))->toBeFalse()
        ->and($input->getAttribute('aria-invalid'))->toBe('true')
        ->and($input->getAttribute('aria-describedby'))->toBe('validUntil-error');
});

it('shows the drop zone with its hidden file input and a disabled read button', function () {
    $page = addPromoHtml(addPromoForm());
    $input = $page->querySelector('input[type="file"]');
    $zone = $input->closest('label');
    $read = $page->querySelector('button[disabled]');

    expect($input->getAttribute('accept'))->toBe('image/*')
        ->and($input->getAttribute('wire:model'))->toBe('photo')
        ->and($input->classList->contains('sr-only'))->toBeTrue()
        ->and($zone->classList->contains('has-[input:focus-visible]:outline-peach'))->toBeTrue()
        ->and($zone->textContent)->toContain('Upload a photo or screenshot', 'On Android you can also Share a screenshot straight to nook.')
        ->and($zone->querySelector('svg[aria-hidden="true"]'))->not->toBeNull()
        ->and($zone->querySelector('img'))->toBeNull()
        ->and(trim($read->textContent))->toBe('Read from photo · coming later')
        ->and($read->querySelector('svg[aria-hidden="true"]'))->not->toBeNull();
});

it('shows Uploading while the photo uploads', function () {
    $status = addPromoHtml(addPromoForm())->querySelector('label [wire\:loading]');

    expect(trim($status->textContent))->toBe('Uploading…')
        ->and($status->getAttribute('wire:target'))->toBe('photo');
});

it('disables the submit button while the photo uploads or the form saves', function () {
    $submit = addPromoHtml(addPromoForm())->querySelector('button[type="submit"]');

    expect($submit->hasAttribute('wire:loading.attr'))->toBeTrue()
        ->and($submit->getAttribute('wire:loading.attr'))->toBe('disabled')
        ->and($submit->getAttribute('wire:target'))->toBe('photo,save')
        ->and(trim($submit->textContent))->toBe('Pin to promo board');
});

it('resets on open and on close', function () {
    $wrapper = addPromoHtml(addPromoForm())->querySelector('body > div');

    expect($wrapper->getAttribute('x-on:open-sheet.window'))->toBe("\$event.detail.name === 'add-promo' && \$wire.resetForm()")
        ->and($wrapper->getAttribute('x-on:close.capture'))->toBe("\$wire.\$cancelUpload('photo'); \$wire.resetForm()");
});

it('offers None and the open items without an active promo', function () {
    app(GroceryItemSeeder::class)->run($this->user);
    app(PromoSeeder::class)->run($this->user);

    expect(addPromoChips(addPromoForm()))->toBe([
        'Store *' => ['Aldi', 'Lidl (pressed)', 'Delhaize', 'Colruyt', 'Other'],
        'Link to a list item' => ['None (pressed)', 'Oat milk', 'Spinach'],
    ]);
});

it('offers at most 6 items, oldest first', function () {
    foreach (range(1, 8) as $number) {
        GroceryItem::factory()->for($this->user)->create(['name' => "Item {$number}", 'done' => false, 'promo_id' => null]);
    }

    expect(addPromoChips(addPromoForm())['Link to a list item'])
        ->toBe(['None (pressed)', 'Item 1', 'Item 2', 'Item 3', 'Item 4', 'Item 5', 'Item 6']);
});

it('offers an item whose promo has ended, and never another user\'s', function () {
    $ended = Promo::factory()->for($this->user)->create(['valid_until' => today()->subDay()]);
    GroceryItem::factory()->for($this->user)->create(['name' => 'Old deal', 'done' => false, 'promo_id' => $ended->id]);
    GroceryItem::factory()->create(['name' => 'Their item', 'done' => false, 'promo_id' => null]);

    expect(addPromoChips(addPromoForm())['Link to a list item'])->toBe(['None (pressed)', 'Old deal']);
});

it('picks up a new item when the component refreshes', function () {
    $component = addPromoForm();

    GroceryItem::factory()->for($this->user)->create(['name' => 'New item', 'done' => false, 'promo_id' => null]);

    expect(addPromoChips($component->call('$refresh'))['Link to a list item'])->toBe(['None (pressed)', 'New item']);
});

it('sets the link from a chip and presses it', function () {
    $item = GroceryItem::factory()->for($this->user)->create(['name' => 'Oat milk', 'done' => false, 'promo_id' => null]);
    $component = addPromoForm();
    $chip = addPromoHtml($component)->querySelectorAll('[aria-labelledby="add-promo-link"] button')->item(1);

    expect($chip->getAttribute('wire:click'))->toBe("\$set('linkItem', {$item->id})")
        ->and(addPromoChips($component->set('linkItem', $item->id))['Link to a list item'])->toBe(['None', 'Oat milk (pressed)']);
});

it('saves every field for the signed-in user and lands on the board with a toast', function () {
    $component = addPromoForm()
        ->set('photo', jpegUpload())
        ->set('store', 'Aldi')
        ->set('product', '  Coffee beans 1kg  ')
        ->set('deal', ' 2+1 free ')
        ->set('note', ' €11.98 for three ')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect('/promos');

    $promo = $this->user->promos()->sole();

    expect([$promo->store, $promo->product, $promo->deal, $promo->note])->toBe([Store::Aldi, 'Coffee beans 1kg', '2+1 free', '€11.98 for three'])
        ->and($promo->valid_from->toDateString())->toBe('2026-10-29')
        ->and($promo->valid_until->toDateString())->toBe('2026-11-03')
        ->and($promo->tone)->toBe(0)
        ->and($promo->photo_path)->toMatch('#^promos/[0-9a-f-]{36}\.jpg$#')
        ->and(getimagesizefromstring(Storage::disk(Promo::PHOTO_DISK)->get($promo->photo_path))['mime'])->toBe('image/jpeg')
        ->and(session('toast'))->toBe('Pinned to the board · until Tue');

    $component->assertNotDispatched('close-sheet');
});

it('takes the next tone from the hook, whatever the request sends', function () {
    Promo::factory()->for($this->user)->create(['tone' => 2]);

    addPromoForm()->call('save')->assertHasNoErrors();

    expect($this->user->promos()->orderByDesc('id')->firstOrFail()->tone)->toBe(3);
});

it('needs only a store and an until date', function () {
    addPromoForm()->call('save')->assertHasNoErrors()->assertRedirect('/promos');

    $promo = $this->user->promos()->sole();

    expect([$promo->store, $promo->product, $promo->deal, $promo->note, $promo->photo_path])->toBe([Store::Lidl, 'Lidl deal', 'Deal', null, null]);
});

it('treats a blank product, deal or note as empty', function () {
    addPromoForm()->set('store', 'Colruyt')->set('product', '   ')->set('deal', '  ')->set('note', ' ')->call('save')->assertHasNoErrors();

    $promo = $this->user->promos()->sole();

    expect([$promo->product, $promo->deal, $promo->note])->toBe(['Colruyt deal', 'Deal', null]);
});

it('reads a blank valid from as today', function () {
    addPromoForm()->set('validFrom', '')->call('save')->assertHasNoErrors();

    expect($this->user->promos()->sole()->valid_from->toDateString())->toBe('2026-10-29');
});

it('toasts how long the deal runs', function (string $from, string $until, string $toast) {
    addPromoForm()->set('validFrom', $from)->set('validUntil', $until)->call('save')->assertHasNoErrors();

    expect(session('toast'))->toBe($toast);
})->with([
    'today' => ['2026-10-29', '2026-10-29', 'Pinned to the board · until today'],
    'tomorrow' => ['2026-10-29', '2026-10-30', 'Pinned to the board · until tomorrow'],
    'a weekday' => ['2026-10-29', '2026-11-03', 'Pinned to the board · until Tue'],
    'a date' => ['2026-10-29', '2026-11-10', 'Pinned to the board · until 10 Nov'],
    'already over' => ['2026-10-20', '2026-10-27', 'Pinned to the board · already expired'],
]);

it('rejects a missing or malformed until date', function (string $until) {
    addPromoForm()->set('validUntil', $until)->call('save')->assertHasErrors('validUntil')->assertNoRedirect();

    expect(Promo::count())->toBe(0);
})->with(['missing' => '', 'not a date' => 'soon', 'another format' => '03/11/2026', 'no such day' => '2026-02-31']);

it('rejects a malformed from date', function () {
    addPromoForm()->set('validFrom', 'soon')->call('save')->assertHasErrors('validFrom')->assertNoRedirect();

    expect(Promo::count())->toBe(0);
});

it('rejects an until date before the from date with its own message', function () {
    $component = addPromoForm()->set('validFrom', '2026-11-05')->set('validUntil', '2026-11-04')->call('save')->assertHasErrors('validUntil');
    $page = addPromoHtml($component);

    expect(trim($page->getElementById('validUntil-error')->textContent))->toBe("Valid until can't be before valid from.")
        ->and(Promo::count())->toBe(0);
});

it('accepts an until date on the from date', function () {
    addPromoForm()->set('validFrom', '2026-11-05')->set('validUntil', '2026-11-05')->call('save')->assertHasNoErrors();
});

it('rejects a product, deal or note over 255 characters, under its field', function (string $field) {
    $component = addPromoForm()->set($field, str_repeat('a', 256))->call('save')->assertHasErrors([$field => 'max'])->assertNoRedirect();
    $input = addPromoHtml($component)->getElementById($field);

    expect($input->getAttribute('aria-invalid'))->toBe('true')
        ->and($input->getAttribute('aria-describedby'))->toBe("{$field}-error")
        ->and(Promo::count())->toBe(0);
})->with(['product', 'deal', 'note']);

it('rejects a store outside the enum', function (string $store) {
    addPromoForm()->set('store', $store)->call('save')->assertHasErrors('store')->assertNoRedirect();

    expect(Promo::count())->toBe(0);
})->with(['Tesco', '', 'lidl']);

it('rejects another user\'s item, a ticked item and an unknown one', function (Closure $item) {
    $id = $item($this->user);

    addPromoForm()->set('linkItem', $id)->call('save')->assertHasErrors('linkItem')->assertNoRedirect();

    expect(Promo::count())->toBe(0)
        ->and(GroceryItem::whereNotNull('promo_id')->count())->toBe(0);
})->with([
    'another user\'s' => fn (User $user) => GroceryItem::factory()->create(['done' => false, 'promo_id' => null])->id,
    'ticked' => fn (User $user) => GroceryItem::factory()->for($user)->create(['done' => true, 'promo_id' => null])->id,
    'unknown' => fn (User $user) => 999999,
]);

it('links the chosen item and leaves its store alone', function () {
    $item = GroceryItem::factory()->for($this->user)->create(['store' => Store::Aldi, 'done' => false, 'promo_id' => null]);
    $other = GroceryItem::factory()->for($this->user)->create(['done' => false, 'promo_id' => null]);

    addPromoForm()->set('store', 'Colruyt')->set('linkItem', $item->id)->call('save')->assertHasNoErrors();

    expect($item->fresh()->promo_id)->toBe($this->user->promos()->sole()->id)
        ->and($item->fresh()->store)->toBe(Store::Aldi)
        ->and($other->fresh()->promo_id)->toBeNull();
});

it('does not link anything with None', function () {
    GroceryItem::factory()->for($this->user)->create(['name' => 'Lidl stuff', 'done' => false, 'promo_id' => null]);

    addPromoForm()->call('save')->assertHasNoErrors();

    expect(GroceryItem::whereNotNull('promo_id')->count())->toBe(0);
});

it('shows a preview of a valid photo', function () {
    $component = addPromoForm()->set('photo', jpegUpload())->assertHasNoErrors('photo');
    $zone = addPromoHtml($component)->querySelector('input[type="file"]')->closest('label');
    $preview = $zone->querySelector('img');

    expect($preview->getAttribute('alt'))->toBe('Selected deal photo')
        ->and($preview->getAttribute('src'))->toContain('preview-file')
        ->and($preview->classList->contains('max-h-40'))->toBeTrue()
        ->and($zone->textContent)->toContain('Tap to change the photo')
        ->and($zone->textContent)->not->toContain('Upload a photo or screenshot')
        ->and($zone->querySelector('svg'))->toBeNull();
});

it('rejects a file that is not a JPEG, PNG or WebP at once, and clears it', function (UploadedFile $file) {
    $component = addPromoForm()->set('photo', $file)->assertHasErrors('photo')->assertSet('photo', null);
    $page = addPromoHtml($component);

    expect(trim($page->getElementById('photo-error')->textContent))->toBe('The photo must be a JPEG, PNG or WebP.')
        ->and($page->querySelector('input[type="file"]')->getAttribute('aria-invalid'))->toBe('true')
        ->and($page->querySelector('input[type="file"]')->getAttribute('aria-describedby'))->toBe('photo-error')
        ->and($page->querySelector('label img'))->toBeNull()
        ->and(temporaryUploads())->toBe([]);

    // A later request has no broken preview to render.
    expect(addPromoChips($component->set('store', 'Aldi'))['Store *'])->toContain('Aldi (pressed)');
})->with([
    'pdf' => fn () => UploadedFile::fake()->create('deal.pdf', 100, 'application/pdf'),
    'svg' => fn () => UploadedFile::fake()->create('deal.svg', 10, 'image/svg+xml'),
    'text' => fn () => UploadedFile::fake()->create('deal.txt', 10, 'text/plain'),
    'gif' => fn () => UploadedFile::fake()->create('deal.gif', 10, 'image/gif'),
]);

it('rejects a file over 12 MB', function () {
    addPromoForm()
        ->set('photo', UploadedFile::fake()->create('big.jpg', 12289, 'image/jpeg'))
        ->assertHasErrors('photo')
        ->assertSet('photo', null);
});

it('clears the photo error when a good file follows', function () {
    addPromoForm()
        ->set('photo', UploadedFile::fake()->create('deal.pdf', 100, 'application/pdf'))
        ->assertHasErrors('photo')
        ->set('photo', jpegUpload())
        ->assertHasNoErrors('photo');
});

it('turns a file that passes the mime check but does not decode into an error, and drops it', function () {
    $component = addPromoForm()
        ->set('photo', UploadedFile::fake()->createWithContent('fake.jpg', "\xFF\xD8\xFF\xE0garbage")->mimeType('image/jpeg'))
        ->assertHasNoErrors('photo')
        ->call('save')
        ->assertHasErrors('photo')
        ->assertNoRedirect()
        ->assertSet('photo', null);

    expect(Promo::count())->toBe(0)
        ->and(Storage::disk(Promo::PHOTO_DISK)->allFiles())->toBe([])
        ->and(temporaryUploads())->toBe([])
        ->and(addPromoHtml($component)->querySelector('label img'))->toBeNull();
});

it('keeps the photo when another field fails', function () {
    addPromoForm()
        ->set('photo', jpegUpload())
        ->set('validUntil', '')
        ->call('save')
        ->assertHasErrors('validUntil')
        ->assertSet('photo', fn ($photo) => $photo !== null);

    expect(temporaryUploads())->not->toBe([]);
});

it('deletes the temporary upload and its .json file after saving', function () {
    $component = addPromoForm()->set('photo', jpegUpload());

    expect(temporaryUploads())->toHaveCount(2);

    $component->call('save')->assertHasNoErrors();

    expect(temporaryUploads())->toBe([]);
});

it('deletes the previous temporary upload when the photo changes', function () {
    addPromoForm()->set('photo', jpegUpload('first.jpg'))->set('photo', jpegUpload('second.jpg'))->assertHasNoErrors();

    expect(temporaryUploads())->toHaveCount(2);
});

it('resets to today\'s defaults, without the photo or errors, when the sheet closes', function () {
    $item = GroceryItem::factory()->for($this->user)->create(['done' => false, 'promo_id' => null]);

    $component = addPromoForm()
        ->set('photo', jpegUpload())
        ->set('store', 'Colruyt')
        ->set('product', 'Butter')
        ->set('deal', '1+1')
        ->set('note', 'A note')
        ->set('linkItem', $item->id)
        ->set('validFrom', '2026-11-05')
        ->set('validUntil', '2026-11-04')
        ->call('save')
        ->assertHasErrors('validUntil');

    $this->travelTo(Carbon::parse('2026-10-30 09:00', 'Europe/Brussels'));

    $component->call('resetForm')
        ->assertHasNoErrors()
        ->assertSet('photo', null)
        ->assertSet('store', 'Lidl')
        ->assertSet('product', '')
        ->assertSet('deal', '')
        ->assertSet('note', '')
        ->assertSet('linkItem', null)
        ->assertSet('validFrom', '2026-10-30')
        ->assertSet('validUntil', '2026-11-04');

    expect(temporaryUploads())->toBe([]);
});
