<?php

declare(strict_types=1);

use App\Livewire\Home;

it('renders the home page as a Livewire component', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSeeLivewire(Home::class);
});
