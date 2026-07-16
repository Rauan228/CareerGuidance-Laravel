<?php

namespace App\Http\Controllers;

use App\Models\CareerTestResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\CareerOrientationService;


class CareerTestController extends Controller
{
    public function store(Request $request)
{
    $data = $request->validate([
        'institution_type' => 'required|in:college,university',
        'answers'          => 'required|array|min:1',
    ]);

    $result = CareerTestResult::create([
        'user_id'          => Auth::id(),
        'institution_type' => $data['institution_type'],
        'answers'          => $data['answers'],
    ]);

    // ИИ-анализ занимает минуты (общий медленный сервер), а artisan serve
    // однопоточный — поэтому анализ уходит в ОТДЕЛЬНЫЙ фоновый php-процесс,
    // а фронт поллит результат до появления summary.
    $this->runAnalysisInBackground($result->id);

    return response()->json($result->fresh(), 201);
}

    /**
     * Запускает `php artisan career-test:process {id}` фоном, не блокируя ответ.
     */
    private function runAnalysisInBackground(int $resultId): void
    {
        $php = escapeshellarg(PHP_BINARY);
        $artisan = escapeshellarg(base_path('artisan'));

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start /B \"career-test\" {$php} {$artisan} career-test:process {$resultId}", 'r'));
        } else {
            exec("{$php} {$artisan} career-test:process {$resultId} > /dev/null 2>&1 &");
        }
    }
    public function index(Request $request)
    {
        $results = CareerTestResult::where('user_id', Auth::id())
            ->latest()
            ->get();
        return response()->json($results);
    }

    public function show(CareerTestResult $careerTestResult)
    {
        // Возвращаем только свои результаты
        if ($careerTestResult->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        return response()->json($careerTestResult);
    }
} 