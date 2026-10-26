<?php

declare(strict_types=1);

use App\Models\Station;
use Illuminate\Support\Str;

beforeEach(function (): void {
    foreach (['tbm:QUIN' => ['Quinconces', 44.84478, -0.57361], 'tbm:GARE' => ['Gare Saint-Jean', 44.82581, -0.55639]] as $id => [$name, $latitude, $longitude]) {
        Station::query()->create(['id' => $id, 'network' => 'tbm', 'name' => $name, 'latitude' => $latitude, 'longitude' => $longitude, 'sync_id' => Str::uuid()]);
    }
});

it('finds a station by its name, centres the map on it and says so, without accessibility issues (E7.1)', function (): void {
    visit('/')
        ->assertPresent('[data-map][data-state="loaded"]')
        ->type('#arret', 'quinconces')
        ->keys('#arret', 'Enter')
        // Said to screen readers only: on screen, the map and the popup show it.
        ->assertScript("document.querySelector('[data-station-status]').textContent", 'Carte centrée sur l’arrêt Quinconces.')
        ->assertScript("document.querySelector('[data-station-status]').classList.contains('sr-only')", true)
        ->assertPresent('[data-map][data-station-count="2"]')
        ->assertSeeIn('.station-popup', 'Quinconces')
        // The focus stays in the field, so that the status is what a screen reader says.
        ->assertScript('document.activeElement.id', 'arret')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(level: 3);
});

it('says when no station matches, on screen even after a station was found', function (): void {
    visit('/')
        ->assertPresent('[data-map][data-state="loaded"]')
        ->type('#arret', 'quinconces')
        ->keys('#arret', 'Enter')
        ->assertScript("document.querySelector('[data-station-status]').classList.contains('sr-only')", true)
        ->type('#arret', 'nulle part')
        ->keys('#arret', 'Enter')
        ->assertSeeIn('[data-station-status]', 'Aucun arrêt ne correspond à « nulle part ».')
        ->assertScript("document.querySelector('[data-station-status]').classList.contains('sr-only')", false)
        ->assertNoJavaScriptErrors();
});

it('shows a suggestion as soon as it is chosen, without the button', function (string $event): void {
    $page = visit('/')
        ->assertPresent('[data-map][data-state="loaded"]')
        ->click('#arret')
        ->assertPresent('#arrets option[value="Gare Saint-Jean"]');

    // What a browser sends when a suggestion of the list replaces the text typed.
    $page->script("const input = document.querySelector('#arret'); input.value = 'Gare Saint-Jean'; input.dispatchEvent({$event});");

    $page->assertSeeIn('.station-popup', 'Gare Saint-Jean')
        ->assertNoJavaScriptErrors();
})->with([
    'Firefox' => "new InputEvent('input', { inputType: 'insertReplacementText', bubbles: true })",
    'Chrome and Safari' => "new Event('input', { bubbles: true })",
]);

it('waits for the button when a full name is typed', function (): void {
    visit('/')
        ->assertPresent('[data-map][data-state="loaded"]')
        ->click('#arret')
        ->assertPresent('#arrets option[value="Quinconces"]')
        ->type('#arret', 'Quinconces')
        ->assertMissing('.station-popup')
        ->assertNoJavaScriptErrors();
});

it('opens the popup of a station clicked while another one is open', function (): void {
    // Found by the search, Quinconces sits at the centre of the map; a click there opens it again.
    visit('/')
        ->assertPresent('[data-map][data-state="loaded"]')
        ->type('#arret', 'gare')
        ->keys('#arret', 'Enter')
        ->assertSeeIn('.station-popup', 'Gare Saint-Jean')
        ->type('#arret', 'quinconces')
        ->keys('#arret', 'Enter')
        ->assertSeeIn('.station-popup', 'Quinconces')
        ->wait(1)
        ->click('[data-map] canvas')
        ->assertSeeIn('.station-popup', 'Quinconces')
        ->assertNoJavaScriptErrors();
});
