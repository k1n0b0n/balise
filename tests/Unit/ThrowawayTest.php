<?php

declare(strict_types=1);

it('fails on purpose to prove the required check (E1.2)', function (): void {
    expect(1 + 1)->toBe(3);
});
