@use('App\Enums\Section')

{{-- Each tile links to its section until that section's add sheet exists. --}}
<x-sheet name="add-something" title="Add something">
    <div class="grid grid-cols-2 gap-2.5">
        @foreach ([
            [Section::Groceries, 'Grocery item', 'add to the list', 'bg-[rgb(47_107_72/0.35)]'],
            [Section::Promos, 'Promo', 'photo or screenshot', 'bg-gold/20'],
            [Section::Todo, 'Task', 'with a due date', 'bg-[rgb(107_83_38/0.45)]'],
            [Section::Subscriptions, 'Subscription', 'or a free trial', 'bg-[rgb(17_57_60/0.6)]'],
        ] as [$section, $label, $hint, $background])
            <a href="{{ route($section->value) }}" class="flex min-h-[104px] flex-col items-start gap-2 rounded-[16px] border border-white/10 p-4 text-cream {{ $background }}">
                <x-dynamic-component :component="'lucide-'.$section->icon()" class="size-[22px] stroke-[1.75]" aria-hidden="true" />
                <span class="text-[15px] font-bold">{{ $label }}</span>
                <span class="text-[12px] text-cream/75">{{ $hint }}</span>
            </a>
        @endforeach
    </div>
</x-sheet>
