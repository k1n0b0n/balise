<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Vite;

beforeEach(function (): void {
    // Browser tests run against the built assets (`npm run build`), not the Vite dev server.
    Vite::useHotFile(storage_path('framework/no-hot-file'));
});

it('shows the map without accessibility issues', function (): void {
    visit('/')
        ->assertPresent('[data-map][data-state="loaded"] canvas')
        ->assertScript('document.documentElement.lang', 'fr')
        ->assertAttribute('[data-map] canvas', 'aria-label', 'Carte des véhicules TBM en circulation')
        ->assertPresent('button[aria-label="Zoom avant"]')
        ->assertNoJavaScriptErrors()
        // Level 3 counts every axe violation, down to minor ones (PRD: no axe violation).
        ->assertNoAccessibilityIssues(level: 3);
});

it('lets keyboard users skip the map to the text view', function (): void {
    visit('/')
        ->keys('html > body', 'Tab')
        ->assertScript("document.activeElement.getAttribute('href')", '#vue-texte')
        ->keys('a[href="#vue-texte"]', 'Enter')
        ->assertScript('document.activeElement.id', 'vue-texte')
        ->assertPresent('#vue-texte h2');
});
