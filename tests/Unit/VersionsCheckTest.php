<?php

declare(strict_types=1);

const CHECKED_FILES = [
    'versions.yaml',
    'compose.yaml',
    '.github/workflows/ci.yml',
    'docker/php/Dockerfile',
    'docker/php/packages.txt',
    'docker/nginx/Dockerfile',
];

/**
 * Run scripts/check-versions.php on a copy of the checked files, some of them rewritten.
 *
 * @param  array<string, array{string, string}>  $replace  file => [search, replacement]
 * @return array{int, string} exit code and output
 */
function checkVersions(array $replace = []): array
{
    $root = sys_get_temp_dir().'/versions-check-'.uniqid();

    foreach (CHECKED_FILES as $file) {
        $contents = (string) file_get_contents(dirname(__DIR__, 2)."/{$file}");

        if (isset($replace[$file])) {
            $contents = str_replace($replace[$file][0], $replace[$file][1], $contents);
        }

        @mkdir(dirname("{$root}/{$file}"), recursive: true);
        file_put_contents("{$root}/{$file}", $contents);
    }

    exec(sprintf('php %s %s 2>&1', escapeshellarg(dirname(__DIR__, 2).'/scripts/check-versions.php'), escapeshellarg($root)), $output, $code);
    exec('rm -rf '.escapeshellarg($root));

    return [$code, implode("\n", $output)];
}

it('passes when every file agrees with versions.yaml', function (): void {
    expect(checkVersions())->toBe([0, 'Versions agree with versions.yaml.']);
});

it('names the place that disagrees', function (string $file, string $search, string $replacement, string $message): void {
    $contents = (string) file_get_contents(dirname(__DIR__, 2)."/{$file}");
    $line = substr_count($contents, "\n", 0, (int) strpos($contents, $search)) + 1;

    [$code, $output] = checkVersions([$file => [$search, $replacement]]);

    expect($code)->toBe(1)->and($output)->toContain(sprintf($message, $line));
})->with([
    'an older Node in the test image' => ['docker/php/Dockerfile', '--from=node:24', '--from=node:22', 'docker/php/Dockerfile:%d: node 22, versions.yaml says 24'],
    'another PHP in CI' => ['.github/workflows/ci.yml', "php-version: '8.5'", "php-version: '8.4'", '.github/workflows/ci.yml:%d: php 8.4, versions.yaml says 8.5'],
    'a newer PostgreSQL in compose' => ['compose.yaml', 'postgres:18', 'postgres:19', 'compose.yaml:%d: postgresql 19, versions.yaml says 18'],
    'the right Node only in a comment' => ['.github/workflows/ci.yml', 'node-version: 24', "node-version: '22'\n          # node-version: 24", '.github/workflows/ci.yml:%d: node 22, versions.yaml says 24'],
    'a version raised in the file only' => ['versions.yaml', 'redis: "8"', 'redis: "9"', 'redis 8, versions.yaml says 9'],
    'a place rewritten out of reach' => ['docker/nginx/Dockerfile', 'FROM ubuntu:', 'FROM debian:', 'docker/nginx/Dockerfile: ubuntu version not found'],
]);
