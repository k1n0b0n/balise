<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\LineResource;
use App\Models\CatalogImport;
use App\Models\Line;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

final class LineController extends Controller
{
    /**
     * The network's line catalogue, fetched once by the map page; it changes only with the daily import, whose time
     * is the ETag, so a later visit or a refetch gets a 304 without a body.
     */
    public function __invoke(Request $request, string $network): JsonResponse
    {
        abort_unless(Config::has("networks.{$network}"), 404);

        // Not the shapes the row also holds (ADR-0016).
        $importedAt = CatalogImport::query()->find($network, ['network', 'imported_at'])?->imported_at;
        $response = (new JsonResponse)->setEtag((string) ($importedAt?->getTimestampMs() ?? 0))->setCache(['no_cache' => true]);

        if ($response->isNotModified($request)) {
            return $response;
        }

        $lines = Line::query()->where('network', $network)->orderBy('id')->get();

        return $response->setData(['lines' => LineResource::collection($lines)->resolve($request)]);
    }
}
