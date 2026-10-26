<?php

declare(strict_types=1);

use Composer\InstalledVersions;

// ADR-0023: the C extension decodes with the runtime the classes were generated against. When Dependabot raises
// `google/protobuf`, this fails until docker/php/install-extensions.sh follows, in the same pull request.
it('runs the protobuf extension at the version of the Composer runtime', function (): void {
    expect(extension_loaded('protobuf'))->toBeTrue()
        ->and(phpversion('protobuf'))->toBe(ltrim((string) InstalledVersions::getPrettyVersion('google/protobuf'), 'v'));
});
