<div class="flex flex-col gap-4.5 wide:gap-6">
    <div class="flex items-center justify-between wide:hidden">
        <span class="font-display text-[22px] font-semibold tracking-[-0.02em] italic">nook</span>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" aria-label="Log out" class="grid size-11 cursor-pointer place-items-center rounded-full border border-white/14 bg-black/25 text-cream">
                <x-lucide-log-out class="size-[18px] stroke-[1.75]" aria-hidden="true" />
            </button>
        </form>
    </div>

    <x-page-header :eyebrow="Str::upper(now()->format('l · j F'))">Welcome <em>home</em></x-page-header>
</div>
