<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CollegeGlobalSpecialty;
use App\Models\CollegeQualification;
use App\Models\CollegeSpecialization;

/**
 * @deprecated Не вызывать. Фейковое дерево ОП колледжей.
 * Чистка: data:purge-seed-specialties
 */
class CollegeSpecialtiesSeeder extends Seeder
{
    public function run()
    {
        // Очищаем таблицы (используем delete вместо truncate из-за внешних ключей)
        CollegeSpecialization::query()->delete();
        CollegeQualification::query()->delete();
        CollegeGlobalSpecialty::query()->delete();

        // Создаем все специальности из университетского сидера
        // но адаптированные для колледжа
        $this->createAllSpecialties();
    }

    private function createAllSpecialties()
    {
        // Педагогические науки
        $this->createEducationSpecialties();
        
        // Искусство и гуманитарные науки  
        $this->createArtsSpecialties();
        
        // Социальные науки
        $this->createSocialSpecialties();
        
        // Бизнес, управление и право
        $this->createBusinessSpecialties();
        
        // Естественные науки
        $this->createNaturalSciencesSpecialties();
        
        // IT
        $this->createITSpecialties();
        
        // Инженерные специальности
        $this->createEngineeringSpecialties();
        
        // Сельское хозяйство
        $this->createAgricultureSpecialties();
        
        // Ветеринария
        $this->createVeterinarySpecialties();
        
        // Здравоохранение
        $this->createHealthcareSpecialties();
        
        // Услуги
        $this->createServicesSpecialties();
        
        // Безопасность
        $this->createSecuritySpecialties();
    }

    private function createSpecialty($name, $qualId)
    {
        return CollegeSpecialization::create([
            'name' => $name,
            'college_qualification_id' => $qualId,
            'description' => $name
        ]);
    }

    private function createEducationSpecialties()
    {
        $education = CollegeGlobalSpecialty::create([
            'name' => 'Педагогические науки',
            'description' => 'Программы подготовки специалистов в области педагогики и образования'
        ]);

        // 1.1 Педагогика дошкольного воспитания и обучения
        $preschool = CollegeQualification::create([
            'qualification_name' => 'Педагогика дошкольного воспитания и обучения',
            'college_global_specialty_id' => $education->id,
            'description' => 'Подготовка специалистов для дошкольного образования'
        ]);

        $this->createSpecialty('Дошкольное образование', $preschool->id);
        $this->createSpecialty('Дошкольное обучение и воспитание', $preschool->id);

        // 1.2 Педагогика и психология
        $psychology = CollegeQualification::create([
            'qualification_name' => 'Педагогика и психология',
            'college_global_specialty_id' => $education->id,
            'description' => 'Подготовка специалистов в области педагогической психологии'
        ]);

        $this->createSpecialty('Педагогика и психология', $psychology->id);

        // 1.3 Социальная педагогика
        $socialPed = CollegeQualification::create([
            'qualification_name' => 'Подготовка специалистов по социальной педагогике и самопознанию',
            'college_global_specialty_id' => $education->id,
            'description' => 'Подготовка специалистов в области социальной педагогики'
        ]);

        $socialPedSpecs = [
            'Социальная педагогика и самопознание',
            'Социальная и ювенальная педагогика'
        ];
        foreach ($socialPedSpecs as $spec) {
            $this->createSpecialty($spec, $socialPed->id);
        }

        // 1.4 Специальная педагогика
        $specialPed = CollegeQualification::create([
            'qualification_name' => 'Подготовка специалистов по специальной педагогике',
            'college_global_specialty_id' => $education->id,
            'description' => 'Подготовка педагогов для работы с детьми с особыми потребностями'
        ]);

        $specialSpecs = ['Дефектология', 'Логопедия'];
        foreach ($specialSpecs as $spec) {
            $this->createSpecialty($spec, $specialPed->id);
        }

        // 1.5 Учителя без предметной специализации
        $general = CollegeQualification::create([
            'qualification_name' => 'Подготовка учителей без предметной специализации',
            'college_global_specialty_id' => $education->id,
            'description' => 'Подготовка учителей начальных классов'
        ]);

        $this->createSpecialty('Начальное образование', $general->id);

        // Продолжаем с остальными квалификациями педагогических наук...
        $this->createMoreEducationQualifications($education->id);
    }

