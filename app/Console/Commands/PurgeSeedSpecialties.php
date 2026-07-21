<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Удаляет устаревшие specialty-данные из сидеров (до парсинга).
 *
 * Маркеры university seed:
 *  - specializations.about1 заполнен (SpecializationAboutSeeder, ровно 531 шт.)
 *  - institution_specialties.created_at IS NULL (рандомные линки InstitutionSpecialtiesSeeder)
 *
 * College seed:
 *  - college_* дерево + college_institution_specs (ровно 60 случайных ОП на колледж)
 *
 * Также опционально чистит «сирот» — specializations без связи с вузом
 * (дубли/обломки импортов, которые раздувают каталог).
 *
 * php artisan data:purge-seed-specialties --dry-run
 * php artisan data:purge-seed-specialties
 * php artisan data:purge-seed-specialties --keep-orphans
 * php artisan data:purge-seed-specialties --keep-colleges
 */
class PurgeSeedSpecialties extends Command
{
    protected $signature = 'data:purge-seed-specialties
        {--dry-run : Только показать, что будет удалено}
        {--keep-orphans : Не удалять specializations без institution_specialties}
        {--keep-colleges : Не трогать college specialty-дерево и связи}';

    protected $description = 'Remove pre-scrape seeder specialties and optional orphan catalog rows';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $keepOrphans = (bool) $this->option('keep-orphans');
        $keepColleges = (bool) $this->option('keep-colleges');

        if ($dry) {
            $this->warn('DRY-RUN — записи не удаляются');
        }

        $stats = [
            'uni_seed_links' => 0,
            'uni_seed_specs' => 0,
            'uni_orphan_specs' => 0,
            'uni_empty_quals' => 0,
            'uni_empty_globals' => 0,
            'college_links' => 0,
            'college_specs' => 0,
            'college_quals' => 0,
            'college_globals' => 0,
        ];

        // ── 1. University seed links (random seeder) ──────────────────────
        $seedSpecIds = DB::table('specializations')
            ->whereNotNull('about1')
            ->where('about1', '!=', '')
            ->pluck('id');

        $stats['uni_seed_specs'] = $seedSpecIds->count();

        $seedLinkQ = DB::table('institution_specialties')
            ->where(function ($q) use ($seedSpecIds) {
                $q->whereNull('created_at');
                if ($seedSpecIds->isNotEmpty()) {
                    $q->orWhereIn('university_specialization_id', $seedSpecIds->all());
                }
            });

        $stats['uni_seed_links'] = (clone $seedLinkQ)->count();

        $this->line(sprintf(
            'University seed: %d specializations (about1), %d pivot links',
            $stats['uni_seed_specs'],
            $stats['uni_seed_links']
        ));

        if (!$dry && $stats['uni_seed_links'] > 0) {
            $seedLinkQ->delete();
        }

        if (!$dry && $seedSpecIds->isNotEmpty()) {
            // FK cascade from specializations may not cover pivot if already deleted;
            // delete remaining links just in case, then specs
            DB::table('institution_specialties')
                ->whereIn('university_specialization_id', $seedSpecIds->all())
                ->delete();
            DB::table('specializations')->whereIn('id', $seedSpecIds->all())->delete();
        }

        // ── 2. Orphan university specializations ──────────────────────────
        if (!$keepOrphans) {
            $orphanIds = DB::table('specializations as sp')
                ->leftJoin('institution_specialties as isp', 'isp.university_specialization_id', '=', 'sp.id')
                ->whereNull('isp.id')
                ->pluck('sp.id');

            $stats['uni_orphan_specs'] = $orphanIds->count();
            $this->line("University orphans (no institution link): {$stats['uni_orphan_specs']}");

            if (!$dry && $orphanIds->isNotEmpty()) {
                foreach (array_chunk($orphanIds->all(), 500) as $chunk) {
                    DB::table('specializations')->whereIn('id', $chunk)->delete();
                }
            }
        } else {
            $this->line('University orphans: skipped (--keep-orphans)');
        }

