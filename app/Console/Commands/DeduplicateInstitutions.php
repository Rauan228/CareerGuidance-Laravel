<?php

namespace App\Console\Commands;

use App\Models\Institution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Сливает дубликаты вузов: переносит specialty-links, reviews, likes на canonical,
 * удаляет дубль.
 *
 * php artisan data:dedupe-institutions --dry-run
 * php artisan data:dedupe-institutions
 */
class DeduplicateInstitutions extends Command
{
    protected $signature = 'data:dedupe-institutions {--dry-run}';

    protected $description = 'Merge known duplicate institutions (e.g. MNU / КАЗГЮУ)';

    /** keep_id_hint => list of name needles for the duplicate to remove */
    private array $pairs = [
        // canonical name substrings (first match wins as keep), then duplicate needles
        [
            'keep_needles' => ['казгюу', 'narikbayev', 'mnu'],
            'drop_needles' => ['maqsut narikbayev', 'narikbayev university'],
            'prefer_website' => ['mnu.kz', 'kazguu.kz'],
        ],
        [
            'keep_needles' => ['международный университет астана', 'международный университет астаны'],
            'drop_needles' => [],
            'prefer_website' => ['aiu.kz', 'mui.kz'],
            'same_group_needles' => ['международный университет астан'],
        ],
        [
            'keep_needles' => ['университет обороны'],
            'drop_needles' => [],
            'prefer_website' => [],
            'same_group_needles' => ['университет обороны'],
        ],
        [
            'keep_needles' => ['астана it', 'astana it', 'aitu'],
            'drop_needles' => [],
            'prefer_website' => ['astanait.edu.kz'],
            'same_group_needles' => ['астана it', 'astana it'],
        ],
        [
            'keep_needles' => ['гумил'],
            'drop_needles' => [],
            'prefer_website' => ['enu.kz'],
            'same_group_needles' => ['гумил'],
        ],
        [
            'keep_needles' => ['назарбаев'],
            'drop_needles' => [],
            'prefer_website' => ['nu.edu.kz'],
            'same_group_needles' => ['назарбаев'],
        ],
        [
            'keep_needles' => ['туран'],
            'drop_needles' => [],
            'prefer_website' => ['tau-edu.kz'],
            'same_group_needles' => ['туран-астана', 'туран астана', '«туран-астана»'],
        ],
        [
            'keep_needles' => ['сейфулл'],
            'drop_needles' => [],
            'prefer_website' => ['kazatu.edu.kz'],
            'same_group_needles' => ['сейфулл'],
        ],
        [
            'keep_needles' => ['медицинский университет астана', 'медицинский университет'],
            'drop_needles' => [],
            'prefer_website' => ['amu.edu.kz', 'amu.kz'],
            'same_group_needles' => ['медицинский университет астана'],
        ],
        [
            'keep_needles' => ['технолог', 'кулажан', 'kutb'],
            'drop_needles' => [],
            'prefer_website' => ['kaztbu.edu.kz', 'kutb.kz'],
            'same_group_needles' => ['технолог', 'кулажан'],
        ],
        [
            'keep_needles' => ['ломоносов', 'мгу'],
            'drop_needles' => [],
            'prefer_website' => ['msu.kz'],
            'same_group_needles' => ['ломоносов'],
        ],
        [
            'keep_needles' => ['хореограф'],
            'drop_needles' => [],
            'prefer_website' => ['balletacademy.kz'],
            'same_group_needles' => ['хореограф'],
        ],
        [
            'keep_needles' => ['искусств'],
            'drop_needles' => [],
            'prefer_website' => ['kaznui'],
            'same_group_needles' => ['национальный университет искусств', 'казнуи'],
        ],
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $unis = Institution::where('type', 'university')->get();
        $this->info('Universities in DB: ' . $unis->count());

        $merged = 0;

        // 1) Explicit MNU / КАЗГЮУ
        $kazguu = $unis->first(fn ($i) => str_contains(mb_strtolower($i->name), 'казгюу')
            || str_contains(mb_strtolower($i->website ?? ''), 'kazguu'));
        $mnu = $unis->first(fn ($i) => str_contains(mb_strtolower($i->name), 'narikbayev')
            || str_contains(mb_strtolower($i->name), 'maqsut')
            || str_contains(mb_strtolower($i->website ?? ''), 'mnu.kz'));

        if ($kazguu && $mnu && $kazguu->id !== $mnu->id) {
            // keep older/seed one (usually lower id = КАЗГЮУ), merge MNU into it
            $keep = $kazguu->id < $mnu->id ? $kazguu : $mnu;
            $drop = $keep->id === $kazguu->id ? $mnu : $kazguu;
            // prefer MNU branding if keep is old name
            $this->merge($keep, $drop, $dry, [
                'name' => 'Maqsut Narikbayev University (КАЗГЮУ)',
                'website' => 'https://mnu.kz/ru',
            ]);
            $merged++;
        }

        // 2) Group by same_group_needles — if 2+ institutions match, merge into oldest
        foreach ($this->pairs as $pair) {
            $groupNeedles = $pair['same_group_needles'] ?? [];
            if (!$groupNeedles) {
                continue;
            }
            $group = $unis->filter(function ($i) use ($groupNeedles) {
                $n = mb_strtolower($i->name);
                $w = mb_strtolower($i->website ?? '');
                foreach ($groupNeedles as $needle) {
                    if (str_contains($n, $needle) || str_contains($w, $needle)) {
                        return true;
                    }
                }
                return false;
            })->sortBy('id')->values();

            if ($group->count() < 2) {
                continue;
            }

            $keep = $group->first();
            foreach ($group->slice(1) as $drop) {
                if ($keep->id === $drop->id) {
                    continue;
                }
                $this->merge($keep, $drop, $dry);
                $merged++;
            }
        }

        // 3) Same normalized website host
        $byHost = [];
        foreach (Institution::where('type', 'university')->get() as $i) {
            $host = $this->host($i->website);
            if (!$host) {
                continue;
            }
            $byHost[$host][] = $i;
        }
        foreach ($byHost as $host => $list) {
            if (count($list) < 2) {
                continue;
            }
            usort($list, fn ($a, $b) => $a->id <=> $b->id);
            $keep = $list[0];
            for ($k = 1; $k < count($list); $k++) {
                $this->merge($keep, $list[$k], $dry);
                $merged++;
            }
        }

        $this->info($dry
            ? "Dry-run: would merge {$merged} duplicate(s)."
            : "Merged {$merged} duplicate(s).");

        return self::SUCCESS;
    }

