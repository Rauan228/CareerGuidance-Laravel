<?php

namespace App\Console\Commands;

use App\Models\GlobalSpecialty;
use App\Models\Institution;
use App\Models\Qualification;
use App\Models\Specialization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Импорт программ с официальных сайтов.
 * cost=null → на фронте показывать «-»
 *
 * php artisan data:import-official-programs "../data-scrapers/output/official_programs.json"
 * php artisan data:import-official-programs path.json --replace
 * php artisan data:import-official-programs path.json --min-programs=3
 */
class ImportOfficialPrograms extends Command
{
    protected $signature = 'data:import-official-programs
        {path : official_programs.json}
        {--replace : Delete old specialty links for institution before import}
        {--min-programs=2 : Skip institutions with fewer extracted programs}
        {--dry-run}';

    protected $description = 'Import specialties/qualifications/costs from official site scrape';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (!is_file($path)) {
            $path = base_path($path);
        }
        if (!is_file($path)) {
            $this->error("File not found");
            return self::FAILURE;
        }

        $payload = json_decode(file_get_contents($path), true);
        $list = $payload['institutions'] ?? $payload;
        if (!is_array($list)) {
            $this->error('Invalid JSON');
            return self::FAILURE;
        }

        $replace = (bool) $this->option('replace');
        $min = (int) $this->option('min-programs');
        $dry = (bool) $this->option('dry-run');

        $stats = [
            'institutions' => 0,
            'skipped' => 0,
            'programs' => 0,
            'with_cost' => 0,
            'links' => 0,
        ];

        foreach ($list as $row) {
            $id = $row['institution_id'] ?? null;
            $programs = $row['programs'] ?? [];
            if (!$id || empty($row['ok']) || count($programs) < $min) {
                $stats['skipped']++;
                $this->line(sprintf(
                    '  skip #%s %s (ok=%s n=%d err=%s)',
                    $id ?? '?',
                    $row['name'] ?? '',
                    !empty($row['ok']) ? '1' : '0',
                    count($programs),
                    $row['error'] ?? ''
                ));
                continue;
            }

            $inst = Institution::find($id);
            if (!$inst) {
                $stats['skipped']++;
                continue;
            }

            $this->info(sprintf(
                '#%d %s → %d programs (%d with cost)',
                $inst->id,
                $inst->name,
                count($programs),
                collect($programs)->whereNotNull('cost')->count()
            ));

            if ($dry) {
                $stats['institutions']++;
                $stats['programs'] += count($programs);
                $stats['with_cost'] += collect($programs)->whereNotNull('cost')->count();
                $stats['links'] += count($programs);
                continue;
            }

            // по вузу отдельно — длинные remote-сессии не держат одну огромную транзакцию
            try {
                if ($replace) {
                    DB::table('institution_specialties')->where('institution_id', $inst->id)->delete();
                }

                foreach ($programs as $p) {
                    $name = trim($p['name'] ?? '');
                    if ($name === '' || mb_strlen($name) < 3) {
                        continue;
                    }

                    $globalName = trim($p['global_specialty'] ?? 'Образовательные программы') ?: 'Образовательные программы';
                    $qualName = trim($p['qualification'] ?? $globalName) ?: $globalName;
                    $code = $p['code'] ?? null;
                    // не дублировать код в названии, если уже есть
                    if ($code && !str_contains($qualName, $code)) {
                        $qualName = $code . ' · ' . $qualName;
                    }

                    $cost = $p['cost'] ?? null;
                    if ($cost !== null && $cost !== '') {
                        $cost = (float) $cost;
                        if ($cost <= 0) {
                            $cost = null;
                        }
                    } else {
                        $cost = null; // «-» на фронте
                    }
                    $duration = isset($p['duration']) && $p['duration'] !== ''
                        ? (int) $p['duration']
                        : null;

                    $global = GlobalSpecialty::firstOrCreate(
                        ['name' => $globalName],
                        ['description' => $code ? "Код: {$code}" : null]
                    );

                    $qualification = Qualification::firstOrCreate(
                        [
                            'qualification_name' => mb_substr($qualName, 0, 240),
                            'global_specialty_id' => $global->id,
                        ]
                    );

                    $specialization = Specialization::firstOrCreate(
                        [
                            'name' => mb_substr($name, 0, 240),
                            'qualification_id' => $qualification->id,
                        ],
                        [
                            'description' => $code ? "Код: {$code}" : null,
                        ]
                    );

                    $exists = DB::table('institution_specialties')
                        ->where('institution_id', $inst->id)
                        ->where('university_specialization_id', $specialization->id)
                        ->first();

                    if ($exists) {
                        DB::table('institution_specialties')->where('id', $exists->id)->update([
                            // overwrite cost only if new has value OR replace mode already cleared
                            'cost' => $cost ?? $exists->cost,
                            'duration' => $duration ?? $exists->duration,
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('institution_specialties')->insert([
                            'institution_id' => $inst->id,
                            'university_specialization_id' => $specialization->id,
                            'cost' => $cost,
                            'duration' => $duration,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $stats['programs']++;
                    $stats['links']++;
                    if ($cost !== null) {
                        $stats['with_cost']++;
                    }
                }

                $stats['institutions']++;
            } catch (\Throwable $e) {
                $this->error("  FAIL #{$inst->id}: " . $e->getMessage());
                // reconnect for next uni
                try {
                    DB::reconnect();
                } catch (\Throwable $e2) {
                    // ignore
                }
            }
        }

        $this->table(array_keys($stats), [array_values($stats)]);
        return self::SUCCESS;
    }
}
