<?php

/*
 * Weekly version raise (E1.3f, ADR-0020 point 3): which of PHP, PostgreSQL, Redis and Node has a newer version than
 * the repository names, and the rewrite of the places that name it. No dependency, so that the workflow runs it with
 * the runner's PHP (8.3 on ubuntu-24.04), before building anything:
 *   php scripts/raise-versions.php                     the newer versions, one JSON object per line, none when up to date
 *   php scripts/raise-versions.php apply <key> <to>    rewrites the places that name one of them
 */

declare(strict_types=1);

namespace Balise\Versions;

use RuntimeException;

const DOCKER_HUB_TAG = 'https://hub.docker.com/v2/namespaces/library/repositories/%s/tags/%s';
const NODE_RELEASES = 'https://nodejs.org/dist/index.json';

/**
 * Where each version is named: file => patterns whose first group is the version. The first file holds the version
 * the images run; the others mention it for readers. Every pattern must match, so that a rewritten line fails the
 * raise instead of being left behind. A version is offered only once every image tag it needs exists.
 *
 * @return array<string, array{name: string, image: string, tags: list<string>, places: array<string, list<string>>}>
 */
function platforms(): array
{
    return [
        'php' => [
            'name' => 'PHP',
            'image' => 'php',
            'tags' => ['%s-fpm-alpine', '%s-cli-trixie'],
            'places' => [
                'docker/php/Dockerfile' => ['/^FROM php:(\d+\.\d+)-fpm-alpine /m', '/^FROM php:(\d+\.\d+)-cli-trixie /m'],
                'AGENTS.md' => ['/^Stack: .*?, PHP (\d+\.\d+),/m'],
                'README.md' => ['/!\[PHP (\d+\.\d+)\]/', '/badge\/PHP-(\d+\.\d+)-/'],
            ],
        ],
        'postgresql' => [
            'name' => 'PostgreSQL',
            'image' => 'postgres',
            'tags' => ['%s-alpine'],
            'places' => [
                'compose.yaml' => ['/image: postgres:(\d+)-alpine/'],
                'AGENTS.md' => ['/^Stack: .*?, PostgreSQL (\d+)\./m', '/--build-arg POSTGRESQL_VERSION=(\d+) /'],
            ],
        ],
        'redis' => [
            'name' => 'Redis',
            'image' => 'redis',
            'tags' => ['%s-alpine'],
            'places' => [
                'compose.yaml' => ['/image: redis:(\d+)-alpine/'],
                'AGENTS.md' => ['/--build-arg REDIS_VERSION=(\d+) /'],
            ],
        ],
        // Node moves by LTS lines only: an odd major never becomes one.
        'node' => [
            'name' => 'Node',
            'image' => 'node',
            'tags' => ['%s-alpine', '%s-trixie-slim'],
            'places' => [
                'docker/php/Dockerfile' => ['/^ARG NODE_VERSION=(\d+)$/m'],
                // The Vite service, until it builds from the same Dockerfile (ADR-0020 point 3).
                'compose.yaml' => ['/image: node:(\d+)-alpine/'],
            ],
        ],
    ];
}

/**
 * The version the repository names for a platform, the same in every place.
 */
function current(string $root, string $key): string
{
    $versions = [];

    foreach (platforms()[$key]['places'] as $file => $patterns) {
        $contents = read("{$root}/{$file}");

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $contents, $matches) === 0) {
                throw new RuntimeException("{$file}: no {$key} version matches {$pattern}");
            }
            $versions = [...$versions, ...$matches[1]];
        }
    }

    $versions = array_values(array_unique($versions));

    if (count($versions) !== 1) {
        throw new RuntimeException("{$key}: the repository names ".implode(', ', $versions));
    }

    return $versions[0];
}