    private function createMoreEducationQualifications($educationId)
    {
        // Музыка
        $music = CollegeQualification::create([
            'qualification_name' => 'Подготовка учителей музыки',
            'college_global_specialty_id' => $educationId,
            'description' => 'Подготовка учителей музыкального образования'
        ]);
        $this->createSpecialty('Музыкальное образование', $music->id);

        // Гуманитарные предметы
        $humanities = CollegeQualification::create([
            'qualification_name' => 'Подготовка учителей по гуманитарным предметам',
            'college_global_specialty_id' => $educationId,
            'description' => 'Подготовка учителей гуманитарных дисциплин'
        ]);
        $this->createSpecialty('История', $humanities->id);

        // Естественные науки
        $sciences = CollegeQualification::create([
            'qualification_name' => 'Подготовка учителей по естественнонаучным предметам',
            'college_global_specialty_id' => $educationId,
            'description' => 'Подготовка учителей естественных наук'
        ]);
        $scienceSpecs = ['Биология', 'Физика'];
        foreach ($scienceSpecs as $spec) {
            $this->createSpecialty($spec, $sciences->id);
        }

        // Языки и литература
        $languages = CollegeQualification::create([
            'qualification_name' => 'Подготовка учителей по языкам и литературе',
            'college_global_specialty_id' => $educationId,
            'description' => 'Подготовка учителей языков и литературы'
        ]);
        $langSpecs = ['Казахский язык и литература', 'Русский язык и литература'];
        foreach ($langSpecs as $spec) {
            $this->createSpecialty($spec, $languages->id);
        }

        // Математика
        $math = CollegeQualification::create([
            'qualification_name' => 'Подготовка учителей математики',
            'college_global_specialty_id' => $educationId,
            'description' => 'Подготовка учителей математики'
        ]);
        $this->createSpecialty('Математика', $math->id);

        // Физическая культура
        $pe = CollegeQualification::create([
            'qualification_name' => 'Подготовка учителей физической культуры',
            'college_global_specialty_id' => $educationId,
            'description' => 'Подготовка учителей физического воспитания'
        ]);
        $peSpecs = ['Физическая культура и спорт', 'Спорт'];
        foreach ($peSpecs as $spec) {
            $this->createSpecialty($spec, $pe->id);
        }

        // Художественный труд
        $artWork = CollegeQualification::create([
            'qualification_name' => 'Подготовка учителей художественного труда',
            'college_global_specialty_id' => $educationId,
            'description' => 'Подготовка учителей художественного труда'
        ]);
        $this->createSpecialty('Художественный труд', $artWork->id);

        // Информатика
        $informatics = CollegeQualification::create([
            'qualification_name' => 'Подготовка учителей информатики',
            'college_global_specialty_id' => $educationId,
            'description' => 'Подготовка учителей информатики'
        ]);
        $this->createSpecialty('Информатика', $informatics->id);

        // Изобразительное искусство
        $art = CollegeQualification::create([
            'qualification_name' => 'Подготовка учителей изобразительного искусства и черчения',
            'college_global_specialty_id' => $educationId,
            'description' => 'Подготовка учителей изобразительного искусства'
        ]);
        $this->createSpecialty('Изобразительное искусство и черчение', $art->id);

        // Иностранные языки
        $foreignLang = CollegeQualification::create([
            'qualification_name' => 'Подготовка учителей иностранного языка',
            'college_global_specialty_id' => $educationId,
            'description' => 'Подготовка учителей иностранных языков'
        ]);
        $foreignSpecs = [
            'Иностранный язык: два иностранных языка', 'Английский язык',
            'Французский язык', 'Немецкий язык', 'Арабский язык',
            'Китайский язык', 'Турецкий язык', 'Корейский язык',
            'Персидский язык', 'Японский язык'
        ];
        foreach ($foreignSpecs as $spec) {
            $this->createSpecialty($spec, $foreignLang->id);
        }

        // Профессиональное обучение
        $prof = CollegeQualification::create([
            'qualification_name' => 'Подготовка учителей технических и профессиональных предметов/дисциплин',
            'college_global_specialty_id' => $educationId,
            'description' => 'Подготовка учителей технических дисциплин'
        ]);
        $this->createSpecialty('Профессиональное обучение', $prof->id);
    }

