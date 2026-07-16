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
 * Откатывает specialty-links, ошибочно приклеенные к вузам Астаны,
 * и СОЗДАЁТ недостающие алматинские вузы напрямую из JSON.
 *
 * php artisan data:repair-mislinked-almaty "../data-scrapers/output/vipusknik_алматы.json"
 */
class RepairMislinkedAlmaty extends Command
{
    protected $signature = 'data:repair-mislinked-almaty {path} {--dry-run}';

    protected $description = 'Repair Astana institutions polluted by Almaty import mismatches';

    /** scraped name → wrong keep institution id */
    private array $wrong = [
        'Алматинский технологический университет' => 3,
        'Казахская национальная академия искусств имени Темирбека Жургенова' => 12,
        'Казахстанско-Швейцарский институт туризма и гостиничного бизнеса при АТУ' => 3,
        'Университет «Туран»' => 11,
    ];

    public function handle(): int
    {
        $path = $this->argument('path');
        if (!is_file($path)) {
            $path = base_path($path);
        }
        if (!is_file($path)) {
            $this->error('JSON not found');
            return self::FAILURE;
        }

        $payload = json_decode(file_get_contents($path), true);
        $byName = [];
        foreach ($payload['institutions'] ?? [] as $row) {
            if (!empty($row['name'])) {
                $byName[$row['name']] = $row;
            }
        }

        $dry = (bool) $this->option('dry-run');
        $cutoff = now()->subHours(3);

        foreach ($this->wrong as $scrapedName => $wrongId) {
            $row = $byName[$scrapedName] ?? null;
            if (!$row) {
                $this->warn("No JSON row for {$scrapedName}");
                continue;
            }

            $specNames = collect($row['specialties'] ?? [])->pluck('name')->filter()->unique()->values();
            $this->line("Repair: {$scrapedName} (was on #{$wrongId}), specs={$specNames->count()}");

            $specIds = DB::table('specializations')
                ->whereIn('name', $specNames->all())
                ->pluck('id');

            $count = DB::table('institution_specialties')
                ->where('institution_id', $wrongId)
                ->whereIn('university_specialization_id', $specIds)
                ->where('created_at', '>=', $cutoff)
                ->count();

            $this->line("  links to detach from #{$wrongId}: {$count}");

            if ($dry) {
                continue;
            }

            if ($count) {
                DB::table('institution_specialties')
                    ->where('institution_id', $wrongId)
                    ->whereIn('university_specialization_id', $specIds)
                    ->where('created_at', '>=', $cutoff)
                    ->delete();
            }

            // create or find correct institution (exact name only)
            $inst = Institution::where('name', $scrapedName)->first();
            if (!$inst) {
                $inst = new Institution();
                $inst->name = $scrapedName;
                $inst->password = Hash::make('ChangeMe!' . substr(md5($scrapedName), 0, 8));
                $inst->fill([
                    'description1' => $row['description1'] ?? null,
                    'description2' => $row['description2'] ?? null,
                    'description3' => $row['description3'] ?? null,
                    'location' => $row['location'] ?? 'Алматы, Казахстан',
                    'address' => $row['address'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'website' => $row['website'] ?? null,
                    'verified' => 'accepted',
                    'type' => 'university',
                    'latitude' => $row['latitude'] ?? null,
                    'longitude' => $row['longitude'] ?? null,
                    'dormitory' => (bool) ($row['dormitory'] ?? false),
                    'grants' => (bool) ($row['grants'] ?? false),
                ]);
                $inst->save();
                $this->line("  + created #{$inst->id}");
            } else {
                $this->line("  = existing #{$inst->id}");
            }

            $linked = 0;
            foreach ($row['specialties'] ?? [] as $spec) {
                $specName = trim($spec['name'] ?? '');
                if ($specName === '') {
                    continue;
                }
                $globalName = trim($spec['global_specialty'] ?? 'Прочее') ?: 'Прочее';
                $code = $spec['code'] ?? null;

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
                $specialization = Specialization::firstOrCreate(
                    [
                        'name' => $specName,
                        'qualification_id' => $qualification->id,
                    ],
                    ['description' => $code ? "Код группы: {$code}" : null]
                );

                $exists = DB::table('institution_specialties')
                    ->where('institution_id', $inst->id)
                    ->where('university_specialization_id', $specialization->id)
                    ->first();

                if ($exists) {
                    DB::table('institution_specialties')->where('id', $exists->id)->update([
                        'cost' => $spec['cost'] ?? $exists->cost,
                        'duration' => $spec['duration'] ?? $exists->duration,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('institution_specialties')->insert([
                        'institution_id' => $inst->id,
                        'university_specialization_id' => $specialization->id,
                        'cost' => $spec['cost'] ?? null,
                        'duration' => $spec['duration'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $linked++;
            }
            $this->line("  linked specialties: {$linked}");
        }

        $this->info($dry ? 'Dry-run complete.' : 'Repair complete.');
        return self::SUCCESS;
    }
}
