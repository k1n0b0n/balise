<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CatalogImport;
use App\Support\NetworkShapes;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

final class ShapeController extends Controller
{
    /**
     * The network's lines drawn on the map (ADR-0016), fetched after the first positions; the file changes only when
     * an import brings new shapes, and its fingerprint is the ETag, so a revisit gets a 304 without a body.
     */
    public function __invoke(Request $request, NetworkShapes $shapes, string $network): Response
    {
        abort_unless(Config::has("networks.{$network}"), 404);

        // The fingerprint alone: a revisit answered 304 does not read the shapes.
        $fingerprint = CatalogImport::query()->whereKey($network)->value('shapes_fingerprint');
        abort_unless(is_string($fingerprint), 404);

        $response = (new Response)->setEtag($fingerprint)->setCache(['no_cache' => true]);

        if ($response->isNotModified($request)) {
            return $response;
        }

        $json = $shapes->read($network);
        abort_if($json === null, 404);

        return $response->setContent($json)->header('Content-Type', 'application/json');
    }
}
