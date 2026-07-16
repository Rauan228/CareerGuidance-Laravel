<?php

namespace App\Console\Commands;

use App\Models\CareerTestResult;
use App\Services\CareerOrientationService;
use Illuminate\Console\Command;

class ProcessCareerTest extends Command
{
    protected $signature = 'career-test:process {id : ID результата теста}';

    protected $description = 'Прогоняет результат профтеста через ИИ-анализ (Ollama). Запускается фоном из CareerTestController.';

    public function handle(CareerOrientationService $service): int
    {
        $result = CareerTestResult::find($this->argument('id'));

        if (!$result) {
            $this->error('CareerTestResult не найден');
            return self::FAILURE;
        }

        $service->process($result);

        $this->info('Готово: summary '.($result->fresh()->summary ? 'сгенерирован' : 'НЕ сгенерирован (см. laravel.log)'));

        return self::SUCCESS;
    }
}
