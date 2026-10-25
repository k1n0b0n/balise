<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;

// Symfony keeps the trusted hosts in a static property: the next tests run on localhost, even after a failure here.
afterEach(fn () => Request::setTrustedHosts([]));

it('builds HTTPS links behind the host proxy, which terminates TLS', function (): void {
    $this->get('/', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'balise.example.org', 'X-Forwarded-Port' => '443'])
        ->assertOk()
        ->assertSee('https://balise.example.org/', false)
        ->assertDontSee('http://balise.example.org/', false);
});

it('answers only for its own host in production, since every proxy is trusted', function (): void {
    // TrustHosts stands aside in local development and tests.
    $this->app['env'] = 'production';
    config(['app.url' => 'https://balise.example']);

    $this->get('http://elsewhere.example/up')->assertBadRequest();
    $this->get('https://sub.balise.example/up')->assertBadRequest();
    $this->get('https://balise.example/up')->assertOk();
});

it('trusts no host at all in production when APP_URL names none', function (): void {
    $this->app['env'] = 'production';
    config(['app.url' => '']);

    $this->get('https://balise.example/up')->assertBadRequest();
});
