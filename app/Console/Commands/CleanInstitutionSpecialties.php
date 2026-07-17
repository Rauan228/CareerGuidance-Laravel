<?php

namespace App\Console\Commands;

use App\Models\Specialization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Чистит specialty-links вузов: дедуп kk/ru/en, мусор, даты аккредитации.
 * Оставляет одно русское (если есть) название на код 6Вxxxxx.
 *
 * php artisan data:clean-specialties
 * php artisan data:clean-specialties --institution=65
 * php artisan data:clean-specialties --dry-run
 */
class CleanInstitutionSpecialties extends Command
{
    protected $signature = 'data:clean-specialties
        {--institution= : Only one institution id}
        {--dry-run}';

    protected $description = 'Deduplicate and normalize institution specialty names (prefer Russian)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $only = $this->option('institution');

        $q = DB::table('institution_specialties')->select('institution_id')->distinct();
        if ($only) {
            $q->where('institution_id', (int) $only);
        }
        $ids = $q->pluck('institution_id');

        $totalRemoved = 0;
        $totalKept = 0;
        $totalRenamed = 0;

        foreach ($ids as $instId) {
            [$kept, $removed, $renamed] = $this->cleanInstitution((int) $instId, $dry);
            $totalKept += $kept;
            $totalRemoved += $removed;
            $totalRenamed += $renamed;
            if ($removed > 0 || $renamed > 0) {
                $this->line(sprintf(
                    '#%d kept=%d removed=%d renamed=%d%s',
                    $instId,
                    $kept,
                    $removed,
                    $renamed,
                    $dry ? ' [dry]' : ''
                ));
            }
        }

        $this->table(
            ['institutions', 'kept', 'removed', 'renamed', 'dry'],
            [[$ids->count(), $totalKept, $totalRemoved, $totalRenamed, $dry ? 'yes' : 'no']]
        );

