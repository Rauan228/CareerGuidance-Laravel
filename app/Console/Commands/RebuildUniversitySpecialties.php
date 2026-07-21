<?php

namespace App\Console\Commands;

use App\Models\Institution;
use App\Models\Specialization;
use App\Services\UniversitySpecialtyTree;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Полная пересборка university specialty-дерева 1:1 из vipusknik JSON.
 *
 * Стирает globals/qualifications/specializations + institution_specialties,
 * затем заново импортирует ОП ровно как у вузов:
 *   global_specialty → qualification (код · группа) → specialization (ОП) → pivot
 *
 * php artisan data:rebuild-university-specialties ^
 *   "../data-scrapers/output/vipusknik_astana.json" ^
 *   "../data-scrapers/output/vipusknik_алматы.json"
 *
 * php artisan data:rebuild-university-specialties path1 path2 --dry-run
 */
class RebuildUniversitySpecialties extends Command
{
    protected $signature = 'data:rebuild-university-specialties
        {paths* : Один или несколько vipusknik_*.json}
        {--dry-run : Только отчёт, без записи}
        {--force : Без интерактивного подтверждения}';

    protected $description = 'Wipe + rebuild university specialty tree 1:1 from vipusknik scrape JSON';

    public function handle(UniversitySpecialtyTree $tree): int
    {
        $dry = (bool) $this->option('dry-run');
        $paths = [];
        foreach ($this->argument('paths') as $p) {
            $resolved = $this->resolvePath($p);
            if (!$resolved) {
                $this->error("File not found: {$p}");
                return self::FAILURE;
            }
            $paths[] = $resolved;
        }

        $institutions = [];
        foreach ($paths as $path) {
            $payload = json_decode(file_get_contents($path), true);
            $list = $payload['institutions'] ?? (is_array($payload) && array_is_list($payload) ? $payload : null);
            if (!is_array($list)) {
                $this->error("Invalid JSON (no institutions): {$path}");
                return self::FAILURE;
            }
            $this->info(sprintf('%s → %d institutions', basename($path), count($list)));
            foreach ($list as $row) {
                if (!empty($row['error']) || empty($row['name'])) {
                    continue;
                }
                $institutions[] = $row;
            }
        }

        $totalPrograms = 0;
        foreach ($institutions as $row) {
            $totalPrograms += count($row['specialties'] ?? []);
        }

        $this->line(sprintf(
            'Rows to import: %d institutions, %d specialty rows%s',
            count($institutions),
            $totalPrograms,
            $dry ? ' [DRY-RUN]' : ''
        ));

        if ($dry) {
            $this->preview($institutions);
            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('Полностью пересобрать university specialties (wipe + import)?', true)) {
            $this->warn('Aborted.');
            return self::SUCCESS;
        }

        $stats = [
            'institutions_matched' => 0,
            'institutions_created' => 0,
            'institutions_missing_specs' => 0,
            'programs' => 0,
            'specializations_created' => 0,
            'links_created' => 0,
            'links_updated' => 0,
            'skipped_programs' => 0,
            'errors' => 0,
        ];

        DB::transaction(function () use ($tree, $institutions, &$stats) {
            $this->wipeTree();

            foreach ($institutions as $row) {
                try {
                    $this->importInstitution($tree, $row, $stats);
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $this->warn('  ! ' . ($row['name'] ?? '?') . ': ' . $e->getMessage());
                }
            }
        });

        $this->newLine();
        $this->table(array_keys($stats), [array_values($stats)]);
        $this->info('DB after rebuild:');
        $this->table(
            ['table', 'count'],
            [
                ['global_specialties', DB::table('global_specialties')->count()],
                ['qualifications', DB::table('qualifications')->count()],
                ['specializations', DB::table('specializations')->count()],
                ['institution_specialties', DB::table('institution_specialties')->count()],
            ]
        );

        // sanity: each global should have ~1 qualification (group code)
        $multi = DB::select("
            SELECT g.id, g.name, COUNT(q.id) as qc
            FROM global_specialties g
            JOIN qualifications q ON q.global_specialty_id = g.id
            GROUP BY g.id, g.name
            HAVING COUNT(q.id) > 1
            ORDER BY qc DESC
            LIMIT 15
        ");
        if ($multi) {
            $this->warn('Globals with >1 qualification (check):');
            foreach ($multi as $r) {
                $this->line("  #{$r->id} q={$r->qc} | {$r->name}");
            }
        } else {
            $this->info('OK: every global has a single qualification (group) — 1:1 with uni cards.');
        }

        return self::SUCCESS;
    }

    private function wipeTree(): void
    {
        $this->warn('Wiping institution_specialties, specializations, qualifications, global_specialties…');
        // order matters for FKs
        DB::table('institution_specialties')->delete();
        DB::table('specializations')->delete();
        DB::table('qualifications')->delete();
        DB::table('global_specialties')->delete();

        // reset sequences on pgsql so ids start clean (optional but nice)
        if (DB::getDriverName() === 'pgsql') {
            foreach (['global_specialties', 'qualifications', 'specializations', 'institution_specialties'] as $t) {
                try {
                    DB::statement("ALTER SEQUENCE {$t}_id_seq RESTART WITH 1");
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
    }

    private function importInstitution(UniversitySpecialtyTree $tree, array $row, array &$stats): void
    {
        $name = trim($row['name']);
        $inst = $this->findOrCreateInstitution($row, $stats);
        $specs = $row['specialties'] ?? [];

        if (!$specs) {
            $stats['institutions_missing_specs']++;
            $this->line("  = #{$inst->id} {$inst->name} (no specialties in JSON)");
            return;
        }

        $created = 0;
        $linked = 0;
        foreach ($specs as $spec) {
            $node = $tree->ensureProgram($spec);
            if (!$node) {
                $stats['skipped_programs']++;
                continue;
            }
            $stats['programs']++;
            if ($node['created_spec']) {
                $stats['specializations_created']++;
                $created++;
            }
            $result = $tree->linkToInstitution(
                $inst->id,
                $node['specialization'],
                $spec['cost'] ?? null,
                $spec['duration'] ?? null
            );
            if ($result === 'created') {
                $stats['links_created']++;
                $linked++;
            } elseif ($result === 'updated') {
                $stats['links_updated']++;
            }
        }

        $this->line(sprintf(
            '  ✓ #%d %s → %d programs (%d new specs, %d new links)',
            $inst->id,
            mb_substr($inst->name, 0, 50),
            count($specs),
            $created,
            $linked
        ));
    }

    private function findOrCreateInstitution(array $row, array &$stats): Institution
    {
        $name = trim($row['name']);
        $existing = $this->findExistingInstitution($name, $row);
        if ($existing) {
            $stats['institutions_matched']++;
            return $existing;
        }

        $institution = new Institution();
        $attrs = [
            'name' => $name,
            'description1' => $row['description1'] ?? null,
            'description2' => $row['description2'] ?? null,
            'description3' => $row['description3'] ?? null,
            'location' => $row['location'] ?? null,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'website' => $row['website'] ?? null,
            'verified' => $row['verified'] ?? 'accepted',
            'dormitory' => (bool) ($row['dormitory'] ?? false),
            'grants' => (bool) ($row['grants'] ?? false),
            'type' => $row['type'] ?? 'university',
            'directions' => $row['directions'] ?? null,
            'latitude' => $row['latitude'] ?? null,
            'longitude' => $row['longitude'] ?? null,
            'password' => Hash::make('ChangeMe!' . substr(md5($name), 0, 8)),
        ];
        $institution->fill($attrs);
        if (Schema::hasColumn('institutions', 'address')) {
            $institution->setAttribute('address', $row['address'] ?? null);
        }
        $institution->save();
        $stats['institutions_created']++;
        $this->line("  + created institution #{$institution->id} {$name}");

        return $institution;
    }

    /**
     * Match only universities (scrape is uni-only). Never attach ОП to colleges.
     */
    private function findExistingInstitution(string $name, array $row): ?Institution
    {
        $wantType = $row['type'] ?? 'university';
        if ($wantType === 'college') {
            $wantType = 'college';
        } else {
            $wantType = 'university';
        }

        $base = Institution::query()->where('type', $wantType);

        $exact = (clone $base)->where('name', $name)->first();
        if ($exact) {
            return $exact;
        }

        // normalized compare (quotes, spaces, case, ё/е, endings а/ы)
        $norm = $this->normName($name);
        $soft = $this->softName($name);
        // older ids first = canonical seed/import rows beat accidental duplicates
        foreach ((clone $base)->orderBy('id')->get(['id', 'name', 'location', 'address']) as $inst) {
            if ($this->normName($inst->name) === $norm) {
                return Institution::find($inst->id);
            }
            if ($soft !== '' && $this->softName($inst->name) === $soft) {
                return Institution::find($inst->id);
            }
        }

        $rowCity = $this->cityKey($row['location'] ?? $row['address'] ?? '');

        $website = $row['website'] ?? null;
        if ($website) {
            $host = parse_url($website, PHP_URL_HOST);
            if ($host) {
                $host = preg_replace('/^www\./', '', strtolower($host));
                $candidates = (clone $base)->whereNotNull('website')
                    ->where('website', 'like', '%' . $host . '%')
                    ->get();
                // if exactly one uni with this host in same city — take it
                $cityFiltered = $candidates->filter(function ($byWeb) use ($rowCity) {
                    $instCity = $this->cityKey($byWeb->location ?? $byWeb->address ?? '');
                    return $this->sameCity($rowCity, $instCity);
                })->values();
                if ($cityFiltered->count() === 1) {
                    return $cityFiltered->first();
                }
                foreach ($cityFiltered as $byWeb) {
                    if ($this->namesRelated($name, $byWeb->name)) {
                        return $byWeb;
                    }
                }
            }
        }

        $aliases = [
            'гумил' => ['гумил'],
            'назарбаев университет' => ['назарбаев'],
            'сейфулл' => ['сейфулл'],
            'narikbayev' => ['narikbayev', 'казгюу'],
            'казгюу' => ['казгюу', 'narikbayev'],
            // AITU: require university, not «колледж …»
            'astana it university' => ['astana it университет', 'астана it университет', 'astana it university'],
            'астана it университет' => ['astana it', 'астана it'],
            'esil university' => ['esil'],
            'ломоносов' => ['ломоносов'],
            'аль-фараби' => ['аль-фараби', 'ал-фараби'],
            'асфендияров' => ['асфендияров'],
            'satbayev' => ['сатпаев', 'satbayev', 'сатбаев'],
            'сатбаев' => ['сатпаев', 'satbayev'],
            'нархоз' => ['нархоз'],
            'кимеп' => ['кимеп', 'kimep'],
            'кбту' => ['кбту', 'british'],
            'абая' => ['абая'],
            'жургенов' => ['жургенов'],
            'курмангазы' => ['курмангазы'],
            'кулажан' => ['кулажан'],
            'технологии и бизнеса' => ['технологии и бизнеса', 'technology and business'],
            'международный университет астан' => ['международный университет астан'],
            'национальный университет искусств' => ['национальный университет искусств'],
        ];

        $lower = mb_strtolower($name);
        $matchedNeedles = [];
        foreach ($aliases as $hint => $needles) {
            if (str_contains($lower, $hint) || collect($needles)->contains(fn ($n) => str_contains($lower, $n))) {
                $matchedNeedles = array_merge($matchedNeedles, $needles);
                $matchedNeedles[] = $hint;
            }
        }
        $matchedNeedles = array_unique($matchedNeedles);
        if (!$matchedNeedles) {
            return null;
        }

        foreach ((clone $base)->get(['id', 'name', 'location', 'address']) as $inst) {
            // never match «колледж …» when looking for a university
            if ($wantType === 'university' && str_contains(mb_strtolower($inst->name), 'колледж')) {
                continue;
            }
            if (!$this->sameCity($rowCity, $this->cityKey($inst->location ?? $inst->address ?? ''))) {
                continue;
            }
            $in = mb_strtolower($inst->name);
            foreach ($matchedNeedles as $n) {
                if (mb_strlen($n) >= 5 && str_contains($in, $n)) {
                    return Institution::find($inst->id);
                }
            }
        }

        return null;
    }

    private function normName(string $s): string
    {
        $s = mb_strtolower($s);
        $s = str_replace(['«', '»', '"', "'", '“', '”', 'ё'], ['', '', '', '', '', '', 'е'], $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    /** Soft key: drop generic words, unify endings for fuzzy equality. */
    private function softName(string $s): string
    {
        $s = $this->normName($s);
        $s = preg_replace('/\b(университет|university|институт|академия|имени|им|республики|казахстан|колледж|college)\b/u', '', $s) ?? $s;
        // казахский / казахстанский
        $s = preg_replace('/\bказахстанск\w*\b/u', 'каз', $s) ?? $s;
        $s = preg_replace('/\bказахск\w*\b/u', 'каз', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        // Астана/Астаны
        $s = preg_replace('/астаны\b/u', 'астана', $s) ?? $s;
        // drop single-letter initials «к.»
        $s = preg_replace('/\b\p{L}\.\b/u', '', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
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

        return '';
    }

    private function sameCity(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return true;
        }

        return $a === $b;
    }

    private function namesRelated(string $a, string $b): bool
    {
        if ($this->softName($a) !== '' && $this->softName($a) === $this->softName($b)) {
            return true;
        }
        $na = mb_strtolower($a);
        $nb = mb_strtolower($b);
        if ($na === $nb) {
            return true;
        }
        // college vs university with same brand — not related for our purposes
        $aCollege = str_contains($na, 'колледж') || str_contains($na, 'college');
        $bCollege = str_contains($nb, 'колледж') || str_contains($nb, 'college');
        if ($aCollege !== $bCollege) {
            return false;
        }
        $stop = ['университет', 'university', 'институт', 'академия', 'имени', 'им', 'казахский',
            'казахская', 'казахстанский', 'национальный', 'международный', 'республики', 'казахстан',
            'астана', 'алматы', 'колледж', 'college'];
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
        // need stronger overlap for short distinctive tokens
        return $ta->intersect($tb)->count() >= 1;
    }

    private function preview(array $institutions): void
    {
        $globals = [];
        $codes = [];
        $programs = 0;
        foreach ($institutions as $row) {
            foreach ($row['specialties'] ?? [] as $s) {
                $programs++;
                $g = trim($s['global_specialty'] ?? '') ?: 'Образовательные программы';
                $globals[$g] = true;
                $c = trim($s['code'] ?? '');
                if ($c !== '') {
                    $codes[$c] = true;
                }
            }
        }
        $this->table(
            ['metric', 'value'],
            [
                ['institutions in JSON', count($institutions)],
                ['program rows', $programs],
                ['unique global_specialty', count($globals)],
                ['unique codes', count($codes)],
            ]
        );
        $this->line('Sample globals: ' . implode(' | ', array_slice(array_keys($globals), 0, 8)));
    }

    private function resolvePath(string $path): ?string
    {
        if (is_file($path)) {
            return $path;
        }
        $alt = base_path($path);
        if (is_file($alt)) {
            return $alt;
        }
        // try sibling data-scrapers from Laravel root
        $alt2 = dirname(base_path()) . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        if (is_file($alt2)) {
            return $alt2;
        }

        return null;
    }
}
