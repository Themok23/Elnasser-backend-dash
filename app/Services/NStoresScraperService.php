<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\TransferStats;

class NStoresScraperService
{
    public function __construct(
        protected ?Client $client = null
    ) {
        $this->client ??= new Client();
    }

    /**
     * Scrape branch pages from a list URL and extract Google Maps lat/lng.
     *
     * @return array<int, array{
     *   name: string|null,
     *   list_text: string|null,
     *   page_url: string,
     *   maps_url: string|null,
     *   resolved_maps_url: string|null,
     *   latitude: float|null,
     *   longitude: float|null
     * }>
     */
    public function scrape(string $sourceUrl, int $timeoutSeconds = 25, bool $verifySsl = true, ?string $userAgent = null, int $limit = 0): array
    {
        [$finalListUrl, $listHtml] = $this->fetchHtml($sourceUrl, $timeoutSeconds, $verifySsl, $userAgent);

        $branchCandidates = $this->extractCandidateBranchLinks($listHtml, $finalListUrl);
        if ($limit > 0) {
            $branchCandidates = array_slice($branchCandidates, 0, $limit);
        }

        $results = [];
        foreach ($branchCandidates as $candidate) {
            $pageUrl = $candidate['url'];
            [$finalPageUrl, $pageHtml] = $this->fetchHtml($pageUrl, $timeoutSeconds, $verifySsl, $userAgent);

            $mapsUrl = $this->extractFirstGoogleMapsUrl($pageHtml);
            $resolvedMapsUrl = null;
            $latLng = $this->parseLatLngFromUrl($finalPageUrl);
            if ($mapsUrl) {
                $resolvedMapsUrl = $this->resolveFinalUrl($mapsUrl, $timeoutSeconds, $verifySsl, $userAgent);
                $latLng ??= $this->parseLatLngFromUrl($resolvedMapsUrl ?? $mapsUrl);
            } else {
                // Some pages are also bitly launchpad pages; grab the first button target and resolve it.
                $launchpadTarget = $this->extractFirstLaunchpadButtonTarget($pageHtml);
                if ($launchpadTarget) {
                    $resolvedMapsUrl = $this->resolveFinalUrl($launchpadTarget, $timeoutSeconds, $verifySsl, $userAgent);
                    $latLng ??= $this->parseLatLngFromUrl($resolvedMapsUrl ?? $launchpadTarget);
                }
            }

            $results[] = [
                'name' => $candidate['text'] ?: $this->extractTitle($pageHtml),
                'list_text' => $candidate['text'],
                'description' => $candidate['description'] ?? null,
                'page_url' => $finalPageUrl,
                'maps_url' => $mapsUrl,
                'resolved_maps_url' => $resolvedMapsUrl,
                'latitude' => $latLng['lat'] ?? null,
                'longitude' => $latLng['lng'] ?? null,
            ];
        }

        return $results;
    }

    /**
     * @return array{0:string,1:string} [finalUrl, html]
     */
    protected function fetchHtml(string $url, int $timeoutSeconds, bool $verifySsl, ?string $userAgent): array
    {
        $effectiveUrl = $url;

        $response = $this->client->request('GET', $url, [
            'timeout' => $timeoutSeconds,
            'connect_timeout' => min(10, $timeoutSeconds),
            'verify' => $verifySsl,
            'http_errors' => false,
            'allow_redirects' => true,
            'headers' => array_filter([
                'User-Agent' => $userAgent,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ]),
            'on_stats' => function (TransferStats $stats) use (&$effectiveUrl) {
                $effectiveUrl = (string) $stats->getEffectiveUri();
            },
        ]);

        $body = (string) $response->getBody();
        $body = $this->maybeFollowMetaRefresh($body, $effectiveUrl, $timeoutSeconds, $verifySsl, $userAgent);

        return [$effectiveUrl, $body];
    }

