<?php

namespace App\Console\Commands;

use App\Models\Institution;
use Illuminate\Console\Command;

/**
 * Применяет output/geocode_results.json к institutions.latitude/longitude
 *
 * php artisan data:apply-geocode "../data-scrapers/output/geocode_results.json"
 */
class ApplyGeocodeResults extends Command
{
    protected $signature = 'data:apply-geocode {path} {--dry-run}';

    protected $description = 'Apply geocode_results.json lat/lng to institutions table';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (!is_file($path)) {
            $path = base_path($path);
        }
        if (!is_file($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $rows = json_decode(file_get_contents($path), true);
        if (!is_array($rows)) {
            $this->error('Invalid JSON');
            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $ok = 0;
        $skip = 0;

        foreach ($rows as $r) {
            if (empty($r['ok']) || empty($r['id']) || !isset($r['lat'], $r['lng'])) {
                $skip++;
                continue;
            }
            $inst = Institution::find($r['id']);
            if (!$inst) {
                $this->warn("missing institution #{$r['id']}");
                $skip++;
                continue;
            }

            $this->line(sprintf(
                '#%d %s → %.7f, %.7f (%s)',
                $inst->id,
                $inst->name,
                $r['lat'],
                $r['lng'],
                $r['source'] ?? '?'
            ));

            if (!$dry) {
                $inst->latitude = $r['lat'];
                $inst->longitude = $r['lng'];
                $inst->save();
            }
            $ok++;
        }

        $this->info(($dry ? 'Dry-run: would update ' : 'Updated ') . "{$ok}, skipped {$skip}");
        return self::SUCCESS;
    }
}
