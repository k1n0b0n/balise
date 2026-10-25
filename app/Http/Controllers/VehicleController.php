<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\VehicleResource;
use App\Models\VehiclePosition;
use App\Support\FeedTiming;
use App\Support\PositionChangeLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

final class VehicleController extends Controller
{
    /**
     * The network's vehicles: all of them, or only those changed and removed since the version given in `since`.
     *
     * The version is also the ETag, so a browser that is up to date gets a 304 without a body (ADR-0006).
     */
    public function __invoke(Request $request, string $network, PositionChangeLog $changes, FeedTiming $timing): JsonResponse
    {
        // Not Config::has(), which would also accept a dotted path such as `tbm.vehicles`.
        abort_unless(array_key_exists($network, Config::array('networks')), 404);

        $version = $changes->version($network);
        $response = (new JsonResponse)->setEtag((string) $version)->setCache(['no_cache' => true]);

        // When to ask again (E4.1), as a time rather than a delay, so that a cached answer stays right; browsers count
        // from the answer's Date, which is the server's clock, not theirs. A 304 says it too.
        $next = $timing->nextVersionAt($network, CarbonImmutable::now());
        if ($next !== null) {
            $response->headers->set('Next-Version-At', number_format($next, 1, '.', ''));
        }

        if ($response->isNotModified($request)) {
            return $response;
        }

        $since = $request->query('since');
        $delta = is_string($since) && ctype_digit($since) ? $changes->since($network, (int) $since) : null;

        $positions = VehiclePosition::query()
            ->where('network', $network)
            ->when($delta !== null, fn ($query) => $query->whereKey($delta['changed'] ?? []))
            ->get();

        // A vehicle removed after the change log was read is reported as removed rather than skipped.
        $removed = array_values(array_unique([
            ...$delta['removed'] ?? [],
            ...array_diff($delta['changed'] ?? [], $positions->modelKeys()),
        ]));

        return $response->setData([
            'version' => $version,
            'full' => $delta === null,
            'vehicles' => VehicleResource::collection($positions)->resolve($request),
            'removed' => $removed,
        ]);
    }
}
