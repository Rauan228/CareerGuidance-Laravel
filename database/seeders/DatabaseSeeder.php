<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Учреждения (карточки вузов/колледжей)
        $this->call(InstitutionsTableSeeder::class);
        $this->call(InstitutionDirectionsSeeder::class);

        // ── Специальности ────────────────────────────────────────────────
        // НЕ сидим specialty-каталог и случайные institution_specialties.
        // Источник истины — парсинг + импорт:
        //   php artisan data:import-scraped path/to/vipusknik_*.json
        //   php artisan data:import-official-programs path/to/official_programs.json --replace
        //   php artisan data:clean-specialties
        //   php artisan data:purge-seed-specialties   # если снова затесался seed
        //
        // Устаревшие сидеры (оставляем в репо только как архив, не вызываем):
        //   UniversitySpecialtiesSeeder, CollegeSpecialtiesSeeder,
        //   InstitutionSpecialtiesSeeder, CollegeInstitutionSpecsSeeder,
        //   SpecializationAboutSeeder, CollegeSpecializationAboutSeeder

        // 2. Пользователи и администраторы
        $this->call(UsersTableSeeder::class);
        $this->call(AdminsTableSeeder::class);

        // 3. Контент
        $this->call([
            EventsCalendarTableSeeder::class,
            ApplicationsTableSeeder::class,
            NotificationsTableSeeder::class,
            GrantsTableSeeder::class,
            ReviewsTableSeeder::class,
            LikesTableSeeder::class,
        ]);

        // 4. Дефолтный админ
        Admin::create([
            'name' => 'admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('password'),
        ]);

        // 5. Тестовые пользователи
        User::factory()->count(20)->create();

        $this->call(TestResultsTableSeeder::class);
    }
}