        // ── 3. Prune empty qualifications / globals ───────────────────────
        $emptyQualIds = DB::table('qualifications as q')
            ->leftJoin('specializations as sp', 'sp.qualification_id', '=', 'q.id')
            ->whereNull('sp.id')
            ->pluck('q.id');
        $stats['uni_empty_quals'] = $emptyQualIds->count();

        if (!$dry && $emptyQualIds->isNotEmpty()) {
            foreach (array_chunk($emptyQualIds->all(), 500) as $chunk) {
                DB::table('qualifications')->whereIn('id', $chunk)->delete();
            }
        }

        $emptyGlobalIds = DB::table('global_specialties as g')
            ->leftJoin('qualifications as q', 'q.global_specialty_id', '=', 'g.id')
            ->whereNull('q.id')
            ->pluck('g.id');
        $stats['uni_empty_globals'] = $emptyGlobalIds->count();

        if (!$dry && $emptyGlobalIds->isNotEmpty()) {
            DB::table('global_specialties')->whereIn('id', $emptyGlobalIds->all())->delete();
        }

        $this->line(sprintf(
            'Pruned empty: %d qualifications, %d global_specialties',
            $stats['uni_empty_quals'],
            $stats['uni_empty_globals']
        ));

        // ── 4. College seed tree (100% random seeder data) ────────────────
        if (!$keepColleges && Schema::hasTable('college_institution_specs')) {
            $stats['college_links'] = DB::table('college_institution_specs')->count();
            $stats['college_specs'] = Schema::hasTable('college_specializations')
                ? DB::table('college_specializations')->count() : 0;
            $stats['college_quals'] = Schema::hasTable('college_qualifications')
                ? DB::table('college_qualifications')->count() : 0;
            $stats['college_globals'] = Schema::hasTable('college_global_specialties')
                ? DB::table('college_global_specialties')->count() : 0;

            $this->line(sprintf(
                'College seed: %d links, %d specs, %d quals, %d globals',
                $stats['college_links'],
                $stats['college_specs'],
                $stats['college_quals'],
                $stats['college_globals']
            ));

            if (!$dry) {
                // order: pivot → specs → quals → globals
                DB::table('college_institution_specs')->delete();
                if (Schema::hasTable('college_specializations')) {
                    DB::table('college_specializations')->delete();
                }
                if (Schema::hasTable('college_qualifications')) {
                    DB::table('college_qualifications')->delete();
                }
                if (Schema::hasTable('college_global_specialties')) {
                    DB::table('college_global_specialties')->delete();
                }
            }
        } else {
            $this->line('College specialties: skipped (--keep-colleges or no table)');
        }

        // ── 5. Final snapshot ─────────────────────────────────────────────
        $this->newLine();
        $this->info($dry ? 'Would change:' : 'Removed:');
        $this->table(array_keys($stats), [array_values($stats)]);

        if (!$dry) {
            $this->newLine();
            $this->info('Current totals:');
            $this->table(
                ['table', 'count'],
                [
                    ['specializations', DB::table('specializations')->count()],
                    ['qualifications', DB::table('qualifications')->count()],
                    ['global_specialties', DB::table('global_specialties')->count()],
                    ['institution_specialties', DB::table('institution_specialties')->count()],
                    ['college_specializations', Schema::hasTable('college_specializations') ? DB::table('college_specializations')->count() : 0],
                    ['college_institution_specs', Schema::hasTable('college_institution_specs') ? DB::table('college_institution_specs')->count() : 0],
                ]
            );

            $this->warn('Вузы #5, #6, #16 потеряли только seed-линки — у них не было спарсенных ОП.');
            $this->warn('Колледжи сейчас без специальностей, пока не будет реального парсинга.');
        }

        return self::SUCCESS;
    }
}
