<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReviewsTableSeeder extends Seeder
{
    public function run()
    {
        // Очищаем таблицу отзывов
        DB::table('reviews')->delete();
        
        $reviews = [];
        $totalUsers = 130;
        $universitiesRange = [1, 18]; // Университеты ID 1-18
        $collegesRange = [19, 42];    // Колледжи ID 19-42
        
        $this->command->info("Генерируем отзывы для {$totalUsers} пользователей...");
        
        // Проходим по всем пользователям
        for ($userId = 1; $userId <= $totalUsers; $userId++) {
            
            // Добавляем минимум 10 отзывов на университеты для текущего пользователя
            $selectedUniversities = $this->getRandomInstitutions($universitiesRange[0], $universitiesRange[1], 10);
            foreach ($selectedUniversities as $institutionId) {
                $randomDate = $this->getRandomDate();
                $rating = rand(1, 5);
                $comment = $this->getRandomComment($rating);
                
                $reviews[] = [
                    'user_id' => $userId,
                    'institution_id' => $institutionId,
                    'rating' => $rating,
                    'comment' => $comment,
                    'created_at' => $randomDate,
                    'updated_at' => $randomDate,
                ];
            }
            
            // Добавляем минимум 10 отзывов на колледжи для текущего пользователя
            $selectedColleges = $this->getRandomInstitutions($collegesRange[0], $collegesRange[1], 10);
            foreach ($selectedColleges as $institutionId) {
                $randomDate = $this->getRandomDate();
                $rating = rand(1, 5);
                $comment = $this->getRandomComment($rating);
                
                $reviews[] = [
                    'user_id' => $userId,
                    'institution_id' => $institutionId,
                    'rating' => $rating,
                    'comment' => $comment,
                    'created_at' => $randomDate,
                    'updated_at' => $randomDate,
                ];
            }
            
            // Выводим прогресс каждые 20 пользователей
            if ($userId % 20 == 0) {
                $this->command->info("Обработано пользователей: {$userId}/{$totalUsers}");
            }
        }
        
        // Вставляем все отзывы одним запросом для оптимизации
        if (!empty($reviews)) {
            // Разбиваем на части по 1000 записей для избежания ограничений MySQL
            $chunks = array_chunk($reviews, 1000);
            foreach ($chunks as $chunk) {
                DB::table('reviews')->insert($chunk);
            }
            
            $totalReviews = count($reviews);
            $this->command->info("Успешно создано {$totalReviews} отзывов!");
            $this->command->info("Каждый пользователь написал минимум 10 отзывов на университеты и 10 на колледжи");
        }
    }
    
    /**
     * Получить случайные уникальные ID учреждений из диапазона
     */
    private function getRandomInstitutions($minId, $maxId, $count)
    {
        $availableIds = range($minId, $maxId);
        
        // Если запрашиваемое количество больше доступных ID, возвращаем все доступные
        if ($count >= count($availableIds)) {
            return $availableIds;
        }
        
        // Перемешиваем массив и берем нужное количество
        shuffle($availableIds);
        return array_slice($availableIds, 0, $count);
    }
    
    /**
     * Получить случайную дату в пределах последнего года
     */
    private function getRandomDate()
    {
        return Carbon::now()
            ->subDays(rand(0, 365))
            ->subHours(rand(0, 23))
            ->subMinutes(rand(0, 59))
            ->subSeconds(rand(0, 59));
    }
    
    /**
     * Получить случайный комментарий в зависимости от рейтинга
     */
    private function getRandomComment($rating)
    {
        $comments = [
            5 => [ // Отличные отзывы (5 звезд)
                'Отличное учебное заведение! Прекрасные преподаватели и современные методики.',
                'Великолепные возможности для профессионального роста!',
                'Лучший выбор для тех, кто хочет качественное образование.',
                'Отличные преподаватели и индивидуальный подход к студентам!',
                'Очень сильная учебная программа, рекомендую!',
                'Советую всем, кто хочет развиваться в этой сфере.',
                'Очень удобная платформа для обучения, много полезных материалов.',
                'Прекрасный опыт, обязательно порекомендую друзьям.',
                'Очень интересные занятия, много интерактива.',
                'Крутые преподаватели, обучение проходит на высоком уровне.'
            ],
            4 => [ // Хорошие отзывы (4 звезды)
                'Хорошая атмосфера, но хотелось бы больше практических занятий.',
                'Хорошие преподаватели, но учебная программа немного устарела.',
                'Учебное заведение достойное, но хотелось бы больше современных технологий.',
                'Много полезной информации, но некоторые предметы слишком сложные.',
                'Качественное образование, но хотелось бы больше интерактива.',
                'В целом доволен, но не хватает гибкости в расписании.',
                'Интересные лекции, но иногда не хватает обратной связи.',
                'Программы актуальны, преподаватели профессионалы своего дела.',
                'Некоторые темы сложные, но преподаватели всегда помогают разобраться.',
                'Очень сильные преподаватели, объясняют понятно и доступно.'
            ],
            3 => [ // Средние отзывы (3 звезды)
                'Средний уровень обучения. Некоторым преподавателям не хватает вовлеченности.',
                'Хорошая база знаний, но иногда не хватает актуальных примеров.',
                'Среднее качество обучения, но хорошие условия для студентов.',
                'Некоторые предметы подаются слишком теоретически.',
                'Средний уровень, но есть хорошие преподаватели.',
                'Неплохая организация, но есть над чем работать.',
                'Можно было бы улучшить программу и добавить больше практики.',
                'Нормально, но есть заведения с более сильной программой.',
                'Организация учебного процесса могла бы быть лучше.',
                'Средний университет, но с хорошими преподавателями.'
            ],
            2 => [ // Плохие отзывы (2 звезды)
                'Организация учебного процесса оставляет желать лучшего.',
                'Не оправдало ожиданий.',
                'Ожидал большего, но в целом неплохо.',
                'Много устаревшей информации, хотелось бы обновлений.',
                'Не оправдал ожиданий.',
                'Не понравилось, ожидал большего.',
                'Не самый лучший университет.',
                'Не понравилась подача материала.',
                'Организация на низком уровне.',
                'Разочарован уровнем преподавания.'
            ],
            1 => [ // Очень плохие отзывы (1 звезда)
                'Полное разочарование! Не рекомендую никому.',
                'Ужасная организация, потеря времени.',
                'Крайне недоволен качеством обучения.',
                'Худшее учебное заведение из всех, где учился.',
                'Абсолютно не стоит потраченных денег.',
                'Катастрофически низкий уровень преподавания.',
                'Полный хаос в организации учебного процесса.',
                'Преподаватели некомпетентны, программа устарела.',
                'Не получил никаких полезных знаний.',
                'Администрация безразлична к проблемам студентов.'
            ]
        ];
        
        $ratingComments = $comments[$rating];
        return $ratingComments[array_rand($ratingComments)];
    }
} 