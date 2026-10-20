<?php

/*
 * Platform versions check (E1.3a): every place that names a version agrees with versions.yaml.
 * No dependency, so that CI runs it before installing anything: php scripts/check-versions.php [root]
 */

declare(strict_types=1);

$root = rtrim($argv[1] ?? dirname(__DIR__), '/');

/*
 * Where each version is named: file => list of patterns whose first group is the version. Every pattern must match
 * at least once, so that a rewritten file cannot silently escape the check.
 */
$places = [
    'ubuntu' => [
        'docker/php/Dockerfile' => ['/^FROM ubuntu:(\S+)/m'],
        'docker/nginx/Dockerfile' => ['/^FROM ubuntu:(\S+)/m'],
    ],
    'php' => [
        'docker/php/Dockerfile' => ['#/etc/php/([\d.]+)/#', '/php-fpm([\d.]+)/'],
        'docker/php/packages.txt' => ['/^php([\d.]+)-/m'],
        'docker/nginx/upstream-docker.conf' => ['/php([\d.]+)-fpm\.sock/'],
        '.github/workflows/ci.yml' => ["/php-version: '([\\d.]+)'/"],
    ],
    'postgresql' => [
        'compose.yaml' => ['/image: postgres:(\d+)/'],
        '.github/workflows/ci.yml' => ['/image: postgres:(\d+)/'],
    ],
    'redis' => [
        'compose.yaml' => ['/image: redis:(\d+)/'],
    ],
    'node' => [
        'compose.yaml' => ['/image: node:(\d+)/'],
        'docker/php/Dockerfile' => ['/--from=node:(\d+)/'],
        '.github/workflows/ci.yml' => ['/node-version: (\d+)/'],
    ],
];

$versionsFile = "{$root}/versions.yaml";
$versions = [];

foreach (file($versionsFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    if (preg_match('/^(\w+): "([^"]+)"$/', $line, $match) === 1) {
        $versions[$match[1]] = $match[2];
    }
}

$errors = [];

foreach ($places as $name => $files) {
    $expected = $versions[$name] ?? null;

    if ($expected === null) {
        $errors[] = "versions.yaml: no {$name} version";

        continue;
    }

    foreach ($files as $file => $patterns) {
        $contents = @file_get_contents("{$root}/{$file}");

        if ($contents === false) {
            $errors[] = "{$file}: missing, expected to name {$name} {$expected}";

            continue;
        }

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === 0) {
                $errors[] = "{$file}: {$name} version not found ({$pattern}), expected {$expected}";
            }

            foreach ($matches as $match) {
                [$found, $offset] = $match[1];

                if ($found !== $expected) {
                    $line = substr_count($contents, "\n", 0, $offset) + 1;
                    $errors[] = "{$file}:{$line}: {$name} {$found}, versions.yaml says {$expected}";
                }
            }
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);

    exit(1);
}

echo 'Versions agree with versions.yaml.'.PHP_EOL;