        return self::SUCCESS;
    }

    /** @return array{0:int,1:int,2:int} */
    private function cleanInstitution(int $instId, bool $dry): array
    {
        $rows = DB::table('institution_specialties as is')
            ->join('specializations as s', 's.id', '=', 'is.university_specialization_id')
            ->leftJoin('qualifications as q', 'q.id', '=', 's.qualification_id')
            ->where('is.institution_id', $instId)
            ->get([
                'is.id as link_id',
                'is.cost',
                'is.duration',
                's.id as spec_id',
                's.name as spec_name',
                's.qualification_id',
                'q.qualification_name',
            ]);

        if ($rows->isEmpty()) {
            return [0, 0, 0];
        }

        $groups = [];
        foreach ($rows as $r) {
            $meta = $this->analyze((string) $r->spec_name, (string) ($r->qualification_name ?? ''));
            if ($meta['drop']) {
                $groups['__drop__' . $r->link_id] = [[$r, $meta]];
                continue;
            }
            $key = $meta['code'] ?: ('name:' . $this->fingerprint($meta['clean_name']));
            $groups[$key][] = [$r, $meta];
        }

        $kept = 0;
        $removed = 0;
        $renamed = 0;

        foreach ($groups as $key => $items) {
            if (str_starts_with($key, '__drop__')) {
                [$r] = $items[0];
                if (!$dry) {
                    DB::table('institution_specialties')->where('id', $r->link_id)->delete();
                }
                $removed++;
                continue;
            }

            // best by score (Russian clean names win)
            usort($items, fn ($a, $b) => $b[1]['score'] <=> $a[1]['score']);

            [$bestRow, $bestMeta] = $items[0];
            // if winner not Russian but group has Russian — force Russian name
            foreach ($items as [$r, $meta]) {
                if ($meta['lang'] === 'ru' && $meta['score'] >= 40) {
                    $bestMeta = $meta;
                    // keep link with best cost merge from any, but name from ru
                    break;
                }
            }

            $bestCost = null;
            $bestDur = null;
            foreach ($items as [$r]) {
                if ($r->cost !== null && (float) $r->cost > 0) {
                    $bestCost = $bestCost === null ? $r->cost : max((float) $bestCost, (float) $r->cost);
                }
                if ($r->duration !== null && (int) $r->duration > 0) {
                    $bestDur = $bestDur === null ? (int) $r->duration : max((int) $bestDur, (int) $r->duration);
                }
            }
            if (!$bestDur && !empty($bestMeta['duration_hint'])) {
                $bestDur = $bestMeta['duration_hint'];
            }

            $finalName = $bestMeta['clean_name'];
            if ($finalName === '' || mb_strlen($finalName) < 3) {
                $finalName = $this->stripNoise((string) $bestRow->spec_name);
            }

            // delete all other links in group
            foreach ($items as $i => [$r]) {
                if ($i === 0) {
                    continue;
                }
                // keep the first as physical link row; if bestRow is not first, swap logic below
            }

            // Choose physical link to keep: prefer one already pointing to a good specialization
            $keepLink = $bestRow;
            foreach ($items as [$r, $meta]) {
                if ($meta['lang'] === 'ru' && $meta['clean_name'] === $finalName) {
                    $keepLink = $r;
                    break;
                }
            }
            // if no exact, first item (highest score)
            if ($keepLink === null) {
                $keepLink = $items[0][0];
            }

            foreach ($items as [$r]) {
                if ((int) $r->link_id === (int) $keepLink->link_id) {
                    continue;
                }
                if (!$dry) {
                    DB::table('institution_specialties')->where('id', $r->link_id)->delete();
                }
                $removed++;
            }

            $needsRename = $this->norm((string) $keepLink->spec_name) !== $this->norm($finalName);

            if (!$dry) {
                DB::table('institution_specialties')->where('id', $keepLink->link_id)->update([
                    'cost' => ($bestCost !== null && (float) $bestCost > 0) ? $bestCost : null,
                    'duration' => ($bestDur !== null && (int) $bestDur > 0) ? (int) $bestDur : null,
                    'updated_at' => now(),
                ]);

                if ($needsRename) {
                    $spec = Specialization::find($keepLink->spec_id);
                    if ($spec) {
                        $existing = Specialization::where('name', $finalName)
                            ->where('qualification_id', $spec->qualification_id)
                            ->where('id', '!=', $spec->id)
                            ->first();

                        if ($existing) {
                            $dup = DB::table('institution_specialties')
                                ->where('institution_id', $instId)
                                ->where('university_specialization_id', $existing->id)
                                ->where('id', '!=', $keepLink->link_id)
                                ->first();
                            if ($dup) {
                                DB::table('institution_specialties')->where('id', $keepLink->link_id)->delete();
                                DB::table('institution_specialties')->where('id', $dup->id)->update([
                                    'cost' => ($bestCost !== null && (float) $bestCost > 0) ? $bestCost : $dup->cost,
                                    'duration' => ($bestDur !== null && (int) $bestDur > 0) ? (int) $bestDur : $dup->duration,
                                    'updated_at' => now(),
                                ]);
                            } else {
                                DB::table('institution_specialties')->where('id', $keepLink->link_id)->update([
                                    'university_specialization_id' => $existing->id,
                                    'updated_at' => now(),
                                ]);
                            }
                        } else {
                            $spec->name = mb_substr($finalName, 0, 240);
                            $spec->save();
                        }
                    }
                    $renamed++;
                }
            } elseif ($needsRename) {
                $renamed++;
            }

            $kept++;
        }

        return [$kept, $removed, $renamed];
    }

    /**
     * @return array{code:?string,clean_name:string,lang:string,score:int,drop:bool,duration_hint:?int}
     */
    private function analyze(string $name, string $qualName): array
    {
        $raw = $this->norm($name);
        $code = $this->extractCode($raw) ?: $this->extractCode($qualName);
        $durationHint = $this->extractDuration($raw);

        if ($this->isJunk($raw)) {
            return $this->meta($code, '', 'xx', -100, true, $durationHint);
        }

        // Multilingual mash: try to pull the Russian phrase only
        $working = $raw;
        if ($this->isMash($raw)) {
            $ru = $this->extractRussianPhrase($raw);
            if ($ru !== null) {
                $working = $ru;
            }
        }

        $clean = $this->stripNoise($working);
        $clean = $this->norm($clean);

        if ($clean === '' || mb_strlen($clean) < 3 || $this->isJunk($clean) || $this->isFragment($clean)) {
            // try strip noise on original
            $clean2 = $this->stripNoise($raw);
            $clean2 = $this->norm($clean2);
            if ($clean2 === '' || mb_strlen($clean2) < 3 || $this->isJunk($clean2) || $this->isFragment($clean2)) {
                return $this->meta($code, $clean, 'xx', -50, true, $durationHint);
            }
            $clean = $clean2;
        }

        $lang = $this->detectLang($clean);

        $score = 0;
        $score += match ($lang) {
            'ru' => 100,
            'kk' => 20,
            'en' => 10,
            'mix' => 5,
            default => 0,
        };
        if ($code) {
            $score += 10;
        }
        if (!preg_match('/^6[BbВв]/u', $clean)) {
            $score += 5;
        }
        // prefer names without digits/dates leftovers
        if (!preg_match('/\d{2}\.\d{2}\.\d{4}/', $raw)) {
            $score += 5;
        }
        // prefer reasonable length
        $len = mb_strlen($clean);
        if ($len >= 12 && $len <= 100) {
            $score += 10;
        }
        if ($this->isMash($raw)) {
            $score -= 20;
        }
        // pure code-like short
        if ($len < 8 && $code) {
            $score -= 5;
        }

        return $this->meta($code, $clean, $lang, $score, false, $durationHint);
    }

    private function meta(?string $code, string $clean, string $lang, int $score, bool $drop, ?int $dur): array
    {
        return [
            'code' => $code ? $this->normalizeCode($code) : null,
            'clean_name' => $clean,
            'lang' => $lang,
            'score' => $score,
            'drop' => $drop,
            'duration_hint' => $dur,
        ];
    }

    private function stripNoise(string $s): string
    {
        $s = preg_replace('/^[–—\-\•\*]+\s*/u', '', $s) ?? $s;
        $s = preg_replace('/^\d{1,2}\s+/u', '', $s) ?? $s;
        $s = preg_replace('/^6[BbВв][0-9]{2,6}\s*/u', '', $s) ?? $s;
        $s = preg_replace('/\d{2}\.\d{2}\.\d{4}\s*[-–—]\s*\d{2}\.\d{2}\.\d{4}/u', '', $s) ?? $s;
        $s = preg_replace('/\(?\s*первичн\w*\s*\)?/iu', '', $s) ?? $s;
        $s = preg_replace('/\b\d\s*(?:год|года|лет)\b/iu', '', $s) ?? $s;
        $s = preg_replace('/\b[ВB]\d{2,3}\b\s*[-–—:]?\s*/u', '', $s) ?? $s;
        return $this->norm($s);
    }

    /**
     * From mash "KK RU EN" extract longest pure-Russian phrase (no Kazakh letters, no long Latin).
     */
    private function extractRussianPhrase(string $text): ?string
    {
        // Known pattern: Kazakh then Russian then English — split by runs
        // Find sequences of Russian words (cyrillic without kazakh letters)
        if (!preg_match_all('/(?:(?![әғқңөұүһі])[А-Яа-яЁё])+(?:\s+(?:(?![әғқңөұүһі])[А-Яа-яЁё0-9\-\(\)«»",\.\/]+))+/u', $text, $m)) {
            // single word russian
            if (preg_match_all('/(?:(?![әғқңөұүһі])[А-Яа-яЁё]){6,}/u', $text, $m2)) {
                $m[0] = $m2[0];
            } else {
                return null;
            }
        }

        $best = null;
        $bestLen = 0;
        foreach ($m[0] as $seg) {
            $seg = $this->norm($seg);
            if (mb_strlen($seg) < 10) {
                continue;
            }
            if ($this->detectLang($seg) !== 'ru') {
                continue;
            }
            if ($this->isJunk($seg) || $this->isFragment($seg)) {
                continue;
            }
            // must look like a specialty title (has space or long word)
            if (mb_strlen($seg) > $bestLen) {
                $bestLen = mb_strlen($seg);
                $best = $seg;
            }
        }
        return $best;
    }

    private function detectLang(string $text): string
    {
        $t = mb_strtolower($text);
        if (preg_match('/[әғқңөұүһі]/u', $t)) {
            return 'kk';
        }
        $cyr = preg_match_all('/[а-яё]/u', $t);
        $lat = preg_match_all('/[a-z]/u', $t);
        if ($cyr > 0 && $lat === 0) {
            return 'ru';
        }
        if ($lat > 0 && $cyr === 0) {
            return 'en';
        }
        if ($cyr > 0 && $lat > 0) {
            return 'mix';
        }
        return 'xx';
    }

    private function isMash(string $text): bool
    {
        $hasKk = (bool) preg_match('/[әғқңөұүһі]/u', mb_strtolower($text));
        $hasRu = (bool) preg_match('/[а-яё]/u', mb_strtolower($text));
        $hasEn = (bool) preg_match('/[a-z]{4,}/i', $text);
        // kk+ru or ru+en long or all three
        if ($hasKk && $hasRu) {
            return true;
        }
        if ($hasRu && $hasEn && mb_strlen($text) > 45) {
            return true;
        }
        if ($hasKk && $hasEn) {
            return true;
        }
        return false;
    }

    private function isFragment(string $text): bool
    {
        // broken leftovers from bad splits
        $t = $this->norm($text);
        if (mb_strlen($t) < 8 && !preg_match('/\s/u', $t)) {
            // single short word — often fragment unless known short title
            return true;
        }
        // starts with lowercase cyrillic mid-word-ish
        if (preg_match('/^[а-яё]{1,3}\s/u', $t) && mb_strlen($t) < 25) {
            return true;
        }
        // ends with open paren
        if (str_ends_with($t, '(') || str_ends_with($t, '«')) {
            return true;
        }
        // too few letters
        $letters = preg_match_all('/\p{L}/u', $t);
        if ($letters < 8) {
            return true;
        }
        return false;
    }

    private function isJunk(string $text): bool
    {
        $t = $this->norm($text);
        $low = mb_strtolower($t);
        if ($t === '' || mb_strlen($t) < 3) {
            return true;
        }
        if (preg_match('/^6[BbВв][0-9]{2,6}$/u', $t)) {
            return true;
        }
        if (str_contains($low, 'ағылшын тілі деңгейін') || str_contains($low, 'уровень английского')) {
            return true;
        }
        if (preg_match('/^в\d{2,3}\b/iu', $low)) {
            return true;
        }
        if (preg_match('/^–\s*(инженерия|көлік|транспорт)/iu', $t)) {
            return true;
        }
        // pure group headings
        if (preg_match('/^(инженерия және инженерлік іс|инженерия и инженерное дело|көлік қызметтері|транспортные услуги|воздушный транспорт и технологии)$/iu', $t)) {
            return true;
        }
        return false;
    }

    private function extractCode(string $text): ?string
    {
        if (preg_match('/6[BbВв][0-9]{2,6}/u', $text, $m)) {
            return $this->normalizeCode($m[0]);
        }
        return null;
    }

    private function normalizeCode(string $code): string
    {
        $code = mb_strtoupper(trim($code));
        return preg_replace('/^6B/u', '6В', $code) ?? $code;
    }

    private function extractDuration(string $text): ?int
    {
        if (preg_match('/(\d)\s*(?:год|года|лет)/u', $text, $m)) {
            $y = (int) $m[1];
            return ($y >= 1 && $y <= 8) ? $y : null;
        }
        return null;
    }

    private function fingerprint(string $name): string
    {
        $s = mb_strtolower($this->norm($name));
        $s = preg_replace('/[^a-zа-яё0-9]+/u', '', $s) ?? $s;
        return $s;
    }

    private function norm(string $s): string
    {
        $s = str_replace("\xc2\xa0", ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }
}
