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
            data-vehicles-url="{{ route('vehicles', $network) }}"
            data-lines-url="{{ route('lines', $network) }}"
            data-neutral-colour="{{ $neutralColour }}"
            data-refresh="{{ $refreshSeconds }}"
            class="h-[70vh] w-full"
        ></div>

        {{-- Rendered here so it reads before JavaScript loads; the map module then keeps it current (ADR-0010). --}}
        <section id="vue-texte" tabindex="-1" aria-labelledby="vue-texte-titre" wire:ignore data-text-view data-timezone="{{ $timezone }}" class="px-4 py-6">
            <h2 id="vue-texte-titre" class="text-lg font-semibold">Liste des véhicules</h2>

            {{-- The only live region, worded to the minute so that screen readers hear it at most once a minute. --}}
            <p role="status" data-status class="text-sm text-gray-700">
                @if ($updatedAt === null)
                    En attente des positions
                @else
                    Mis à jour à {{ $updatedAt->setTimezone($timezone)->format('H:i') }}
                @endif
            </p>

            <div data-lines>
                @forelse ($groups as $group)
                    <div class="mt-4">
                        <h3 class="font-semibold">
                            @if ($group['line'] === null)
                                Ligne inconnue
                            @else
                                {{ $group['line']->mode->label() }} {{ $group['line']->short_name }}{{ $group['line']->long_name === null ? '' : " – {$group['line']->long_name}" }}
                            @endif
                        </h3>
                        <ul class="mt-1 space-y-1">
                            @foreach ($group['vehicles'] as $position)
                                <li>
                                    Véhicule {{ $position->displayId() }},
                                    <time datetime="{{ $position->recorded_at->toIso8601String() }}" class="text-gray-700">relevé à {{ $position->recorded_at->setTimezone($timezone)->format('H:i:s') }}</time>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <p class="mt-4">Aucun véhicule en circulation.</p>
                @endforelse
            </div>

            {{-- Markup the map module copies when it renders lines and vehicles. --}}
            <template data-line-template>
                <div class="mt-4">
                    <h3 class="font-semibold" data-heading></h3>
                    <ul class="mt-1 space-y-1" data-vehicles></ul>
                </div>
            </template>
            <template data-vehicle-template>
                <li>
                    <button type="button" class="underline underline-offset-2 hover:no-underline" data-open>Véhicule <span data-name></span><span class="sr-only">, afficher sur la carte</span></button>,
                    <time class="text-gray-700" data-age></time>
                </li>
            </template>
            <template data-empty-template>
                <p class="mt-4">Aucun véhicule en circulation.</p>
            </template>
        </section>
    </main>

    @vite('resources/js/map.ts')
</div>
