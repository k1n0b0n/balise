<?php

declare(strict_types=1);

/** Criteria per topic in RGESN 2024, 78 in all. */
const RGESN_CRITERIA_PER_TOPIC = [1 => 10, 2 => 10, 3 => 7, 4 => 15, 5 => 8, 6 => 7, 7 => 4, 8 => 10, 9 => 7];

it('assesses every RGESN criterion, in order, with a status and its justification', function (): void {
    /** @var array{criteria: array<string, array{priority: string, status: string, note: string}>} $assessment */
    $assessment = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/resources/audits/rgesn.json'), associative: true, flags: JSON_THROW_ON_ERROR);

    $expected = [];
    foreach (RGESN_CRITERIA_PER_TOPIC as $topic => $count) {
        foreach (range(1, $count) as $criterion) {
            $expected[] = "{$topic}.{$criterion}";
        }
    }

    expect(array_keys($assessment['criteria']))->toBe($expected);

    foreach ($assessment['criteria'] as $id => $criterion) {
        expect($criterion['status'])->toBeIn(['S', 'NS', 'NA'], $id)
            ->and($criterion['priority'])->toBeIn(['Prioritaire', 'Recommandé', 'Modéré'], $id)
            ->and($criterion['note'])->not->toBe('', $id);
    }
});
