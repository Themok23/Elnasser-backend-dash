<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\NStoresScraperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ScrapeNStoresBranches extends Command
{
    protected $signature = 'nstores:scrape
        {--url= : Source list URL (default: services.nstores.source_url)}
        {--limit=0 : Limit number of branches (0 = no limit)}
        {--timeout= : HTTP timeout seconds (default: services.nstores.timeout)}
        {--verify-ssl= : Verify SSL (default: services.nstores.verify_ssl)}
        {--out=storage/app/nstores_branches.json : Output JSON file path}
        {--post-url= : Optional API URL to POST results to}
        {--post-token= : Optional Bearer token for POST}
        {--update-stores=0 : If 1, update Store latitude/longitude by exact name match}
        {--sync-branches=0 : If 1, upsert into branch_locations table}
        {--dry-run=0 : If 1, don\'t write file / don\'t update stores / don\'t post}';

    protected $description = 'Scrape NStores branch pages, extract Google Maps lat/lng, and export results.';

    public function handle(NStoresScraperService $scraper): int
    {
        $sourceUrl = (string) ($this->option('url') ?: config('services.nstores.source_url'));
        $limit = (int) ($this->option('limit') ?? 0);
        $timeout = (int) ($this->option('timeout') ?: config('services.nstores.timeout', 25));
        $verifySsl = filter_var($this->option('verify-ssl') ?? config('services.nstores.verify_ssl', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $verifySsl = $verifySsl ?? true;
        $userAgent = (string) config('services.nstores.user_agent');

        $this->info("Scraping: {$sourceUrl}");
        $results = $scraper->scrape($sourceUrl, $timeout, $verifySsl, $userAgent, $limit);

        $found = count($results);
        $withCoords = count(array_filter($results, fn ($r) => $r['latitude'] !== null && $r['longitude'] !== null));
        $this->info("Done. branches={$found}, with_coords={$withCoords}");

        $dryRun = (int) ($this->option('dry-run') ?? 0) === 1;

        // Write file
        $out = (string) ($this->option('out') ?: 'storage/app/nstores_branches.json');
        $out = base_path($out);
        if (!$dryRun) {
            File::ensureDirectoryExists(dirname($out));
            File::put($out, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->info("Saved: {$out}");
        } else {
            $this->warn('Dry run: skipping file write.');
        }

        // Optional: update stores
        $updateStores = (int) ($this->option('update-stores') ?? 0) === 1;
        if ($updateStores) {
            if ($dryRun) {
                $this->warn('Dry run: skipping store updates.');
            } else {
                $updated = 0;
                foreach ($results as $row) {
                    if (!$row['name'] || $row['latitude'] === null || $row['longitude'] === null) {
                        continue;
                    }
                    $store = Store::query()->where('name', $row['name'])->first();
                    if (!$store) {
                        continue;
                    }
                    $store->latitude = (string) $row['latitude'];
                    $store->longitude = (string) $row['longitude'];
                    $store->save();
                    $updated++;
                }
                $this->info("Updated stores (exact-name match): {$updated}");
            }
        }

        // Optional: sync branch_locations table
        $syncBranches = (int) ($this->option('sync-branches') ?? 0) === 1;
        if ($syncBranches) {
            if ($dryRun) {
                $this->warn('Dry run: skipping branch_locations sync.');
            } else {
                $synced = 0;
                foreach ($results as $row) {
                    $sourceKey = null;
                    if (!empty($row['page_url']) && is_string($row['page_url'])) {
                        $sourceKey = (string) parse_url($row['page_url'], PHP_URL_PATH);
                    }
                    // Prefer using the branch bitlink keyword if present in page_url (fallback handled by unique index)
                    $sourceKey = $sourceKey ?: ($row['name'] ?? null);

                    if (!$sourceKey) {
                        continue;
                    }

                    \App\Models\BranchLocation::query()->updateOrCreate(
                        ['source' => 'nstores', 'source_key' => $sourceKey],
                        [
                            'name' => $row['name'] ?? null,
                            'description' => $row['description'] ?? null,
                            'latitude' => $row['latitude'] ?? null,
                            'longitude' => $row['longitude'] ?? null,
                            'page_url' => $row['page_url'] ?? null,
                            'maps_url' => $row['maps_url'] ?? null,
                            'resolved_maps_url' => $row['resolved_maps_url'] ?? null,
                            // image/rank are optional and can be filled later
                        ]
                    );
                    $synced++;
                }
                $this->info("Synced branch_locations: {$synced}");
            }
        }

        // Optional: POST to external API
        $postUrl = (string) ($this->option('post-url') ?? '');
        if ($postUrl !== '') {
            if ($dryRun) {
                $this->warn('Dry run: skipping POST.');
            } else {
                $token = (string) ($this->option('post-token') ?? '');
                $headers = [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ];
                if ($token !== '') {
                    $headers['Authorization'] = 'Bearer ' . $token;
                }

                $client = new \GuzzleHttp\Client();
                $res = $client->request('POST', $postUrl, [
                    'headers' => $headers,
                    'json' => [
                        'source_url' => $sourceUrl,
                        'branches' => $results,
                    ],
                    'timeout' => $timeout,
                    'http_errors' => false,
                ]);

                $this->info("POST {$postUrl} -> HTTP " . $res->getStatusCode());
            }
        }

        return self::SUCCESS;
    }
}


