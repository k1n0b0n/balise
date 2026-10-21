<?php

declare(strict_types=1);

use function Balise\Versions\apply;
use function Balise\Versions\current;
use function Balise\Versions\newer;

require_once dirname(__DIR__, 2).'/scripts/raise-versions.php';

const RAISED_FILES = ['docker/php/Dockerfile', 'compose.yaml', 'AGENTS.md', 'README.md'];

// The versions the tests start from, whatever the repository runs once the weekly raise has moved it.
const BASELINE = ['php' => '8.5', 'postgresql' => '18', 'redis' => '8', 'node' => '24'];

/**
 * A copy of the files that name versions, at the baseline versions, some of them rewritten.
 *
 * @param  array<string, array{string, string}>  $replace  file => [search, replacement]
 */
function versionedCopy(array $replace = []): string
{
    $root = sys_get_temp_dir().'/raise-versions-'.uniqid();

    foreach (RAISED_FILES as $file) {
        @mkdir(dirname("{$root}/{$file}"), recursive: true);
        copy(dirname(__DIR__, 2)."/{$file}", "{$root}/{$file}");
    }

    foreach (BASELINE as $key => $version) {
        apply($root, $key, $version);
    }

    foreach ($replace as $file => [$search, $replacement]) {
        file_put_contents("{$root}/{$file}", str_replace($search, $replacement, (string) file_get_contents("{$root}/{$file}")));
    }

    return $root;
}

/**
 * The version sources as recorded, with only the given image tags published and Node's newest LTS line chosen.
 *
 * @param  list<string>  $published  image:tag
 * @return callable(string): ?string
 */
function recordedSources(array $published = [], ?string $nodeLts = null): callable
{
    $fixtures = dirname(__DIR__).'/fixtures/versions';

    return function (string $url) use ($published, $nodeLts, $fixtures): ?string {
        if ($url === 'https://nodejs.org/dist/index.json') {
            $releases = json_decode((string) file_get_contents("{$fixtures}/node-releases.json"), associative: true);

            // The day a line becomes LTS, its newest release gets the line's name.
            return json_encode(array_map(
                fn (array $release): array => str_starts_with($release['version'], "v{$nodeLts}.") ? [...$release, 'lts' => 'Vega'] : $release,
                $releases,
            ));
        }

        preg_match('#/repositories/([^/]+)/tags/([^/]+)$#', $url, $match);

        return in_array("{$match[1]}:{$match[2]}", $published, true) ? (string) file_get_contents("{$fixtures}/docker-hub-tag.json") : null;
    };
}

it('raises nothing when every version is the newest, and the repository names each in agreement', function (): void {
    expect(newer(versionedCopy(), recordedSources()))->toBe([]);
});

it('raises PostgreSQL and Redis major by major, to the newest published', function (): void {
    $raises = newer(versionedCopy(), recordedSources(['postgres:19-alpine', 'postgres:20-alpine', 'redis:9-alpine']));

    expect($raises)->toBe([
        ['key' => 'postgresql', 'name' => 'PostgreSQL', 'from' => '18', 'to' => '20'],
        ['key' => 'redis', 'name' => 'Redis', 'from' => '8', 'to' => '9'],
    ]);
});

it('raises PHP only once both of its images are published, through minors then to the next major', function (): void {
    expect(newer(versionedCopy(), recordedSources(['php:8.6-fpm-alpine'])))->toBe([]);

    expect(newer(versionedCopy(), recordedSources(['php:8.6-fpm-alpine', 'php:8.6-cli-trixie', 'php:9.0-fpm-alpine', 'php:9.0-cli-trixie'])))
        ->toBe([['key' => 'php', 'name' => 'PHP', 'from' => '8.5', 'to' => '9.0']]);
});

it('raises Node to a newer LTS line only, once its images are published', function (): void {
    $images = ['node:26-alpine', 'node:26-trixie-slim'];

    // 26 is out but not LTS yet, 25 never will be.
    expect(newer(versionedCopy(), recordedSources($images)))->toBe([]);
    expect(newer(versionedCopy(), recordedSources(['node:26-alpine'], nodeLts: '26')))->toBe([]);

    expect(newer(versionedCopy(), recordedSources($images, nodeLts: '26')))
        ->toBe([['key' => 'node', 'name' => 'Node', 'from' => '24', 'to' => '26']]);
});

it('takes the highest LTS line, whatever the order of the releases', function (): void {
    $sources = recordedSources(['node:26-alpine', 'node:26-trixie-slim'], nodeLts: '26');
    // A patch of 24 published after 26's latest release comes first in the list.
    $patchedLater = fn (string $url): ?string => $url === 'https://nodejs.org/dist/index.json'
        ? (string) json_encode([['version' => 'v24.22.2', 'date' => '2026-11-03', 'lts' => 'Krypton'], ...json_decode((string) $sources($url), associative: true)])
        : $sources($url);

    expect(newer(versionedCopy(), $patchedLater))->toBe([['key' => 'node', 'name' => 'Node', 'from' => '24', 'to' => '26']]);
});

it('rewrites every place that names a version, and nothing else', function (): void {
    $root = versionedCopy();
    $before = array_map(fn (string $file): string => (string) file_get_contents("{$root}/{$file}"), RAISED_FILES);

    apply($root, 'postgresql', '19');

    expect(current($root, 'postgresql'))->toBe('19')
        ->and(current($root, 'redis'))->toBe('8')
        ->and((string) file_get_contents("{$root}/compose.yaml"))->toBe(str_replace('postgres:18-alpine', 'postgres:19-alpine', $before[1]))
        ->and((string) file_get_contents("{$root}/AGENTS.md"))->toContain('PHP 8.5, PostgreSQL 19.', '--build-arg POSTGRESQL_VERSION=19 ');

    apply($root, 'php', '8.6');

    expect((string) file_get_contents("{$root}/docker/php/Dockerfile"))->toContain('FROM php:8.6-fpm-alpine ', 'FROM php:8.6-cli-trixie ')
        ->and((string) file_get_contents("{$root}/README.md"))->toContain('![PHP 8.6](https://img.shields.io/badge/PHP-8.6-');
});

it('fails when a place no longer names the version as expected, or names another one', function (): void {
    expect(fn () => current(versionedCopy(['compose.yaml' => ['image: redis:8-alpine', 'image: redis:8.2-alpine']]), 'redis'))
        ->toThrow(RuntimeException::class, 'compose.yaml: no redis version');

    expect(fn () => current(versionedCopy(['compose.yaml' => ['image: node:24-alpine', 'image: node:22-alpine']]), 'node'))
        ->toThrow(RuntimeException::class, 'node: the repository names 24, 22');
});

it('refuses to rewrite to something that is not a version', function (): void {
    expect(fn () => apply(versionedCopy(), 'php', '8.6; rm -rf /'))->toThrow(RuntimeException::class);
    expect(fn () => apply(versionedCopy(), 'mysql', '9'))->toThrow(RuntimeException::class);
});
