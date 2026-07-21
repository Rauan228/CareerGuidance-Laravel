<?php

namespace App\Services;

use App\Models\GlobalSpecialty;
use App\Models\Qualification;
use App\Models\Specialization;
use Illuminate\Support\Facades\DB;

/**
 * Дерево ОП вузов 1:1 как на карточке университета (vipusknik / классификатор):
 *
 *   global_specialty  = название группы («Бизнес и управление»)
 *   qualification     = группа с кодом («6В041 · Бизнес и управление») — 1 на код/группу
 *   specialization    = образовательная программа («IT-маркетинг»)
 *   institution_specialties = вуз + cost + duration
 */
class UniversitySpecialtyTree
{
    /**
     * @param  array{name?:string,global_specialty?:string,code?:string|null,cost?:mixed,duration?:mixed,level?:string|null}  $spec
     * @return array{global:GlobalSpecialty,qualification:Qualification,specialization:Specialization,created_spec:bool}|null
     */
    public function ensureProgram(array $spec): ?array
    {
        $programName = $this->normalizeName($spec['name'] ?? '');
        if ($programName === '' || mb_strlen($programName) < 2) {
            return null;
        }

        $globalName = $this->normalizeName($spec['global_specialty'] ?? '') ?: 'Образовательные программы';
        $code = $this->normalizeCode($spec['code'] ?? null);

        $global = GlobalSpecialty::firstOrCreate(
            ['name' => $globalName],
            ['description' => $code ? "Код: {$code}" : null]
        );

        if ($code && (!$global->description || !str_contains((string) $global->description, $code))) {
            $global->description = "Код: {$code}";
            $global->save();
        }

        // Квалификация = группа ОП с кодом (как заголовок блока у вуза)
        $qualName = $code ? "{$code} · {$globalName}" : $globalName;

        $qualification = null;
        if ($code) {
            // стабильный ключ по коду внутри global (не плодим «Name (code)» / «code · Name»)
            $qualification = Qualification::query()
                ->where('global_specialty_id', $global->id)
                ->where(function ($q) use ($code, $qualName) {
                    $q->where('qualification_name', $qualName)
                        ->orWhere('qualification_name', "{$code}")
                        ->orWhere('description', $code)
                        ->orWhere('qualification_name', 'like', $code . ' · %')
                        ->orWhere('qualification_name', 'like', $code . ' %');
                })
                ->first();
        }

        if (!$qualification) {
            $qualification = Qualification::firstOrCreate(
                [
                    'global_specialty_id' => $global->id,
                    'qualification_name' => mb_substr($qualName, 0, 240),
                ],
                [
                    'description' => $code,
                ]
            );
        }

        if ($code && $qualification->description !== $code) {
            $qualification->description = $code;
            if ($qualification->qualification_name !== $qualName) {
                $qualification->qualification_name = mb_substr($qualName, 0, 240);
            }
            $qualification->save();
        }

        $specialization = Specialization::firstOrCreate(
            [
                'name' => mb_substr($programName, 0, 240),
                'qualification_id' => $qualification->id,
            ],
            [
                'description' => $code ? "Код группы: {$code}" : null,
            ]
        );

        return [
            'global' => $global,
            'qualification' => $qualification,
            'specialization' => $specialization,
            'created_spec' => $specialization->wasRecentlyCreated,
        ];
    }

    /**
     * Привязать ОП к вузу (cost/duration как на сайте).
     *
     * @return string created|updated|skipped
     */
    public function linkToInstitution(int $institutionId, Specialization $specialization, $cost, $duration): string
    {
        $cost = $this->normalizeCost($cost);
        $duration = $this->normalizeDuration($duration);

        $exists = DB::table('institution_specialties')
            ->where('institution_id', $institutionId)
            ->where('university_specialization_id', $specialization->id)
            ->first();

        if ($exists) {
            DB::table('institution_specialties')->where('id', $exists->id)->update([
                'cost' => $cost ?? $exists->cost,
                'duration' => $duration ?? $exists->duration,
                'updated_at' => now(),
            ]);

            return 'updated';
        }

        DB::table('institution_specialties')->insert([
            'institution_id' => $institutionId,
            'university_specialization_id' => $specialization->id,
            'cost' => $cost,
            'duration' => $duration,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'created';
    }

    public function normalizeCode(null|string $code): ?string
    {
        if ($code === null) {
            return null;
        }
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        // 6B041 → 6В041 (кириллическая В, как в классификаторе РК)
        $code = preg_replace('/^6[Bb]/u', '6В', $code) ?? $code;
        $code = preg_replace('/\s+/u', '', $code) ?? $code;

        return mb_substr($code, 0, 32);
    }

    public function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        return $name;
    }

    public function normalizeCost(mixed $cost): ?float
    {
        if ($cost === null || $cost === '') {
            return null;
        }
        if (is_string($cost)) {
            $cost = str_replace([' ', "\xc2\xa0", '₸', 'тг', 'тґ'], '', mb_strtolower($cost));
            $cost = str_replace(',', '.', $cost);
        }
        if (!is_numeric($cost)) {
            return null;
        }
        $v = (float) $cost;

        return $v > 0 ? $v : null;
    }

    public function normalizeDuration(mixed $duration): ?int
    {
        if ($duration === null || $duration === '') {
            return null;
        }
        if (is_string($duration) && preg_match('/(\d+)/', $duration, $m)) {
            $duration = $m[1];
        }
        if (!is_numeric($duration)) {
            return null;
        }
        $v = (int) $duration;

        return $v > 0 && $v <= 10 ? $v : null;
    }
}
