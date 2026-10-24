<?php

declare(strict_types=1);

/** Criteria per topic in RGAA 4.1.2, 106 in all. */
const RGAA_CRITERIA_PER_TOPIC = [1 => 9, 2 => 2, 3 => 3, 4 => 13, 5 => 8, 6 => 2, 7 => 5, 8 => 10, 9 => 4, 10 => 14, 11 => 13, 12 => 11, 13 => 12];

/**
 * @return array{pages: array<string, array{name: string, path: string}>, criteria: array<string, array<string, string>>}
 */
function rgaaAudit(): array
{
    /** @var array{pages: array<string, array{name: string, path: string}>, criteria: array<string, array<string, string>>} */
    return json_decode((string) file_get_contents(dirname(__DIR__, 2).'/resources/audits/rgaa.json'), associative: true, flags: JSON_THROW_ON_ERROR);
}

it('assesses every RGAA criterion, in order', function (): void {
    $expected = [];
    foreach (RGAA_CRITERIA_PER_TOPIC as $topic => $count) {
        foreach (range(1, $count) as $criterion) {
            $expected[] = "{$topic}.{$criterion}";
        }
    }

    expect(array_keys(rgaaAudit()['criteria']))->toBe($expected);
});

it('gives each criterion a result on every page of the sample, and says what was checked', function (): void {
    $audit = rgaaAudit();

    // E1.5: the map page with and without a selected line, and both statements.
    expect(array_keys($audit['pages']))->toBe(['carte', 'carte-ligne', 'accessibilite', 'ecoconception']);

    foreach ($audit['criteria'] as $id => $result) {
        foreach (array_keys($audit['pages']) as $page) {
            expect($result[$page] ?? null)->toBeIn(['C', 'NC', 'NA'], "{$id} on {$page}");
        }
        expect($result['note'] ?? '')->not->toBe('', "{$id} has no note");
    }
});
