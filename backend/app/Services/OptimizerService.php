<?php

namespace App\Services;

class OptimizerService
{
    private const MAX_ITER      = 500;
    private const LEARNING_RATE = 0.02;
    private const MIN_SERVINGS  = 0.5;
    private const MAX_SERVINGS  = 3.0;
    private const MAX_DISHES_PER_MEAL = 6;

    /**
     * Optimize meal servings by selecting a small subset of dishes
     * that best fits the user's macro targets.
     */
    public function optimize(array $dishesByMealType, array $targets): array
    {
        $items       = [];
        $mealCount   = count(array_filter($dishesByMealType, fn($d) => !empty($d)));
        $mealWeights = $this->getMealWeights(array_keys(array_filter($dishesByMealType, fn($d) => !empty($d))));

        foreach ($dishesByMealType as $mealType => $dishes) {
            if (empty($dishes)) continue;

            $weight      = $mealWeights[$mealType] ?? (1 / $mealCount);
            $mealTargets = [
                'calories' => $targets['calories'] * $weight,
                'protein'  => $targets['protein']  * $weight,
                'carbs'    => $targets['carbs']     * $weight,
                'fat'      => $targets['fat']       * $weight,
            ];

            $selected  = $this->selectDishes($dishes, $mealTargets);
            $optimized = $this->optimizeMeal($selected, $mealTargets);

            foreach ($optimized as $dishId => $servings) {
                $items[] = [
                    'dish_id'   => $dishId,
                    'meal_type' => $mealType,
                    'servings'  => round($servings, 1),
                ];
            }
        }

        $totals = $this->computeTotals($items, $dishesByMealType);

        return ['items' => $items, 'totals' => $totals];
    }

    private function selectDishes(array $dishes, array $targets): array
    {
        $targetCal = $targets['calories'];
        $maxDishes = self::MAX_DISHES_PER_MEAL;

        $dishes = array_values(array_filter($dishes, fn($d) => ($d['calories'] ?? 0) > 0));
        if (count($dishes) <= $maxDishes) return $dishes;

        // Separate drinks from food
        $drinks = [];
        $foods  = [];
        foreach ($dishes as $i => $d) {
            if ($this->isDrink($d['name'] ?? '')) {
                $drinks[$i] = $d;
            } else {
                $foods[$i] = $d;
            }
        }

        // Pick at most 1 drink (highest protein score)
        $selectedDrink = null;
        if (!empty($drinks)) {
            $bestScore = -1;
            foreach ($drinks as $i => $d) {
                $cal   = max(1, $d['calories']);
                $score = ($d['protein'] ?? 0) / $cal * 100;
                if ($score > $bestScore) {
                    $bestScore     = $score;
                    $selectedDrink = $d;
                }
            }
        }

        $foodSlots = $selectedDrink ? $maxDishes - 1 : $maxDishes;

        // Score food items: prefer protein-dense, penalize very high calorie
        $scored = [];
        foreach ($foods as $i => $d) {
            $cal          = max(1, $d['calories']);
            $proteinRatio = ($d['protein'] ?? 0) / $cal * 100;
            $calFit       = 1.0 - min(1.0, abs($cal - ($targetCal / $maxDishes)) / ($targetCal / $maxDishes));
            $scored[$i]   = $proteinRatio * 0.6 + $calFit * 0.4;
        }

        arsort($scored);

        // Spread across counters: pick top dish from each counter first, then fill remaining
        $selected = [];
        $counterUsed = [];
        $remaining = [];

        foreach (array_keys($scored) as $i) {
            $counter = $foods[$i]['counter'] ?? 'General';
            if (!isset($counterUsed[$counter]) && count($selected) < $foodSlots) {
                $selected[] = $foods[$i];
                $counterUsed[$counter] = true;
            } else {
                $remaining[] = $i;
            }
        }

        foreach ($remaining as $i) {
            if (count($selected) >= $foodSlots) break;
            $selected[] = $foods[$i];
        }

        if ($selectedDrink) {
            $selected[] = $selectedDrink;
        }

        return $selected;
    }

