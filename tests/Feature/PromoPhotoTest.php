<?php

use App\Models\Promo;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(Promo::PHOTO_DISK);

    $this->user = User::factory()->create();
    $this->promo = Promo::factory()->for($this->user)->create(['photo_path' => 'promos/photo.jpg']);

    Storage::disk(Promo::PHOTO_DISK)->put('promos/photo.jpg', 'the stored bytes');
});

it('serves the photo to its owner with safe headers', function () {
    $response = $this->actingAs($this->user)->get(route('promos.photo', $this->promo));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->streamedContent())->toBe('the stored bytes')
        ->and($response->headers->getCacheControlDirective('private'))->toBeTrue()
        ->and($response->headers->getCacheControlDirective('max-age'))->toBe('86400')
        ->and($response->headers->getCacheControlDirective('public'))->toBeNull();
});

it('answers another user with a 404', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('promos.photo', $this->promo))
        ->assertNotFound();
});

it('sends a guest to the login page', function () {
    $this->get(route('promos.photo', $this->promo))->assertRedirect(route('login'));
});

it('answers a 404 for a promo with no photo', function () {
    $promo = Promo::factory()->for($this->user)->create(['photo_path' => null]);

    $this->actingAs($this->user)->get("/promos/{$promo->id}/photo")->assertNotFound();
});

it('answers a 404 when the file is missing from the disk', function () {
    Storage::disk(Promo::PHOTO_DISK)->delete('promos/photo.jpg');

    $this->actingAs($this->user)->get(route('promos.photo', $this->promo))->assertNotFound();
});

it('answers a 404 for an id that does not exist', function (string $id) {
    $this->actingAs($this->user)->get("/promos/{$id}/photo")->assertNotFound();
})->with([
    'unknown' => '999999',
    'not a number' => 'abc',
    'negative' => '-1',
    'too big for a bigint' => '99999999999999999999',
]);

it('names the route and gives its URL through the promo', function () {
    expect($this->promo->photoUrl())->toBe(url("/promos/{$this->promo->id}/photo"))
        ->and(Promo::factory()->create(['photo_path' => null])->photoUrl())->toBeNull();
});
