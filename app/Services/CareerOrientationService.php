<?php

namespace App\Services;

use App\Models\CareerTestResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CareerOrientationService
{
    /**
     * Генерация summary + рекомендаций для CareerTestResult через Ollama на VPS.
     *
     * Важно: сервер общий и медленный (CPU, высокая фоновая нагрузка), поэтому:
     *  - промпт максимально компактный (без полного списка из 500+ специальностей);
     *  - ответ читаем стримингом (иначе прокси оборвёт долгую «тихую» генерацию);
     *  - keep_alive: 0 — сразу освобождаем память под модели других сервисов.
     */
    public function process(CareerTestResult $result): void
    {
        set_time_limit(0);

        try {
            $content = $this->askOllama($result);
            $parsed = $this->normalizeParsed($this->parseAiOutput($content));

            $ids = $this->mapNamesToIds($parsed['specialties'] ?? [], $result->institution_type);

            $result->update([
                'summary'     => $parsed['summary'] ?? null,
                'suggestions' => empty($ids) ? ($parsed['specialties'] ?? []) : $ids,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Ollama error: '.$e->getMessage());
            // Результат остаётся без AI-данных; фронт покажет «не удалось» после таймаута поллинга
        }
    }

    /* --------------------------------------------------------------------- */

    private function askOllama(CareerTestResult $result): string
    {
        $cfg = config('services.ollama');

        $payload = [
            'model' => $cfg['model'],
            'stream' => true,
            // format json — Ollama сама гарантирует валидный JSON на выходе
            'format' => 'json',
            'keep_alive' => 0,
            'options' => [
                'temperature' => 0.7,
                'num_predict' => 550,
            ],
            'messages' => [
                ['role' => 'system', 'content' => 'Ты — опытный профориентолог и карьерный консультант. Отвечай по-русски, на «вы», строго валидным JSON.'],
                ['role' => 'user', 'content' => $this->buildPrompt($result)],
            ],
        ];

        // proxy '' — в обход системного HTTP_PROXY (корпоративный Squid не знает наш VPS)
        $response = Http::withBasicAuth($cfg['user'], $cfg['password'])
            ->withOptions(['stream' => true, 'proxy' => ''])
            ->timeout($cfg['timeout'])
            ->connectTimeout(30)
            ->post(rtrim($cfg['url'], '/').'/api/chat', $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Ollama HTTP '.$response->status().': '.substr($response->body(), 0, 300));
        }

        // NDJSON-стрим: копим content из каждого чанка
        $body = $response->toPsrResponse()->getBody();
        $content = '';
        $buffer = '';
        while (!$body->eof()) {
            $buffer .= $body->read(8192);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if ($line === '') {
                    continue;
                }
                $chunk = json_decode($line, true);
                if (isset($chunk['message']['content'])) {
                    $content .= $chunk['message']['content'];
                }
                if (!empty($chunk['error'])) {
                    throw new \RuntimeException('Ollama: '.$chunk['error']);
                }
            }
        }

        return $content;
    }

    private function buildPrompt(CareerTestResult $result): string
    {
        $target = $result->institution_type === 'college' ? 'колледж' : 'университет';

        // Даём модели только компактный список направлений (~12 шт.),
        // конкретные специальности она называет свободно — их маппим по базе через ILIKE.
        $dirTable = $result->institution_type === 'college' ? 'college_global_specialties' : 'global_specialties';
        $directions = implode('; ', DB::table($dirTable)->pluck('name')->toArray());

        return "Ответы теста (пользователь планирует в {$target}):\n".
            $this->compactAnswers($result->answers ?? [])."\n".
            "Направления в базе: {$directions}.\n".
            "Ответь СТРОГО таким JSON, ровно с двумя ключами summary и specialties, без вложенных объектов:\n".
            "{\"summary\":\"текст разбора одной строкой\",\"specialties\":[\"специальность 1\",\"специальность 2\",\"специальность 3\",\"специальность 4\",\"специальность 5\"]}\n".
            "В summary — 4 абзаца по 3–4 предложения (интересы из ответов; сильные стороны; подходящие направления и почему; следующие шаги), между абзацами ставь \\n\\n. ".
            "В specialties — 5 коротких конкретных названий специальностей на русском.";
    }

    /**
     * Компактное представление ответов. Полный тест — 80 вопросов; вместе с текстами
     * вопросов это ~2.5k токенов, что на медленном CPU-сервере означает десятки минут
     * только на чтение промпта. Варианты ответов самоописательны, поэтому для больших
     * тестов отправляем только ответы, сгруппированные по этапам.
     */
    private function compactAnswers(array $answers): string
    {
        $extract = function ($a) {
            if (is_array($a)) {
                return [trim((string) ($a['question'] ?? '')), trim((string) ($a['answer'] ?? ''))];
            }
            return ['', trim((string) $a)];
        };

        // Небольшой тест — можно позволить полные пары «вопрос — ответ»
        if (count($answers) <= 25) {
            $lines = [];
            foreach (array_values($answers) as $i => $a) {
                [$q, $ans] = $extract($a);
                $lines[] = ($i + 1).'. '.($q !== '' ? $q.' — ' : '').$ans;
            }
            return implode("\n", $lines);
        }

        // Полный тест: только ответы, по этапам (каждые 20 вопросов — этап)
        $stageNames = ['Личностные качества', 'Предпочтения в работе', 'Интересы', 'Профильные предпочтения'];
        $lines = [];
        foreach (array_values($answers) as $i => $a) {
            if ($i % 20 === 0) {
                $lines[] = ($stageNames[intdiv($i, 20)] ?? 'Этап '.(intdiv($i, 20) + 1)).':';
            }
            [, $ans] = $extract($a);
            $lines[] = '- '.$ans;
        }
        return implode("\n", $lines);
    }

    private function parseAiOutput(string $text): array
    {
        if (preg_match('/\{.*\}/s', $text, $m)) {
            return json_decode($m[0], true) ?: [];
        }
        return [];
    }

    /**
     * Модель (7b) не всегда соблюдает схему: кладёт в summary вложенный JSON,
     * использует русские ключи, отдаёт массив абзацев вместо строки.
     * Приводим всё к виду ['summary' => string|null, 'specialties' => string[]].
     */
    private function normalizeParsed(array $parsed): array
    {
        $summary = $parsed['summary'] ?? $parsed['разбор'] ?? $parsed['личный разбор'] ?? null;
        $specialties = $parsed['specialties'] ?? $parsed['специальности'] ?? [];

        // summary оказался JSON-строкой — разворачиваем
        if (is_string($summary)) {
            $trim = trim($summary);
            if (str_starts_with($trim, '{') || str_starts_with($trim, '[')) {
                $inner = json_decode($trim, true);
                if (is_array($inner)) {
                    if (empty($specialties)) {
                        $specialties = $inner['specialties'] ?? $inner['специальности'] ?? [];
                    }
                    unset($inner['specialties'], $inner['специальности']);
                    $summary = $inner;
                }
            }
        }

        // summary-массив (абзацы/секции) — склеиваем в текст
        if (is_array($summary)) {
            $summary = $this->flattenText($summary);
        }

        if (!is_array($specialties)) {
            $specialties = [];
        }
        $specialties = array_values(array_filter(array_map(
            static fn ($s) => is_string($s) ? trim($s) : '',
            $specialties
        )));

        return [
            'summary' => is_string($summary) && trim($summary) !== '' ? trim($summary) : null,
            'specialties' => $specialties,
        ];
    }

    /** Рекурсивно собирает строки из вложенных массивов в абзацы. */
    private function flattenText($node): string
    {
        if (is_string($node)) {
            return trim($node);
        }
        if (!is_array($node)) {
            return '';
        }
        $parts = [];
        foreach ($node as $value) {
            $text = $this->flattenText($value);
            if ($text !== '') {
                $parts[] = $text;
            }
        }
        return implode("\n\n", $parts);
    }

    private function mapNamesToIds(array $names, string $type): array
    {
        if (empty($names)) {
            return [];
        }

        $table = $type === 'college' ? 'college_specializations' : 'specializations';
        // PostgreSQL: LIKE регистрозависимый, поэтому ILIKE
        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $ids = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            // 1) точное совпадение (без учёта регистра)
            $id = DB::table($table)->where('name', $like, $name)->value('id');

            // 2) частичное совпадение по полному названию
            if (!$id) {
                $id = DB::table($table)->where('name', $like, '%'.$name.'%')->value('id');
            }

            // 3) совпадение по самым длинным словам названия («инженерия», «дизайн»…)
            if (!$id) {
                $words = preg_split('/[\s,\-\/]+/u', $name) ?: [];
                usort($words, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
                foreach (array_slice($words, 0, 2) as $word) {
                    if (mb_strlen($word) < 5) {
                        continue;
                    }
                    $id = DB::table($table)->where('name', $like, '%'.$word.'%')->value('id');
                    if ($id) {
                        break;
                    }
                }
            }

            if ($id) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
