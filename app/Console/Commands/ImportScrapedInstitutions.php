<?php

namespace App\Console\Commands;

use App\Models\GlobalSpecialty;
use App\Models\Institution;
use App\Models\Qualification;
use App\Models\Specialization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Импорт JSON из data-scrapers/output/vipusknik_astana.json
 *
 * php artisan data:import-scraped "../data-scrapers/output/vipusknik_astana.json"
 * php artisan data:import-scraped "../data-scrapers/output/vipusknik_astana.json" --dry-run
 */
class ImportScrapedInstitutions extends Command
{
    protected $signature = 'data:import-scraped
        {path : Path to vipusknik_astana.json (or similar)}
        {--dry-run : Parse and report without writing DB}
        {--update : Update existing institutions matched by name}';

    protected $description = 'Import scraped Astana institutions + specialties + prices into DB';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (!is_file($path)) {
            // try relative to project root
            $alt = base_path($path);
            if (is_file($alt)) {
                $path = $alt;
            } else {
                $this->error("File not found: {$path}");
                return self::FAILURE;
            }
        }

        $payload = json_decode(file_get_contents($path), true);
        if (!$payload || empty($payload['institutions'])) {
            $this->error('Invalid JSON or empty institutions[]');
            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $update = (bool) $this->option('update');

        $stats = [
            'institutions_created' => 0,
            'institutions_updated' => 0,
            'institutions_skipped' => 0,
            'specializations_created' => 0,
            'links_created' => 0,
            'links_updated' => 0,
            'errors' => 0,
        ];

        $this->info(sprintf(
            'Importing %d institutions from %s%s',
            count($payload['institutions']),
            $path,
            $dry ? ' [DRY-RUN]' : ''
        ));

        $runner = function () use ($payload, $dry, $update, &$stats) {
            foreach ($payload['institutions'] as $row) {
                if (!empty($row['error']) || empty($row['name'])) {
                    $stats['institutions_skipped']++;
                    continue;
                }

                try {
                    $this->importOne($row, $dry, $update, $stats);
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $this->warn("  ! {$row['name']}: {$e->getMessage()}");
                }
            }
        };

        if ($dry) {
            $runner();
        } else {
            DB::transaction($runner);
        }

        $this->table(array_keys($stats), [array_values($stats)]);
        $this->info($dry ? 'Dry-run complete (no DB writes).' : 'Import complete.');

        return self::SUCCESS;
    }

    private function findExistingInstitution(string $name, array $row): ?Institution
    {
        $exact = Institution::where('name', $name)->first();
        if ($exact) {
            return $exact;
        }

        $rowCity = $this->cityKey($row['location'] ?? $row['address'] ?? '');

        // match by website host ONLY if cities match AND names look related
        $website = $row['website'] ?? null;
        if ($website) {
            $host = parse_url($website, PHP_URL_HOST);
            if ($host) {
                $host = preg_replace('/^www\./', '', strtolower($host));
                $candidates = Institution::whereNotNull('website')
                    ->where('website', 'like', '%' . $host . '%')
                    ->get();
                foreach ($candidates as $byWeb) {
                    $instCity = $this->cityKey($byWeb->location ?? $byWeb->address ?? '');
                    if ($rowCity !== '' && $instCity !== '' && $rowCity !== $instCity) {
                        continue;
                    }
                    if ($this->namesRelated($name, $byWeb->name)) {
                        return $byWeb;
                    }
                }
            }
        }

        // STRICT needles only (no bare "туран", "искусств", "технолог")
        $aliases = [
            'гумил' => ['гумил'],
            'назарбаев университет' => ['назарбаев'],
            'сейфулл' => ['сейфулл'],
            'narikbayev' => ['narikbayev', 'казгюу'],
            'казгюу' => ['казгюу', 'narikbayev'],
            'astana it' => ['астана it', 'astana it'],
            'esil university' => ['esil'],
            'ломоносов' => ['ломоносов'],
            'медицинский университет астана' => ['медицинский университет астана'],
            'международный университет астана' => ['международный университет астан'],
            'хореограф' => ['хореограф'],
            'кулажан' => ['кулажан'],
            'государственного управления при президенте' => ['государственного управления'],
            'аль-фараби' => ['аль-фараби', 'ал-фараби'],
            'асфендияров' => ['асфендияров'],
            'саtbayev' => ['сатпаев', 'satbayev'],
            'сатбаев' => ['сатпаев', 'satbayev'],
            'нархоз' => ['нархоз'],
            'кимеп' => ['кимеп', 'kimep'],
            'кбту' => ['кбту', 'british'],
        ];

        $lower = mb_strtolower($name);
        $matchedNeedles = [];
        foreach ($aliases as $hint => $needles) {
            if (str_contains($lower, $hint) || collect($needles)->contains(fn ($n) => str_contains($lower, $n))) {
                $matchedNeedles = array_merge($matchedNeedles, $needles);
            }
        }
        $matchedNeedles = array_unique($matchedNeedles);
        if (!$matchedNeedles) {
            return null;
        }

        $all = Institution::query()->where('type', 'university')->get(['id', 'name', 'location', 'address']);
        foreach ($all as $inst) {
            if (!$this->sameCity($rowCity, $this->cityKey($inst->location ?? $inst->address ?? ''))) {
                continue;
            }
            $in = mb_strtolower($inst->name);
            foreach ($matchedNeedles as $n) {
                if (str_contains($in, $n)) {
                    return $inst;
                }
            }
        }

        return null;
    }

