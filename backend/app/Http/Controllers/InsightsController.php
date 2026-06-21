<?php

namespace App\Http\Controllers;

use App\Models\MealLog;
use App\Models\WeightHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class InsightsController extends Controller
{
    public function weekly(Request $request): JsonResponse
    {
        $user  = JWTAuth::user();
        $end   = Carbon::today();
        $start = $end->copy()->subDays(6);

        // Single query for all 7 days instead of 7 individual queries
        $allLogs = MealLog::where('user_id', $user->id)
            ->whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
            ->with('dish')
            ->get()
            ->groupBy(fn($log) => $log->log_date->toDateString());

        $dailyLogs = [];
        $streak = 0;
        $streakBroken = false;

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $date = $d->toDateString();
            $logs = $allLogs->get($date, collect());

            $totals = ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0];
            foreach ($logs as $log) {
                $s = $log->servings;
                $totals['calories'] += $log->dish->calories * $s;
                $totals['protein']  += $log->dish->protein  * $s;
                $totals['carbs']    += $log->dish->carbs    * $s;
                $totals['fat']      += $log->dish->fat      * $s;
            }

            $dailyLogs[] = [
                'date'       => $date,
                'logged'     => $logs->isNotEmpty(),
                'totals'     => array_map(fn($v) => round($v), $totals),
                'dish_count' => $logs->count(),
            ];
        }

        // Streak: walk backwards from today through the daily data
        for ($i = count($dailyLogs) - 1; $i >= 0; $i--) {
            if ($dailyLogs[$i]['logged']) {
                $streak++;
            } else {
                break;
            }
        }

        // Weekly averages
        $loggedDays = array_filter($dailyLogs, fn($d) => $d['logged']);
        $avgMacros = ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0];
        if (count($loggedDays) > 0) {
            foreach ($loggedDays as $day) {
                foreach ($avgMacros as $k => &$v) {
                    $v += $day['totals'][$k];
                }
            }
            unset($v);
            $avgMacros = array_map(fn($v) => round($v / count($loggedDays)), $avgMacros);
        }

        // Weight trend
        $weights = WeightHistory::where('user_id', $user->id)
            ->orderByDesc('recorded_at')
            ->limit(14)
            ->get();

        $weightTrend = null;
        if ($weights->count() >= 2) {
            $latest  = $weights->first()->weight_kg;
            $oldest  = $weights->last()->weight_kg;
            $diff    = round($latest - $oldest, 1);
            $weightTrend = [
                'direction' => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'stable'),
                'change_kg' => $diff,
                'latest_kg' => $latest,
                'entries'   => $weights->count(),
            ];
        }

        // Tips based on patterns
        $tips = [];
        $targets = [
            'calories' => $user->target_calories,
            'protein'  => $user->target_protein,
            'carbs'    => $user->target_carbs,
            'fat'      => $user->target_fat,
        ];

        if (count($loggedDays) >= 3) {
            if ($avgMacros['protein'] < $targets['protein'] * 0.8) {
                $tips[] = "You're consistently low on protein. Try adding an egg, paneer, or chicken dish.";
            }
            if ($avgMacros['calories'] > $targets['calories'] * 1.15) {
                $tips[] = "Your average calorie intake is above target. Consider reducing portion sizes.";
            }
            if ($avgMacros['calories'] < $targets['calories'] * 0.7) {
                $tips[] = "You're eating significantly under your calorie target. Make sure you're eating enough.";
            }
            if ($avgMacros['fat'] > $targets['fat'] * 1.2) {
                $tips[] = "Fat intake is higher than target. Try swapping fried items for grilled options.";
            }
        }

        if ($streak === 0) {
            $tips[] = "Start logging your meals to get personalized insights!";
        } elseif ($streak >= 5) {
            $tips[] = "Great consistency! You've logged meals for {$streak} days straight.";
        }

        return response()->json([
            'streak'       => $streak,
            'daily'        => $dailyLogs,
            'avg_macros'   => $avgMacros,
            'targets'      => $targets,
            'weight_trend' => $weightTrend,
            'tips'         => $tips,
            'logged_days'  => count($loggedDays),
        ]);
    }
}
