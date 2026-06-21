<?php

namespace App\Services;

use App\Jobs\AnalyzeDishNutrition;
use App\Models\AiJob;
use App\Models\Dish;
use App\Models\Menu;
use App\Models\MenuDish;
use Illuminate\Support\Facades\DB;

class MenuService
{
    /**
     * Bulk-import a weekly menu from JSON structure.
     *
     * Format: {"2026-06-22": {"breakfast": ["Poha", "Milk"], "lunch": [...], ...}, ...}
     */
    public function importFromJson(array $data): array
    {
        $created = ['menus' => 0, 'dishes' => 0, 'jobs' => 0];

        DB::transaction(function () use ($data, &$created) {
            foreach ($data as $date => $mealTypes) {
                foreach ($mealTypes as $mealType => $countersOrDishes) {
                    // Support both formats:
                    // Flat:   {"lunch": ["dish1", "dish2"]}
                    // Nested: {"lunch": {"Counter1": ["dish1"], "Counter2": ["dish2"]}}
                    $isNested = is_array($countersOrDishes)
                        && !empty($countersOrDishes)
                        && is_string(array_key_first($countersOrDishes))
                        && is_array(array_values($countersOrDishes)[0]);

                    if ($isNested) {
                        foreach ($countersOrDishes as $counter => $dishNames) {
                            $menu = Menu::firstOrCreate([
                                'menu_date' => $date,
                                'meal_type' => $mealType,
                                'counter'   => $counter,
                            ]);
                            $created['menus']++;

                            $this->importDishes($menu, $dishNames, $created);
                        }
                    } else {
                        $menu = Menu::firstOrCreate([
                            'menu_date' => $date,
                            'meal_type' => $mealType,
                        ]);
                        $created['menus']++;

                        $this->importDishes($menu, $countersOrDishes, $created);
                    }
                }
            }
        });

        return $created;
    }

    private function importDishes(Menu $menu, array $dishNames, array &$created): void
    {
        foreach ($dishNames as $dishName) {
            if (!is_string($dishName) || trim($dishName) === '') continue;

            $dish = Dish::firstOrCreate(
                ['name' => trim($dishName)],
                ['serving_size' => '1 serving', 'calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'ai_generated' => false]
            );

            if ($dish->wasRecentlyCreated) {
                $created['dishes']++;
            }

            MenuDish::firstOrCreate([
                'menu_id' => $menu->id,
                'dish_id' => $dish->id,
            ]);

            $existingJob = AiJob::where('dish_id', $dish->id)->where('status', 'done')->first();
            if (!$existingJob && $dish->calories == 0) {
                AiJob::firstOrCreate(
                    ['dish_id' => $dish->id, 'status' => 'pending'],
                );
                AnalyzeDishNutrition::dispatch($dish->id, $dish->name);
                $created['jobs']++;
            }
        }
    }
}