    protected function resolveFinalUrl(string $url, int $timeoutSeconds, bool $verifySsl, ?string $userAgent): ?string
    {
        $effectiveUrl = $url;
        try {
            $this->client->request('GET', $url, [
                'timeout' => $timeoutSeconds,
                'connect_timeout' => min(10, $timeoutSeconds),
                'verify' => $verifySsl,
                'http_errors' => false,
                'allow_redirects' => true,
                'headers' => array_filter([
                    'User-Agent' => $userAgent,
                    'Accept' => '*/*',
                ]),
                'on_stats' => function (TransferStats $stats) use (&$effectiveUrl) {
                    $effectiveUrl = (string) $stats->getEffectiveUri();
                },
            ]);
        } catch (\Throwable) {
            return null;
        }

        return $effectiveUrl ?: null;
    }

    /**
     * Some link aggregators use <meta http-equiv="refresh" content="0; url=...">
     */
    protected function maybeFollowMetaRefresh(string $html, string $baseUrl, int $timeoutSeconds, bool $verifySsl, ?string $userAgent): string
    {
        if (!preg_match('/<meta[^>]+http-equiv=["\']?refresh["\']?[^>]+content=["\']?\\s*\\d+\\s*;\\s*url=([^"\'>\\s]+)\\s*["\']?/i', $html, $m)) {
            return $html;
        }
        $refreshUrl = html_entity_decode($m[1], ENT_QUOTES);
        $refreshUrl = $this->toAbsoluteUrl($refreshUrl, $baseUrl);
        try {
            [, $refreshed] = $this->fetchHtml($refreshUrl, $timeoutSeconds, $verifySsl, $userAgent);
            return $refreshed;
        } catch (\Throwable) {
            return $html;
        }
    }

