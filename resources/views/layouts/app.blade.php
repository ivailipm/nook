@use('App\Enums\Section')
@php($section = Section::current())
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

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

            <x-toast />
            <x-add-something />
            <livewire:sheets.add-item />
            <livewire:sheets.promo-sheet />
        </div>
    </body>
</html>
