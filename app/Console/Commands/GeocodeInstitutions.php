<?php

namespace App\Console\Commands;

use App\Models\Institution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Заполняет latitude/longitude для вузов.
 *
 * Приоритет:
 *  1) Google Geocoding API, если есть GOOGLE_MAPS_API_KEY / GOOGLE_GEOCODING_API_KEY
 *  2) Nominatim (OpenStreetMap) — бесплатно, по name+address+city
 *
 * php artisan data:geocode-institutions
 * php artisan data:geocode-institutions --only-missing
 * php artisan data:geocode-institutions --provider=google
 * php artisan data:geocode-institutions --provider=nominatim
 * php artisan data:geocode-institutions --dry-run
 */
class GeocodeInstitutions extends Command
{
    protected $signature = 'data:geocode-institutions
        {--only-missing : Only rows without coords}
        {--provider=auto : auto|google|nominatim}
        {--dry-run : Do not write DB}
        {--limit=0 : Max institutions (0 = all)}';

    protected $description = 'Fill institution latitude/longitude from Google Geocoding or Nominatim';

    public function handle(): int
    {
        $onlyMissing = (bool) $this->option('only-missing');
        $provider = strtolower((string) $this->option('provider'));
        $dry = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $googleKey = env('GOOGLE_MAPS_API_KEY')
            ?: env('GOOGLE_GEOCODING_API_KEY')
            ?: env('GOOGLE_API_KEY');

        if ($provider === 'auto') {
            $provider = $googleKey ? 'google' : 'nominatim';
        }

        if ($provider === 'google' && !$googleKey) {
            $this->warn('Google key not found (GOOGLE_MAPS_API_KEY). Falling back to Nominatim.');
            $provider = 'nominatim';
        }

        $this->info("Provider: {$provider}" . ($dry ? ' [DRY-RUN]' : ''));

        $q = Institution::query()->where('type', 'university')->orderBy('id');
        if ($onlyMissing) {
            $q->where(function ($qq) {
                $qq->whereNull('latitude')
                    ->orWhereNull('longitude')
                    ->orWhere('latitude', 0)
                    ->orWhere('longitude', 0);
            });
        }
        if ($limit > 0) {
            $q->limit($limit);
        }

        $rows = $q->get();
        $this->info('To process: ' . $rows->count());

        $ok = 0;
        $fail = 0;
        $skip = 0;

        foreach ($rows as $i => $inst) {
            $query = $this->buildQuery($inst);
            $this->line(sprintf('[%d/%d] #%d %s', $i + 1, $rows->count(), $inst->id, $inst->name));
            $this->line('  q: ' . $query);

            try {
                $coords = $provider === 'google'
                    ? $this->geocodeGoogle($query, $googleKey)
                    : $this->geocodeNominatim($query, $inst);

                // rate limits
                usleep($provider === 'google' ? 150_000 : 1_100_000);

                if (!$coords) {
                    // one more try: name + city only
                    $city = $this->cityFrom($inst);
                    $fallback = trim($inst->name . ', ' . $city . ', Kazakhstan');
                    $this->line('  retry: ' . $fallback);
                    $coords = $provider === 'google'
                        ? $this->geocodeGoogle($fallback, $googleKey)
                        : $this->geocodeNominatim($fallback, $inst);
                    usleep($provider === 'google' ? 150_000 : 1_100_000);
                }

                if (!$coords) {
                    $this->warn('  NOT FOUND');
                    $fail++;
                    continue;
                }

                [$lat, $lng, $src, $display] = $coords;
                $this->line(sprintf('  → %.7f, %.7f (%s) %s', $lat, $lng, $src, $display ?? ''));

                if (!$dry) {
                    $inst->latitude = $lat;
                    $inst->longitude = $lng;
                    $inst->save();
                }
                $ok++;
            } catch (\Throwable $e) {
                $this->error('  ERR ' . $e->getMessage());
                $fail++;
                usleep(1_000_000);
            }
        }

        $this->table(
            ['ok', 'fail', 'skip', 'provider', 'dry'],
            [[$ok, $fail, $skip, $provider, $dry ? 'yes' : 'no']]
        );

        return self::SUCCESS;
    }

