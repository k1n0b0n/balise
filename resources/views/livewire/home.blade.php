{{--
    Landscape: the text view beside the map. Portrait: once Alpine runs, a side panel over the map that opens and
    closes; without JavaScript it stays under the map.
--}}
<div
    x-data="{
        open: false,
        portrait: () => window.matchMedia('(orientation: portrait)').matches,
        show(target) {
            this.open = true;
            this.$nextTick(() => (target ?? this.$refs.panel).focus());
        },
        hide() {
            this.open = false;
            this.$nextTick(() => this.$refs.toggle.focus());
        },
    }"
    x-bind:data-panel="open ? 'open' : 'closed'"
    x-on:click="if (portrait() && $event.target.closest('#vue-texte [data-open]')) open = false"
    x-on:keydown.escape="if (open && portrait() && $event.target.closest('#vue-texte')) hide()"
    x-on:vehicle-popup-focus-lost.document="show($event.detail)"
    class="group/page flex min-h-dvh flex-col landscape:h-dvh data-panel:h-dvh"
>
    <a href="#vue-texte" x-on:click.prevent="show()" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-20 focus:rounded focus:bg-white focus:px-3 focus:py-2">
        Aller à la liste des véhicules
    </a>

    <header class="flex items-center justify-between gap-4 border-b border-gray-300 px-4 py-3">
        <div>
            <h1 class="text-xl font-semibold">balise</h1>
            <p class="text-sm text-gray-700">Véhicules {{ $networkName }} en circulation</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" hidden data-pause aria-pressed="false" class="rounded border border-gray-500 px-3 py-2 text-sm aria-pressed:bg-gray-900 aria-pressed:text-white">
                Pause<span class="sr-only"> des mises à jour</span>
            </button>
            <button
                type="button"
                x-cloak
                x-ref="toggle"
                x-bind:aria-expanded="open"
                aria-controls="vue-texte"
                x-on:click="open ? hide() : show()"
                class="rounded border border-gray-500 px-3 py-2 text-sm landscape:hidden"
            >
                Liste des véhicules
            </button>
        </div>
    </header>

    <main class="relative flex min-h-0 grow flex-col landscape:flex-row">
        <div
            wire:ignore
            data-map
            data-style="{{ $styleUrl }}"
            data-bounds="{{ json_encode($bounds) }}"
            data-label="Carte des véhicules {{ $networkName }} en circulation"
            data-vehicles-url="{{ route('vehicles', $network) }}"
            data-lines-url="{{ route('lines', $network) }}"
            data-shapes-url="{{ route('shapes', $network) }}"
            data-neutral-colour="{{ $neutralColour }}"
            data-refresh="{{ $refreshSeconds }}"
            class="h-[70vh] w-full shrink-0 group-data-panel/page:h-auto group-data-panel/page:grow landscape:h-auto landscape:min-w-0 landscape:shrink landscape:grow"
        ></div>

        {{-- Rendered here so it reads before JavaScript loads; the map module then keeps it current (ADR-0010). --}}
        <section
            id="vue-texte"
            tabindex="-1"
            aria-labelledby="vue-texte-titre"
            wire:ignore
            data-text-view
            data-timezone="{{ $timezone }}"
            x-ref="panel"
            @class([
                // Positioned, so that the visually hidden texts stay inside the scrolling list rather than lengthen the page.
                'relative px-4 py-6',
                'landscape:w-80 landscape:shrink-0 landscape:overflow-y-auto landscape:border-l landscape:border-gray-300',
                'group-data-panel/page:portrait:absolute group-data-panel/page:portrait:inset-y-0 group-data-panel/page:portrait:right-0 group-data-panel/page:portrait:z-10',
                'group-data-panel/page:portrait:w-[min(22rem,85vw)] group-data-panel/page:portrait:overflow-y-auto group-data-panel/page:portrait:bg-white',
                'group-data-panel/page:portrait:border-l group-data-panel/page:portrait:border-gray-300 group-data-panel/page:portrait:shadow-lg',
                'group-data-[panel=closed]/page:portrait:hidden',
            ])
        >
            <div class="flex items-start justify-between gap-4">
                <h2 id="vue-texte-titre" class="text-lg font-semibold">Liste des véhicules</h2>
                <button type="button" x-cloak x-on:click="hide()" class="rounded border border-gray-500 px-3 py-1 text-sm landscape:hidden">
                    Fermer<span class="sr-only"> la liste des véhicules</span>
                </button>
            </div>

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
                    <button type="button" class="underline underline-offset-2 hover:no-underline" data-open>Véhicule <span data-name></span><span class="sr-only">, <span data-line-name></span>, afficher sur la carte</span></button>,
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