    private function host(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        $h = parse_url($url, PHP_URL_HOST);
        if (!$h) {
            return null;
        }
        $h = strtolower(preg_replace('/^www\./', '', $h));
        // ignore junk
        if (in_array($h, ['zero.kz', 'facebook.com', 'instagram.com'], true)) {
            return null;
        }
        return $h;
    }

    private function merge(Institution $keep, Institution $drop, bool $dry, array $forceAttrs = []): void
    {
        $this->line(sprintf(
            '  merge #%d "%s"  ←  #%d "%s"%s',
            $keep->id,
            $keep->name,
            $drop->id,
            $drop->name,
            $dry ? ' [dry]' : ''
        ));

        if ($dry) {
            return;
        }

        DB::transaction(function () use ($keep, $drop, $forceAttrs) {
            // move specialty links
            $dropLinks = DB::table('institution_specialties')
                ->where('institution_id', $drop->id)
                ->get();

            foreach ($dropLinks as $link) {
                $exists = DB::table('institution_specialties')
                    ->where('institution_id', $keep->id)
                    ->where('university_specialization_id', $link->university_specialization_id)
                    ->first();

                if ($exists) {
                    DB::table('institution_specialties')->where('id', $exists->id)->update([
                        'cost' => $exists->cost ?? $link->cost,
                        'duration' => $exists->duration ?? $link->duration,
                        'updated_at' => now(),
                    ]);
                    DB::table('institution_specialties')->where('id', $link->id)->delete();
                } else {
                    DB::table('institution_specialties')->where('id', $link->id)->update([
                        'institution_id' => $keep->id,
                        'updated_at' => now(),
                    ]);
                }
            }

            // reassign common FKs if tables exist
            foreach (['events_calendar', 'reviews', 'likes', 'grants', 'institution_applications'] as $table) {
                if (!\Schema::hasTable($table) || !\Schema::hasColumn($table, 'institution_id')) {
                    continue;
                }
                try {
                    DB::table($table)->where('institution_id', $drop->id)->update(['institution_id' => $keep->id]);
                } catch (\Throwable $e) {
                    // unique constraints etc. — ignore
                }
            }

            // fill empty fields on keep from drop
            foreach (['description1', 'description2', 'description3', 'location', 'address',
                'email', 'phone', 'website', 'logo_url', 'photo_url', 'directions',
                'latitude', 'longitude'] as $field) {
                if (empty($keep->{$field}) && !empty($drop->{$field})) {
                    $keep->{$field} = $drop->{$field};
                }
            }
            foreach ($forceAttrs as $k => $v) {
                $keep->{$k} = $v;
            }
            if ($drop->dormitory) {
                $keep->dormitory = true;
            }
            if ($drop->grants) {
                $keep->grants = true;
            }
            $keep->save();

            $drop->delete();
        });
    }
}
