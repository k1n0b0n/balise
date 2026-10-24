<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The accessibility and ecodesign statements (E1.5), computed from the self-audits in resources/audits.
 */
final readonly class Statements
{
    /**
     * The RGAA self-audit: a criterion fails when it fails on one page of the sample, and is not applicable only when
     * it applies to none; the rate is the share of applicable criteria that pass, rounded down so as never to overstate.
     *
     * @return array{date: string, status: string, rate: int, counts: array{C: int, NC: int, NA: int}, failing: list<array{id: string, title: string}>, pages: list<array{name: string, path: string}>, environment: list<string>, tools: list<string>}
     */
    public function accessibility(): array
    {
        /** @var array{date: string, environment: list<string>, tools: list<string>, pages: array<string, array{name: string, path: string}>, criteria: array<string, array<string, string>>} $audit */
        $audit = $this->read('rgaa.json');
        $counts = ['C' => 0, 'NC' => 0, 'NA' => 0];
        $failing = [];

        foreach ($audit['criteria'] as $id => $criterion) {
            $results = array_map(fn (string $page): string => $criterion[$page], array_keys($audit['pages']));
            $result = match (true) {
                in_array('NC', $results, true) => 'NC',
                in_array('C', $results, true) => 'C',
                default => 'NA',
            };
            $counts[$result]++;

            if ($result === 'NC') {
                $failing[] = ['id' => $id, 'title' => $criterion['title']];
            }
        }

        $applicable = $counts['C'] + $counts['NC'];
        $rate = $applicable === 0 ? 0 : intdiv(100 * $counts['C'], $applicable);

        return [
            'date' => $audit['date'],
            // The wording the RGAA prescribes for each level.
            'status' => match (true) {
                $rate === 100 => 'totalement conforme',
                $rate >= 50 => 'partiellement conforme',
                default => 'non conforme',
            },
            'rate' => $rate,
            'counts' => $counts,
            'failing' => $failing,
            'pages' => array_values($audit['pages']),
            'environment' => $audit['environment'],
            'tools' => $audit['tools'],
        ];
    }

    /**
     * The RGESN self-assessment: the score is the share of applicable criteria satisfied, rounded down.
     *
     * @return array{date: string, score: int, counts: array{S: int, NS: int, NA: int}, criteria: array{S: list<array{id: string, title: string, priority: string, note: string}>, NS: list<array{id: string, title: string, priority: string, note: string}>, NA: list<array{id: string, title: string, priority: string, note: string}>}}
     */
    public function ecodesign(): array
    {
        /** @var array{date: string, criteria: array<string, array{title: string, priority: string, status: 'S'|'NS'|'NA', note: string}>} $assessment */
        $assessment = $this->read('rgesn.json');
        $criteria = ['S' => [], 'NS' => [], 'NA' => []];

        foreach ($assessment['criteria'] as $id => $criterion) {
            $criteria[$criterion['status']][] = ['id' => $id, 'title' => $criterion['title'], 'priority' => $criterion['priority'], 'note' => $criterion['note']];
        }

        $counts = array_map(count(...), $criteria);
        $applicable = $counts['S'] + $counts['NS'];

        return [
            'date' => $assessment['date'],
            'score' => $applicable === 0 ? 0 : intdiv(100 * $counts['S'], $applicable),
            'counts' => $counts,
            'criteria' => $criteria,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $file): array
    {
        /** @var array<string, mixed> */
        return json_decode((string) file_get_contents(resource_path("audits/{$file}")), associative: true, flags: JSON_THROW_ON_ERROR);
    }
}
