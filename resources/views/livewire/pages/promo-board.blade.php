@use('App\Enums\Section')

<div class="flex flex-col gap-4.5 wide:gap-6">
    <x-page-header eyebrow="GROCERIES" subtitle="snapped from this week’s folders">Promo <em>board</em></x-page-header>

    <x-groceries-switch :current="Section::Promos" :list-count="$open" />
</div>
