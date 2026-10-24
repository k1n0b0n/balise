<?php

declare(strict_types=1);

use App\Support\Statements;
use Livewire\Livewire;

beforeEach(function (): void {
    config(['app.repository_url' => 'https://github.com/someone/fork']);
    // Livewire injects its script after any page with a component, and remembers it within one test process.
    Livewire::flushState();
});

it('publishes the accessibility statement from the self-audit, without JavaScript', function (): void {
    $statement = app(Statements::class)->accessibility();

    $this->get('/accessibilite')
        ->assertOk()
        ->assertSee('<title>Déclaration d&#039;accessibilité - balise</title>', escape: false)
        ->assertSee("balise est <strong>{$statement['status']}</strong> avec le RGAA 4.1.2", escape: false)
        ->assertSee("révèle que {$statement['rate']}&nbsp;% des critères applicables", escape: false)
        ->assertSee('auditeur indépendant')
        ->assertDontSee('@if')
        ->assertSee('Défenseur des droits')
        ->assertSee('href="https://github.com/someone/fork/issues/new/choose"', escape: false)
        ->assertDontSee('livewire.js');
});

it('publishes the ecodesign statement from the self-assessment, without JavaScript', function (): void {
    $statement = app(Statements::class)->ecodesign();

    $this->get('/ecoconception')
        ->assertOk()
        ->assertSee("{$statement['score']}&nbsp;% des critères applicables sont validés", escape: false)
        ->assertSee("Critères non validés et ce qui est prévu ({$statement['counts']['NS']})", escape: false)
        ->assertSee('<span lang="en">Power Usage Effectiveness</span>', escape: false)
        ->assertSee('href="https://github.com/someone/fork/issues/new/choose"', escape: false)
        ->assertDontSee('livewire.js');
});

it('links both statements from every page, with the conformance status', function (string $path): void {
    $status = app(Statements::class)->accessibility()['status'];

    $this->get($path)
        ->assertSee('<a href="'.route('accessibility').'" class="underline underline-offset-2 hover:no-underline">Accessibilité : '.$status.'</a>', escape: false)
        ->assertSee('<a href="'.route('ecodesign').'" class="underline underline-offset-2 hover:no-underline">Écoconception</a>', escape: false);
})->with(['/', '/accessibilite', '/ecoconception']);

it('counts a criterion failing on one page as failing, and computes the rate on applicable criteria', function (): void {
    $statement = app(Statements::class)->accessibility();

    expect(array_sum($statement['counts']))->toBe(106)
        ->and($statement['rate'])->toBe(intdiv(100 * $statement['counts']['C'], $statement['counts']['C'] + $statement['counts']['NC']));
});