    private function buildQuery(Institution $inst): string
    {
        $parts = array_filter([
            $inst->name,
            $inst->address,
            $this->cityFrom($inst),
            'Kazakhstan',
        ], fn ($p) => $p && is_string($p) && mb_strlen(trim($p)) > 1);

        // address sometimes contains full description noise — keep short
        $addr = $inst->address;
        if ($addr && mb_strlen($addr) > 120) {
            $parts = array_filter([
                $inst->name,
                $this->cityFrom($inst),
                'Kazakhstan',
            ]);
        }

        return implode(', ', $parts);
    }

    private function cityFrom(Institution $inst): string
    {
        $t = mb_strtolower(($inst->location ?? '') . ' ' . ($inst->address ?? ''));
        if (str_contains($t, 'алматы') || str_contains($t, 'almaty')) {
            return 'Almaty';
        }
        if (str_contains($t, 'астана') || str_contains($t, 'astana') || str_contains($t, 'нур-султан')) {
            return 'Astana';
        }
        if (str_contains($t, 'шымкент') || str_contains($t, 'shymkent')) {
            return 'Shymkent';
        }
        // fallback from location string
        if ($inst->location) {
            return explode(',', $inst->location)[0];
        }
        return 'Kazakhstan';
    }

    /**
     * @return array{0:float,1:float,2:string,3:?string}|null
     */
    private function geocodeGoogle(string $query, string $key): ?array
    {
        $resp = Http::timeout(30)->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $query,
            'key' => $key,
            'language' => 'ru',
            'region' => 'kz',
        ]);

        if (!$resp->ok()) {
            throw new \RuntimeException('Google HTTP ' . $resp->status());
        }

        $json = $resp->json();
        $status = $json['status'] ?? 'UNKNOWN';
        if ($status === 'ZERO_RESULTS') {
            return null;
        }
        if ($status !== 'OK') {
            throw new \RuntimeException('Google status: ' . $status . ' ' . ($json['error_message'] ?? ''));
        }

        $r = $json['results'][0] ?? null;
        if (!$r) {
            return null;
        }
        $loc = $r['geometry']['location'] ?? null;
        if (!$loc) {
            return null;
        }

        return [
            (float) $loc['lat'],
            (float) $loc['lng'],
            'google',
            $r['formatted_address'] ?? null,
        ];
    }

    /**
     * @return array{0:float,1:float,2:string,3:?string}|null
     */
    private function geocodeNominatim(string $query, Institution $inst): ?array
    {
        $resp = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'TorapCareerGuidance/1.0 (education platform geocoding; local)',
            ])
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query,
                'format' => 'json',
                'limit' => 3,
                'countrycodes' => 'kz',
                'addressdetails' => 1,
            ]);

        if (!$resp->ok()) {
            throw new \RuntimeException('Nominatim HTTP ' . $resp->status());
        }

        $items = $resp->json();
        if (!is_array($items) || !$items) {
            return null;
        }

        // prefer university/college class if present
        $best = $items[0];
        foreach ($items as $it) {
            $type = ($it['type'] ?? '') . ' ' . ($it['class'] ?? '');
            if (preg_match('/university|college|school|campus/i', $type)) {
                $best = $it;
                break;
            }
        }

        $lat = isset($best['lat']) ? (float) $best['lat'] : null;
        $lng = isset($best['lon']) ? (float) $best['lon'] : null;
        if ($lat === null || $lng === null) {
            return null;
        }

        // sanity: Kazakhstan bbox roughly
        if ($lat < 40 || $lat > 56 || $lng < 46 || $lng > 88) {
            return null;
        }

        return [$lat, $lng, 'nominatim', $best['display_name'] ?? null];
    }
}
