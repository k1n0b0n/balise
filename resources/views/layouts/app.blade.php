<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite('resources/css/app.css')

        {{-- Pages without a Livewire component, the statements, load none of its script (RGESN 6.5). --}}
        @unless ($withoutLivewire ?? false)
            @livewireStyles
        @endunless
    </head>
    <body class="bg-white text-gray-900 antialiased">
        {{ $slot }}

        @unless ($withoutLivewire ?? false)
            @livewireScripts
        @endunless
    </body>
</html>
