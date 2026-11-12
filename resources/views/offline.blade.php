{{-- The service worker (resources/js/sw.js) answers with this page when a page can't be loaded. It shows no user data, because the worker keeps it for whoever signs in. The page has no Livewire, so its link is a plain one and "Try again" doesn't need Alpine. --}}
<x-layouts::guest title="Offline">
    <div class="font-display text-[30px] leading-none font-semibold tracking-[-0.02em] italic">nook</div>

    <x-page-header eyebrow="OFFLINE" subtitle="check your signal and try again">Can't reach <em>nook</em></x-page-header>

    <x-card>
        <p class="text-[15px]">This page needs a connection. The grocery list still opens without one, as you last saw it here.</p>

        <div class="mt-3.5 flex flex-col gap-2.5">
            <a href="/groceries" class="flex min-h-12 items-center justify-center rounded-button bg-accent text-[15px] font-bold text-on-accent">Open the grocery list</a>
            <button type="button" onclick="location.reload()" class="min-h-12 cursor-pointer rounded-button border border-cream/40 text-[15px] font-bold text-cream">Try again</button>
        </div>
    </x-card>
</x-layouts::guest>
