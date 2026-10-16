<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\VehicleResource;
use App\Models\VehiclePosition;
use App\Support\PositionChangeLog;
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
    public function __invoke(Request $request, string $network, PositionChangeLog $changes): JsonResponse
    {
        abort_unless(Config::has("networks.{$network}"), 404);

        $version = $changes->version($network);
        $response = (new JsonResponse)->setEtag((string) $version)->setCache(['no_cache' => true]);

        if ($response->isNotModified($request)) {
            return $response;
        }

        $since = $request->query('since');
        $delta = is_string($since) && ctype_digit($since) ? $changes->since($network, (int) $since) : null;

        $positions = VehiclePosition::query()
            ->with('line')
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
