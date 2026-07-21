<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * @deprecated Не вызывать. Ровно 60 случайных ОП на каждый колледж.
 * Чистка: data:purge-seed-specialties
 */
class CollegeInstitutionSpecsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('college_institution_specs')->delete();

        // Все ID спец. из предыдущего сидера
        $specIds = \App\Models\CollegeSpecialization::pluck('id')->all();
        if (empty($specIds)) {
            $this->command->warn('Нет специализаций: сначала запустите CollegeSpecialtiesSeeder');
            return;
        }

        // Список колледжей (институций) – ID известны из InstitutionsTableSeeder (19-42)
        $collegeIds = range(19, 42);

        $rows = [];
        foreach ($collegeIds as $collegeId) {
            // Случайно выбираем 60 уникальных специальностей для каждого колледжа
            $shuffledSpecs = $specIds;
            shuffle($shuffledSpecs);
            $chosen = array_slice($shuffledSpecs, 0, min(60, count($shuffledSpecs)));
            
            // Если специальностей меньше 60, добираем случайными
            while (count($chosen) < 60) {
                $randomSpec = $specIds[array_rand($specIds)];
                if (!in_array($randomSpec, $chosen)) {
                    $chosen[] = $randomSpec;
                }
            }

            foreach ($chosen as $specId) {
                $rows[] = [
                    'institution_id'          => $collegeId,
                    'college_specialization_id' => $specId,
                    'cost'                    => rand(240000, 540000),  // Цена от 240,000 до 540,000
                    'duration'                => 3, // Всегда 3 года обучения
                ];
            }
        }

        DB::table('college_institution_specs')->insert($rows);
    }
} 