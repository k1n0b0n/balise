<?php

declare(strict_types=1);

/**
 * Keys of .env.example the host's app environment leaves to the defaults of config/ on purpose (ADR-0020).
 */
const KEYS_LEFT_TO_DEFAULTS = [
    // Development only; replays are refused in production (E5.2).
    'APP_FAKER_LOCALE', 'VITE_APP_NAME', 'RECORDINGS_SOURCE_URL',
    // Defaults that suit the server; LOG_STACK is unused, since containers log to stderr.
    'BCRYPT_ROUNDS', 'LOG_STACK', 'LOG_DEPRECATIONS_CHANNEL', 'SESSION_ENCRYPT', 'SESSION_PATH', 'SESSION_DOMAIN', 'REDIS_PASSWORD',
    // Services the application does not use: mail goes to the log, no Memcached, no S3.
    'MAIL_SCHEME', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
    'MEMCACHED_HOST', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_DEFAULT_REGION', 'AWS_BUCKET', 'AWS_USE_PATH_STYLE_ENDPOINT',
];

/**
 * @return list<string>
 */
function envKeys(string $file): array
{
    preg_match_all('/^([A-Z][A-Z0-9_]*)=/m', (string) file_get_contents(dirname(__DIR__, 2)."/{$file}"), $matches);

    return $matches[1];
}

it('gives the server every key of .env.example, or leaves it to its default on purpose', function (): void {
    $missing = array_diff(envKeys('.env.example'), envKeys('ansible/templates/env.j2'), KEYS_LEFT_TO_DEFAULTS);

    expect(array_values($missing))->toBe([]);
});

it('lists only keys of .env.example that the server leaves out', function (): void {
    expect(array_values(array_diff(KEYS_LEFT_TO_DEFAULTS, envKeys('.env.example'))))->toBe([])
        ->and(array_values(array_intersect(KEYS_LEFT_TO_DEFAULTS, envKeys('ansible/templates/env.j2'))))->toBe([]);
});
