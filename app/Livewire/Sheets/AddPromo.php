<?php

namespace App\Livewire\Sheets;

use App\Actions\PinPromo;
use App\Enums\Store;
use App\Models\GroceryItem;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The "Pin a deal" sheet, rendered once in the layout and opened by the snap card, the Promo board FAB and the chooser.
 */
class AddPromo extends Component
{
    use WithFileUploads;

    /**
     * A temporary upload that has passed validation. It still holds the original's EXIF, so it never outlives the form.
     */
    public ?TemporaryUploadedFile $photo = null;

    public string $store = Store::Lidl->value;

    public string $validFrom = '';

    public string $validUntil = '';

    public string $product = '';

    public string $deal = '';

    public string $note = '';

    public ?int $linkItem = null;

    public function mount(): void
    {
        $this->validFrom = today()->toDateString();
        $this->validUntil = today()->addDays(5)->toDateString();
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'photo' => ['nullable', 'file', 'mimes:jpeg,png,webp', 'max:12288'],
            'store' => ['required', Rule::in(auth()->user()->storeNames())],
            'validFrom' => ['date_format:Y-m-d'],
            'validUntil' => ['required', 'date_format:Y-m-d', 'after_or_equal:validFrom'],
            'product' => ['string', 'max:255'],
            'deal' => ['string', 'max:255'],
            'note' => ['string', 'max:255'],
            'linkItem' => ['nullable', Rule::exists('grocery_items', 'id')->where('user_id', auth()->id())->where('done', false)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'photo.mimes' => 'The photo must be a JPEG, PNG or WebP.',
            'validUntil.after_or_equal' => "Valid until can't be before valid from.",
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['validFrom' => 'valid from', 'validUntil' => 'valid until', 'linkItem' => 'list item'];
    }

    public function updatingPhoto(): void
    {
        $this->discardPhoto();
    }

    /**
     * Validates a new photo at once. A bad file goes straight away, because the preview can't render it.
     */
    public function updatedPhoto(): void
    {
        try {
            $this->validateOnly('photo');
        } catch (ValidationException $exception) {
            $this->discardPhoto();

            throw $exception;
        }
    }

    public function save(PinPromo $pinPromo): void
    {
        if (blank($this->validFrom)) {
            $this->validFrom = today()->toDateString();
        }

        $this->validate();

        try {
            $promo = $pinPromo->handle(
                auth()->user(),
                $this->store,
                $this->validFrom,
                $this->validUntil,
                $this->product,
                $this->deal,
                $this->note,
                $this->linkItem,
                $this->photo,
            );
        } catch (ValidationException $exception) {
            // It passed the mime check but isn't a usable image, so it can't stay as the preview.
            $this->discardPhoto();

            throw $exception;
        }

        $this->discardPhoto();

        session()->flash('toast', $pinPromo->message($promo));

        $this->redirectRoute('promos');
    }

    /**
     * A store removed in the Your stores sheet while it was picked falls back to Lidl, as its items moved to Other.
     */
    #[On('stores-changed')]
    public function keepStore(): void
    {
        if (! in_array($this->store, auth()->user()->storeNames(), true)) {
            $this->store = Store::Lidl->value;
        }
    }

    /**
     * Back to an empty form with today's dates, without errors or an upload. The view calls this whenever the sheet opens or closes.
     */
    public function resetForm(): void
    {
        $this->discardPhoto();
        $this->reset();
        $this->resetValidation();
        $this->mount();
    }

    public function render(): View
    {
        return view('livewire.sheets.add-promo', [
            'stores' => auth()->user()->storeNames(),
            'linkOptions' => auth()->user()->groceryItems()->with('promo')->where('done', false)->orderBy('id')->get()
                ->reject(fn (GroceryItem $item) => $item->hasActivePromo())
                ->take(6),
        ]);
    }

    /**
     * Deletes the temporary upload and the .json file Livewire keeps next to it with the client's filename.
     */
    private function discardPhoto(): void
    {
        if ($this->photo === null) {
            return;
        }

        $this->photo->delete();
        FileUploadConfiguration::storage()->delete(FileUploadConfiguration::path($this->photo->getFilename().'.json'));

        $this->photo = null;
    }
}
