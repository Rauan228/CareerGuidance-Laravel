<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GlobalSpecialty;
use App\Models\Qualification;
use App\Models\Specialization;

/**
 * @deprecated Не вызывать. Фейковый каталог ОП до парсинга.
 * Источник истины: data:import-scraped / data:import-official-programs.
 * Чистка: php artisan data:purge-seed-specialties
 */
class UniversitySpecialtiesSeeder extends Seeder
{
    public function run()
    {
        // 1. Педагогические науки
        $education = GlobalSpecialty::create([
            'name' => 'Педагогические науки',
            'description' => 'Программы подготовки специалистов в области педагогики и образования'
        ]);

        // 1.1 Педагогика дошкольного воспитания и обучения
        $preschool = Qualification::create([
            'qualification_name' => 'Педагогика дошкольного воспитания и обучения',
            'global_specialty_id' => $education->id,
            'description' => 'Подготовка специалистов для дошкольного образования'
        ]);

        Specialization::create([
            'name' => 'Дошкольное образование',
            'qualification_id' => $preschool->id,
            'description' => 'Обучение и воспитание детей дошкольного возраста'
        ]);

        Specialization::create([
            'name' => 'Дошкольное обучение и воспитание',
            'qualification_id' => $preschool->id,
            'description' => 'Организация образовательного процесса в детских садах'
        ]);

        // 1.2 Педагогика и психология
        $psychology = Qualification::create([
            'qualification_name' => 'Педагогика и психология',
            'global_specialty_id' => $education->id,
            'description' => 'Подготовка специалистов в области педагогической психологии'
        ]);

        Specialization::create([
            'name' => 'Педагогика и психология',
            'qualification_id' => $psychology->id,
            'description' => 'Изучение психолого-педагогических аспектов образования'
        ]);

        // 1.3 Подготовка специалистов по социальной педагогике и самопознанию
        $socialPedagogy = Qualification::create([
            'qualification_name' => 'Подготовка специалистов по социальной педагогике и самопознанию',
            'global_specialty_id' => $education->id,
            'description' => 'Подготовка специалистов в области социальной педагогики'
        ]);

        Specialization::create([
            'name' => 'Социальная педагогика и самопознание',
            'qualification_id' => $socialPedagogy->id,
            'description' => 'Работа с детьми и молодежью в социальной сфере'
        ]);

        Specialization::create([
            'name' => 'Социальная и ювенальная педагогика',
            'qualification_id' => $socialPedagogy->id,
            'description' => 'Работа с детьми и подростками в социальной сфере'
        ]);

        // 1.4 Подготовка специалистов по специальной педагогике
        $specialPedagogy = Qualification::create([
            'qualification_name' => 'Подготовка специалистов по специальной педагогике',
            'global_specialty_id' => $education->id,
            'description' => 'Подготовка педагогов для работы с детьми с особыми потребностями'
        ]);

        Specialization::create([
            'name' => 'Дефектология',
            'qualification_id' => $specialPedagogy->id,
            'description' => 'Работа с детьми с нарушениями развития'
        ]);

        Specialization::create([
            'name' => 'Логопедия',
            'qualification_id' => $specialPedagogy->id,
            'description' => 'Коррекция речевых нарушений'
        ]);

        // 1.5 Подготовка учителей без предметной специализации
        $generalTeachers = Qualification::create([
            'qualification_name' => 'Подготовка учителей без предметной специализации',
            'global_specialty_id' => $education->id,
            'description' => 'Подготовка учителей начальных классов'
        ]);

        Specialization::create([
            'name' => 'Начальное образование',
            'qualification_id' => $generalTeachers->id,
            'description' => 'Преподавание в начальной школе'
        ]);

        // 1.6 Подготовка учителей музыки
        $musicTeachers = Qualification::create([
            'qualification_name' => 'Подготовка учителей музыки',
            'global_specialty_id' => $education->id,
            'description' => 'Подготовка учителей музыкального образования'
        ]);

        Specialization::create([
            'name' => 'Музыкальное образование',
            'qualification_id' => $musicTeachers->id,
            'description' => 'Преподавание музыки в школах'
        ]);

        // 1.7 Подготовка учителей по гуманитарным предметам
        $humanitiesTeachers = Qualification::create([
            'qualification_name' => 'Подготовка учителей по гуманитарным предметам',
            'global_specialty_id' => $education->id,
            'description' => 'Подготовка учителей гуманитарных дисциплин'
        ]);

        Specialization::create([
            'name' => 'История',
            'qualification_id' => $humanitiesTeachers->id,
            'description' => 'Преподавание истории в школах'
        ]);

        // 1.8 Подготовка учителей по естественнонаучным предметам
        $scienceTeachers = Qualification::create([
            'qualification_name' => 'Подготовка учителей по естественнонаучным предметам',
            'global_specialty_id' => $education->id,
            'description' => 'Подготовка учителей естественных наук'
        ]);

        Specialization::create([
            'name' => 'Биология',
            'qualification_id' => $scienceTeachers->id,
            'description' => 'Преподавание биологии в школах'
        ]);

        Specialization::create([
            'name' => 'Физика',
            'qualification_id' => $scienceTeachers->id,
            'description' => 'Преподавание физики в школах'
        ]);

        // 1.9 Подготовка учителей по языкам и литературе
        $languageTeachers = Qualification::create([
            'qualification_name' => 'Подготовка учителей по языкам и литературе',
            'global_specialty_id' => $education->id,
            'description' => 'Подготовка учителей языков и литературы'
        ]);

        Specialization::create([
            'name' => 'Казахский язык и литература',
            'qualification_id' => $languageTeachers->id,
            'description' => 'Преподавание казахского языка и литературы'
        ]);

        Specialization::create([
            'name' => 'Русский язык и литература',
            'qualification_id' => $languageTeachers->id,
            'description' => 'Преподавание русского языка и литературы'
        ]);

        // 2. Искусство и гуманитарные науки
        $arts = GlobalSpecialty::create([
            'name' => 'Искусство и гуманитарные науки',
            'description' => 'Программы в области искусства и гуманитарных наук'
        ]);

        // 2.1 Аудиовизуальные средства и медиапроизводство
        $media = Qualification::create([
            'qualification_name' => 'Аудиовизуальные средства и медиапроизводство',
            'global_specialty_id' => $arts->id,
            'description' => 'Создание медиаконтента и работа с аудиовизуальными средствами'
        ]);

        Specialization::create([
            'name' => 'Операторское искусство',
            'qualification_id' => $media->id,
            'description' => 'Работа оператором в кино и медиа'
        ]);

        Specialization::create([
            'name' => 'Режиссура игрового кино',
            'qualification_id' => $media->id,
            'description' => 'Режиссура художественных фильмов'
        ]);

        // 2.2 Гуманитарные науки
        $humanities = Qualification::create([
            'qualification_name' => 'Гуманитарные науки',
            'global_specialty_id' => $arts->id,
            'description' => 'Изучение истории, философии и других гуманитарных дисциплин'
        ]);

        Specialization::create([
            'name' => 'История',
            'qualification_id' => $humanities->id,
            'description' => 'Исследование исторических процессов'
        ]);

        Specialization::create([
            'name' => 'Философия',
            'qualification_id' => $humanities->id,
            'description' => 'Изучение философских концепций'
        ]);

        // 2.3 Искусствоведение
        $artStudies = Qualification::create([
            'qualification_name' => 'Искусствоведение',
            'global_specialty_id' => $arts->id,
            'description' => 'Изучение истории и теории искусства'
        ]);

        Specialization::create([
            'name' => 'Искусствоведение',
            'qualification_id' => $artStudies->id,
            'description' => 'Анализ и исследование искусства'
        ]);

        Specialization::create([
            'name' => 'Музееведение',
            'qualification_id' => $artStudies->id,
            'description' => 'Организация музейной деятельности'
        ]);

        // 2.4 Театральное искусство
        $theater = Qualification::create([
            'qualification_name' => 'Театральное искусство',
            'global_specialty_id' => $arts->id,
            'description' => 'Подготовка специалистов для театра'
        ]);

        Specialization::create([
            'name' => 'Артист драматического театра и кино',
            'qualification_id' => $theater->id,
            'description' => 'Актерская работа в театре и кино'
        ]);

        Specialization::create([
            'name' => 'Сценография',
            'qualification_id' => $theater->id,
            'description' => 'Создание декораций и костюмов для театра'
        ]);

        // 3. Социальные науки, журналистика и информация
        $social = GlobalSpecialty::create([
            'name' => 'Социальные науки, журналистика и информация',
            'description' => 'Программы в области журналистики и социальных наук'
        ]);

        // 3.1 Журналистика и информация
        $journalism = Qualification::create([
            'qualification_name' => 'Журналистика и информация',
            'global_specialty_id' => $social->id,
            'description' => 'Подготовка журналистов и медиаспециалистов'
        ]);

        Specialization::create([
            'name' => 'Digital Journalism (Цифровая журналистика)',
            'qualification_id' => $journalism->id,
            'description' => 'Создание контента для цифровых платформ'
        ]);

        Specialization::create([
            'name' => 'Телерадиожурналистика',
            'qualification_id' => $journalism->id,
            'description' => 'Работа на телевидении и радио'
        ]);

        // 3.2 Социальные науки
        $socialSciences = Qualification::create([
            'qualification_name' => 'Социальные науки',
            'global_specialty_id' => $social->id,
            'description' => 'Изучение социальных процессов и психологии'
        ]);

        Specialization::create([
            'name' => 'Психология',
            'qualification_id' => $socialSciences->id,
            'description' => 'Изучение психологии личности и поведения'
        ]);

        Specialization::create([
            'name' => 'Социология',
            'qualification_id' => $socialSciences->id,
            'description' => 'Исследование социальных процессов'
        ]);

        // 4. Бизнес, управление и право
        $business = GlobalSpecialty::create([
            'name' => 'Бизнес, управление и право',
            'description' => 'Программы в области экономики, менеджмента и юриспруденции'
        ]);

        // 4.1 Бизнес и управление
        $management = Qualification::create([
            'qualification_name' => 'Бизнес и управление',
            'global_specialty_id' => $business->id,
            'description' => 'Подготовка специалистов в области управления и экономики'
        ]);

        Specialization::create([
            'name' => 'Бизнес-администрирование',
            'qualification_id' => $management->id,
            'description' => 'Управление бизнес-процессами'
        ]);

        Specialization::create([
            'name' => 'Маркетинг',
            'qualification_id' => $management->id,
            'description' => 'Разработка маркетинговых стратегий'
        ]);

        // 4.2 Право
        $law = Qualification::create([
            'qualification_name' => 'Право',
            'global_specialty_id' => $business->id,
            'description' => 'Подготовка юристов'
        ]);

        Specialization::create([
            'name' => 'Юриспруденция',
            'qualification_id' => $law->id,
            'description' => 'Изучение правовых норм и их применение'
        ]);

        Specialization::create([
            'name' => 'Международное право',
            'qualification_id' => $law->id,
            'description' => 'Работа с международным законодательством'
        ]);

        // 5. Естественные науки, математика и статистика
        $naturalSciences = GlobalSpecialty::create([
            'name' => 'Естественные науки, математика и статистика',
            'description' => 'Программы в области естественных наук и математики'
        ]);

        // 5.1 Биологические и смежные науки
        $biology = Qualification::create([
            'qualification_name' => 'Биологические и смежные науки',
            'global_specialty_id' => $naturalSciences->id,
            'description' => 'Изучение биологии и биотехнологий'
        ]);

        Specialization::create([
            'name' => 'Биология',
            'qualification_id' => $biology->id,
            'description' => 'Исследование живых организмов'
        ]);

        Specialization::create([
            'name' => 'Биотехнология',
            'qualification_id' => $biology->id,
            'description' => 'Применение биологии в технологиях'
        ]);

        // 5.2 Математика и статистика
        $math = Qualification::create([
            'qualification_name' => 'Математика и статистика',
            'global_specialty_id' => $naturalSciences->id,
            'description' => 'Подготовка математиков и статистиков'
        ]);

        Specialization::create([
            'name' => 'Математика',
            'qualification_id' => $math->id,
            'description' => 'Изучение математических методов'
        ]);

        Specialization::create([
            'name' => 'Статистика',
            'qualification_id' => $math->id,
            'description' => 'Анализ данных и статистика'
        ]);

        // 5.3 Физические и химические науки
        $physicsChemistry = Qualification::create([
            'qualification_name' => 'Физические и химические науки',
            'global_specialty_id' => $naturalSciences->id,
            'description' => 'Изучение физики и химии'
        ]);

        Specialization::create([
            'name' => 'Физика',
            'qualification_id' => $physicsChemistry->id,
            'description' => 'Исследование физических явлений'
        ]);

        Specialization::create([
            'name' => 'Химия',
            'qualification_id' => $physicsChemistry->id,
            'description' => 'Исследование химических процессов'
        ]);

        

       

        /* 1.3 Социальная педагогика и самопознание — добавляем */
        // (строки удалены: дубликат, уже добавлено выше)

        /* 1.4 Специальная педагогика — дополнительные специализации */
        foreach ([
            'Специальная педагогика',
            'Сурдопедагогика',
            'Тьюторство в инклюзивном образовании',
        ] as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $specialPedagogy->id,
                'description' => $spec,
            ]);
        }

        /* 1.5 Учителя без предметной специализации */
        foreach ([
            'Педагогика и методика начального обучения',
            'Учитель начальных классов со знанием иностранного языка',
        ] as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $generalTeachers->id,
                'description' => $spec,
            ]);
        }

        /* 1.7 Учителя по гуманитарным предметам */
        foreach ([
            'История и обществознание (IP)',
            'История и религиоведение',
            'История и цифровая гуманитаристика',
        ] as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $humanitiesTeachers->id,
                'description' => $spec,
            ]);
        }

        /* 1.8 Учителя по естественнонаучным предметам – полный список */
        $scienceSpecs = [
            'Биология (IP)', 'Биология-Естествознание',
            'География', 'География-История',
            'Информатика', 'Информатика и робототехника',
            'Кибер-Математика', 'Математика',
            'Математика-Информатика', 'Математика-Физика',
            'Физика (IP)', 'Физика-Информатика',
            'Химия', 'Химия (IP)', 'Химия-Биология',
        ];
        foreach ($scienceSpecs as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $scienceTeachers->id,
                'description' => $spec,
            ]);
        }

        /* 1.9 Учителя по языкам и литературе – дополняем */
        $languageSpecs = [
            'Английский язык (IP)',
            'Иностранный язык: два иностранных языка',
            'Казахский язык и литература – английский язык',
            'Казахский язык и литература в учреждениях образования с казахским и русским языком обучения',
            'Казахский язык и литература в школах с неказахским языком обучения',
            'Казахский, русский языки и литература',
            'Русский язык и литература в школах с нерусским языком обучения',
            'Русский язык и литература в школах с русским и нерусским языками обучения (IP)',
            'Узбекский язык и литература',
            'Учитель английского и русского языков',
            'Учитель английского языка и начальных классов',
        ];
        foreach ($languageSpecs as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $languageTeachers->id,
                'description' => $spec,
            ]);
        }

        /* 1.10 Подготовка учителей с предметной специализацией общего развития */
        $developmentTeachers = Qualification::create([
            'qualification_name' => 'Подготовка учителей с предметной специализацией общего развития',
            'global_specialty_id' => $education->id,
            'description' => 'Обучение учителей предметов общего развития',
        ]);

        $devSpecs = [
            'Арт-образование и предпринимательство',
            'Визуальное искусство, художественный труд, графика и проектирование',
            'Изобразительное искусство и черчение',
            'Изобразительное искусство, художественный труд и графика',
            'Начальная военная подготовка',
            'Оздоровительная физическая культура',
            'Основы права и экономики',
            'Профессиональное обучение',
            'Профессиональное обучение, художественный труд и графика',
            'Трудовое обучение и основы предпринимательства (IP)',
            'Учитель художественного труда и изобразительного искусства',
            'Физическая культура и начальная военная подготовка',
            'Физическая культура и спорт',
            'Художественное образование (IP)',
        ];
        foreach ($devSpecs as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $developmentTeachers->id,
                'description' => $spec,
            ]);
        }

        /* 2.2 Гуманитарные науки – расширяем список */
        $humanitySpecsExtra = [
            'Археология и этнология', 'Востоковедение', 'Исламоведение',
            'Музейное дело и охрана памятников', 'Научная история',
            'Религиоведение', 'Религиоведение и философия', 'Теология',
            'Тюркология',
        ];
        foreach ($humanitySpecsExtra as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $humanities->id,
                'description' => $spec,
            ]);
        }

        /* 2.3 Искусствоведение – дополняем */
        foreach (['Киноведение', 'Кинотеледраматургия', 'Театроведение'] as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $artStudies->id,
                'description' => $spec,
            ]);
        }

        /* 2.x Дирижирование */
        $conducting = Qualification::create([
            'qualification_name' => 'Дирижирование',
            'global_specialty_id' => $arts->id,
            'description' => 'Подготовка дирижёров',
        ]);
        foreach (['Оркестровое дирижирование', 'Хоровое дирижирование'] as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $conducting->id,
                'description' => $spec,
            ]);
        }

        /* 2.x Искусство */
        $artGeneral = Qualification::create([
            'qualification_name' => 'Искусство',
            'global_specialty_id' => $arts->id,
            'description' => 'Широкий спектр программ в сфере искусства',
        ]);
        $artSpecs = [
            'Creative artes', 'Digital cultures', 'Digital Filmmaking', 'Web-дизайн',
            'Актерское искусство', 'Арт-дизайн', 'Арт-менеджмент', 'Архитектурный дизайн',
            'Аудиопроизводство', 'Вокальное искусство', 'Графика',
            'Графический дизайн', 'Графический дизайн (иллюстрация)',
            'Декоративно-прикладное искусство', 'Декоративное искусство',
            'Декоративное искусство — художественная обработка дерева',
            'Декоративное искусство — художественная обработка металла и других материалов',
            'Декоративное искусство — художественное ткачество',
            'Декоративное искусство и этнодизайн', 'Дизайн', 'Дизайн интерьера',
            'Дизайн моды', 'Дизайн среды', 'Дирижирование', 'Живопись',
            'Живопись — Монументальная живопись', 'Живопись — Реставрация',
            'Живопись — Станковая живопись', 'Издательское дело', 'Инструментальное искусство',
            'Инструментальное исполнительство — Духовые и ударные инструменты',
            'Инструментальное исполнительство — Струнные инструменты-АВАК',
            'Инструментальное исполнительство — Струнные инструменты-Скрипка',
            'Инструментальное исполнительство — Фортепиано', 'Искусство эстрады',
            'Искусство эстрады — Артист эстрадного оркестра',
            'Искусство эстрады — Вокалист эстрады',
            'Искусство эстрады — Композитор эстрады, аранжировщик', 'Искусствоведение',
            'Композиция', 'Народные инструменты (Домбыра)', 'Народные инструменты (Қобыз и РНИ)',
            'Полиграфия', 'Поэзия и искусство', 'Продюсирование кино и ТВ',
            'Промышленный дизайн', 'Режиссура', 'Сервис в индустрии моды и красоты',
            'Скульптура', 'Сценография', 'Сценография — Дизайн одежды',
            'Сценография — Светотехника', 'Сценография — Сценография костюма театра, кино и ТВ',
            'Сценография — Театральная техника и оформление спектакля',
            'Сценография — Театрально-декорационное искусство',
            'Сценография грима театра, кино и ТВ', 'Традиционное музыкальное искусство',
            'Традиционное пение', 'Традиционный жыр', 'Традиционный жыр (Айтыс)',
            'Хореография', 'Художник кино и ТВ',
        ];
        foreach ($artSpecs as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $artGeneral->id,
                'description' => $spec,
            ]);
        }

        /* 2.x Искусствоведение (новые квалификации) */
        $composition = Qualification::create([
            'qualification_name' => 'Композиция',
            'global_specialty_id' => $arts->id,
            'description' => 'Музыкальная композиция',
        ]);
        Specialization::create([
            'name' => 'Композиция',
            'qualification_id' => $composition->id,
            'description' => 'Музыкальная композиция',
        ]);

        $musicology = Qualification::create([
            'qualification_name' => 'Музыковедение',
            'global_specialty_id' => $arts->id,
            'description' => 'Изучение музыковедения',
        ]);
        Specialization::create([
            'name' => 'Музыковедение',
            'qualification_id' => $musicology->id,
            'description' => 'Музыковедение',
        ]);

        $directingArtMgmt = Qualification::create([
            'qualification_name' => 'Режиссура, арт-менеджмент',
            'global_specialty_id' => $arts->id,
            'description' => 'Специалисты режиссуры и арт-менеджмента',
        ]);
        $directSpecs = [
            'Актёр кино', 'Арт-менеджмент', 'Звукорежиссура кино и ТВ', 'Музыкальная звукорежиссура',
            'Режиссура анимационного фильма', 'Режиссура документального кино', 'Режиссура игрового кино',
            'Режиссура музыкального театра', 'Режиссура телевидения', 'Режиссура, продюсерство кино и ТВ',
        ];
        foreach ($directSpecs as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $directingArtMgmt->id,
                'description' => $spec,
            ]);
        }

        /* 2.4 Театральное искусство – добавляем недостающие */
        foreach (['Артист кукольного театра', 'Артист музыкального театра'] as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $theater->id,
                'description' => $spec,
            ]);
        }

        /* 2.x Языки и литература */
        $langLit = Qualification::create([
            'qualification_name' => 'Языки и литература',
            'global_specialty_id' => $arts->id,
            'description' => 'Филологические и переводческие программы',
        ]);
        $langLitSpecs = [
            'Иностранная филология', 'Иностранная филология: узбекский язык',
            'Межкультурно-коммуникативный перевод', 'Перевод и переводоведение',
            'Переводческое дело', 'Технический перевод',
            'Филология: казахская филология', 'Филология: русская филология',
            'Цифровая лингвистика',
        ];
        foreach ($langLitSpecs as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $langLit->id,
                'description' => $spec,
            ]);
        }

        /* 3.1 Журналистика и информация – расширяем */
        $journalismExtra = [
            'New media', 'PR и Реклама', 'PR-журналистика',
            'Архивоведение и библиотечно-информационная деятельность',
            'Архивоведение, документоведение и документационное обеспечение',
            'Библиотечно-педагогическая деятельность', 'Библиотечное дело',
            'Библиотечные информационные системы', 'Бизнес-журналистка, SMM & PR',
            'Журналистика', 'Конвергентная журналистика',
            'Международная журналистика и интернет-безопасность', 'Связь с общественностью',
        ];
        foreach ($journalismExtra as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $journalism->id,
                'description' => $spec,
            ]);
        }

        /* 3.2 Социальные науки – расширяем */
        $socialExtra = [
            'Клиническая психология', 'Культурология', 'Международная аналитика',
            'Международные отношения', 'Организационная психология',
            'Политические массовые коммуникации', 'Политология',
            'Психологические особенности развития личности', 'Психологическое консультирование',
            'Психология и менеджмент в образовании', 'Регионоведение',
            'Управление социально-психологическими процессами',
        ];
        foreach ($socialExtra as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $socialSciences->id,
                'description' => $spec,
            ]);
        }

        // ---- Конец дополнений ----

        // ---- Новые глобальные специальности (Бизнес, Естественные науки, ИКТ) ----

        /* Проверяем, существует ли уже глобальная специальность "Бизнес, управление и право" */
        $businessGlobal = GlobalSpecialty::where('name', 'Бизнес, управление и право')->first();
        if (!$businessGlobal) {
            $businessGlobal = GlobalSpecialty::create([
                'name' => 'Бизнес, управление и право',
                'description' => 'Программы в области экономики, менеджмента и юриспруденции'
            ]);
        }

        /* Бизнес и управление */
        $businessManagement = Qualification::create([
            'qualification_name' => 'Бизнес и управление',
            'global_specialty_id' => $businessGlobal->id,
            'description' => 'Подготовка специалистов в области управления и экономики'
        ]);

        $businessSpecs = [
            'BBA in Accounting', 'BBA in Accounting (2 года)', 'BBA in Accounting (3 года)',
            'BBA in Business Economics', 'BBA in Business Economics (2 года)', 'BBA in Business Economics (3 года)',
            'BBA in Finance', 'BBA in Finance (2 года)', 'BBA in Finance (3 года)',
            'BBA in Financial Risk Management', 'BBA in HR and Business Planning',
            'BBA in Management', 'BBA in Management (2 года)', 'BBA in Management (3 года)',
            'BBA in Marketing', 'BBA in Marketing (2 года)',
            'Business Administration in Entrepreneurship', 'Business Management',
            'Digital маркетинг', 'Digital-бизнес', 'EVENT-менеджмент', 'Global Management',
            'HR и бизнес-планирование', 'HR-менеджмент', 'International Trade',
            'IT-маркетинг', 'IТ-менеджмент', 'Product Manager', 'Social медиа маркетинг',
            'Агротуризм', 'Банковское дело', 'Банковское дело и финансовый менеджмент',
            'Бизнес-администрирование', 'Бизнес-администрирование (Двудипломная программа с UIBE)',
            'Бизнес-администрирование в области предпринимательства',
            'Бизнес-аналитика и экономика', 'Бизнес-управление производственными системами',
            'Бизнес-финансы', 'Бренд-менеджмент', 'Бухгалтерский учет и экономический анализ',
            'Государственная служба и управление', 'Государственное и местное управление',
            'Государственные финансы', 'Государственный аудит',
            'Государственный менеджмент и e-government', 'Государственный финансовый менеджмент',
            'Инновационная экономика', 'Инновационный менеджмент',
            'Информационные и инновационные технологии в экономике', 'Корпоративные финансы',
            'Маркетинг', 'Маркетинг в цифровой экономике', 'Маркетинг и PR-менеджмент',
            'Маркетинговые исследования и анализ',
            'Международная торговля и экономика (Двудипломная программа с UIBE)',
            'Международные отношения и экономика', 'Международный бизнес',
            'Международный бизнес (Двудипломная программа с UIBE)',
            'Международный бизнес аграрными товарами', 'Менеджмент',
            'Менеджмент / Business administration', 'Менеджмент /Bachelor of Hotel Management',
            'Менеджмент в сетевом бизнесе', 'Менеджмент гостеприимства',
            'Менеджмент и маркетинг', 'Менеджмент онлайн-продаж', 'Менеджмент предприятий',
            'Менеджмент спорта', 'Менеджмент. Маркетинг. Продажи', 'Мировая экономика',
            'Налоговый менеджмент', 'Организация налогового учета и аудита в отраслях экономики',
            'Оценка (по отраслям)', 'Предпринимательство', 'Проектно-инновационный менеджмент',
            'Региональное развитие', 'Таможенное дело (экономика)',
            'Управление и экономика промышленности', 'Управление финансами наукоемких предприятий',
            'Урбанистика', 'Учет и аудит', 'Учет и аудит в промышленности',
            'Учет и аудит по программе ACCA', 'Учет, анализ и аудит на предприятии',
            'Финансово-таможенный менеджмент', 'Финансовые рынки и финансовые институты',
            'Финансовые технологии', 'Финансовый инжиниринг', 'Финансы',
            'Экономика', 'Экономика и бизнес-управление', 'Экономика и менеджмент',
            'Экономика и менеджмент в строительстве', 'Экономика и управление',
            'Экономика производства', 'Экономика промышленности', 'Экономика цифровизации',
            'Экономист-аналитик', 'Экономическая кибернетика', 'Электронный бизнес',
        ];
        foreach ($businessSpecs as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $businessManagement->id,
                'description' => $spec,
            ]);
        }

        /* Междисциплинарные программы */
        $interdisciplinary = Qualification::create([
            'qualification_name' => 'Междисциплинарные программы, связанные с бизнесом, управлением и правом',
            'global_specialty_id' => $businessGlobal->id,
            'description' => 'Междисциплинарные программы'
        ]);
        Specialization::create([
            'name' => 'Урбанистика и сити-менеджмент',
            'qualification_id' => $interdisciplinary->id,
            'description' => 'Урбанистика и сити-менеджмент',
        ]);

        /* Право */
        $lawQual = Qualification::create([
            'qualification_name' => 'Право',
            'global_specialty_id' => $businessGlobal->id,
            'description' => 'Подготовка юристов'
        ]);

        $lawSpecs = [
            'In-house юрист', 'International law and security', 'IT LAW', 'IT-юрист',
            'Бизнес-право', 'Государственная служба', 'Гражданское право и гражданский процесс',
            'Корпоративное право', 'Корпоративный юрист', 'Международное право',
            'Международное право, юрист-международник со знанием двух иностранных языков',
            'Международное экономическое право', 'Право',
            'Право зеленой экономики со знанием двух иностранных языков',
            'Право и государственное управление', 'Право и правоохранительная деятельность',
            'Правовое обеспечение экономической безопасности',
            'Правовое обеспечение экономической деятельности', 'Правовое регулирование экономики',
            'Правовое сопровождение бизнеса', 'Правоохранительная и судебная деятельность',
            'Противодействие уголовным правонарушениям в сфере информзащиты', 'Публичное право',
            'Социально-правовое партнерство', 'Судебная и правоохранительная деятельность',
            'Судебно-правозащитная деятельность', 'Судебно-прокурорская деятельность',
            'Таможенное дело', 'Таможенное дело (право)', 'Таможенное оформление и таможенный контроль',
            'Уголовное право и уголовный процесс', 'Финансовое право', 'Частное право',
            'Юридическое сопровождение предпринимательской деятельности', 'Юриспруденция',
            'Юриспруденция (2 года)', 'Юриспруденция (3 года)',
        ];
        foreach ($lawSpecs as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $lawQual->id,
                'description' => $spec,
            ]);
        }

        /* Естественные науки, математика и статистика */
        $naturalSciGlobal = GlobalSpecialty::where('name', 'Естественные науки, математика и статистика')->first();
        if (!$naturalSciGlobal) {
            $naturalSciGlobal = GlobalSpecialty::create([
                'name' => 'Естественные науки, математика и статистика',
                'description' => 'Программы в области естественных наук и математики'
            ]);
        }

        /* Биологические и смежные науки */
        $biologyQual = Qualification::create([
            'qualification_name' => 'Биологические и смежные науки',
            'global_specialty_id' => $naturalSciGlobal->id,
            'description' => 'Изучение биологии и биотехнологий'
        ]);
        $bioSpecs = [
            'Биоинженерия и биоинформатика', 'Биологическая инженерия', 'Биология',
            'Биомедицина', 'Биотехнология', 'Биофизика', 'Генетика', 'Геоботаника',
            'Ландшафтный дизайн', 'Микробиология',
        ];
        foreach ($bioSpecs as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $biologyQual->id,
                'description' => $spec,
            ]);
        }

        /* Математика и статистика */
        $mathQual = Qualification::create([
            'qualification_name' => 'Математика и статистика',
            'global_specialty_id' => $naturalSciGlobal->id,
            'description' => 'Подготовка математиков и статистиков'
        ]);
        $mathSpecs = [
            'Applied mathematics in digital economy', 'Statistics and data science',
            'Актуарная математика', 'Математика', 'Математическая экономика и анализ данных',
            'Механика', 'Статистика',
        ];
        foreach ($mathSpecs as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $mathQual->id,
                'description' => $spec,
            ]);
        }

        /* Окружающая среда */
        $environmentQual = Qualification::create([
            'qualification_name' => 'Окружающая среда',
            'global_specialty_id' => $naturalSciGlobal->id,
            'description' => 'Изучение окружающей среды и экологии'
        ]);
        $envSpecs = [
            'География', 'География и природопользование', 'Гидрология', 'Ландшафтное проектирование',
            'Метеорология', 'Окружающая среда и устойчивое развитие',
            'Охрана окружающей среды и рациональное использование природных ресурсов',
            'Рекреационная география и туризм', 'Экология', 'Экология и природопользование',
            'Экотехнология и устойчивое развитие',
        ];
        foreach ($envSpecs as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $environmentQual->id,
                'description' => $spec,
            ]);
        }

        /* Физические и химические науки */
        $physChemQual = Qualification::create([
            'qualification_name' => 'Физические и химические науки',
            'global_specialty_id' => $naturalSciGlobal->id,
            'description' => 'Изучение физики и химии'
        ]);
        $physChemSpecs = [
            'Астрономия и методы дистанционных исследований', 'Инженерная физика и материаловедение',
            'Инженерная физика и технологии новых материалов', 'Компьютерная физика',
            'Техническая физика', 'Физика', 'Физика и астрономия',
            'Химическая криминалистическая экспертиза', 'Химическая технология органических веществ',
            'Химическая экспертиза и аналитический контроль производства',
            'Химическая, криминалистическая и экологическая экспертиза', 'Химия',
            'Химия и наноматериалы', 'Экспертиза веществ и материалов в химической инженерии',
            'Ядерная физика и атомная энергетика',
        ];
        foreach ($physChemSpecs as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $physChemQual->id,
                'description' => $spec,
            ]);
        }

        /* Информационно-коммуникационные технологии */
        $ictGlobal = GlobalSpecialty::create([
            'name' => 'Информационно-коммуникационные технологии',
            'description' => 'Программы в области информационных технологий'
        ]);

        /* Информационная безопасность */
        $infoSecQual = Qualification::create([
            'qualification_name' => 'Информационная безопасность',
            'global_specialty_id' => $ictGlobal->id,
            'description' => 'Обеспечение информационной безопасности'
        ]);
        $infoSecSpecs = [
            'Cybersecurity (Кибербезопасность)', 'Аппаратные средства защиты информации',
            'Информационная безопасность финансовых структур',
            'Информационные технологии и защита данных', 'Компьютерная безопасность',
            'Криптографическая защита информации', 'Сетевая безопасность',
            'Системы информационной безопасности', 'Техническая защита информации',
        ];
        foreach ($infoSecSpecs as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $infoSecQual->id,
                'description' => $spec,
            ]);
        }

        /* ИКТ общие */
        $ictQual = Qualification::create([
            'qualification_name' => 'Информационно-коммуникационные технологии',
            'global_specialty_id' => $ictGlobal->id,
            'description' => 'Общие программы ИКТ'
        ]);
        $ictSpecs = [
            'BBA in IT in Business', 'Big Data Analysis (Анализ больших данных)',
            'Business analytics and Big Data', 'Data Science', 'Digital Engineering',
            'Digital Engineering (3 года)', 'Digital management and design',
            'Industrial Automation (Индустриальная автоматизация)', 'IT в здравоохранении',
            'IT-аналитика', 'IT-медицина', 'IT-менеджмент', 'Media Technologies (Медиа технологии)',
            'Mobile computing', 'SMART технологии', 'Software Engineering',
            'Автоматизация и робототехника', 'Аналитика Big Data', 'Архитектор программного обеспечения',
            'Биокомпьютинг', 'Вычислительная техника и программное обеспечение',
            'Вычислительная техника и программное обеспечение /Smart computing',
            'Иммерсивные технологии', 'Инженерная математика', 'Интеллектуальная робототехника',
            'Информатика', 'Информационно-программные системы', 'Информационные системы',
            'Информационные системы и технологии', 'Информационные технологии',
            'Информационные технологии в бизнесе', 'Информационный инжиниринг в экономике',
            'Искусственный интеллект', 'Искусственный интеллект и анализ данных',
            'ІТ и программирование', 'Киберфизические системы', 'Компьютерная инженерия',
            'Компьютерная инженерия (KZ + UK)', 'Компьютерная мехатроника',
            'Компьютерные науки (Computer Science)', 'Компьютерные науки и программная инженерия',
            'Корпоративные информационные системы', 'Математическое и компьютерное моделирование',
            'Моделирование и конструирование виртуальной реальности', 'Программная инженерия',
            'Телематика', 'Технологии искусственного интеллекта', 'Финансовая математика',
            'Цифровые агросистемы и комплексы',
        ];
        foreach ($ictSpecs as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $ictQual->id,
                'description' => $spec,
            ]);
        }

        /* Телекоммуникации */
        $telecomQual = Qualification::create([
            'qualification_name' => 'Телекоммуникации',
            'global_specialty_id' => $ictGlobal->id,
            'description' => 'Телекоммуникационные технологии'
        ]);
        $telecomSpecs = [
            'Telecommunication systems (Телекоммуникационные системы)',
            'Инфокоммуникационные технологии и системы связи', 'Мобильные технологии телекоммуникации',
            'Радиотехника, электроника и телекоммуникации', 'Системы радиовещания и телевидения',
            'Телекоммуникационная инженерия', 'Телекоммуникационные системы и сети ж.д. связи',
            'Электронная инженерия',
        ];
        foreach ($telecomSpecs as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $telecomQual->id,
                'description' => $spec,
            ]);
        }

        // ---- Конец новых специальностей ----

        // ---- Заключительные глобальные специальности ----

        /* Инженерные, обрабатывающие и строительные отрасли */
        $engineeringGlobal = GlobalSpecialty::create([
            'name' => 'Инженерные, обрабатывающие и строительные отрасли',
            'description' => 'Программы в области инженерии и строительства'
        ]);

        /* Архитектура и строительство */
        $architectureQual = Qualification::create([
            'qualification_name' => 'Архитектура и строительство',
            'global_specialty_id' => $engineeringGlobal->id,
            'description' => 'Подготовка архитекторов и строителей'
        ]);
        $archSpecs = [
            'BIM проектирование зданий и сооружений', 'Архитектура',
            'Архитектура жилых и общественных зданий', 'Водоснабжение и канализация',
            'Геодезия и картография', 'Геоинформатика',
        ];
        foreach ($archSpecs as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $architectureQual->id,
                'description' => $spec,
            ]);
        }

        /* Инженерия и инженерное дело */
        $engineeringQual = Qualification::create([
            'qualification_name' => 'Инженерия и инженерное дело',
            'global_specialty_id' => $engineeringGlobal->id,
            'description' => 'Инженерные программы'
        ]);
        $engSpecs = [
            'Processing of mineral and technologenic raw materials', 'Автоматизация и управление',
            'Автоматизация и управление бизнес-процессами', 'Автоматизированные электромеханические системы',
            'Автомобили и автомобильное хозяйство', 'Автомобильные дороги и аэродромы',
            'Автономные энергетические системы', 'Атомные электрические станции и установки',
            'Аэрокосмическая инженерия',
        ];
        foreach ($engSpecs as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $engineeringQual->id,
                'description' => $spec,
            ]);
        }

        /* Производственные и обрабатывающие отрасли */
        $manufacturingQual = Qualification::create([
            'qualification_name' => 'Производственные и обрабатывающие отрасли',
            'global_specialty_id' => $engineeringGlobal->id,
            'description' => 'Производственные программы'
        ]);
        $manufacturingSpecs = [
            'Petroleum engineering', 'Геология и разведка месторождений полезных ископаемых',
            'Геология и разведка природных ресурсов', 'Геология нефти и газа',
            'Геофизические технологии и инжиниринг', 'Горная инженерия', 'Горное дело',
        ];
        foreach ($manufacturingSpecs as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $manufacturingQual->id,
                'description' => $spec,
            ]);
        }

        /* Сельское хозяйство и биоресурсы */
        $agricultureGlobal = GlobalSpecialty::where('name', 'Сельское хозяйство и биоресурсы')->first();
        if (!$agricultureGlobal) {
            $agricultureGlobal = GlobalSpecialty::create([
                'name' => 'Сельское хозяйство и биоресурсы',
                'description' => 'Программы в области сельского хозяйства и биоресурсов'
            ]);
        }

        /* Агроинженерия */
        $agroEngQual = Qualification::create([
            'qualification_name' => 'Агроинженерия',
            'global_specialty_id' => $agricultureGlobal->id,
            'description' => 'Техническое обеспечение сельского хозяйства'
        ]);
        $agroEngSpecs = [
            'Аграрная техника и технология',
            'Гибридные системы электроснабжения агропромышленных объектов',
            'Цифровые технологии в агропромышленном комплексе',
            'Энергообеспечение сельского хозяйства',
        ];
        foreach ($agroEngSpecs as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $agroEngQual->id,
                'description' => $spec,
            ]);
        }

        /* Водные ресурсы и водопользование */
        $waterQual = Qualification::create([
            'qualification_name' => 'Водные ресурсы и водопользование',
            'global_specialty_id' => $agricultureGlobal->id,
            'description' => 'Управление водными ресурсами'
        ]);
        $waterSpecs = [
            'Водные ресурсы и водопользование', 'Мелиорация, рекультивация и охрана земель',
        ];
        foreach ($waterSpecs as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $waterQual->id,
                'description' => $spec,
            ]);
        }

        /* Животноводство */
        $livestockQual = Qualification::create([
            'qualification_name' => 'Животноводство',
            'global_specialty_id' => $agricultureGlobal->id,
            'description' => 'Разведение и содержание животных'
        ]);
        $livestockSpecs = [
            'Менеджер животноводства', 'Технология производства продуктов животноводства',
        ];
        foreach ($livestockSpecs as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $livestockQual->id,
                'description' => $spec,
            ]);
        }

        /* Землеустройство */
        $landQual = Qualification::create([
            'qualification_name' => 'Землеустройство',
            'global_specialty_id' => $agricultureGlobal->id,
            'description' => 'Организация использования земельных ресурсов'
        ]);
        Specialization::create([
            'name' => 'Механизация производства и переработки продукции сельского хозяйства',
            'qualification_id' => $landQual->id,
            'description' => 'Механизация производства и переработки продукции сельского хозяйства',
        ]);

        /* Лесное хозяйство */
        $forestryQual = Qualification::create([
            'qualification_name' => 'Лесное хозяйство',
            'global_specialty_id' => $agricultureGlobal->id,
            'description' => 'Ведение лесного хозяйства'
        ]);
        $forestrySpecs = [
            'Лесное дело с основами деревообработки', 'Лесные ресурсы и лесоводство',
            'Лесные ресурсы, охотоведение и пчеловодство', 'Охотоведение и звероводство',
        ];
        foreach ($forestrySpecs as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $forestryQual->id,
                'description' => $spec,
            ]);
        }

        /* Растениеводство */
        $cropQual = Qualification::create([
            'qualification_name' => 'Растениеводство',
            'global_specialty_id' => $agricultureGlobal->id,
            'description' => 'Выращивание сельскохозяйственных культур'
        ]);
        $cropSpecs = [
            'Агрономия', 'Биоинформатика', 'Защита и карантин растений',
            'Наука о растениях и технологии',
            'Охрана и диагностика растений в агропромышленном комплексе',
            'Плодоовощеводство', 'Плодоовощеводство и агропочвоведение',
            'Почвоведение и агрохимия', 'Технология производства продукции растениеводства',
        ];
        foreach ($cropSpecs as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $cropQual->id,
                'description' => $spec,
            ]);
        }

        /* Рыбное хозяйство */
        $fisheryQual = Qualification::create([
            'qualification_name' => 'Рыбное хозяйство',
            'global_specialty_id' => $agricultureGlobal->id,
            'description' => 'Рыболовство и аквакультура'
        ]);
        $fisherySpecs = [
            'Аквакультура и водные биоресурсы', 'Рыбное хозяйство и промышленное рыболовство',
        ];
        foreach ($fisherySpecs as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $fisheryQual->id,
                'description' => $spec,
            ]);
        }

        /* Ветеринария */
        $veterinaryGlobal = GlobalSpecialty::where('name', 'Ветеринария')->first();
        if (!$veterinaryGlobal) {
            $veterinaryGlobal = GlobalSpecialty::create([
                'name' => 'Ветеринария',
                'description' => 'Программы в области ветеринарной медицины'
            ]);
        }

        $vetQual = Qualification::create([
            'qualification_name' => 'Ветеринария',
            'global_specialty_id' => $veterinaryGlobal->id,
            'description' => 'Лечение и профилактика заболеваний животных'
        ]);
        $vetSpecs = [
            'Ветеринария', 'Ветеринарная медицина', 'Ветеринарная санитария',
            'Ветеринарно-пищевая безопасность и технологии',
        ];
        foreach ($vetSpecs as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $vetQual->id,
                'description' => $spec,
            ]);
        }

        /* Здравоохранение и социальное обеспечение (медицина) */
        $healthcareGlobal = GlobalSpecialty::where('name', 'Здравоохранение и социальное обеспечение (медицина)')->first();
        if (!$healthcareGlobal) {
            $healthcareGlobal = GlobalSpecialty::create([
                'name' => 'Здравоохранение и социальное обеспечение (медицина)',
                'description' => 'Программы в области медицины и здравоохранения'
            ]);
        }

        /* Здравоохранение */
        $healthQual = Qualification::create([
            'qualification_name' => 'Здравоохранение',
            'global_specialty_id' => $healthcareGlobal->id,
            'description' => 'Подготовка медицинских специалистов'
        ]);
        $healthSpecs = [
            'Кинезитерапия', 'Лабораторная медицина', 'Медико-профилактическое дело',
            'Медицина', 'Общая медицина', 'Общественное здоровье', 'Общественное здравоохранение',
            'Педиатрия', 'Сестринское дело', 'Сестринское дело в реабилиталогии',
            'Стоматология', 'Фармация', 'Эрготерапия',
        ];
        foreach ($healthSpecs as $spec) {
        Specialization::create([
                'name' => $spec,
                'qualification_id' => $healthQual->id,
                'description' => $spec,
            ]);
        }

        /* Социальное обеспечение */
        $socialCareQual = Qualification::create([
            'qualification_name' => 'Социальное обеспечение',
            'global_specialty_id' => $healthcareGlobal->id,
            'description' => 'Социальная работа и поддержка'
        ]);
        $socialCareSpecs = [
            'Социальная политика и предпринимательство', 'Социальная работа',
        ];
        foreach ($socialCareSpecs as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $socialCareQual->id,
                'description' => $spec,
            ]);
        }

        /* Услуги */
        $servicesGlobal = GlobalSpecialty::where('name', 'Услуги')->first();
        if (!$servicesGlobal) {
            $servicesGlobal = GlobalSpecialty::create([
                'name' => 'Услуги',
                'description' => 'Программы в области туризма, логистики и безопасности'
            ]);
        }

        /* Гигиена и охрана труда на производстве */
        $occupationalSafetyQual = Qualification::create([
            'qualification_name' => 'Гигиена и охрана труда на производстве',
            'global_specialty_id' => $servicesGlobal->id,
            'description' => 'Обеспечение безопасности на производстве'
        ]);
        $occupationalSafetySpecs = [
            'Безопасность жизнедеятельности и защита окружающей среды',
            'Инженерная экология и безопасность в энергетике',
            'Инновационное управление безопасностью ЧС природного и техногенного характера',
            'Промышленная безопасность', 'Промышленная, экологическая и пожарная безопасность',
            'Экоаналитика в отраслях',
        ];
        foreach ($occupationalSafetySpecs as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $occupationalSafetyQual->id,
                'description' => $spec,
            ]);
        }

        /* Национальная безопасность и военное дело */
        $securityGlobal = GlobalSpecialty::where('name', 'Национальная безопасность и военное дело')->first();
        if (!$securityGlobal) {
            $securityGlobal = GlobalSpecialty::create([
                'name' => 'Национальная безопасность и военное дело',
                'description' => 'Программы в области безопасности и военного дела'
            ]);
        }

        /* Военное дело */
        $militaryQual = Qualification::create([
            'qualification_name' => 'Военное дело',
            'global_specialty_id' => $securityGlobal->id,
            'description' => 'Военные программы'
        ]);
        Specialization::create([
            'name' => 'Командная тактическая сил гражданской обороны',
            'qualification_id' => $militaryQual->id,
            'description' => 'Командная тактическая сил гражданской обороны',
        ]);

        /* Общественная безопасность */
        $publicSafetyQual = Qualification::create([
            'qualification_name' => 'Общественная безопасность',
            'global_specialty_id' => $securityGlobal->id,
            'description' => 'Обеспечение безопасности общества'
        ]);
        $publicSafetySpecs = [
            'Защита в чрезвычайных ситуациях', 'Пожарная безопасность',
            'Правоохранительная деятельность', 'Следственно-криминалистическая деятельность',
        ];
        foreach ($publicSafetySpecs as $spec) {
            Specialization::create([
                'name' => $spec,
                'qualification_id' => $publicSafetyQual->id,
                'description' => $spec,
            ]);
        }

    }
}