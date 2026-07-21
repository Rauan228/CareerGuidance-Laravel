<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\CollegeSpecialization;
use App\Models\Specialization;

/**
 * @deprecated Не вызывать. Рандомные specialty-links (24–52 на вуз).
 * Источник истины: парсинг. Чистка: data:purge-seed-specialties
 */
class InstitutionSpecialtiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        DB::table('institution_specialties')->truncate();

        // Общее количество университетов: 18 (ID от 1 до 18)
        // Общее количество специальностей: 531 (ID от 1 до 531)
        // Каждый университет должен иметь от 24 до 46 специальностей

        $specialties = [];
        $usedSpecialities = []; // Массив для отслеживания использованных специальностей
        
        for ($institutionId = 1; $institutionId <= 18; $institutionId++) {
            // Определяем количество специальностей для данного университета (от 24 до 46)
            $specialtyCount = rand(24, 52);
            
            // Генерируем уникальные ID специальностей для данного университета
            $institutionSpecialties = [];
            while (count($institutionSpecialties) < $specialtyCount) {
                $specialtyId = rand(1, 531);
                
                // Проверяем, что данная специальность еще не назначена этому университету
                if (!in_array($specialtyId, $institutionSpecialties)) {
                    $institutionSpecialties[] = $specialtyId;
                }
            }
            
            // Создаем записи для каждой специальности данного университета
            foreach ($institutionSpecialties as $specialtyId) {
                $specialties[] = [
                    'institution_id' => $institutionId,
                    'university_specialization_id' => $specialtyId,
                    'cost' => rand(2000000, 6000000), // Стоимость от 2млн до 6млн тенге
                    'duration' => rand(3, 5) // Продолжительность от 3 до 5 лет
                ];
            }
        }

        // Вставляем данные батчами для оптимизации
        $batchSize = 100;
        $batches = array_chunk($specialties, $batchSize);
        
        foreach ($batches as $batch) {
            DB::table('institution_specialties')->insert($batch);
        }
        
        Log::info('InstitutionSpecialtiesSeeder completed. Total specialties created: ' . count($specialties));
    }
}
