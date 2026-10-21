<?php

declare(strict_types=1);

it('builds HTTPS links behind the host proxy, which terminates TLS', function (): void {
    $this->get('/', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'balise.example.org', 'X-Forwarded-Port' => '443'])
        ->assertOk()
        ->assertSee('https://balise.example.org/', false)
        ->assertDontSee('http://balise.example.org/', false);
});
