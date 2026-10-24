@props(['title'])

{{-- The frame of both statements: a skip link, the way back to the map, the statement, the footer. --}}
<x-layouts::app :title="$title.' - balise'" :without-livewire="true">
    <div class="flex min-h-dvh flex-col">
        <a href="#contenu" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-20 focus:rounded focus:bg-white focus:px-3 focus:py-2">
            Aller au contenu
        </a>

        <header class="border-b border-gray-300 px-4 py-3">
            <p class="text-xl font-semibold">balise</p>
            <a href="{{ route('home') }}" class="text-sm underline underline-offset-2 hover:no-underline">Retour à la carte des véhicules</a>
        </header>

        <main id="contenu" tabindex="-1" class="mx-auto w-full max-w-3xl grow px-4 py-6 [&_h2]:mt-8 [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:mt-6 [&_h3]:font-semibold [&_p]:mt-3 [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-6 [&_a]:underline [&_a]:underline-offset-2">
            <h1 class="text-2xl font-semibold">{{ $title }}</h1>
            {{ $slot }}
        </main>

        <x-site-footer />
    </div>
</x-layouts::app>