    private function createArtsSpecialties()
    {
        $arts = CollegeGlobalSpecialty::create([
            'name' => 'Искусство и гуманитарные науки',
            'description' => 'Программы в области искусства и гуманитарных наук'
        ]);

        // Аудиовизуальные средства
        $media = CollegeQualification::create([
            'qualification_name' => 'Аудиовизуальные средства и медиапроизводство',
            'college_global_specialty_id' => $arts->id,
            'description' => 'Создание медиаконтента и работа с аудиовизуальными средствами'
        ]);

        $mediaSpecs = [
            'Операторское искусство', 'Режиссура игрового кино',
            'Режиссура неигрового кино и телефильмов', 'Режиссура театра и кино',
            'Режиссура эстрады и массовых представлений', 'Продюсерство в кино и телефильмах',
            'Звукорежиссура', 'Оператор-постановщик', 'Фотожурналистика',
            'Художник кино и телевидения', 'Монтаж аудиовизуальных произведений',
            'Художественная обработка кино', 'Анимация', 'Кинематография',
            'Кинооператорство', 'Цифровое кино и медиатехнологии',
            'Музыкальная звукорежиссура', 'Теле- и радиопрограммы',
            'Медиапроизводство', 'Музыкальная звукорежиссура продюсерского искусства'
        ];
        foreach ($mediaSpecs as $spec) {
            $this->createSpecialty($spec, $media->id);
        }

        // Продолжение создания остальных специальностей искусства...
        $this->createMoreArtsSpecialties($arts->id);
    }

    private function createMoreArtsSpecialties($artsId)
    {
        // Гуманитарные науки
        $humanities = CollegeQualification::create([
            'qualification_name' => 'Гуманитарные науки',
            'college_global_specialty_id' => $artsId,
            'description' => 'Изучение истории, философии и других гуманитарных дисциплин'
        ]);

        $humanitiesSpecs = [
            'История', 'Философия', 'Теология', 'Религиоведение',
            'Востоковедение', 'Археология и этнография', 'Археология', 'Этнография'
        ];
        foreach ($humanitiesSpecs as $spec) {
            $this->createSpecialty($spec, $humanities->id);
        }

        // Изобразительное искусство
        $visual = CollegeQualification::create([
            'qualification_name' => 'Изобразительное и прикладное искусство',
            'college_global_specialty_id' => $artsId,
            'description' => 'Изобразительное и декоративно-прикладное искусство'
        ]);

        $visualSpecs = [
            'Изобразительное искусство и черчение', 'Живопись',
            'Скульптура', 'Графика', 'Декоративно-прикладное искусство'
        ];
        foreach ($visualSpecs as $spec) {
            $this->createSpecialty($spec, $visual->id);
        }

        // Исполнительское искусство
        $performing = CollegeQualification::create([
            'qualification_name' => 'Исполнительское искусство',
            'college_global_specialty_id' => $artsId,
            'description' => 'Музыкальное и театральное исполнительство'
        ]);

        $performingSpecs = [
            'Вокальное искусство', 'Инструментальное исполнительство',
            'Музыковедение', 'Композиция', 'Дирижирование',
            'Традиционная музыка', 'Эстрадное пение', 'Хоровое дирижирование',
            'Оперное пение', 'Камерное пение', 'Оркестровые духовые и ударные инструменты',
            'Оркестровые струнные инструменты', 'Фортепиано',
            'Баян, аккордеон и гармонь', 'Казахские народные инструменты',
            'Эстрадно-джазовые инструменты'
        ];
        foreach ($performingSpecs as $spec) {
            $this->createSpecialty($spec, $performing->id);
        }

        // Языки и литература
        $languages = CollegeQualification::create([
            'qualification_name' => 'Языки и литература',
            'college_global_specialty_id' => $artsId,
            'description' => 'Изучение языков и литературы'
        ]);

        $langSpecs = [
            'Филология', 'Казахский язык и литература', 'Русский язык и литература',
            'Лингвистика', 'Иностранная филология', 'Переводческое дело',
            'Переводческое дело (восточные языки)', 'Синхронный перевод'
        ];
        foreach ($langSpecs as $spec) {
            $this->createSpecialty($spec, $languages->id);
        }
    }