    /**
     * @return array<int, array{url:string,text:string|null}>
     */
    protected function extractCandidateBranchLinks(string $html, string $baseUrl): array
    {
        // 1) Normal HTML anchors
        $links = $this->extractAnchorLinks($html, $baseUrl);
        if (count($links) > 0) {
            return $this->rankAndUniqueLinks($links, $baseUrl);
        }

        // 2) Bitly Launchpad pages (like https://bit.ly/m/NStores) store all links in window.initLaunchpad({...})
        $launchpad = $this->parseLaunchpadPayload($html);
        if (!$launchpad) {
            return [];
        }

        $scheme = (string) ($launchpad['scheme'] ?? 'https');
        $domain = (string) ($launchpad['domain'] ?? 'bit.ly');
        $buttons = $launchpad['buttons'] ?? [];

        $out = [];
        foreach ($buttons as $btn) {
            if (!is_array($btn)) {
                continue;
            }
            if (($btn['type'] ?? null) !== 'bitlink') {
                continue;
            }
            if (($btn['is_active'] ?? true) !== true) {
                continue;
            }

            $title = $btn['title'] ?? ($btn['content']['title'] ?? null);
            $desc = $btn['description'] ?? ($btn['content']['description'] ?? null);

            $bitlink = $btn['bitlink'] ?? ($btn['content']['target'] ?? null);
            if (!is_string($bitlink) || trim($bitlink) === '') {
                $keyword = $btn['keyword'] ?? null;
                if (is_string($keyword) && $keyword !== '') {
                    $bitlink = $domain . '/' . $keyword;
                }
            }
            if (!is_string($bitlink) || trim($bitlink) === '') {
                continue;
            }

            $url = $bitlink;
            if (!preg_match('#^https?://#i', $url)) {
                $url = $scheme . '://' . ltrim($url, '/');
            }

            // Ignore socials (they're included separately, but just in case)
            if (preg_match('/(facebook|instagram|twitter|x\\.com|tiktok|snapchat|youtube|wa\\.me|whatsapp|mailto:|tel:|linkedin|t\\.me)/i', $url)) {
                continue;
            }

            $out[] = [
                'url' => $url,
                'text' => is_string($title) && trim($title) !== '' ? trim($title) : null,
                'description' => is_string($desc) && trim($desc) !== '' ? trim($desc) : null,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{url:string,text:string|null}>
     */
    protected function extractAnchorLinks(string $html, string $baseUrl): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//a[@href]');

        $out = [];
        foreach ($nodes as $node) {
            $href = trim((string) $node->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#')) {
                continue;
            }

            $abs = $this->toAbsoluteUrl($href, $baseUrl);
            if (!preg_match('#^https?://#i', $abs)) {
                continue;
            }

            $text = trim(preg_replace('/\\s+/', ' ', $node->textContent ?? ''));
            $out[] = [
                'url' => $abs,
                'text' => $text !== '' ? $text : null,
            ];
        }

        return $out;
    }

    protected function extractFirstGoogleMapsUrl(string $html): ?string
    {
        // 1) Direct hrefs
        if (preg_match('#https?://(?:www\\.)?(?:google\\.[^/]+/maps|maps\\.google\\.[^/]+|maps\\.app\\.goo\\.gl|goo\\.gl/maps)[^\\s"\'<>]+#i', $html, $m)) {
            return html_entity_decode($m[0], ENT_QUOTES);
        }

        // 2) onclick / data-url style attributes
        if (preg_match('#(https?://(?:www\\.)?(?:google\\.[^/]+/maps|maps\\.google\\.[^/]+|maps\\.app\\.goo\\.gl|goo\\.gl/maps)[^\\s"\'<>]+)#i', $html, $m2)) {
            return html_entity_decode($m2[1], ENT_QUOTES);
        }

        return null;
    }

    /**
     * Launchpad pages often store button targets as bit.ly links; resolve them.
     */
    protected function extractFirstLaunchpadButtonTarget(string $html): ?string
    {
        $launchpad = $this->parseLaunchpadPayload($html);
        if (!$launchpad) {
            return null;
        }
        $scheme = (string) ($launchpad['scheme'] ?? 'https');
        $domain = (string) ($launchpad['domain'] ?? 'bit.ly');
        $buttons = $launchpad['buttons'] ?? [];
        foreach ($buttons as $btn) {
            if (!is_array($btn)) {
                continue;
            }
            if (($btn['type'] ?? null) !== 'bitlink') {
                continue;
            }
            if (($btn['is_active'] ?? true) !== true) {
                continue;
            }
            $target = $btn['bitlink'] ?? ($btn['content']['target'] ?? null);
            if (!is_string($target) || trim($target) === '') {
                $keyword = $btn['keyword'] ?? null;
                if (is_string($keyword) && $keyword !== '') {
                    $target = $domain . '/' . $keyword;
                }
            }
            if (!is_string($target) || trim($target) === '') {
                continue;
            }
            $url = $target;
            if (!preg_match('#^https?://#i', $url)) {
                $url = $scheme . '://' . ltrim($url, '/');
            }
            return $url;
        }
        return null;
    }

    /**
     * Parse Bitly Launchpad JSON embedded in:
     *   window.initLaunchpad({...});
     */
    protected function parseLaunchpadPayload(string $html): ?array
    {
        if (!preg_match('/window\\.initLaunchpad\\(\\s*(\\{.*?\\})\\s*\\);/s', $html, $m)) {
            return null;
        }
        $json = trim($m[1]);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }
        return $data;
    }

    /**
     * Rank links (heuristic) and unique them by URL.
     *
     * @param array<int, array{url:string,text:string|null}> $links
     * @return array<int, array{url:string,text:string|null}>
     */
    protected function rankAndUniqueLinks(array $links, string $baseUrl): array
    {
        $baseHost = (string) parse_url($baseUrl, PHP_URL_HOST);
        $filtered = [];
        foreach ($links as $link) {
            $href = $link['url'];
            $host = (string) parse_url($href, PHP_URL_HOST);
            if ($host === '') {
                continue;
            }
            if (preg_match('/(facebook|instagram|twitter|x\\.com|tiktok|snapchat|youtube|wa\\.me|whatsapp|mailto:|tel:)/i', $href)) {
                continue;
            }
            $score = 0;
            if ($host === $baseHost) {
                $score += 10;
            }
            if ($link['text']) {
                $score += min(5, (int) floor(strlen($link['text']) / 10));
            }
            $filtered[] = $link + ['score' => $score];
        }

        usort($filtered, fn ($a, $b) => ($b['score'] <=> $a['score']));

        $seen = [];
        $out = [];
        foreach ($filtered as $link) {
            if (isset($seen[$link['url']])) {
                continue;
            }
            $seen[$link['url']] = true;
            $out[] = ['url' => $link['url'], 'text' => $link['text']];
        }
        return $out;
    }

    /**
     * @return array{lat:float,lng:float}|null
     */
    protected function parseLatLngFromUrl(string $url): ?array
    {
        $decoded = html_entity_decode($url, ENT_QUOTES);
        $decoded = $this->decodeJsEscapes($decoded);
        $decoded = rtrim($decoded, "\\ \t\n\r\0\x0B");

        // Pattern: .../@lat,lng,zoom...
        if (preg_match('/@\\s*(-?\\d+(?:\\.\\d+)?),\\s*(-?\\d+(?:\\.\\d+)?),/i', $decoded, $m)) {
            return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
        }

        // Pattern: ...!3dLAT!4dLNG...
        if (preg_match('/!3d\\s*(-?\\d+(?:\\.\\d+)?)!4d\\s*(-?\\d+(?:\\.\\d+)?)/i', $decoded, $m)) {
            return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
        }

        // Pattern: ?q=lat,lng  OR  &q=lat,lng
        if (preg_match('/[?&]q=\\s*(-?\\d+(?:\\.\\d+)?),\\s*(-?\\d+(?:\\.\\d+)?)/i', $decoded, $m)) {
            return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
        }

        // Pattern: ?query=lat,lng (Maps search api=1)
        if (preg_match('/[?&]query=\\s*(-?\\d+(?:\\.\\d+)?),\\s*(-?\\d+(?:\\.\\d+)?)/i', $decoded, $m)) {
            return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
        }

        // Static maps pattern: center=lat,lng or center=lat%2Clng
        if (preg_match('/[?&]center=\\s*(-?\\d+(?:\\.\\d+)?)(?:%2C|,)\\s*(-?\\d+(?:\\.\\d+)?)/i', $decoded, $m)) {
            return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
        }

        // Static maps pattern: markers=lat,lng or markers=lat%2Clng
        if (preg_match('/[?&]markers=\\s*(-?\\d+(?:\\.\\d+)?)(?:%2C|,)\\s*(-?\\d+(?:\\.\\d+)?)/i', $decoded, $m)) {
            return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
        }

        return null;
    }

    /**
     * Decode JS/JSON unicode escapes (e.g. \u0026) if present in the string.
     */
    protected function decodeJsEscapes(string $value): string
    {
        if (!str_contains($value, '\\u')) {
            return $value;
        }

        // Safely decode by re-encoding as a JSON string literal.
        try {
            $json = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
            $decoded = json_decode($json, true);
            if (is_string($decoded)) {
                return $decoded;
            }
        } catch (\Throwable) {
            // ignore
        }
        return $value;
    }

    protected function extractTitle(string $html): ?string
    {
        if (!preg_match('/<title[^>]*>(.*?)<\\/title>/is', $html, $m)) {
            return null;
        }
        $t = trim(preg_replace('/\\s+/', ' ', strip_tags($m[1])));
        return $t !== '' ? $t : null;
    }

    protected function toAbsoluteUrl(string $maybeRelative, string $baseUrl): string
    {
        $maybeRelative = trim($maybeRelative);
        if (preg_match('#^https?://#i', $maybeRelative)) {
            return $maybeRelative;
        }
        if (str_starts_with($maybeRelative, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $maybeRelative;
        }
        if (str_starts_with($maybeRelative, '/')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
            $host = parse_url($baseUrl, PHP_URL_HOST) ?: '';
            return $scheme . '://' . $host . $maybeRelative;
        }

        // Relative path
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($baseUrl, PHP_URL_HOST) ?: '';
        $path = parse_url($baseUrl, PHP_URL_PATH) ?: '/';
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
        return $scheme . '://' . $host . ($dir ? ($dir . '/') : '/') . $maybeRelative;
    }
}


