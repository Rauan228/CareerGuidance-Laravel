<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use App\Models\CareerTestResult;

class ChartController extends Controller
{
    public function getChartData()
    {
        $type = request('type', 'days'); // Получаем тип графика (days, weeks, months)

        $data = [];
        $labels = [];

        if ($type === 'days') {
            $data = [33, 20, 25, 80, 50, 60, 70];
            $labels = ["Пн", "Вт", "Ср", "Чт", "Пт", "Сб", "Вс"];
        } elseif ($type === 'weeks') {
            $data = [241, 190, 300, 200];
            $labels = ["Неделя 1", "Неделя 2", "Неделя 3", "Неделя 4"];
        } elseif ($type === 'months') {
            $data = [1500, 1200, 1100, 1300, 1400, 1500, 1600, 700, 2300, 1900, 200, 2100];
            $labels = ["Янв", "Фев", "Мар", "Апр", "Май", "Июн", "Июл", "Авг", "Сен", "Окт", "Ноя", "Дек"];
        }

        return response()->json([
            'data' => $data,
            'labels' => $labels
        ]);
    }

    public function getTestResultChartData()
    {
        $type = request('type', 'days');
        $data = [];
        $labels = [];
        $driver = \DB::getDriverName();

        if ($type === 'days') {
            // last 7 days
            $start = Carbon::now()->subDays(6)->startOfDay();
            $results = CareerTestResult::where('created_at', '>=', $start)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
                ->groupBy('date')
                ->pluck('total', 'date');
            for ($i = 0; $i < 7; $i++) {
                $date = $start->copy()->addDays($i);
                $labels[] = $date->format('d M');
                $data[] = $results->get($date->toDateString(), 0);
            }
        } elseif ($type === 'weeks') {
            // last 4 weeks (current week inclusive)
            $startOfWeek = Carbon::now()->startOfWeek(CarbonInterface::MONDAY)->subWeeks(3);
            $yearWeekExpr = match ($driver) {
                'mysql' => "YEARWEEK(created_at, 1)",
                'pgsql' => "to_char(created_at, 'IYYYIW')",
                default => "strftime('%G%V', created_at)",
            };
            $results = CareerTestResult::where('created_at', '>=', $startOfWeek)
                ->selectRaw("$yearWeekExpr as year_week, COUNT(*) as total")
                ->groupBy('year_week')
                ->pluck('total', 'year_week');
            for ($i = 0; $i < 4; $i++) {
                $week = $startOfWeek->copy()->addWeeks($i);
                $yearWeek = $week->format('oW');
                $labels[] = 'Week ' . ($i + 1);
                $data[] = $results->get($yearWeek, 0);
            }
        } elseif ($type === 'months') {
            // last 12 months (current month inclusive)
            $startOfMonth = Carbon::now()->startOfMonth()->subMonths(11);
            $ymExpr = match ($driver) {
                'mysql' => "DATE_FORMAT(created_at, '%Y-%m')",
                'pgsql' => "to_char(created_at, 'YYYY-MM')",
                default => "strftime('%Y-%m', created_at)",
            };
            $results = CareerTestResult::where('created_at', '>=', $startOfMonth)
                ->selectRaw("$ymExpr as ym, COUNT(*) as total")
                ->groupBy('ym')
                ->pluck('total', 'ym');
            for ($i = 0; $i < 12; $i++) {
                $month = $startOfMonth->copy()->addMonths($i);
                $ym = $month->format('Y-m');
                $labels[] = $month->locale(app()->getLocale())->isoFormat('MMM');
                $data[] = $results->get($ym, 0);
            }
        }

        return response()->json([
            'data' => $data,
            'labels' => $labels
        ]);
    }
}