    private function createSocialSpecialties()
    {
        $social = CollegeGlobalSpecialty::create([
            'name' => 'Социальные науки, журналистика и информация',
            'description' => 'Программы в области социальных наук и информации'
        ]);

        // Библиотечное дело
        $library = CollegeQualification::create([
            'qualification_name' => 'Библиотечное дело, обработка информации и архивное дело',
            'college_global_specialty_id' => $social->id,
            'description' => 'Работа с информацией и документооборотом'
        ]);
        $this->createSpecialty('Документоведение и архивоведение', $library->id);
        $this->createSpecialty('Библиотечное дело', $library->id);

        // Журналистика
        $journalism = CollegeQualification::create([
            'qualification_name' => 'Журналистика и информация',
            'college_global_specialty_id' => $social->id,
            'description' => 'Журналистика и массовые коммуникации'
        ]);
        $journalismSpecs = [
            'Журналистика и массовые коммуникации', 'Телерадиожурналистика',
            'Печатная журналистика', 'Международная журналистика',
            'Деловая журналистика', 'Социальная журналистика', 'Спортивная журналистика'
        ];
        foreach ($journalismSpecs as $spec) {
            $this->createSpecialty($spec, $journalism->id);
        }

        // Политология
        $politics = CollegeQualification::create([
            'qualification_name' => 'Политические науки и гражданственность',
            'college_global_specialty_id' => $social->id,
            'description' => 'Политология и международные отношения'
        ]);
        $politicsSpecs = ['Политология', 'Международные отношения', 'Регионоведение', 'Дипломатия'];
        foreach ($politicsSpecs as $spec) {
            $this->createSpecialty($spec, $politics->id);
        }

        // Психология
        $psych = CollegeQualification::create([
            'qualification_name' => 'Психология',
            'college_global_specialty_id' => $social->id,
            'description' => 'Практическая психология'
        ]);
        $psychSpecs = ['Психология', 'Практическая психология'];
        foreach ($psychSpecs as $spec) {
            $this->createSpecialty($spec, $psych->id);
        }

        // Социальные науки
        $socSci = CollegeQualification::create([
            'qualification_name' => 'Социальные науки',
            'college_global_specialty_id' => $social->id,
            'description' => 'Социология и социальная работа'
        ]);
        $socSpecs = [
            'Социология', 'Социальная работа', 'Религиоведение',
            'Культурология', 'Гендерные исследования'
        ];
        foreach ($socSpecs as $spec) {
            $this->createSpecialty($spec, $socSci->id);
        }
    }
    private function createBusinessSpecialties()
    {
        $business = CollegeGlobalSpecialty::create([
            'name' => 'Бизнес, управление и право',
            'description' => 'Программы в области бизнеса, управления и права'
        ]);

        // Администрирование
        $admin = CollegeQualification::create([
            'qualification_name' => 'Администрирование',
            'college_global_specialty_id' => $business->id,
            'description' => 'Государственное и корпоративное управление'
        ]);
        $adminSpecs = [
            'Государственное и местное управление', 'Менеджмент',
            'Управление человеческими ресурсами', 'Логистика', 'Управление проектами'
        ];
        foreach ($adminSpecs as $spec) {
            $this->createSpecialty($spec, $admin->id);
        }

        // Бизнес и управление
        $mgmt = CollegeQualification::create([
            'qualification_name' => 'Бизнес и управление',
            'college_global_specialty_id' => $business->id,
            'description' => 'Бизнес-процессы и управление организацией'
        ]);
        $mgmtSpecs = [
            'Экономика', 'Управление', 'Маркетинг', 'Международный бизнес',
            'Бизнес-администрирование', 'Управление в АПК', 'Управление качеством',
            'Управление природными ресурсами', 'Менеджмент организации',
            'Антикризисное управление', 'Производственный менеджмент',
            'Управление персоналом', 'Стратегический менеджмент',
            'Менеджмент в сфере услуг', 'Менеджмент в строительстве',
            'Менеджмент в сфере образования', 'Менеджмент в здравоохранении',
            'Менеджмент в сфере культуры', 'Туристский и гостиничный менеджмент',
            'Инновационный менеджмент', 'Управленческий консалтинг',
            'Управление цепями поставок', 'Управление закупками',
            'Управление недвижимостью', 'Управление транспортными системами',
            'Спортивный менеджмент', 'Event менеджмент',
            'Управление информационными системами'
        ];
        foreach ($mgmtSpecs as $spec) {
            $this->createSpecialty($spec, $mgmt->id);
        }

        // Финансы
        $finance = CollegeQualification::create([
            'qualification_name' => 'Финансы, банковское и страховое дело',
            'college_global_specialty_id' => $business->id,
            'description' => 'Финансовые услуги и учет'
        ]);
        $financeSpecs = [
            'Финансы', 'Банковское дело', 'Страхование', 'Учет и аудит',
            'Налоги и налогообложение', 'Казначейское дело',
            'Международные финансы', 'Корпоративные финансы',
            'Инвестиционное дело', 'Бюджетное планирование и прогнозирование',
            'Оценка недвижимости', 'Оценка бизнеса', 'Актуарное дело',
            'Финансовая аналитика'
        ];
        foreach ($financeSpecs as $spec) {
            $this->createSpecialty($spec, $finance->id);
        }

        // Право
        $law = CollegeQualification::create([
            'qualification_name' => 'Право',
            'college_global_specialty_id' => $business->id,
            'description' => 'Юридические услуги и правоохранительная деятельность'
        ]);
        $lawSpecs = [
            'Юриспруденция', 'Правоохранительная деятельность',
            'Юриспруденция (2 года)', 'Юриспруденция (3 года)',
            'Международное право', 'Гражданское право', 'Уголовное право',
            'Административное право', 'Конституционное право',
            'Трудовое право', 'Семейное право', 'Экологическое право',
            'Предпринимательское право', 'Банковское право',
            'Налоговое право', 'Земельное право', 'Авторское право',
            'Государственное управление и право', 'Право и экономика',
            'Юридическая психология', 'Криминология', 'Криминалистика',
            'Прокурорская деятельность', 'Адвокатская деятельность',
            'Нотариальная деятельность', 'Судебная деятельность',
            'Пенитенциарная деятельность', 'Антикоррупционная деятельность',
            'Правовая экспертиза', 'Юридическое консультирование',
            'Медиация', 'Арбитраж', 'Таможенное дело'
        ];
        foreach ($lawSpecs as $spec) {
            $this->createSpecialty($spec, $law->id);
        }
    }  
    private function createNaturalSciencesSpecialties()
    {
        $sciences = CollegeGlobalSpecialty::create([
            'name' => 'Естественные науки, математика и статистика',
            'description' => 'Программы в области естественных наук и математики'
        ]);

        // Биологические науки
        $bio = CollegeQualification::create([
            'qualification_name' => 'Биологические и смежные науки',
            'college_global_specialty_id' => $sciences->id,
            'description' => 'Биология и биотехнология'
        ]);
        $bioSpecs = [
            'Биоинженерия и биоинформатика', 'Биологическая инженерия',
            'Биология', 'Биомедицина', 'Биотехнология', 'Биофизика',
            'Генетика', 'Геоботаника', 'Ландшафтный дизайн', 'Микробиология'
        ];
        foreach ($bioSpecs as $spec) {
            $this->createSpecialty($spec, $bio->id);
        }

        // Математика и статистика
        $math = CollegeQualification::create([
            'qualification_name' => 'Математика и статистика',
            'college_global_specialty_id' => $sciences->id,
            'description' => 'Математические науки и анализ данных'
        ]);
        $mathSpecs = [
            'Applied mathematics in digital economy', 'Statistics and data science',
            'Актуарная математика', 'Математика',
            'Математическая экономика и анализ данных', 'Механика', 'Статистика'
        ];
        foreach ($mathSpecs as $spec) {
            $this->createSpecialty($spec, $math->id);
        }

        // Окружающая среда
        $env = CollegeQualification::create([
            'qualification_name' => 'Окружающая среда',
            'college_global_specialty_id' => $sciences->id,
            'description' => 'Экология и природопользование'
        ]);
        $envSpecs = [
            'География', 'География и природопользование', 'Гидрология',
            'Ландшафтное проектирование', 'Метеорология',
            'Окружающая среда и устойчивое развитие',
            'Охрана окружающей среды и рациональное использование природных ресурсов',
            'Рекреационная география и туризм', 'Экология',
            'Экология и природопользование', 'Экотехнология и устойчивое развитие'
        ];
        foreach ($envSpecs as $spec) {
            $this->createSpecialty($spec, $env->id);
        }

        // Физические и химические науки
        $phys = CollegeQualification::create([
            'qualification_name' => 'Физические и химические науки',
            'college_global_specialty_id' => $sciences->id,
            'description' => 'Физика, химия и материаловедение'
        ]);
        $physSpecs = [
            'Астрономия и методы дистанционных исследований',
            'Инженерная физика и материаловедение',
            'Инженерная физика и технологии новых материалов',
            'Компьютерная физика', 'Техническая физика', 'Физика',
            'Физика и астрономия', 'Химическая криминалистическая экспертиза',
            'Химическая технология органических веществ',
            'Химическая экспертиза и аналитический контроль производства',
            'Химическая, криминалистическая и экологическая экспертиза',
            'Химия', 'Химия и наноматериалы',
            'Экспертиза веществ и материалов в химической инженерии',
            'Ядерная физика и атомная энергетика'
        ];
        foreach ($physSpecs as $spec) {
            $this->createSpecialty($spec, $phys->id);
        }
    }
    private function createITSpecialties()
    {
        $it = CollegeGlobalSpecialty::create([
            'name' => 'Информационно-коммуникационные технологии',
            'description' => 'Программы в области информационных технологий'
        ]);

        // Информационная безопасность
        $security = CollegeQualification::create([
            'qualification_name' => 'Информационная безопасность',
            'college_global_specialty_id' => $it->id,
            'description' => 'Защита информации и кибербезопасность'
        ]);
        $secSpecs = [
            'Cybersecurity (Кибербезопасность)',
            'Аппаратные средства защиты информации',
            'Информационная безопасность финансовых структур',
            'Информационные технологии и защита данных',
            'Компьютерная безопасность', 'Криптографическая защита информации',
            'Сетевая безопасность', 'Системы информационной безопасности',
            'Техническая защита информации'
        ];
        foreach ($secSpecs as $spec) {
            $this->createSpecialty($spec, $security->id);
        }

        // ИКТ
        $ict = CollegeQualification::create([
            'qualification_name' => 'Информационно-коммуникационные технологии',
            'college_global_specialty_id' => $it->id,
            'description' => 'Разработка ПО и IT-системы'
        ]);
        $ictSpecs = [
            'BBA in IT in Business', 'Big Data Analysis (Анализ больших данных)',
            'Business analytics and Big Data', 'Data Science', 'Digital Engineering',
            'Digital Engineering (3 года)', 'Digital management and design',
            'Industrial Automation (Индустриальная автоматизация)',
            'IT в здравоохранении', 'IT-аналитика', 'IT-медицина',
            'IT-менеджмент', 'Media Technologies (Медиа технологии)',
            'Mobile computing', 'SMART технологии', 'Software Engineering',
            'Автоматизация и робототехника', 'Аналитика Big Data',
            'Архитектор программного обеспечения', 'Биокомпьютинг',
            'Вычислительная техника и программное обеспечение',
            'Вычислительная техника и программное обеспечение /Smart computing',
            'Иммерсивные технологии', 'Инженерная математика',
            'Интеллектуальная робототехника', 'Информатика',
            'Информационно-программные системы', 'Информационные системы',
            'Информационные системы и технологии', 'Информационные технологии',
            'Информационные технологии в бизнесе',
            'Информационный инжиниринг в экономике',
            'Искусственный интеллект', 'Искусственный интеллект и анализ данных',
            'ІТ и программирование', 'Киберфизические системы',
            'Компьютерная инженерия', 'Компьютерная инженерия (KZ + UK)',
            'Компьютерная мехатроника',
            'Компьютерные науки (Computer Science)',
            'Компьютерные науки и программная инженерия',
            'Корпоративные информационные системы',
            'Математическое и компьютерное моделирование',
            'Моделирование и конструирование виртуальной реальности',
            'Программная инженерия', 'Телематика',
            'Технологии искусственного интеллекта', 'Финансовая математика',
            'Цифровые агросистемы и комплексы'
        ];
        foreach ($ictSpecs as $spec) {
            $this->createSpecialty($spec, $ict->id);
        }

        // Телекоммуникации
        $telecom = CollegeQualification::create([
            'qualification_name' => 'Телекоммуникации',
            'college_global_specialty_id' => $it->id,
            'description' => 'Телекоммуникационные системы и связь'
        ]);
        $telecomSpecs = [
            'Telecommunication systems (Телекоммуникационные системы)',
            'Инфокоммуникационные технологии и системы связи',
            'Мобильные технологии телекоммуникации',
            'Радиотехника, электроника и телекоммуникации',
            'Системы радиовещания и телевидения',
            'Телекоммуникационная инженерия',
            'Телекоммуникационные системы и сети ж.д. связи',
            'Электронная инженерия'
        ];
        foreach ($telecomSpecs as $spec) {
            $this->createSpecialty($spec, $telecom->id);
        }
    }
    private function createEngineeringSpecialties()
    {
        $eng = CollegeGlobalSpecialty::create(['name' => 'Инженерные, обрабатывающие и строительные отрасли', 'description' => 'Инженерные и строительные специальности']);
        
        $arch = CollegeQualification::create(['qualification_name' => 'Архитектура и строительство', 'college_global_specialty_id' => $eng->id, 'description' => 'Архитектура и строительство']);
        foreach(['BIM проектирование зданий и сооружений', 'Архитектура', 'Архитектура жилых и общественных зданий', 'Водоснабжение и канализация', 'Геодезия и картография', 'Геоинформатика'] as $s) $this->createSpecialty($s, $arch->id);
        
        $engr = CollegeQualification::create(['qualification_name' => 'Инженерия и инженерное дело', 'college_global_specialty_id' => $eng->id, 'description' => 'Инженерные специальности']);
        foreach(['Processing of mineral and technologenic raw materials', 'Автоматизация и управление', 'Автоматизация и управление бизнес-процессами', 'Автоматизированные электромеханические системы', 'Автомобили и автомобильное хозяйство', 'Автомобильные дороги и аэродромы', 'Автономные энергетические системы', 'Атомные электрические станции и установки', 'Аэрокосмическая инженерия'] as $s) $this->createSpecialty($s, $engr->id);
        
        $prod = CollegeQualification::create(['qualification_name' => 'Производственные и обрабатывающие отрасли', 'college_global_specialty_id' => $eng->id, 'description' => 'Производство и обработка']);
        foreach(['Petroleum engineering', 'Геология и разведка месторождений полезных ископаемых', 'Геология и разведка природных ресурсов', 'Геология нефти и газа', 'Геофизические технологии и инжиниринг', 'Горная инженерия', 'Горное дело'] as $s) $this->createSpecialty($s, $prod->id);
    }

