@props(['title'])
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title }} · nook</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div data-ground="home" class="flex h-dvh overflow-hidden">
            <main class="flex h-full min-w-0 flex-1 flex-col overflow-y-auto px-4.5 py-5 wide:px-12 wide:py-11">
                <div class="m-auto flex w-full max-w-[400px] flex-col gap-4.5 wide:gap-6">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
