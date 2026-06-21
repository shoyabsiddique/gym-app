<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\Recommendation;
use App\Models\RecommendationItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    public function __construct(private OptimizerService $optimizer) {}

    public function generateForDate(User $user, string $date, bool $regenerate = false): Recommendation
    {
        $existing = Recommendation::where('user_id', $user->id)
            ->whereDate('recommendation_date', $date)
            ->with('items.dish')
            ->first();

        if ($existing && !$regenerate) return $existing;

        if ($existing && $regenerate) {
            $existing->items()->delete();
            $existing->delete();
        }

        $menus = Menu::whereDate('menu_date', $date)
            ->with('dishes')
            ->get()
            ->groupBy('meal_type');

        if ($menus->isEmpty()) {
            throw new \RuntimeException("No menu available for {$date}");
        }

        // Build dish list with counter info for each meal type
        $dishesByMealType = [];
        $dishCounterMap   = []; // dish_id → counter (first one found per meal)

        foreach ($menus as $mealType => $menuItems) {
            $seen = [];
            $dishesByMealType[$mealType] = [];
            foreach ($menuItems as $menu) {
                foreach ($menu->dishes as $dish) {
                    if (isset($seen[$dish->id])) continue;
                    $seen[$dish->id] = true;

                    $dishesByMealType[$mealType][] = [
                        'id'        => $dish->id,
                        'name'      => $dish->name,
                        'calories'  => $dish->calories,
                        'protein'   => $dish->protein,
                        'carbs'     => $dish->carbs,
                        'fat'       => $dish->fat,
                        'diet_type' => $dish->diet_type,
                        'counter'   => $menu->counter ?? 'General',
                    ];
                    $dishCounterMap[$dish->id] = $menu->counter ?? 'General';
                }
            }
        }

        // Filter dishes based on user's dietary preferences
        $userDiet = $user->diet_type ?? 'non_veg';
        $userAllergies = $user->allergies ?? [];

        foreach ($dishesByMealType as $mealType => &$dishes) {
            $dishes = array_values(array_filter($dishes, function ($dish) use ($userDiet, $userAllergies) {
                // Diet type filtering
                if ($userDiet === 'veg' && $dish['diet_type'] !== 'veg') {
                    return false;
                }
                if ($userDiet === 'eggetarian' && $dish['diet_type'] === 'non_veg') {
                    return false;
                }

                // Allergy filtering
                foreach ($userAllergies as $allergy) {
                    if (str_contains(strtolower($dish['name']), strtolower($allergy))) {
                        return false;
                    }
                }

                return true;
            }));
        }
        unset($dishes);

        $targets = [
            'calories' => $user->target_calories,
            'protein'  => $user->target_protein,
            'carbs'    => $user->target_carbs,
            'fat'      => $user->target_fat,
        ];

        $result = $this->optimizer->optimize($dishesByMealType, $targets);

        return DB::transaction(function () use ($user, $date, $result, $dishCounterMap) {
            $rec = Recommendation::create([
                'user_id'             => $user->id,
                'recommendation_date' => $date,
                'total_calories'      => $result['totals']['calories'],
                'total_protein'       => $result['totals']['protein'],
                'total_carbs'         => $result['totals']['carbs'],
                'total_fat'           => $result['totals']['fat'],
            ]);

            foreach ($result['items'] as $item) {
                RecommendationItem::create([
                    'recommendation_id' => $rec->id,
                    'meal_type'         => $item['meal_type'],
                    'dish_id'           => $item['dish_id'],
                    'counter'           => $dishCounterMap[$item['dish_id']] ?? 'General',
                    'servings'          => $item['servings'],
                ]);
            }

            return $rec->load('items.dish');
        });
    }

    public function getWeek(User $user, string $startDate): array
    {
        $start = Carbon::parse($startDate)->startOfWeek(Carbon::MONDAY);
        $end   = $start->copy()->addDays(6);

        $recs = Recommendation::where('user_id', $user->id)
            ->whereBetween('recommendation_date', [$start->toDateString(), $end->toDateString()])
            ->with('items.dish')
            ->get()
            ->keyBy(fn($r) => $r->recommendation_date->toDateString());

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date   = $start->copy()->addDays($i)->toDateString();
            $days[] = ['date' => $date, 'recommendation' => $recs->get($date)];
        }

        return $days;
    }
}