    private function createAgricultureSpecialties()
    {
        $agr = CollegeGlobalSpecialty::create(['name' => 'Сельское хозяйство и биоресурсы', 'description' => 'Сельское хозяйство и природные ресурсы']);
        
        $ageng = CollegeQualification::create(['qualification_name' => 'Агроинженерия', 'college_global_specialty_id' => $agr->id, 'description' => 'Сельскохозяйственная техника']);
        foreach(['Аграрная техника и технология', 'Гибридные системы электроснабжения агропромышленных объектов', 'Цифровые технологии в агропромышленном комплексе', 'Энергообеспечение сельского хозяйства'] as $s) $this->createSpecialty($s, $ageng->id);
        
        $water = CollegeQualification::create(['qualification_name' => 'Водные ресурсы и водопользование', 'college_global_specialty_id' => $agr->id, 'description' => 'Водные ресурсы']);
        foreach(['Водные ресурсы и водопользование', 'Мелиорация, рекультивация и охрана земель'] as $s) $this->createSpecialty($s, $water->id);
        
        $animal = CollegeQualification::create(['qualification_name' => 'Животноводство', 'college_global_specialty_id' => $agr->id, 'description' => 'Животноводство']);
        foreach(['Менеджер животноводства', 'Технология производства продуктов животноводства'] as $s) $this->createSpecialty($s, $animal->id);
        
        $land = CollegeQualification::create(['qualification_name' => 'Землеустройство', 'college_global_specialty_id' => $agr->id, 'description' => 'Землеустройство']);
        $this->createSpecialty('Механизация производства и переработки продукции сельского хозяйства', $land->id);
        
        $forest = CollegeQualification::create(['qualification_name' => 'Лесное хозяйство', 'college_global_specialty_id' => $agr->id, 'description' => 'Лесное хозяйство']);
        foreach(['Лесное дело с основами деревообработки', 'Лесные ресурсы и лесоводство', 'Лесные ресурсы, охотоведение и пчеловодство', 'Охотоведение и звероводство'] as $s) $this->createSpecialty($s, $forest->id);
        
        $plant = CollegeQualification::create(['qualification_name' => 'Растениеводство', 'college_global_specialty_id' => $agr->id, 'description' => 'Растениеводство']);
        foreach(['Агрономия', 'Биоинформатика', 'Защита и карантин растений', 'Наука о растениях и технологии', 'Охрана и диагностика растений в агропромышленном комплексе', 'Плодоовощеводство', 'Плодоовощеводство и агропочвоведение', 'Почвоведение и агрохимия', 'Технология производства продукции растениеводства'] as $s) $this->createSpecialty($s, $plant->id);
        
        $fish = CollegeQualification::create(['qualification_name' => 'Рыбное хозяйство', 'college_global_specialty_id' => $agr->id, 'description' => 'Рыбное хозяйство']);
        foreach(['Аквакультура и водные биоресурсы', 'Рыбное хозяйство и промышленное рыболовство'] as $s) $this->createSpecialty($s, $fish->id);
    }

