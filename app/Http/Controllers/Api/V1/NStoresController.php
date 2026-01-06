<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NStoresScraperService;
use Illuminate\Http\Request;

class NStoresController extends Controller
{
    /**
     * Protected endpoint to scrape NStores branches and return lat/lng.
     *
     * Headers:
     * - X-NSTORES-IMPORT-KEY: must match services.nstores.import_key
     */
    public function scrape(Request $request, NStoresScraperService $scraper)
    {
        $key = (string) $request->header('X-NSTORES-IMPORT-KEY');
        if ($key === '' || $key !== (string) config('services.nstores.import_key')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $sourceUrl = (string) ($request->input('url') ?: config('services.nstores.source_url'));
        $limit = (int) ($request->input('limit') ?: 0);

        $timeout = (int) config('services.nstores.timeout', 25);
        $verifySsl = (bool) config('services.nstores.verify_ssl', true);
        $userAgent = (string) config('services.nstores.user_agent');

        $results = $scraper->scrape($sourceUrl, $timeout, $verifySsl, $userAgent, $limit);

        return response()->json([
            'source_url' => $sourceUrl,
            'count' => count($results),
            'branches' => $results,
        ]);
    }
}