    private function isDrink(string $name): bool
    {
        $patterns = [
            'chai', 'tea', 'coffee', 'juice', 'chaas', 'lassi',
            'milkshake', 'smoothie', 'sharbat', 'buttermilk',
            'lemonade', 'nimbu', 'jaljeera', 'aam panna',
            'soup', 'broth', 'shorba',
        ];
        $lower = strtolower($name);
        foreach ($patterns as $p) {
            if (str_contains($lower, $p)) return true;
        }
        return false;
    }

    private function optimizeMeal(array $dishes, array $targets): array
    {
        $n       = count($dishes);
        if ($n === 0) return [];

        $serving = array_fill(0, $n, 1.0);

        // Scale initial servings so total calories roughly match target
        $totalCal = 0;
        foreach ($dishes as $d) {
            $totalCal += $d['calories'];
        }
        if ($totalCal > 0) {
            $scale = $targets['calories'] / $totalCal;
            $scale = max(self::MIN_SERVINGS, min(self::MAX_SERVINGS, $scale));
            $serving = array_fill(0, $n, $scale);
        }

        for ($iter = 0; $iter < self::MAX_ITER; $iter++) {
            $totalCal = 0; $totalPro = 0; $totalCarb = 0; $totalFat = 0;
            foreach ($dishes as $i => $d) {
                $s = $serving[$i];
                $totalCal  += $s * $d['calories'];
                $totalPro  += $s * $d['protein'];
                $totalCarb += $s * $d['carbs'];
                $totalFat  += $s * $d['fat'];
            }

            $dCal  = $totalCal  - $targets['calories'];
            $dPro  = $totalPro  - $targets['protein'];
            $dCarb = $totalCarb - $targets['carbs'];
            $dFat  = $totalFat  - $targets['fat'];

            // Weighted gradients: calories matter most for portion control
            foreach ($dishes as $i => $d) {
                $grad = 2.0 * $dCal  * $d['calories']
                      + 1.5 * $dPro  * $d['protein']
                      + 1.0 * $dCarb * $d['carbs']
                      + 1.0 * $dFat  * $d['fat'];

                $norm = max(1.0, abs($grad));
                $serving[$i] -= self::LEARNING_RATE * $grad / $norm;
                $serving[$i]  = max(self::MIN_SERVINGS, min(self::MAX_SERVINGS, $serving[$i]));
            }
        }

        $result = [];
        foreach ($dishes as $i => $d) {
            $result[$d['id']] = $serving[$i];
        }
        return $result;
    }

    private function getMealWeights(array $mealTypes): array
    {
        $defaults = [
            'breakfast' => 0.25,
            'lunch'     => 0.35,
            'snacks'    => 0.10,
            'dinner'    => 0.30,
        ];

        $present = array_intersect_key($defaults, array_flip($mealTypes));
        $sum     = array_sum($present);

        if ($sum <= 0) {
            $even = 1 / count($mealTypes);
            return array_fill_keys($mealTypes, $even);
        }

        // Normalize so present meals sum to 1.0
        return array_map(fn($w) => $w / $sum, $present);
    }

    private function computeTotals(array $items, array $dishesByMealType): array
    {
        $dishMap = [];
        foreach ($dishesByMealType as $dishes) {
            foreach ($dishes as $d) {
                $dishMap[$d['id']] = $d;
            }
        }

        $totals = ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0];
        foreach ($items as $item) {
            $d = $dishMap[$item['dish_id']] ?? null;
            if (!$d) continue;
            $s = $item['servings'];
            $totals['calories'] += $s * $d['calories'];
            $totals['protein']  += $s * $d['protein'];
            $totals['carbs']    += $s * $d['carbs'];
            $totals['fat']      += $s * $d['fat'];
        }
        return array_map(fn($v) => round($v), $totals);
    }
}