    private function createVeterinarySpecialties()
    {
        $vet = CollegeGlobalSpecialty::create(['name' => 'Ветеринария', 'description' => 'Ветеринарные специальности']);
        $vetQ = CollegeQualification::create(['qualification_name' => 'Ветеринария', 'college_global_specialty_id' => $vet->id, 'description' => 'Ветеринария']);
        foreach(['Ветеринария', 'Ветеринарная медицина', 'Ветеринарная санитария', 'Ветеринарно-пищевая безопасность и технологии'] as $s) $this->createSpecialty($s, $vetQ->id);
    }

    private function createHealthcareSpecialties()
    {
        $health = CollegeGlobalSpecialty::create(['name' => 'Здравоохранение и социальное обеспечение (медицина)', 'description' => 'Медицинские специальности']);
        
        $med = CollegeQualification::create(['qualification_name' => 'Здравоохранение', 'college_global_specialty_id' => $health->id, 'description' => 'Медицинские специальности']);
        foreach(['Кинезитерапия', 'Лабораторная медицина', 'Медико-профилактическое дело', 'Медицина', 'Общая медицина', 'Общественное здоровье', 'Общественное здравоохранение', 'Педиатрия', 'Сестринское дело', 'Сестринское дело в реабилиталогии', 'Стоматология', 'Фармация', 'Эрготерапия'] as $s) $this->createSpecialty($s, $med->id);
        
        $social = CollegeQualification::create(['qualification_name' => 'Социальное обеспечение', 'college_global_specialty_id' => $health->id, 'description' => 'Социальная работа']);
        foreach(['Социальная политика и предпринимательство', 'Социальная работа'] as $s) $this->createSpecialty($s, $social->id);
    }

