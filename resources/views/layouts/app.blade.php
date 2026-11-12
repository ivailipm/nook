@use('App\Enums\Section')
@php($section = Section::current())
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#190a0d">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

        <title>{{ $title }} · nook</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div data-ground="{{ $section->ground() }}" class="relative flex h-dvh overflow-hidden">
            <x-nav.sidebar :current="$section" />

            <main class="h-full min-w-0 flex-1 overflow-y-auto px-4.5 pt-5 pb-30 wide:px-12 wide:pt-11 wide:pb-14">
                <div class="mx-auto flex max-w-[1120px] flex-col gap-4.5 wide:gap-6">
                    {{ $slot }}
                </div>
            </main>

            <x-nav.floating :current="$section" />

            {{-- A navigation carries this block over as it is (the same toast, sheets and Livewire components), while the rest of the page comes fresh from the server. resources/js/app.js writes each new page's title into the live region. --}}
            @persist('overlays')
                <x-toast />
                <div id="page-announcer" aria-live="polite" class="sr-only"></div>
                <x-add-something />
                <livewire:sheets.add-item />
                <livewire:sheets.promo-sheet />
                <livewire:sheets.add-promo />
                <livewire:sheets.stores />
                <livewire:sheets.add-task />
                <livewire:sheets.task-sheet />
                <livewire:sheets.tags />
                <livewire:sheets.add-subscription />
                <livewire:sheets.subscription-sheet />
            @endpersist
        </div>
    </body>
</html>
