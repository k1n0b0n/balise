<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CatalogImport;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

final class StationController extends Controller
{
    /**
     * The network's stations as GeoJSON (E7.1), fetched by the map page once it reaches a street zoom, or once the
     * station search is used; like the line catalogue, they change only with the import, whose time is the ETag.
     */
    public function __invoke(Request $request, string $network): JsonResponse
    {
        abort_unless(array_key_exists($network, Config::array('networks')), 404);

        $importedAt = CatalogImport::query()->find($network, ['network', 'imported_at'])?->imported_at;
        $response = (new JsonResponse)->setEtag((string) ($importedAt?->getTimestampMs() ?? 0))->setCache(['no_cache' => true]);

        if ($response->isNotModified($request)) {
            return $response;
        }

        $stations = Station::query()->where('network', $network)->orderBy('name')->orderBy('id')->get(['id', 'name', 'latitude', 'longitude']);

        return $response->setData([
            'type' => 'FeatureCollection',
            'features' => $stations->map(fn (Station $station): array => [
                'type' => 'Feature',
                'id' => $station->id,
                // Five decimals are a metre: enough for a marker, and a lighter file.
                'geometry' => ['type' => 'Point', 'coordinates' => [round($station->longitude, 5), round($station->latitude, 5)]],
                'properties' => ['name' => $station->name],
            ])->all(),
        ]);
    }
}