/**
 * The platforms whose newer version has all its image tags, the newest such version for each. PHP climbs minor by
 * minor, then to the next major; PostgreSQL and Redis major by major; Node to the newest LTS line.
 *
 * @param  callable(string): ?string  $fetch  the body at a URL, null when it is not found
 * @return list<array{key: string, name: string, from: string, to: string}>
 */
function newer(string $root, callable $fetch): array
{
    $raises = [];

    foreach (platforms() as $key => $platform) {
        $from = current($root, $key);
        $published = function (string $version) use ($platform, $fetch): bool {
            foreach ($platform['tags'] as $tag) {
                if ($fetch(sprintf(DOCKER_HUB_TAG, $platform['image'], sprintf($tag, $version))) === null) {
                    return false;
                }
            }

            return true;
        };

        if ($key === 'node') {
            $lts = newestLts(read: fn (): string => $fetch(NODE_RELEASES) ?? throw new RuntimeException('Node releases not found'));
            $to = $lts > (int) $from && $published((string) $lts) ? (string) $lts : $from;
        } else {
            $to = $from;
            while (($next = array_values(array_filter(nextVersions($to), $published))[0] ?? null) !== null) {
                $to = $next;
            }
        }

        if ($to !== $from) {
            $raises[] = ['key' => $key, 'name' => $platform['name'], 'from' => $from, 'to' => $to];
        }
    }

    return $raises;
}

/**
 * The versions that may follow one, the closest first: "8.5" gives "8.6" then "9.0", "18" gives "19".
 *
 * @return list<string>
 */
function nextVersions(string $version): array
{
    if (preg_match('/^(\d+)\.(\d+)$/', $version, $match) === 1) {
        return [$match[1].'.'.((int) $match[2] + 1), ((int) $match[1] + 1).'.0'];
    }

    return [(string) ((int) $version + 1)];
}

/**
 * The major of the newest Node release that belongs to an LTS line, from nodejs.org's list (newest first).
 *
 * @param  callable(): string  $read
 */
function newestLts(callable $read): int
{
    /** @var list<array{version: string, lts: string|false}> $releases */
    $releases = json_decode($read(), associative: true, flags: JSON_THROW_ON_ERROR);
    foreach ($releases as $release) {
        if ($release['lts'] !== false) {
            return (int) ltrim($release['version'], 'v');
        }
    }

    throw new RuntimeException('No Node LTS release');
}

/**
 * Rewrites every place that names a platform's version.
 */
function apply(string $root, string $key, string $to): void
{
    if (! isset(platforms()[$key]) || preg_match('/^\d+(\.\d+)?$/', $to) !== 1) {
        throw new RuntimeException("Not a platform and version: {$key} {$to}");
    }
    current($root, $key);

    foreach (platforms()[$key]['places'] as $file => $patterns) {
        $contents = read("{$root}/{$file}");

        foreach ($patterns as $pattern) {
            $contents = (string) preg_replace_callback(
                $pattern,
                fn (array $match): string => substr_replace($match[0][0], $to, $match[1][1] - $match[0][1], strlen($match[1][0])),
                $contents,
                flags: PREG_OFFSET_CAPTURE,
            );
        }
        file_put_contents("{$root}/{$file}", $contents);
    }
}

function read(string $path): string
{
    $contents = @file_get_contents($path);

    return $contents === false ? throw new RuntimeException("Cannot read {$path}") : $contents;
}

/**
 * GET a URL: its body, null on 404, an exception on anything else, so that a source that fails fails the raise.
 */
function fetch(string $url): ?string
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

    return match (true) {
        $status === 404 => null,
        $status === 200 && is_string($body) => $body,
        default => throw new RuntimeException("{$url}: HTTP {$status} ".curl_error($curl)),
    };
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $root = dirname(__DIR__);

    if (($argv[1] ?? null) === 'apply') {
        apply($root, $argv[2] ?? '', $argv[3] ?? '');
    } else {
        foreach (newer($root, fetch(...)) as $raise) {
            echo json_encode($raise, JSON_THROW_ON_ERROR), "\n";
        }
    }
}