    private function createServicesSpecialties()
    {
        $services = CollegeGlobalSpecialty::create(['name' => 'Услуги', 'description' => 'Сфера услуг']);
        $safety = CollegeQualification::create(['qualification_name' => 'Гигиена и охрана труда на производстве', 'college_global_specialty_id' => $services->id, 'description' => 'Безопасность и охрана труда']);
        foreach(['Безопасность жизнедеятельности и защита окружающей среды', 'Инженерная экология и безопасность в энергетике', 'Инновационное управление безопасностью ЧС природного и техногенного характера', 'Промышленная безопасность', 'Промышленная, экологическая и пожарная безопасность', 'Экоаналитика в отраслях'] as $s) $this->createSpecialty($s, $safety->id);
    }

    private function createSecuritySpecialties()
    {
        $security = CollegeGlobalSpecialty::create(['name' => 'Национальная безопасность и военное дело', 'description' => 'Безопасность и военное дело']);
        
        $military = CollegeQualification::create(['qualification_name' => 'Военное дело', 'college_global_specialty_id' => $security->id, 'description' => 'Военные специальности']);
        $this->createSpecialty('Командная тактическая сил гражданской обороны', $military->id);
        
        $public = CollegeQualification::create(['qualification_name' => 'Общественная безопасность', 'college_global_specialty_id' => $security->id, 'description' => 'Общественная безопасность']);
        foreach(['Защита в чрезвычайных ситуациях', 'Пожарная безопасность', 'Правоохранительная деятельность', 'Следственно-криминалистическая деятельность'] as $s) $this->createSpecialty($s, $public->id);
    }
} 