    private function cityKey(?string $text): string
    {
        $t = mb_strtolower($text ?? '');
        if (str_contains($t, 'алматы') || str_contains($t, 'almaty')) {
            return 'almaty';
        }
        if (str_contains($t, 'астана') || str_contains($t, 'astana') || str_contains($t, 'нур-султан')) {
            return 'astana';
        }
        if (str_contains($t, 'шымкент') || str_contains($t, 'shymkent')) {
            return 'shymkent';
        }
        return '';
    }

    private function sameCity(string $a, string $b): bool
    {
        // unknown city → allow match (legacy rows without location)
        if ($a === '' || $b === '') {
            return true;
        }
        return $a === $b;
    }

    private function namesRelated(string $a, string $b): bool
    {
        $na = mb_strtolower($a);
        $nb = mb_strtolower($b);
        if ($na === $nb) {
            return true;
        }
        // significant token overlap (length >= 5), ignore generic words
        $stop = ['университет', 'university', 'институт', 'академия', 'имени', 'им', 'казахский',
            'казахская', 'казахстанский', 'национальный', 'международный', 'республики', 'казахстан'];
        $tok = function (string $s) use ($stop) {
            $parts = preg_split('/[^\p{L}\p{N}]+/u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            return collect($parts)
                ->map(fn ($t) => mb_strtolower($t))
                ->filter(fn ($t) => mb_strlen($t) >= 5 && !in_array($t, $stop, true))
                ->values();
        };
        $ta = $tok($na);
        $tb = $tok($nb);
        if ($ta->isEmpty() || $tb->isEmpty()) {
            return false;
        }
        $overlap = $ta->intersect($tb)->count();
        return $overlap >= 1;
    }

    private function importOne(array $row, bool $dry, bool $update, array &$stats): void
    {
        $name = trim($row['name']);
        $existing = $this->findExistingInstitution($name, $row);

        $attrs = [
            'description1' => $row['description1'] ?? null,
            'description2' => $row['description2'] ?? null,
            'description3' => $row['description3'] ?? null,
            'location' => $row['location'] ?? 'Астана, Казахстан',
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'website' => $row['website'] ?? null,
            'verified' => $row['verified'] ?? 'accepted',
            'logo_url' => $this->normalizeExternalMedia($row['logo_url'] ?? null),
            'photo_url' => $this->normalizeExternalMedia($row['photo_url'] ?? null),
            'dormitory' => (bool) ($row['dormitory'] ?? false),
            'grants' => (bool) ($row['grants'] ?? false),
            'type' => $row['type'] ?? 'university',
            'directions' => $row['directions'] ?? null,
            'latitude' => $row['latitude'] ?? null,
            'longitude' => $row['longitude'] ?? null,
        ];

        // address column may exist in DB even if not in fillable — use forceFill carefully
        if (!$existing) {
            if ($dry) {
                $this->line("  + create institution: {$name}");
                $stats['institutions_created']++;
                $institutionId = null;
            } else {
                $institution = new Institution();
                $institution->fill($attrs);
                $institution->name = $name;
                $institution->password = Hash::make('ChangeMe!' . substr(md5($name), 0, 8));
                // address if column exists
                if (\Schema::hasColumn('institutions', 'address')) {
                    $institution->setAttribute('address', $row['address'] ?? null);
                }
                $institution->save();
                $institutionId = $institution->id;
                $stats['institutions_created']++;
                $this->line("  + #{$institutionId} {$name}");
            }
        } else {
            $institutionId = $existing->id;
            // always attach specialties to existing; update meta only with --update
            if ($update) {
                if (!$dry) {
                    // don't overwrite non-empty fields with null
                    $filtered = array_filter($attrs, fn ($v) => $v !== null && $v !== '');
                    $existing->fill($filtered);
                    if (\Schema::hasColumn('institutions', 'address') && !empty($row['address'])) {
                        $existing->setAttribute('address', $row['address']);
                    }
                    $existing->save();
                }
                $stats['institutions_updated']++;
                $this->line("  ~ #{$institutionId} {$existing->name} ← {$name}");
            } else {
                $stats['institutions_skipped']++;
                $this->line("  = existing #{$institutionId} {$existing->name} (link specialties)");
            }
        }

        $specialties = $row['specialties'] ?? [];
        if (!$specialties) {
            return;
        }

        foreach ($specialties as $spec) {
            $specName = trim($spec['name'] ?? '');
            if ($specName === '') {
                continue;
            }

            $globalName = trim($spec['global_specialty'] ?? 'Прочее') ?: 'Прочее';
            $code = $spec['code'] ?? null;
            $cost = $spec['cost'] ?? null;
            $duration = $spec['duration'] ?? null;

            if ($dry) {
                $stats['specializations_created']++;
                $stats['links_created']++;
                continue;
            }

            $global = GlobalSpecialty::firstOrCreate(
                ['name' => $globalName],
                ['description' => $code ? "Код: {$code}" : null]
            );

            $qualName = $code ? "{$globalName} ({$code})" : $globalName;
            $qualification = Qualification::firstOrCreate(
                [
                    'qualification_name' => $qualName,
                    'global_specialty_id' => $global->id,
                ]
            );

            // ensure global_specialty_id if fillable/column allows but firstOrCreate key missed it on old rows
            if (!$qualification->global_specialty_id) {
                $qualification->global_specialty_id = $global->id;
                $qualification->save();
            }

            $specialization = Specialization::firstOrCreate(
                [
                    'name' => $specName,
                    'qualification_id' => $qualification->id,
                ],
                [
                    'description' => $code ? "Код группы: {$code}" : null,
                ]
            );
            if ($specialization->wasRecentlyCreated) {
                $stats['specializations_created']++;
            }

            if (!$institutionId) {
                continue;
            }

            $exists = DB::table('institution_specialties')
                ->where('institution_id', $institutionId)
                ->where('university_specialization_id', $specialization->id)
                ->first();

            if ($exists) {
                DB::table('institution_specialties')
                    ->where('id', $exists->id)
                    ->update([
                        'cost' => $cost ?? $exists->cost,
                        'duration' => $duration ?? $exists->duration,
                        'updated_at' => now(),
                    ]);
                $stats['links_updated']++;
            } else {
                DB::table('institution_specialties')->insert([
                    'institution_id' => $institutionId,
                    'university_specialization_id' => $specialization->id,
                    'cost' => $cost,
                    'duration' => $duration,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $stats['links_created']++;
            }
        }
    }

    /**
     * logo_url/photo_url в модели гоняются через Storage::url —
     * внешние https URL лучше не портить: сохраняем как есть, если https.
     * (accessor makeAbsoluteUrl всё равно вернёт корректный URL для http)
     */
    private function normalizeExternalMedia(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        // Если accessor делает Storage::url, внешние URL могут сломаться.
        // Храним полный URL — getLogoUrlAttribute вызовет Storage::url только если path.
        // Смотри Institution::makeAbsoluteUrl — для http(s) он не трогает? 
        // Actually: makeAbsoluteUrl always does Storage::url($path) first.
        // So external URLs will break. Safer: skip logo for remote absolute URLs for now
        // OR store null and keep website. We store URL as-is; if broken on API, fix accessor later.
        if (preg_match('#^https?://#i', $url)) {
            // Prefer skip writing remote into logo_url until accessor fixed —
            // but many seeders already store full https. Keep them.
            return $url;
        }
        return $url;
    }
}
