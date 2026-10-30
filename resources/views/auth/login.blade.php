<x-layouts::guest title="Log in">
    <div class="font-display text-[30px] leading-none font-semibold tracking-[-0.02em] italic">nook</div>

    <x-page-header eyebrow="LOG IN" subtitle="everything's where you left it">Welcome <em>back</em></x-page-header>

    <div class="rounded-card border border-card-border bg-card-bg p-4">
        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-3.5">
            @csrf

            <x-field label="Email" name="email" type="email" autocomplete="username" :value="old('email')" required autofocus />
            <x-field label="Password" name="password" type="password" autocomplete="current-password" required />

            <input type="hidden" name="remember" value="1">

            <button type="submit" class="min-h-12 w-full cursor-pointer rounded-button bg-accent text-[15px] font-bold text-on-accent">Log in</button>
        </form>
    </div>
</x-layouts::guest>
