<?php

namespace App\Http\Controllers;

use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class RecommendationController extends Controller
{
    public function __construct(private RecommendationService $service) {}

    public function today(Request $request): JsonResponse
    {
        $user       = JWTAuth::user();
        $date       = $this->findNextMenuDate(now());
        $regenerate = $request->boolean('regenerate', false);

        try {
            $rec = $this->service->generateForDate($user, $date, $regenerate);
            $resp = $this->format($rec, $user);
            $resp['is_future'] = $date !== now()->toDateString();
            return response()->json($resp);
        } catch (\RuntimeException $e) {
            $dayName = now()->format('l');
            $isWeekend = in_array($dayName, ['Saturday', 'Sunday']);
            $msg = $isWeekend
                ? "It's {$dayName} — no cafeteria menu uploaded for next week yet. Ask your admin to upload the menu."
                : 'No cafeteria menu uploaded for today. Ask your admin to upload the menu.';
            return response()->json(['message' => $msg], 404);
        }
    }

    /**
     * If today has no menu (weekend / holiday), walk forward up to 7 days
     * and return the first date that has a menu entry.
     */
    private function findNextMenuDate(\Illuminate\Support\Carbon $from): string
    {
        for ($i = 0; $i < 7; $i++) {
            $candidate = $from->copy()->addDays($i)->toDateString();
            $hasMenu   = \App\Models\Menu::whereDate('menu_date', $candidate)->exists();
            if ($hasMenu) return $candidate;
        }

        return $from->toDateString();
    }

    public function generate(Request $request): JsonResponse
    {
        $user       = JWTAuth::user();
        $date       = $request->input('date', now()->toDateString());
        $regenerate = $request->boolean('regenerate', false);

        try {
            $rec = $this->service->generateForDate($user, $date, $regenerate);
            return response()->json($this->format($rec, $user), 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function week(Request $request): JsonResponse
    {
        $user      = JWTAuth::user();
        $startDate = $request->input('start', now()->startOfWeek()->toDateString());
        $week      = $this->service->getWeek($user, $startDate);

        return response()->json(array_map(
            fn($day) => [
                'date'           => $day['date'],
                'recommendation' => $day['recommendation']
                    ? $this->format($day['recommendation'], $user)
                    : null,
            ],
            $week
        ));
    }

    public function swap(Request $request): JsonResponse
    {
        $user = JWTAuth::user();
        $data = $request->validate([
            'dish_id'   => 'required|exists:dishes,id',
            'meal_type' => 'required|in:breakfast,lunch,snacks,dinner',
            'date'      => 'required|date',
        ]);

        $currentDish = \App\Models\Dish::findOrFail($data['dish_id']);

        // Get all dishes available for this meal on this date
        $menus = \App\Models\Menu::whereDate('menu_date', $data['date'])
            ->where('meal_type', $data['meal_type'])
            ->with('dishes')
            ->get();

        $alternatives = [];
        foreach ($menus as $menu) {
            foreach ($menu->dishes as $dish) {
                if ($dish->id === $currentDish->id) continue;

                // Filter by user diet preference
                if ($user->diet_type === 'veg' && $dish->diet_type !== 'veg') continue;
                if ($user->diet_type === 'eggetarian' && $dish->diet_type === 'non_veg') continue;

                // Filter allergies
                if (!empty($user->allergies)) {
                    $skip = false;
                    foreach ($user->allergies as $allergy) {
                        if (stripos($dish->name, $allergy) !== false) { $skip = true; break; }
                    }
                    if ($skip) continue;
                }

                $calDiff = abs($dish->calories - $currentDish->calories);
                $protDiff = abs($dish->protein - $currentDish->protein);
                $score = $calDiff + $protDiff * 4; // weight protein similarity higher

                $alternatives[] = [
                    'dish'    => $dish,
                    'counter' => $menu->counter ?? 'General',
                    'score'   => $score,
                    'cal_diff'  => $dish->calories - $currentDish->calories,
                    'prot_diff' => $dish->protein - $currentDish->protein,
                ];
            }
        }

        usort($alternatives, fn($a, $b) => $a['score'] <=> $b['score']);
        $alternatives = array_slice($alternatives, 0, 8);

        return response()->json([
            'current'      => $currentDish,
            'alternatives' => $alternatives,
        ]);
    }

    public function confirmSwap(Request $request): JsonResponse
    {
        $user = JWTAuth::user();
        $data = $request->validate([
            'recommendation_id' => 'required|exists:recommendations,id',
            'old_dish_id'       => 'required|exists:dishes,id',
            'new_dish_id'       => 'required|exists:dishes,id',
            'meal_type'         => 'required|in:breakfast,lunch,snacks,dinner',
        ]);

        $rec = \App\Models\Recommendation::where('id', $data['recommendation_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        $item = \App\Models\RecommendationItem::where('recommendation_id', $rec->id)
            ->where('dish_id', $data['old_dish_id'])
            ->where('meal_type', $data['meal_type'])
            ->firstOrFail();

        $newDish = \App\Models\Dish::findOrFail($data['new_dish_id']);
        $oldDish = \App\Models\Dish::findOrFail($data['old_dish_id']);

        // Update the item
        $item->dish_id = $newDish->id;
        $item->save();

        // Recalculate recommendation totals
        $rec->total_calories += ($newDish->calories - $oldDish->calories) * $item->servings;
        $rec->total_protein  += ($newDish->protein  - $oldDish->protein)  * $item->servings;
        $rec->total_carbs    += ($newDish->carbs    - $oldDish->carbs)    * $item->servings;
        $rec->total_fat      += ($newDish->fat      - $oldDish->fat)      * $item->servings;
        $rec->save();

        return response()->json($this->format($rec->load('items.dish'), $user));
    }

    private function format($rec, $user): array
    {
        $itemsByMeal = [];
        foreach ($rec->items as $item) {
            $itemsByMeal[$item->meal_type][] = [
                'dish'     => $item->dish,
                'counter'  => $item->counter ?? 'General',
                'servings' => $item->servings,
            ];
        }

        return [
            'id'               => $rec->id,
            'date'             => $rec->recommendation_date,
            'targets'          => [
                'calories' => $user->target_calories,
                'protein'  => $user->target_protein,
                'carbs'    => $user->target_carbs,
                'fat'      => $user->target_fat,
            ],
            'totals'           => [
                'calories' => $rec->total_calories,
                'protein'  => $rec->total_protein,
                'carbs'    => $rec->total_carbs,
                'fat'      => $rec->total_fat,
            ],
            'meals'            => $itemsByMeal,
        ];
    }
}
