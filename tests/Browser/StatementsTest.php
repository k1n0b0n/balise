<?php

declare(strict_types=1);

beforeEach(function (): void {
    config(['app.repository_url' => 'https://github.com/someone/fork']);
});

it('renders both statements with no axe violation and skips to their content', function (string $path, string $heading): void {
    visit($path)
        ->assertScript("document.querySelector('h1').textContent.includes('".$heading."')", true)
        ->keys('html > body', 'Tab')
        ->assertScript("document.activeElement.getAttribute('href')", '#contenu')
        ->keys('a[href="#contenu"]', 'Enter')
        ->assertScript('document.activeElement.id', 'contenu')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(level: 3);
})->with([
    ['/accessibilite', 'accessibilité'],
    ['/ecoconception', 'écoconception'],
]);
