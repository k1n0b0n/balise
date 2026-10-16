<div class="flex min-h-screen flex-col">
    <a href="#vue-texte" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-10 focus:rounded focus:bg-white focus:px-3 focus:py-2">
        Aller à la liste des véhicules
    </a>

    <header class="border-b border-gray-300 px-4 py-3">
        <h1 class="text-xl font-semibold">balise</h1>
        <p class="text-sm text-gray-700">Véhicules {{ $networkName }} en circulation</p>
    </header>

    <main class="flex grow flex-col">
        <div
            wire:ignore
            data-map
            data-style="{{ $styleUrl }}"
            data-bounds="{{ json_encode($bounds) }}"
            data-label="Carte des véhicules {{ $networkName }} en circulation"
            class="h-[70vh] w-full"
        ></div>

        <section id="vue-texte" tabindex="-1" aria-labelledby="vue-texte-titre" class="px-4 py-6">
            <h2 id="vue-texte-titre" class="text-lg font-semibold">Liste des véhicules</h2>
            <p class="text-gray-700">Disponible prochainement.</p>
        </section>
    </main>

    @vite('resources/js/map.ts')
</div>
